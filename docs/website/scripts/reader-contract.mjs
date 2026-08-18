import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { JSDOM, VirtualConsole } from 'jsdom';
import { repositoryRoot as defaultRepositoryRoot, sourceRoot as defaultSourceRoot, distRoot as defaultDistRoot } from './website-paths.mjs';

export const readerTypes = ['tutorial', 'how-to', 'concept', 'reference', 'troubleshooting'];

export const readerTypeCounts = {
  tutorial: 3,
  'how-to': 18,
  concept: 10,
  reference: 8,
  troubleshooting: 1,
};

const referenceLookupFields = [
  ['signature', /(?:Signature|署名|呼出形|Method|メソッド)/iu],
  ['parameter', /(?:Parameter|パラメータ|引数|Option|Property)/iu],
  ['return', /(?:Return|返却|戻り値|返す|Result|結果)/iu],
  ['default', /(?:Default|既定|デフォルト|省略)/iu],
  ['error', /(?:Error|エラー|失敗|拒否|Unavailable|Invalid)/iu],
  ['typical-use', /(?:Typical\s+Use|典型(?:利用|的)|利用箇所|使う|呼ぶ|完全例)/iu],
];

const roleRequirements = {
  tutorial: ['prerequisites', 'runnable', 'success', 'failure'],
  'how-to': ['prerequisites', 'runnable', 'success', 'failure'],
  concept: ['mentalModel', 'invariant', 'boundary'],
  reference: ['scope', 'lookup', 'boundary'],
  troubleshooting: ['diagnostics', 'faq', 'groups', 'auxiliary'],
};

const actionTypes = new Set(['tutorial', 'how-to']);
const headingPattern = /^(#{1,6})\s+(.+?)\s*$/gm;
const markdownLinkPattern = /!?\[[^\]]*\]\(([^)]+)\)/g;
const placeholderPattern = /(?:TODO|TBD|FIXME|placeholder|ここに|このPage|this page|same as|同じ説明)/iu;
const protectedDecodePattern = /convert_from\s*\(\s*(?:encoded_record|encoded_payload|encoded_context|encoded_reason|encoded_response|encoded_result)\b|(?:encoded_record|encoded_payload|encoded_context|encoded_reason|encoded_response|encoded_result)[^\n`]*::\s*jsonb/iu;
const invalidRetryEventPattern = /\bretry\.scheduled\b/u;
const currentMainOnlyPattern = /(?:(?:（main）|\(main\))|Stable(?:\s+1\.2\.0)?[^\n。]*(?:main-only|main専用|main限定|main Source|Repository\s+`?main)|(?:mainでは|mainの(?:Canonical|Framework|公開|Surface|build:compile)|main Source|Stable／main境界)[^\n。]*(?:限定|唯一|未提供|Preview|だけ|差))/iu;
const staleStableMainPattern = /^(?:#{1,6}\s+)?Stable(?:と\s*`?main`?|／\s*main)(?:の差|境界)?\s*$|Stableと\s*`?main`?の差/iu;
const internalEvidencePattern = /(?:Remote\s+(?:create-project\s+)?smoke|root比較|root\s+comparison|Consumer\s+E2E|Consumer\s+script|tests\/Consumer|Repository\s+Local\s+Consumer|Local\s+Consumer|Local\s*[／/]\s*CI\s+(?:only|だけ|で|validation|検証|確認|test|テスト)|Real\s+Browser\s+E2E|Local\s*[／/]\s*CI\s+Build|(?:ローカル|Local)\s*(?:[／/]\s*|と\s*)(?:CI|シーアイ)[^。\n]*(?:だけ|のみ|で|検証|確認|テスト|再現|validation)|Repository\s+CI[^。\n]*(?:検証|確認|evidence|validation|test))/iu;
const historicalPreviewText = 'Repository main Preview';
const historicalPreviewKey = 'repositorymainpreview';

function historicalPreviewVisibleText(text) {
  return text.replace(/&nbsp;|&#160;|&#xA0;/giu, ' ').replace(/<[^>]+>/gu, '').trim();
}

function containsHistoricalPreviewVariant(text) {
  return historicalPreviewVisibleText(text).toLocaleLowerCase('en-US').replace(/[\s_-]+/gu, '').includes(historicalPreviewKey);
}

export function validateReaderContract(contentMap, { sourceTexts = new Map(), sourceFiles = null } = {}) {
  if (contentMap === null || typeof contentMap !== 'object' || Array.isArray(contentMap)) {
    throw new Error('Reader Content Map must be an object.');
  }

  const entries = Object.entries(contentMap);
  const landingEntries = entries.filter(([, metadata]) => metadata?.slug === 'index');
  if (landingEntries.length !== 1 || landingEntries[0][0] !== 'README.md') {
    throw new Error('Reader Content Map must exclude exactly the README.md Landing entry from the reader inventory.');
  }
  if (landingEntries[0][1].reader !== undefined) throw new Error('Landing must not declare a reader type.');

  const pages = entries.filter(([source]) => source !== 'README.md');
  if (pages.length !== 40) throw new Error(`Reader inventory must contain exactly 40 non-Landing pages; found ${pages.length}.`);

  const counts = Object.fromEntries(readerTypes.map((type) => [type, 0]));
  const outcomes = new Map();
  const identities = { topic: new Map(), recipe: new Map() };
  for (const [source, metadata] of pages) {
    validateEntryShape(source, metadata);
    const { reader } = metadata;
    counts[reader.type] += 1;
    const outcomeKey = reader.outcome.trim().toLocaleLowerCase('ja');
    if (outcomes.has(outcomeKey)) throw new Error(`Reader outcomes must be unique: ${source} duplicates ${outcomes.get(outcomeKey)}.`);
    outcomes.set(outcomeKey, source);
    for (const kind of ['topic', 'recipe']) validateReaderIdentity(source, reader[kind], kind, contentMap, identities[kind]);

    for (const role of roleRequirements[reader.type]) validateRoleList(source, reader, role);
    validateNextPages(source, reader.next, contentMap, sourceTexts.get(source) ?? '', sourceTexts);
    validateTypeSpecificRoles(source, metadata, sourceTexts.get(source) ?? '');
    if (sourceTexts.has(source)) validateReaderBody(source, metadata, sourceTexts.get(source));
  }

  for (const [type, expected] of Object.entries(readerTypeCounts)) {
    if (counts[type] !== expected) throw new Error(`Reader type count for ${type} must be ${expected}; found ${counts[type]}.`);
  }
  for (const kind of ['topic', 'recipe']) {
    for (const [identity, declarations] of identities[kind]) {
      const owners = declarations.filter((declaration) => declaration.role === 'owner');
      if (owners.length === 0) throw new Error(`Reader ${kind} identity has no owner: ${identity}.`);
      if (owners.length > 1) throw new Error(`Reader ${kind} identity owner is duplicated: ${owners.map(({ source }) => source).join(' and ')}.`);
      const [owner] = owners;
      if (owner.referenceSource !== owner.source) {
        throw new Error(`Reader ${kind} identity owner must reference itself: ${owner.source} -> ${owner.reference}.`);
      }
      for (const declaration of declarations) {
        if (declaration.role === 'reference' && declaration.referenceSource !== owner.source) {
          throw new Error(`Reader ${kind} identity reference must resolve to its owner: ${declaration.source} -> ${declaration.reference}; owner is ${owner.source}.`);
        }
      }
    }
  }
  if (sourceFiles !== null) {
    const expected = new Set(pages.map(([source]) => source));
    const actual = new Set(sourceFiles.filter((source) => source !== 'README.md'));
    if (actual.size !== expected.size || [...expected].some((source) => !actual.has(source)) || [...actual].some((source) => !expected.has(source))) {
      throw new Error('Reader Content Map and docs/guide source inventory must match exactly.');
    }
  }

  return { counts, outcomes, pages: pages.map(([source, metadata]) => ({ source, ...metadata.reader })) };
}

export async function validateSourceReaderContract({ contentMap, sourceDirectory = defaultSourceRoot, repositoryRoot = defaultRepositoryRoot } = {}) {
  if (contentMap === undefined) throw new Error('Source reader validation requires a Content Map.');
  const files = await markdownFiles(sourceDirectory);
  const sourceTexts = new Map();
  for (const source of files) sourceTexts.set(source, await readFile(path.join(sourceDirectory, ...source.split('/')), 'utf8'));
  const result = validateReaderContract(contentMap, { sourceTexts, sourceFiles: files });
  await assertSourceDerivedReferenceCoverage(contentMap, repositoryRoot);
  for (const [source, markdown] of sourceTexts) {
    assertNoProtectedDecode(markdown, source);
    assertNoCurrentMainOnly(markdown, source);
    assertNoInternalEvidenceVoice(markdown, source);
    assertNoUnsafeLgtmDiagnostics(markdown, source);
  }
  const contentMapText = await readFile(path.join(repositoryRoot, 'docs/website/content-map.mjs'), 'utf8');
  assertNoProtectedDecode(contentMapText, 'docs/website/content-map.mjs');
  assertNoCurrentMainOnly(contentMapText, 'docs/website/content-map.mjs');
  assertNoInternalEvidenceVoice(contentMapText, 'docs/website/content-map.mjs');
  assertNoUnsafeLgtmDiagnostics(contentMapText, 'docs/website/content-map.mjs');
  return result;
}

export async function validateArtifactReaderContract({ contentMap, artifactDirectory = defaultDistRoot } = {}) {
  if (contentMap === undefined) throw new Error('Artifact reader validation requires a Content Map.');
  validateReaderContract(contentMap);
  const pages = Object.entries(contentMap).filter(([source]) => source !== 'README.md');
  const byRoute = new Map(pages.map(([source, metadata]) => [routeFor(metadata.slug), { source, metadata }]));
  await validateArtifactPageRouteInventory({ artifactDirectory, expectedRoutes: new Set(byRoute.keys()) });
  const outcomes = pages.map(([, metadata]) => metadata.reader.outcome);
  const searchPath = path.join(artifactDirectory, 'blume-search.json');
  let search;
  try {
    search = JSON.parse(await readFile(searchPath, 'utf8'));
  } catch {
    throw new Error('Artifact reader contract requires blume-search.json.');
  }
  if (!Array.isArray(search)) throw new Error('Search artifact must be an array.');
  const searchByRoute = new Map();
  for (const record of search) {
    if (typeof record?.route !== 'string') throw new Error('Search artifact record is missing a route.');
    if (searchByRoute.has(record.route)) throw new Error(`Search artifact contains a duplicate route: ${record.route}.`);
    searchByRoute.set(record.route, record);
  }
  const expectedRoutes = new Set(byRoute.keys());
  const actualRoutes = validateSearchRouteInventory(search, expectedRoutes);
  if (expectedRoutes.size !== actualRoutes.size || [...expectedRoutes].some((route) => !actualRoutes.has(route))) {
    throw new Error('Search artifact route inventory does not match the 40-page Content Map.');
  }
  for (const [route, { source, metadata }] of byRoute) {
    const record = searchByRoute.get(route);
    const strings = collectStrings(record);
    assertSingleOutcome(strings, metadata.reader.outcome, outcomes, `Search ${route}`);
    const generated = path.join(artifactDirectory, ...metadata.slug.split('/'));
    const rawPath = await firstExisting(generated, `${generated}.md`, `${generated}.mdx`);
    if (rawPath === null) throw new Error(`Raw Markdown artifact is missing for ${source}: ${metadata.slug}.`);
    const raw = await readFile(rawPath, 'utf8');
    if (!raw.includes(`description: ${JSON.stringify(metadata.reader.outcome)}`)) {
      throw new Error(`Raw Markdown artifact outcome drifted for ${source}.`);
    }
    const htmlPath = path.join(artifactDirectory, ...metadata.slug.split('/'), 'index.html');
    const html = await readFile(htmlPath, 'utf8').catch(() => '');
    if (html === '') throw new Error(`HTML artifact is missing for ${source}: ${metadata.slug}.`);
    assertSingleOutcome([html], metadata.reader.outcome, outcomes, `HTML ${route}`);
  }

  const llmsPath = path.join(artifactDirectory, 'llms.txt');
  const llms = await readFile(llmsPath, 'utf8').catch(() => '');
  if (llms === '') throw new Error('LLM short artifact is missing.');
  const llmsByRoute = validateLlmRouteInventory(llms, expectedRoutes);
  for (const [route, { metadata }] of byRoute) {
    const expected = metadata.reader.outcome;
    const line = llmsByRoute.get(route);
    assertSingleOutcome([line], expected, outcomes, `llms.txt ${route}`);
  }
  const llmsFull = await readFile(path.join(artifactDirectory, 'llms-full.txt'), 'utf8').catch(() => '');
  if (llmsFull === '') throw new Error('LLM full artifact is missing.');
  const segments = llmsFull.split(/\n---\n\n(?=# )/u).filter(Boolean);
  const segmentByRoute = new Map();
  for (const segment of segments) {
    const sourceMatch = segment.match(/^Source:\s*https?:\/\/[^/]+(\/[^\n]*)$/m);
    if (sourceMatch) {
      const route = sourceMatch[1] === '/' ? '/' : `/${sourceMatch[1].replace(/^\/+|\/+$/g, '')}`;
      if (segmentByRoute.has(route)) throw new Error(`llms-full.txt contains a duplicate route: ${route}.`);
      segmentByRoute.set(route, segment);
    }
  }
  const llmsFullRoutes = new Set([...segmentByRoute.keys()].filter((route) => route !== '/'));
  if (llmsFullRoutes.size !== expectedRoutes.size || [...expectedRoutes].some((route) => !llmsFullRoutes.has(route))) {
    throw new Error('llms-full.txt route inventory does not match the 40-page Content Map.');
  }
  for (const [route, { source, metadata }] of byRoute) {
    const segment = segmentByRoute.get(route);
    if (!segment) throw new Error(`llms-full.txt is missing the segment for ${source}: ${route}.`);
    const markers = [
      `<!-- blackops-reader-outcome: ${metadata.reader.outcome} -->`,
      `{/* blackops-reader-outcome: ${metadata.reader.outcome} */}`,
    ];
    if (!markers.some((marker) => segment.includes(marker))) throw new Error(`llms-full ${route} is missing its generated reader outcome marker.`);
    assertSingleOutcome([segment], metadata.reader.outcome, outcomes, `llms-full ${route}`);
  }

  for (const file of await textFiles(artifactDirectory)) {
    const location = path.relative(artifactDirectory, file);
    const content = await readFile(file, 'utf8');
    assertNoProtectedDecode(content, location);
    assertNoCurrentMainOnly(content, location);
    assertNoInternalEvidenceVoice(content, location);
    const readerText = artifactReaderSurfaceText(content, location);
    if (readerText !== null) assertNoUnsafeLgtmDiagnostics(readerText, location);
  }
  return { routes: expectedRoutes.size, searchRoutes: actualRoutes.size };
}

export async function validateArtifactPageRouteInventory({ artifactDirectory = defaultDistRoot, expectedRoutes = new Set() } = {}) {
  const expected = expectedRoutes instanceof Set ? expectedRoutes : new Set(expectedRoutes);
  const rawFiles = await artifactFiles(artifactDirectory, (name) => /\.mdx?$/u.test(name));
  const rawRoutes = new Map();
  for (const file of rawFiles) {
    const route = routeFromArtifactFile(file, artifactDirectory);
    const files = rawRoutes.get(route) ?? [];
    files.push(file);
    rawRoutes.set(route, files);
  }
  const allowedNonReaderRoutes = new Set([
    '/',
    '/404',
    '/operations/lifecycle',
    '/reference/security',
    '/reference/troubleshooting',
    '/reference/current-status',
  ]);
  const unknownRaw = [...rawRoutes.keys()].filter((route) => !expected.has(route) && !allowedNonReaderRoutes.has(route));
  if (unknownRaw.length > 0) throw new Error(`Raw Markdown artifact contains unknown route(s): ${unknownRaw.join(', ')}.`);
  for (const route of expected) {
    const files = rawRoutes.get(route) ?? [];
    if (files.length === 0) throw new Error(`Raw Markdown artifact route is missing: ${route}.`);
    const extensions = files.map((file) => path.extname(file));
    if (files.length > 2 || new Set(extensions).size !== files.length) {
      throw new Error(`Raw Markdown artifact contains duplicate route files: ${route}.`);
    }
  }

  const htmlFiles = await artifactFiles(artifactDirectory, (name) => name === 'index.html');
  const htmlRoutes = new Map();
  for (const file of htmlFiles) {
    const route = routeFromArtifactFile(file, artifactDirectory);
    if (htmlRoutes.has(route)) throw new Error(`HTML artifact contains a duplicate route: ${route}.`);
    htmlRoutes.set(route, file);
  }
  const unknownHtml = [...htmlRoutes.keys()].filter((route) => !expected.has(route) && !allowedNonReaderRoutes.has(route));
  if (unknownHtml.length > 0) throw new Error(`HTML artifact contains unknown route(s): ${unknownHtml.join(', ')}.`);
  for (const route of expected) {
    if (!htmlRoutes.has(route)) throw new Error(`HTML artifact route is missing: ${route}.`);
  }
  return { rawRoutes: new Set([...rawRoutes.keys()].filter((route) => expected.has(route))), htmlRoutes: new Set([...htmlRoutes.keys()].filter((route) => expected.has(route))) };
}

export function assertNoProtectedDecode(text, location = 'source') {
  if (protectedDecodePattern.test(text)) throw new Error(`Protected Blob decode or JSON cast is forbidden in ${location}.`);
  if (invalidRetryEventPattern.test(text)) throw new Error(`Unknown retry event retry.scheduled is forbidden in ${location}; use attempt.retry_scheduled.`);
}

export function assertNoCurrentMainOnly(text, location = 'source') {
  for (const line of text.split(/\r?\n/u)) {
    if (/^### Repository main Preview\s*$/u.test(line)) continue;
    const markdownHeading = line.match(/^(#{1,6})\s+(.+?)\s*$/u);
    if (markdownHeading && containsHistoricalPreviewVariant(markdownHeading[2])) {
      throw new Error(`Repository main Preview historical exemption must be an exact heading in ${location}: ${line.trim()}`);
    }
    for (const match of line.matchAll(/<h([1-6])\b([^>]*)>([\s\S]*?)<\/h\1>/giu)) {
      const headingText = historicalPreviewVisibleText(match[3]);
      if (containsHistoricalPreviewVariant(headingText)
        && !(match[1] === '3' && headingText === historicalPreviewText && /\bid=["']repository-main-preview["']/u.test(match[2]))) {
        throw new Error(`Repository main Preview historical exemption must be an exact heading in ${location}: ${line.trim()}`);
      }
    }
    let historicalPreviewStripped = line.replace(/<h([1-6])\b([^>]*)>([\s\S]*?)<\/h\1>/giu, (full, level, attributes, body) => {
      const headingText = historicalPreviewVisibleText(body);
      return level === '3' && headingText === historicalPreviewText && /\bid=["']repository-main-preview["']/u.test(attributes) ? '' : full;
    });
    historicalPreviewStripped = historicalPreviewStripped.replace(/<a\b([^>]*)>([\s\S]*?)<\/a>/giu, (full, attributes, body) => {
      const anchorText = historicalPreviewVisibleText(body);
      if (!containsHistoricalPreviewVariant(anchorText)) return full;
      if (/\bhref=["']#repository-main-preview["']/u.test(attributes) && anchorText === historicalPreviewText) return '';
      throw new Error(`Repository main Preview historical exemption must be an exact anchored unit in ${location}: ${line.trim()}`);
    });
    historicalPreviewStripped = historicalPreviewStripped.replace(/\[([^\]]+)\]\(([^)]+)\)/gu, (full, label, href) => {
      const linkText = historicalPreviewVisibleText(label);
      if (!containsHistoricalPreviewVariant(linkText)) return full;
      if (href === '#repository-main-preview' && linkText === historicalPreviewText) return '';
      throw new Error(`Repository main Preview historical exemption must be an exact anchored unit in ${location}: ${line.trim()}`);
    });
    const exactSearchFragment = /["']#repository-main-preview["']/u.test(historicalPreviewStripped);
    historicalPreviewStripped = historicalPreviewStripped.replace(/(?:["']?(?:title|heading|label|name|text)["']?\s*[:=]\s*["'])([^"']+)(["'])/giu, (full, value) => {
      if (!containsHistoricalPreviewVariant(value)) return full;
      if (exactSearchFragment && value === historicalPreviewText) return '';
      throw new Error(`Repository main Preview historical exemption must be an exact anchored unit in ${location}: ${line.trim()}`);
    });
    if (exactSearchFragment) historicalPreviewStripped = historicalPreviewStripped.replace(/["']#repository-main-preview["']/gu, '');
    historicalPreviewStripped = historicalPreviewStripped.replace(/。\s+Repository main Preview\s+このAnchorは旧PreviewからのMigration Linkを壊さないために残しています。/gu, '。');
    if (containsHistoricalPreviewVariant(historicalPreviewStripped)) {
      throw new Error(`Repository main Preview historical exemption must be an exact anchored unit in ${location}: ${line.trim()}`);
    }
    if (staleStableMainPattern.test(historicalPreviewStripped.trim())) throw new Error(`Current Stable main-only availability claim is forbidden in ${location}: ${line.trim()}`);
    if (currentMainOnlyPattern.test(historicalPreviewStripped)) throw new Error(`Current Stable main-only availability claim is forbidden in ${location}: ${line.trim()}`);
  }
}

export function assertNoInternalEvidenceVoice(text, location = 'source') {
  for (const line of text.split(/\r?\n/u)) {
    if (!internalEvidencePattern.test(line)) continue;
    const plainLine = line
      .replace(/<[^>]+>/gu, '')
      .replace(/\[([^\]]+)\]\([^)]*\)/gu, '$1');
    const historicalFrameworkUpdate = /公開PackageのComposer／Generator更新は、実際のannotated Tag\s+`?1\.1\.0`?を起点にしたFramework Update Consumerで検証済みです。/u.test(plainLine)
      && !internalEvidencePattern.test(plainLine.replace(/公開PackageのComposer／Generator更新は、実際のannotated Tag\s+`?1\.1\.0`?を起点にしたFramework Update Consumerで検証済みです。/gu, ''));
    if (!historicalFrameworkUpdate) throw new Error(`Internal evidence voice is forbidden in ${location}: ${line.trim()}`);
  }
}

const namedArtifactEntities = new Map([
  ['amp', '&'],
  ['lt', '<'],
  ['gt', '>'],
  ['quot', '"'],
  ['apos', "'"],
  ['nbsp', '\u00a0'],
]);
const artifactBlockTagPattern = /<\/?(?:address|article|aside|blockquote|caption|dd|details|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)\b[^>]*\/?>/giu;

export function normalizeArtifactVisibleText(text) {
  if (typeof text !== 'string') return '';
  const lineMarker = '\u0000';
  const breakMarker = '\u0001';
  const withLineBreaks = text
    .replace(/<br\b[^>]*\/?>\s*/giu, breakMarker)
    .replace(/<span\b[^>]*>/giu, (tag) => {
      const classValue = tag.match(/\bclass\s*=\s*(['"])(.*?)\1/iu)?.[2] ?? '';
      return classValue.split(/\s+/u).includes('line') ? lineMarker : tag;
    })
    .replace(/<\/span>\s*\u0000/gu, '</span>\u0000');
  const withoutTags = withLineBreaks
    .replace(artifactBlockTagPattern, (tag) => `\n${tag}\n`)
    .replace(/<\/code>\s*<code\b[^>]*>/giu, '\n')
    .replace(/<\/?[A-Za-z][A-Za-z0-9-]*(?:\s+[^<>]*?)?\s*\/?>/gu, '');
  return withoutTags.replace(/[\u0000\u0001]|&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos|nbsp);/giu, (entity, token) => {
    if (entity === lineMarker || entity === breakMarker) return '\n';
    if (token.toLocaleLowerCase('en-US').startsWith('#x')) {
      const codePoint = Number.parseInt(token.slice(2), 16);
      return Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff
        ? String.fromCodePoint(codePoint)
        : entity;
    }
    if (token.startsWith('#')) {
      const codePoint = Number.parseInt(token.slice(1), 10);
      return Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff
        ? String.fromCodePoint(codePoint)
        : entity;
    }
    return namedArtifactEntities.get(token.toLocaleLowerCase('en-US')) ?? entity;
  });
}

function hasUnescapedTrailingBackslash(line) {
  let slashCount = 0;
  for (let index = line.length - 1; index >= 0 && line[index] === '\\'; index -= 1) slashCount += 1;
  return slashCount % 2 === 1;
}

function joinShellContinuations(text) {
  const physicalLines = text.split(/\r?\n/u);
  const logicalLines = [];
  for (let index = 0; index < physicalLines.length; index += 1) {
    let line = physicalLines[index];
    while (hasUnescapedTrailingBackslash(line) && index + 1 < physicalLines.length) {
      line = `${line.slice(0, -1)} ${physicalLines[index + 1].replace(/^\s+/u, '')}`;
      index += 1;
    }
    logicalLines.push(line);
  }
  return logicalLines;
}

function splitShellCommandSegments(line) {
  const segments = [];
  let segmentStart = 0;
  let quote = null;
  let escaped = false;
  let parameterDepth = 0;
  const pushSegment = (end) => {
    const segment = line.slice(segmentStart, end).trim();
    if (segment !== '') segments.push(segment);
  };
  for (let index = 0; index < line.length; index += 1) {
    const character = line[index];
    if (escaped) {
      escaped = false;
      continue;
    }
    if (quote !== null) {
      if (quote === '"' && character === '\\') {
        escaped = true;
        continue;
      }
      if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"' || character === '`') {
      quote = character;
      continue;
    }
    if (character === '\\') {
      escaped = true;
      continue;
    }
    if (parameterDepth > 0) {
      if (character === '$' && line[index + 1] === '{') {
        parameterDepth += 1;
        index += 1;
        continue;
      }
      if (character === '}') parameterDepth -= 1;
      continue;
    }
    if (character === '$' && line[index + 1] === '{') {
      parameterDepth = 1;
      index += 1;
      continue;
    }
    if (character === '#' && (index === segmentStart || /\s/u.test(line[index - 1] ?? ''))) {
      pushSegment(index);
      return segments;
    }
    if (character === ';' || character === '|' || character === '&') {
      pushSegment(index);
      segmentStart = index + 1;
    }
  }
  pushSegment(line.length);
  return segments;
}

function matchingInlineBacktick(value, start, runLength) {
  let escaped = false;
  for (let index = start + runLength; index < value.length; index += 1) {
    const character = value[index];
    if (escaped) {
      escaped = false;
      continue;
    }
    if (character === '\\') {
      escaped = true;
      continue;
    }
    if (character !== '`' || (index > 0 && value[index - 1] === '`')) continue;
    let closingLength = 0;
    while (value[index + closingLength] === '`') closingLength += 1;
    if (closingLength === runLength) return index + runLength - 1;
    index += closingLength - 1;
  }
  return -1;
}

function normalizeInlineCodeBody(value) {
  return value.replace(/\r\n?|\n/gu, ' ');
}

function replaceInlineBackticks(value, shouldMask) {
  const output = value.split('');
  for (let index = 0; index < value.length; index += 1) {
    if (value[index] !== '`' || (index > 0 && value[index - 1] === '\\')) continue;
    let runLength = 0;
    while (value[index + runLength] === '`') runLength += 1;
    const end = matchingInlineBacktick(value, index, runLength);
    if (end === -1) {
      index += runLength - 1;
      continue;
    }
    const body = normalizeInlineCodeBody(value.slice(index + runLength, end - runLength + 1));
    const lineStart = Math.max(value.lastIndexOf('\n', index), value.lastIndexOf('\r', index)) + 1;
    const lineEndMatch = value.slice(end + 1).search(/[\r\n]/u);
    const lineEnd = lineEndMatch === -1 ? value.length : end + 1 + lineEndMatch;
    const decision = shouldMask(body, {
      prefix: value.slice(lineStart, index),
      suffix: value.slice(end + 1, lineEnd),
    });
    if (decision === true) {
      for (let cursor = index; cursor <= end; cursor += 1) output[cursor] = ' ';
    } else if (decision === 'segment') {
      for (let cursor = index; cursor < index + runLength; cursor += 1) output[cursor] = ' ';
      for (let cursor = end - runLength + 1; cursor <= end; cursor += 1) output[cursor] = ' ';
      for (let cursor = index + runLength; cursor < end - runLength + 1; cursor += 1) {
        if (value[cursor] === '\r' || value[cursor] === '\n') output[cursor] = ' ';
      }
      output[index] = ';';
      output[end] = ';';
    }
    index = end;
  }
  return output.join('');
}

function inlineCodeRequiresShellInspection(value, { includeEnvironmentCommands = true } = {}) {
  const visible = normalizeArtifactVisibleText(value);
  const tokens = tokenizeShellSegment(visible);
  const environmentCommands = findEnvironmentCommands(tokens);
  const hasProtectedIdentifier = tokens.some((token) => /(?:^|[^A-Za-z0-9_])(GRAFANA_PASSWORD|GF_SECURITY_ADMIN_PASSWORD)(?:$|[^A-Za-z0-9_])/u.test(token.value)
    || token.expansions.some((expansion) => ['GRAFANA_PASSWORD', 'GF_SECURITY_ADMIN_PASSWORD'].includes(shellParameterIdentifier(expansion))));
  const hasProtectedDeclarationDump = includeEnvironmentCommands && environmentCommands.some(({ index, command }) => ['export', 'declare', 'typeset', 'readonly'].includes(command)
    && (tokens.slice(index + 1).some((token) => /^-[A-Za-z]*p[A-Za-z]*$/u.test(token.value)) || hasProtectedIdentifier));
  const hasEnvironmentOutput = includeEnvironmentCommands && environmentCommands.some(({ command }) => ['env', 'printenv', 'set', 'unresolved-prefix-option'].includes(command));
  return tokens.some((token) => hasLgtmExpansion(token) || hasGrafanaSecretExpansion(token))
    || tokens.some((token, index) => executableBasename(token.value) === 'docker'
      && executableBasename(tokens[index + 1]?.value) === 'inspect')
    || hasEnvironmentOutput
    || hasProtectedDeclarationDump
    || includeEnvironmentCommands && findProtectedContextCommands(tokens).length > 0;
}

function inlineCodeHasProtectedEnvironmentCommand(value) {
  const tokens = tokenizeShellSegment(normalizeArtifactVisibleText(value));
  const protectedNames = new Set(['GRAFANA_PASSWORD', 'GF_SECURITY_ADMIN_PASSWORD']);
  return findEnvironmentCommands(tokens).some(({ index, command }) => {
    if (['env', 'printenv'].includes(command)) {
      return tokens.slice(index + 1).some((token) => token.value.split(/[^A-Za-z0-9_]+/u).some((part) => protectedNames.has(part)))
        || tokens.slice(index + 1).some((token) => token.expansions.some((expansion) => protectedNames.has(shellParameterIdentifier(expansion))));
    }
    if (!['export', 'declare', 'typeset', 'readonly'].includes(command)) return false;
    const operands = tokens.slice(index + 1);
    return operands.some((token) => {
      const parts = token.value.split(/[^A-Za-z0-9_]+/u);
      return parts.some((part) => protectedNames.has(part))
        || token.expansions.some((expansion) => protectedNames.has(shellParameterIdentifier(expansion)));
    });
  });
}

function inlineCodeHasEnvironmentOutputCommand(value) {
  const tokens = tokenizeShellSegment(normalizeArtifactVisibleText(value));
  return findEnvironmentCommands(tokens).some(({ command }) => ['env', 'printenv'].includes(command));
}

function normalizedInlineDescription(value) {
  return normalizeArtifactVisibleText(value).replace(/\s+/gu, ' ').trim();
}

function normalizedInlineDescriptionLine(value, side) {
  const lines = normalizeArtifactVisibleText(value).split(/\r\n?|\n/u);
  let line = side === 'prefix' ? lines.at(-1) ?? '' : lines[0] ?? '';
  let previousLine = null;
  while (line !== previousLine) {
    previousLine = line;
    line = line.replace(/^[ \t]*>[ \t]?/u, '');
    line = line.replace(/^[ \t]*(?:[*+-]|\d{1,9}[.)])(?=[ \t])/u, '');
  }
  return line.replace(/\s+/gu, ' ').trim();
}

function inlineCodeHasTechnicalDescription(value, command, { prefix = '', suffix = '' } = {}) {
  if (normalizedInlineDescription(value) !== command) return false;
  const marker = `⟦${command}⟧`;
  const context = `${normalizedInlineDescriptionLine(prefix, 'prefix')} ${marker} ${normalizedInlineDescriptionLine(suffix, 'suffix')}`
    .replace(/\s+/gu, ' ')
    .trim();
  if (command === 'env') {
    return /^Use\s+⟦env⟧\s+as\s+(?:the\s+command\s+name|prose|documentation\s+text|text)[.!?]$/u.test(context)
      || /^コマンド名として\s*⟦env⟧\s*を(?:説明|紹介|記載|示)します?[。.!?]?$/u.test(context);
  }
  if (command === 'readonly') {
    return /^Use\s+⟦readonly⟧\s+as\s+(?:a\s+PHP\s+keyword|prose|documentation\s+text|text)[.!?]$/u.test(context)
      || /^PHPキーワードとして\s*⟦readonly⟧\s*を(?:説明|紹介|記載|示)します?[。.!?]?$/u.test(context);
  }
  return false;
}

function inlineCodeHasContinuationEnvironmentDump(value, { prefix = '', suffix = '' } = {}) {
  const tokens = tokenizeShellSegment(normalizeArtifactVisibleText(value));
  const environmentCommands = findEnvironmentCommands(tokens);
  return environmentCommands.some(({ index, command }) => {
    if (command === 'printenv' || command === 'set') return true;
    if (command === 'env') return !inlineCodeHasTechnicalDescription(value, command, { prefix, suffix });
    if (!['export', 'declare', 'typeset', 'readonly'].includes(command)) return false;
    const operands = commandArguments(tokens, index).filter((argument) => argument !== '--');
    const hasNamedOperand = operands.some((argument) => /^[A-Za-z_][A-Za-z0-9_]*(?:=.*)?$/u.test(argument));
    const hasPrintOption = operands.some((argument) => /^-[A-Za-z]*p[A-Za-z]*$/u.test(argument));
    if (hasPrintOption) return true;
    if (!hasNamedOperand) return !inlineCodeHasTechnicalDescription(value, command, { prefix, suffix });
    return false;
  });
}

function maskInlineHtmlCode(text) {
  let output = '';
  let cursor = 0;
  let preDepth = 0;
  let codeDepth = 0;
  const codeStack = [];
  const proseContainerStack = [];
  const pendingCodes = [];
  const executableLanguages = new Set(['bash', 'sh', 'shell', 'zsh', 'console', 'shellsession']);
  let pendingExecutablePreInBlockquote = false;
  let proseAfterExecutableBlockquote = false;
  const tagPattern = /<\/?([A-Za-z][A-Za-z0-9-]*)(?:\s[^>]*)?>/gu;
  const appendText = (value) => {
    for (const context of codeStack) context.visible += value;
    for (const context of proseContainerStack) {
      context.text += value;
      if (/\r?\n/u.test(value)) context.hasLineBreak = true;
    }
    const context = codeStack.at(-1);
    if (codeDepth > 0 && context?.mask === true) {
      const masked = value.replace(/[^\r\n]/gu, ' ');
      const start = output.length;
      output += masked;
      context.ranges.push({ start, masked, original: value });
      return;
    }
    output += value;
  };
  const finishCode = () => {
    const context = codeStack.pop();
    if (context === undefined) return;
    if (context.executablePreInBlockquote) pendingExecutablePreInBlockquote = true;
    pendingCodes.push(context);
  };
  const finalizeCode = (context) => {
    const proseText = context.proseContainer?.text ?? '';
    const prosePrefix = context.proseContainer === null
      ? context.prosePrefix
      : proseText.slice(0, context.proseStartOffset);
    const proseSuffix = context.proseContainer === null
      ? ''
      : proseText.slice(context.proseStartOffset + context.visible.length);
    const continuationDump = context.proseContinuation
      && inlineCodeHasContinuationEnvironmentDump(context.visible, { prefix: prosePrefix, suffix: proseSuffix });
    const includeEnvironmentCommands = !context.nonExecutableBlock
      && (!context.proseContinuation
        || inlineCodeHasProtectedEnvironmentCommand(context.visible)
        || context.proseAfterExecutableBlockquote && inlineCodeHasEnvironmentOutputCommand(context.visible));
    if (context.mask !== true || (!continuationDump && !inlineCodeRequiresShellInspection(context.visible, { includeEnvironmentCommands }))) return;
    for (let index = context.ranges.length - 1; index >= 0; index -= 1) {
      const { start, masked, original } = context.ranges[index];
      const before = index === 0 ? ';' : '';
      const after = index === context.ranges.length - 1 ? ';' : '';
      output = output.slice(0, start) + before + original + after + output.slice(start + masked.length);
    }
  };
  for (const match of text.matchAll(tagPattern)) {
    appendText(text.slice(cursor, match.index));
    output += match[0];
    const tagName = match[1].toLocaleLowerCase('en-US');
    const closing = /^<\//u.test(match[0]);
    const selfClosing = /\/>\s*$/u.test(match[0]);
    if (closing) {
      if (tagName === 'code') {
        finishCode();
        codeDepth = Math.max(0, codeDepth - 1);
      }
      if (tagName === 'pre') preDepth = Math.max(0, preDepth - 1);
      if (['blockquote', 'li', 'p'].includes(tagName)) {
        if (tagName === 'blockquote' && pendingExecutablePreInBlockquote) {
          proseAfterExecutableBlockquote = true;
          pendingExecutablePreInBlockquote = false;
        }
        const index = proseContainerStack.findLastIndex(({ tag }) => tag === tagName);
        if (index >= 0) proseContainerStack.splice(index, 1);
      }
    } else if (!selfClosing) {
      if (tagName === 'pre') preDepth += 1;
      if (tagName === 'code') {
        codeDepth += 1;
        const classValue = match[0].match(/\bclass\s*=\s*(['"])(.*?)\1/iu)?.[2] ?? '';
        const language = classValue.split(/\s+/u).find((className) => className.startsWith('language-'))?.slice('language-'.length).toLocaleLowerCase('en-US');
        const nonExecutableBlock = preDepth > 0 && language !== undefined && !executableLanguages.has(language);
        const inBlockquote = proseContainerStack.some(({ tag }) => tag === 'blockquote');
        const proseContainer = proseContainerStack.at(-1) ?? null;
        codeStack.push({
          mask: preDepth === 0 || nonExecutableBlock,
          visible: '',
          ranges: [],
          proseContinuation: proseAfterExecutableBlockquote
            || proseContainerStack.some(({ hasLineBreak, tag }) => hasLineBreak || tag === 'li'),
          proseContainer,
          proseStartOffset: proseContainer?.text.length ?? 0,
          prosePrefix: proseContainer?.text ?? '',
          proseAfterExecutableBlockquote,
          nonExecutableBlock,
          executablePreInBlockquote: preDepth > 0 && inBlockquote && !nonExecutableBlock,
        });
        proseAfterExecutableBlockquote = false;
      }
      if (['blockquote', 'li', 'p'].includes(tagName)) proseContainerStack.push({ tag: tagName, hasLineBreak: false, text: '' });
    }
    cursor = (match.index ?? cursor) + match[0].length;
  }
  appendText(text.slice(cursor));
  for (const context of pendingCodes) finalizeCode(context);
  return output;
}

function markdownContainerInfo(line) {
  let content = line;
  let blockquoteDepth = 0;
  while (true) {
    const prefix = content.match(/^ {0,3}>[ \t]?/u);
    if (prefix === null) break;
    blockquoteDepth += 1;
    content = content.slice(prefix[0].length);
  }
  const listMarker = content.match(/^ {0,3}(?:[-+*]|\d+[.)])(?=$|[ \t])/u);
  if (listMarker === null) return { content, blockquoteDepth, listMarker: false, listIndent: null, listMarkerCode: false };

  const markerText = listMarker[0];
  const afterMarker = content.slice(markerText.length);
  let consumedPadding = 0;
  let listMarkerCode = false;
  const tabPadding = afterMarker.match(/^ *(?=\t)/u);
  if (tabPadding !== null) {
    const spacesBeforeTab = tabPadding[0].length;
    consumedPadding = spacesBeforeTab + 1;
    listMarkerCode = spacesBeforeTab >= Math.max(0, 4 - markerText.length);
  } else {
    const spaces = afterMarker.match(/^ */u)?.[0].length ?? 0;
    if (spaces > 0) {
      consumedPadding = spaces <= 4 ? spaces : 1;
      listMarkerCode = spaces > 4 || /\t/u.test(afterMarker.slice(consumedPadding));
    }
  }
  content = afterMarker.slice(consumedPadding);
  return {
    content,
    blockquoteDepth,
    listMarker: true,
    listIndent: tabPadding !== null ? 4 : markerText.length + consumedPadding,
    listMarkerCode,
  };
}

function maskNonExecutableInlineBackticks(text, { markdownSource = false } = {}) {
  if (!markdownSource) return text;

  const lines = text.split(/\r?\n/u);
  const output = [];
  let fence = null;
  let listContext = false;
  let listContinuationIndent = null;
  let lazyContainer = null;
  let previousBlock = 'blank';
  let chunkLines = [];
  let chunkMode = null;
  let chunkContinuationDumps = false;
  const flushChunk = () => {
    if (chunkLines.length === 0) return;
    const chunk = chunkLines.join('\n');
    const maskInlineCode = (body, { prefix, suffix } = {}) => {
      const continuationDump = chunkContinuationDumps && inlineCodeHasContinuationEnvironmentDump(body, { prefix, suffix });
      const includeEnvironmentCommands = chunkMode
        || inlineCodeHasProtectedEnvironmentCommand(body);
      return continuationDump || inlineCodeRequiresShellInspection(body, { includeEnvironmentCommands }) ? 'segment' : true;
    };
    output.push(replaceInlineBackticks(chunk, maskInlineCode));
    chunkLines = [];
    chunkMode = null;
    chunkContinuationDumps = false;
  };
  const appendExecutableLine = (line) => {
    flushChunk();
    output.push(line);
  };

  for (const line of lines) {
    const container = markdownContainerInfo(line);
    if (fence !== null && container.blockquoteDepth < fence.blockquoteDepth) {
      flushChunk();
      fence = null;
      previousBlock = 'fence';
      lazyContainer = null;
    }
    const regularFence = container.content.match(/^ {0,3}(`{3,}|~{3,})(.*)$/u);
    const nestedFence = listContext ? container.content.match(/^( {4,})(`{3,}|~{3,})(.*)$/u) : null;
    const fenceMatch = regularFence === null && nestedFence !== null
      ? { markerToken: nestedFence[2], info: nestedFence[3] }
      : regularFence === null ? null : { markerToken: regularFence[1], info: regularFence[2] };
    if (fenceMatch !== null) {
      flushChunk();
      output.push(line);
      lazyContainer = null;
      const marker = fenceMatch.markerToken[0];
      const runLength = fenceMatch.markerToken.length;
      if (fence !== null && fence.marker === marker && runLength >= fence.runLength && /^\s*$/u.test(fenceMatch.info)) {
        fence = null;
      } else if (fence === null) {
        const info = fenceMatch.info.trim().split(/\s+/u, 1)[0]?.toLocaleLowerCase('en-US') ?? '';
        fence = {
          marker,
          runLength,
          blockquoteDepth: container.blockquoteDepth,
          executable: ['bash', 'sh', 'shell', 'zsh', 'console', 'shellsession'].includes(info),
        };
      }
      previousBlock = 'fence';
      continue;
    }

    if (fence !== null) {
      if (fence.executable) appendExecutableLine(line);
      else {
        if (chunkMode !== false) flushChunk();
        chunkMode = false;
        chunkContinuationDumps = false;
        chunkLines.push(line);
      }
      previousBlock = 'fence-content';
      continue;
    }

    const hasIndent = /^(?: {4}|\t)/u.test(container.content);
    const leadingWhitespace = container.content.match(/^[ \t]*/u)?.[0] ?? '';
    const indentWidth = leadingWhitespace.replace(/\t/gu, '    ').length;
    const listCodeBlock = listContext && previousBlock === 'blank' && listContinuationIndent !== null && indentWidth >= listContinuationIndent + 4;
    const listIndentedContinuation = hasIndent && listContext && listContinuationIndent !== null && indentWidth >= listContinuationIndent;
    const paragraphContinuation = container.blockquoteDepth === 0 && ['paragraph', 'paragraph-continuation'].includes(previousBlock);
    const blockquoteContinuation = container.blockquoteDepth > 0 && ['blockquote', 'blockquote-continuation'].includes(previousBlock);
    const content = container.content.trim();
    const startsBlock = content === ''
      || /^#{1,6}(?:[ \t]+|$)/u.test(content)
      || /^(?:`{3,}|~{3,})/u.test(content)
      || /^(?:[-+*]|\d+[.)])(?:[ \t]+|$)/u.test(content);
    const lazyListContinuation = lazyContainer === 'list'
      && container.blockquoteDepth === 0
      && !container.listMarker
      && ['list', 'paragraph-continuation'].includes(previousBlock)
      && !startsBlock;
    const lazyBlockquoteContinuation = lazyContainer === 'blockquote'
      && container.blockquoteDepth === 0
      && !container.listMarker
      && ['blockquote', 'blockquote-continuation'].includes(previousBlock)
      && !startsBlock;
    const listMarkerProse = container.listMarker && !container.listMarkerCode;
    const listCodeMarker = container.listMarker && container.listMarkerCode;
    const continuationProse = !listCodeBlock && !listCodeMarker && (
      lazyListContinuation
      || lazyBlockquoteContinuation
      || listMarkerProse
      || listIndentedContinuation
      || (hasIndent && (paragraphContinuation || blockquoteContinuation))
      || (blockquoteContinuation && !startsBlock)
    );
    const executableBlock = hasIndent && !continuationProse && (!container.listMarker || listCodeMarker);
    if (executableBlock) {
      appendExecutableLine(line);
      previousBlock = 'indented-code';
      listContext = false;
      listContinuationIndent = null;
      continue;
    }

    const mode = !continuationProse;
    if (chunkMode !== null && chunkMode !== mode) flushChunk();
    chunkMode = mode;
    chunkContinuationDumps = chunkContinuationDumps || continuationProse;
    chunkLines.push(line);

    if (content === '') {
      previousBlock = 'blank';
      lazyContainer = null;
    } else if (container.listMarker) {
      previousBlock = 'list';
      listContext = true;
      listContinuationIndent = container.listIndent;
      lazyContainer = 'list';
    } else if (container.blockquoteDepth > 0) {
      previousBlock = continuationProse ? 'blockquote-continuation' : 'blockquote';
      lazyContainer = 'blockquote';
      if (!continuationProse) {
        listContext = false;
        listContinuationIndent = null;
      }
    } else if (continuationProse) {
      previousBlock = 'paragraph-continuation';
    } else if (/^#{1,6}(?:[ \t]+|$)/u.test(content)) {
      previousBlock = 'block-boundary';
      listContext = false;
      listContinuationIndent = null;
      lazyContainer = null;
    } else {
      previousBlock = 'paragraph';
      listContext = false;
      listContinuationIndent = null;
      lazyContainer = null;
    }
  }
  flushChunk();
  return output.join('\n');
}

function shellCommandSubstitutions(segment) {
  const substitutions = [];
  const matchingParenthesis = (value, start) => {
    let depth = 1;
    let quote = null;
    let escaped = false;
    for (let index = start + 2; index < value.length; index += 1) {
      const character = value[index];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (quote !== null) {
        if (quote === '"' && character === '\\') escaped = true;
        else if (character === quote) quote = null;
        continue;
      }
      if (character === "'" || character === '"') {
        quote = character;
        continue;
      }
      if (character === '\\') {
        escaped = true;
        continue;
      }
      if ((character === '$' || character === '<' || character === '>') && value[index + 1] === '(') {
        depth += 1;
        index += 1;
        continue;
      }
      if (character === '(') {
        depth += 1;
        continue;
      }
      if (character === ')') {
        depth -= 1;
        if (depth === 0) return index;
      }
    }
    return -1;
  };
  const collect = (value) => {
    let quote = null;
    let escaped = false;
    for (let index = 0; index < value.length; index += 1) {
      const character = value[index];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (quote === "'") {
        if (character === "'") quote = null;
        continue;
      }
      if (quote === '"' && character === '\\') {
        escaped = true;
        continue;
      }
      if (quote === null && (character === "'" || character === '"')) {
        quote = character;
        continue;
      }
      if (character === '$' && value[index + 1] === '(') {
        const end = matchingParenthesis(value, index);
        if (end === -1) continue;
        const body = value.slice(index + 2, end);
        substitutions.push(body);
        collect(body);
        index = end;
        continue;
      }
      if ((character === '<' || character === '>') && value[index + 1] === '(') {
        const end = matchingParenthesis(value, index);
        if (end === -1) continue;
        const body = value.slice(index + 2, end);
        substitutions.push(body);
        collect(body);
        index = end;
        continue;
      }
      if (character === '`') {
        let runLength = 0;
        while (value[index + runLength] === '`') runLength += 1;
        const end = matchingInlineBacktick(value, index, runLength);
        if (end === -1) continue;
        const body = value.slice(index + runLength, end - runLength + 1);
        substitutions.push(body);
        collect(body);
        index = end;
      }
    }
  };
  collect(segment);
  return substitutions;
}

function maskShellSubstitutionBodies(segment) {
  const masked = [...segment];
  const matchingParenthesis = (value, start) => {
    let depth = 1;
    let quote = null;
    let escaped = false;
    for (let index = start + 2; index < value.length; index += 1) {
      const character = value[index];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (quote !== null) {
        if (quote === '"' && character === '\\') escaped = true;
        else if (character === quote) quote = null;
        continue;
      }
      if (character === "'" || character === '"') {
        quote = character;
        continue;
      }
      if (character === '\\') {
        escaped = true;
        continue;
      }
      if (character === '$' && value[index + 1] === '(') {
        depth += 1;
        index += 1;
        continue;
      }
      if ((character === '<' || character === '>') && value[index + 1] === '(') {
        depth += 1;
        index += 1;
        continue;
      }
     if (character === '(') {
       depth += 1;
       continue;
     }
      if (character === ')') {
        depth -= 1;
        if (depth === 0) return index;
      }
    }
    return -1;
  };
  let quote = null;
  let escaped = false;
  for (let index = 0; index < segment.length; index += 1) {
    const character = segment[index];
    if (escaped) {
      escaped = false;
      continue;
    }
    if (quote === "'" ) {
      if (character === "'") quote = null;
      continue;
    }
    if (quote === '"' && character === '\\') {
      escaped = true;
      continue;
    }
    if (quote === null && (character === "'" || character === '"')) {
      quote = character;
      continue;
    }
    const processStart = (character === '$' && segment[index + 1] === '(')
      || ((character === '<' || character === '>') && segment[index + 1] === '(');
    if (quote !== "'" && processStart) {
      const end = matchingParenthesis(segment, index);
      if (end === -1) continue;
      for (let cursor = index; cursor <= end; cursor += 1) masked[cursor] = ' ';
      index = end;
      continue;
    }
    if (quote !== "'" && character === '`') {
      let runLength = 0;
      while (segment[index + runLength] === '`') runLength += 1;
      const end = matchingInlineBacktick(segment, index, runLength);
      if (end === -1) continue;
      for (let cursor = index; cursor <= end; cursor += 1) masked[cursor] = ' ';
      index = end;
    }
  }
  return masked.join('');
}

function shellParameterExpansions(raw) {
  const expansions = [];
  const matchingBrace = (value, start) => {
    let depth = 1;
    let quote = null;
    let escaped = false;
    for (let index = start + 2; index < value.length; index += 1) {
      const character = value[index];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (quote !== null) {
        if (quote === '"' && character === '\\') escaped = true;
        else if (character === quote) quote = null;
        continue;
      }
      if (character === "'" || character === '"') {
        quote = character;
        continue;
      }
      if (character === '\\') {
        escaped = true;
        continue;
      }
      if (character === '$' && value[index + 1] === '{') {
        depth += 1;
        index += 1;
        continue;
      }
     if (character === '}') {
        depth -= 1;
        if (depth === 0) return index;
      }
    }
    return -1;
  };
  const collect = (value) => {
    let quote = null;
    let escaped = false;
    for (let index = 0; index < value.length; index += 1) {
      const character = value[index];
      if (escaped) {
        escaped = false;
        continue;
      }
      if (quote === "'") {
        if (character === "'") quote = null;
        continue;
      }
      if (quote === '"' && character === '\\') {
        escaped = true;
        continue;
      }
      if (quote === null && (character === "'" || character === '"')) {
        quote = character;
        continue;
      }
      if (character !== '$' || quote === "'") continue;
      if (value[index + 1] === '{') {
        const end = matchingBrace(value, index);
        if (end !== -1) {
          const expansion = value.slice(index, end + 1);
          expansions.push(expansion);
          collect(value.slice(index + 2, end));
          index = end;
        }
        continue;
      }
      if (/[A-Za-z_]/u.test(value[index + 1] ?? '')) {
        let end = index + 2;
        while (/[A-Za-z0-9_]/u.test(value[end] ?? '')) end += 1;
        expansions.push(value.slice(index, end));
        index = end - 1;
      }
    }
  };
  collect(raw);
  return expansions;
}

function shellParameterIdentifier(expansion) {
  const body = expansion.startsWith('${') && expansion.endsWith('}')
    ? expansion.slice(2, -1)
    : expansion.slice(1);
  return body.match(/^(?:!|#)?([A-Za-z_][A-Za-z0-9_]*)/u)?.[1] ?? null;
}

function tokenizeShellSegment(segment) {
  const tokens = [];
  let value = '';
  let tokenStart = -1;
  let quote = null;
  let escaped = false;
  let parameterDepth = 0;
  let parameterQuote = null;
  const pushToken = (end) => {
    if (tokenStart === -1) return;
    const raw = segment.slice(tokenStart, end);
    tokens.push({ value, raw, expansions: shellParameterExpansions(raw) });
    value = '';
    tokenStart = -1;
  };
  for (let index = 0; index < segment.length; index += 1) {
    const character = segment[index];
    if (escaped) {
      value += character;
      escaped = false;
      continue;
    }
    if (parameterDepth > 0) {
      value += character;
      if (parameterQuote !== null) {
        if (parameterQuote === '"' && character === '\\') {
          escaped = true;
        } else if (character === parameterQuote) {
          parameterQuote = null;
        }
        continue;
      }
      if (character === '\'' || character === '"') {
        parameterQuote = character;
      } else if (character === '$' && segment[index + 1] === '{') {
        parameterDepth += 1;
      } else if (character === '}') {
        parameterDepth -= 1;
      }
      continue;
    }
    if (quote !== null) {
      if (quote === '"' && character === '\\') {
        escaped = true;
        continue;
      }
      if (character === quote) quote = null;
      else value += character;
      continue;
    }
    if (character === "'" || character === '"') {
      if (tokenStart === -1) tokenStart = index;
      quote = character;
      continue;
    }
    if (character === '\\') {
      if (tokenStart === -1) tokenStart = index;
      escaped = true;
      continue;
    }
   if (character === '<' && segment.slice(index, index + 3) === '<<<') {
     pushToken(index);
     tokens.push({ value: '<<<', raw: '<<<', expansions: [] });
     index += 2;
     continue;
   }
    if (character === '|' || character === ';' || character === '&') {
      pushToken(index);
      tokens.push({ value: character, raw: character, expansions: [] });
      continue;
    }
    if (character === '(' || character === ')' || character === '{' || character === '}') {
      pushToken(index);
      tokens.push({ value: character, raw: character, expansions: [] });
      continue;
    }
   if (character === '$' && segment[index + 1] === '{') {
      if (tokenStart === -1) tokenStart = index;
      value += character + '{';
      parameterDepth = 1;
      index += 1;
      continue;
    }
    if (/\s/u.test(character)) {
      pushToken(index);
      continue;
    }
    if (tokenStart === -1) tokenStart = index;
    value += character;
  }
  if (escaped) value += '\\';
  pushToken(segment.length);
  return tokens;
}

function hasLgtmExpansion(token) {
  return token.expansions.some((expansion) => shellParameterIdentifier(expansion) === 'LGTM');
}

function hasGrafanaSecretExpansion(token) {
  return token.expansions.some((expansion) => ['GRAFANA_PASSWORD', 'GF_SECURITY_ADMIN_PASSWORD'].includes(shellParameterIdentifier(expansion)));
}

function isShellAssignment(value) {
  return /^[A-Za-z_][A-Za-z0-9_]*=/u.test(value);
}

const shellControlWords = new Set(['if', 'then', 'else', 'elif', 'do', 'done', 'while', 'until', '!', 'case', 'in', 'esac']);
const shellCommandPrefixes = new Set(['sudo', 'command', 'builtin', 'exec', 'nohup', 'setsid', 'nice', 'time', 'env', 'xargs']);
const shellPrefixOptions = new Map([
  ['sudo', {
    value: new Set(['-a', '--auth-type', '--authentication-type', '-c', '--login-class', '-C', '--close-from', '-D', '--chdir', '-g', '--group', '-h', '--host', '-p', '--prompt', '-R', '--chroot', '-r', '--role', '-T', '--command-timeout', '-t', '--type', '-U', '--other-user', '-u', '--user']),
    optionalValue: new Set(['--preserve-env']),
    boolean: new Set(['-A', '--askpass', '-b', '--background', '-B', '--bell', '-E', '--preserve-env', '-e', '--edit', '-H', '--set-home', '-i', '--login', '-K', '--remove-timestamp', '-k', '--reset-timestamp', '-N', '--no-update', '-n', '--non-interactive', '-P', '--preserve-groups', '-S', '--stdin', '-s', '--shell', '--']),
    terminal: new Set(['-e', '--edit', '-K', '--remove-timestamp', '-V', '--version', '-v', '--validate', '-l', '--list', '--help']),
  }],
  ['command', { boolean: new Set(['-p', '--']), terminal: new Set(['--help', '--version']) }],
  ['builtin', { boolean: new Set(['--']), terminal: new Set(['--help', '--version']) }],
  ['exec', { value: new Set(['-a']), boolean: new Set(['-c', '-l', '--']), terminal: new Set(['--help', '--version']) }],
  ['nohup', { boolean: new Set(['--']), terminal: new Set(['--help', '--version']) }],
  ['setsid', { boolean: new Set(['-c', '-f', '-w', '--ctty', '--fork', '--wait', '--wait-child']), terminal: new Set(['-h', '--help', '-V', '--version']) }],
  ['nice', { value: new Set(['-n', '--adjustment']), boolean: new Set(['--']), terminal: new Set(['--help', '--version']) }],
  ['time', { boolean: new Set(['-a', '--append', '-p', '--portability', '-q', '--quiet', '-v', '--verbose', '--']), value: new Set(['-f', '--format', '-o', '--output']), terminal: new Set(['-h', '--help', '-V', '--version']) }],
  ['xargs', {
    value: new Set(['-a', '--arg-file', '-d', '--delimiter', '-E', '-I', '-L', '-n', '--max-args', '-P', '--max-procs', '-s', '--max-chars', '--process-slot-var']),
    optionalValue: new Set(['-e', '--eof', '-i', '--replace', '-l', '--max-lines']),
    boolean: new Set(['-0', '--null', '-o', '--open-tty', '-r', '--no-run-if-empty', '-t', '--verbose', '-p', '--interactive', '-x', '--exit', '--show-limits', '--']),
    terminal: new Set(['--help', '--version']),
  }],
]);

function shellPrefixOption(prefix, value) {
  const rule = shellPrefixOptions.get(prefix);
  if (!rule || !value.startsWith('-')) return null;
  if (prefix === 'nice' && /^-(?:[+-]?[0-9]+)$/u.test(value)) return { kind: 'value', next: 1 };
  if (rule.terminal?.has(value)) return { kind: 'terminal', next: null };
  if (rule.boolean?.has(value)) return { kind: 'boolean', next: 1 };
  if (value.length > 2 && value[1] !== '-' && !value.includes('=')) {
    const shortOptions = value.slice(1);
    let booleanCluster = true;
    for (let index = 0; index < shortOptions.length; index += 1) {
      const shortOption = `-${shortOptions[index]}`;
      if (rule.terminal?.has(shortOption)) return { kind: 'terminal', next: null };
      if (rule.boolean?.has(shortOption)) continue;
      const optionalValue = rule.optionalValue?.has(shortOption);
      const requiredValue = rule.value?.has(shortOption);
      if (optionalValue || requiredValue) {
        const hasAttachedValue = index < shortOptions.length - 1;
        return optionalValue
          ? { kind: 'optional-value', next: 1 }
          : { kind: 'value', next: hasAttachedValue ? 1 : 2 };
      }
      booleanCluster = false;
      break;
    }
    if (booleanCluster) return { kind: 'boolean', next: 1 };
  }
  const equalsIndex = value.indexOf('=');
  const optionName = equalsIndex === -1 ? value : value.slice(0, equalsIndex);
  const optionalValueOption = [...(rule.optionalValue ?? [])].find((option) => optionName === option
    || equalsIndex === -1 && option.length === 2 && value.startsWith(option) && value.length > option.length);
  if (optionalValueOption !== undefined) return { kind: 'optional-value', next: 1 };
  const valueOption = [...(rule.value ?? [])].find((option) => optionName === option
    || equalsIndex === -1 && option.length === 2 && value.startsWith(option) && value.length > option.length);
  if (valueOption === undefined) return { kind: 'unknown', next: 1 };
  if (equalsIndex !== -1 || value.length > valueOption.length && valueOption.length === 2) return { kind: 'value', next: 1 };
  return { kind: 'value', next: 2 };
}

function executableBasename(value) {
  return value.match(/(?:^|[\\/])([^\\/]+)$/u)?.[1] ?? value;
}

function isDockerExecutableToken(token) {
  if (token === undefined) return false;
  const value = token.value;
  if (executableBasename(value) === 'docker') return true;
  return /(?:^|=)\$\((?:(?:[^()\s]+[\\/])*)docker$/u.test(value)
    || /^(?:<|>)\((?:(?:[^()\s]+[\\/])*)docker$/u.test(value);
}

function isEnvironmentExecutableToken(token) {
  if (token === undefined) return false;
  return ['env', 'printenv'].includes(executableBasename(token.value));
}

const dockerGlobalOptionsWithValue = new Set([
  '--config', '-c', '--context', '-H', '--host', '-l', '--log-level',
  '--tlscacert', '--tlscert', '--tlskey',
]);
const dockerGlobalBooleanOptions = new Set(['--debug', '-D', '--tls', '--tlsverify']);

function isUnquotedToken(token, value) {
  return token?.value === value && token.raw === value;
}

function commandArguments(tokens, commandIndex) {
  const argumentsAfterCommand = [];
  for (let index = commandIndex + 1; index < tokens.length; index += 1) {
    const token = tokens[index];
    if ([')', '}', '|', ';', '&'].some((control) => isUnquotedToken(token, control))) break;
    if (/^[0-9]*[<>].+/u.test(token.value)) continue;
    if (/^(?:[0-9]*>>?|[0-9]*<<?)$/u.test(token.value)) {
      index += 1;
      continue;
    }
    argumentsAfterCommand.push(token.value);
  }
  return argumentsAfterCommand;
}

function commandTokenPositions(tokens) {
  const positions = [];
  let expectingCommand = true;
  let prefix = null;
  let unresolvedPrefixOption = false;
  for (let index = 0; index < tokens.length; index += 1) {
    const token = tokens[index];
    const value = token.value;
    const alwaysBoundary = ['\u007c', ';', '&', ')', '}'].some((control) => isUnquotedToken(token, control));
    const openingBoundary = isUnquotedToken(token, '(')
      && (expectingCommand || tokens[index - 1]?.value === '<' || tokens[index - 1]?.value === '>');
    const groupBoundary = isUnquotedToken(token, '{') && expectingCommand;
    if (alwaysBoundary || openingBoundary || groupBoundary || (shellControlWords.has(value) && expectingCommand)) {
      prefix = null;
      expectingCommand = true;
      continue;
    }
    if (!expectingCommand) continue;
    if (/^[0-9]*[<>].+/u.test(value) || /^(?:[0-9]*>>?|[0-9]*<<?)$/u.test(value)) {
      if (/^(?:[0-9]*>>?|[0-9]*<<?)$/u.test(value)) index += 1;
      continue;
    }
   if (isShellAssignment(value)) continue;
    if (prefix !== null && value.startsWith('-')) {
      if (prefix === 'command' && /^-[^-]*[vV]/u.test(value)) {
        if (tokens[index + 1] !== undefined && !value.includes('=')) index += 1;
        prefix = null;
        expectingCommand = false;
        continue;
      }
      const option = shellPrefixOption(prefix, value);
      if (option?.kind === 'terminal') {
        prefix = null;
        expectingCommand = false;
        continue;
      }
      if (option?.kind === 'unknown') {
        unresolvedPrefixOption = true;
        continue;
      }
      if (option?.kind === 'value' && option.next === 2 && tokens[index + 1] !== undefined) index += 1;
      continue;
    }
    const executable = executableBasename(value);
    if (shellCommandPrefixes.has(executable)) {
      positions.push(index);
      prefix = executable;
      expectingCommand = true;
      continue;
    }
    positions.push(index);
    expectingCommand = false;
    prefix = null;
  }
  positions.unresolvedPrefixOption = unresolvedPrefixOption;
  return positions;
}

function parseDockerCommand(tokens, dockerIndex) {
  let cursor = dockerIndex + 1;
  let containerSubcommand = false;
  while (cursor < tokens.length) {
    const value = tokens[cursor].value;
    if (dockerGlobalOptionsWithValue.has(value)) {
      if (tokens[cursor + 1] === undefined) return null;
      cursor += 2;
      continue;
    }
    const equalsOption = value.includes('=') ? value.slice(0, value.indexOf('=')) : null;
    if (equalsOption !== null && (dockerGlobalOptionsWithValue.has(equalsOption) || dockerGlobalBooleanOptions.has(equalsOption))) {
      cursor += 1;
      continue;
    }
    if (dockerGlobalBooleanOptions.has(value)
      || (value.startsWith('-H') && value !== '-H')
      || value.startsWith('-c') && value !== '-c'
      || value.startsWith('-l') && value !== '-l') {
      cursor += 1;
      continue;
    }
    if (value === '--') {
      cursor += 1;
      continue;
    }
    if (value === 'container' && !containerSubcommand) {
      containerSubcommand = true;
      cursor += 1;
      continue;
    }
    if (value === 'inspect' || value === 'run' || value === 'exec') break;
    if (value.startsWith('-')) {
      cursor += 1;
      continue;
    }
    return null;
  }
  const command = tokens[cursor]?.value;
  if (!['inspect', 'run', 'exec'].includes(command)) return null;
  return { dockerIndex, commandIndex: cursor, command };
}

function prefixedCommandIndex(tokens, prefixIndex, prefix) {
  let cursor = prefixIndex + 1;
  while (cursor < tokens.length) {
    const token = tokens[cursor];
    if ([')', '}', '|', ';', '&'].some((control) => isUnquotedToken(token, control))) return null;
    if (!token.value.startsWith('-')) return cursor;
    const option = shellPrefixOption(prefix, token.value);
    if (option?.kind === 'terminal') return null;
    if (option?.kind === 'value' && option.next === 2) {
      cursor += 2;
      continue;
    }
    cursor += 1;
  }
  return null;
}

function findDockerCommands(tokens) {
  const commands = [];
  for (let index = 0; index < tokens.length; index += 1) {
    if (!isDockerExecutableToken(tokens[index])) continue;
    const command = parseDockerCommand(tokens, index);
    if (command) commands.push(command);
  }
  return commands;
}

function findEnvironmentCommands(tokens) {
  const commandIndexes = commandTokenPositions(tokens);
  const environmentIndexes = commandIndexes
    .filter((index) => isEnvironmentExecutableToken(tokens[index]))
    .map((index) => ({ index, command: executableBasename(tokens[index].value) }));
  if (commandIndexes.unresolvedPrefixOption) environmentIndexes.push({ index: -1, command: 'unresolved-prefix-option' });
  for (const index of commandIndexes) {
    const value = executableBasename(tokens[index].value);
    const argumentsAfterCommand = commandArguments(tokens, index);
    if (value === 'set' && argumentsAfterCommand.length === 0) {
      environmentIndexes.push({ index, command: value });
    }
    if (['export', 'declare', 'typeset', 'readonly'].includes(value)) {
      const isNoArgumentDump = argumentsAfterCommand.length === 0;
      const isPrintDump = argumentsAfterCommand.some((argument) => argument === '-p' || /^-[A-Za-z]*p[A-Za-z]*$/u.test(argument));
      const declarationOperands = argumentsAfterCommand.filter((argument) => argument !== '--' && !/^-\S*$/u.test(argument));
      const hasNamedOperand = declarationOperands.some((argument) => /^[A-Za-z_][A-Za-z0-9_]*(?:=.*)?$/u.test(argument));
      const isOptionOnlyDump = declarationOperands.length === 0 || !hasNamedOperand;
      if (isNoArgumentDump || isPrintDump || isOptionOnlyDump) environmentIndexes.push({ index, command: value });
    }
  }
  for (const index of commandIndexes) {
    if (executableBasename(tokens[index].value) !== 'xargs') continue;
    const cursor = prefixedCommandIndex(tokens, index, 'xargs');
    if (isEnvironmentExecutableToken(tokens[cursor])) {
      environmentIndexes.push({ index: cursor, command: executableBasename(tokens[cursor].value) });
    }
  }
  return environmentIndexes.filter((entry, index, entries) => entries.findIndex((candidate) => candidate.index === entry.index) === index);
}

function findProtectedContextCommands(tokens) {
  const environmentIndexes = [];
  for (const index of commandTokenPositions(tokens)) {
    if (!isEnvironmentExecutableToken(tokens[index])) continue;
    const hasProtectedIdentifier = tokens.slice(index + 1).some((token) => {
      if (/(?:^|[^A-Za-z0-9_])(GRAFANA_PASSWORD|GF_SECURITY_ADMIN_PASSWORD)(?:$|[^A-Za-z0-9_])/u.test(token.value)) return true;
      return token.expansions.some((expansion) => ['GRAFANA_PASSWORD', 'GF_SECURITY_ADMIN_PASSWORD'].includes(shellParameterIdentifier(expansion)));
    });
    if (hasProtectedIdentifier) environmentIndexes.push({ index, command: executableBasename(tokens[index].value) });
  }
  return environmentIndexes;
}

function makeShellPayloadToken(parts) {
  const raw = parts.map((part) => part.raw).join(' ');
  return { value: parts.map((part) => part.value).join(' '), raw, expansions: shellParameterExpansions(raw) };
}

function findLiteralShellProducerPayload(tokens) {
  const commandIndex = commandTokenPositions(tokens).find((index) => ['printf', 'echo'].includes(executableBasename(tokens[index].value)));
  if (commandIndex === undefined || tokens.length <= commandIndex + 1) return null;
  return makeShellPayloadToken(tokens.slice(commandIndex + 1));
}

function findPipelineShellStdinPayloads(tokens) {
  const payloads = [];
  for (let shellIndex = 0; shellIndex < tokens.length; shellIndex += 1) {
    if (!['sh', 'bash'].includes(executableBasename(tokens[shellIndex].value))) continue;
    let pipeIndex = -1;
    for (let index = shellIndex - 1; index >= 0; index -= 1) {
      if (tokens[index].value === '|') {
        pipeIndex = index;
        break;
      }
    }
    if (pipeIndex === -1) continue;
    const token = findLiteralShellProducerPayload(tokens.slice(0, pipeIndex));
    if (token) payloads.push({ shellIndex, payloadIndex: pipeIndex, token });
  }
  return payloads;
}

function findShellPayloads(tokens) {
  const payloads = [];
  const optionsWithValue = new Set(['-o', '-O', '--rcfile', '--init-file']);
  for (let index = 0; index < tokens.length; index += 1) {
    if (!['sh', 'bash'].includes(executableBasename(tokens[index].value))) continue;
    for (let cursor = index + 1; cursor < tokens.length; cursor += 1) {
      const value = tokens[cursor].value;
      if ((value === '<' || value === '>') && tokens[cursor + 1]?.value === '(') {
        let depth = 1;
        let closeIndex = -1;
        for (let processIndex = cursor + 2; processIndex < tokens.length; processIndex += 1) {
          if (tokens[processIndex].value === '(') depth += 1;
          if (tokens[processIndex].value === ')') {
            depth -= 1;
            if (depth === 0) {
              closeIndex = processIndex;
              break;
            }
          }
        }
        if (closeIndex !== -1) {
          const token = findLiteralShellProducerPayload(tokens.slice(cursor + 2, closeIndex));
          if (token) payloads.push({ shellIndex: index, payloadIndex: cursor, token });
        }
        break;
      }
      if (value.startsWith('<(') || value.startsWith('>(')) {
        const rawParts = [];
        for (let processIndex = cursor; processIndex < tokens.length; processIndex += 1) {
          rawParts.push(tokens[processIndex].raw);
          if (tokens[processIndex].raw.endsWith(')')) break;
        }
        const processRaw = rawParts.join(' ');
        if (processRaw.endsWith(')')) {
          const processBodyTokens = tokenizeShellSegment(processRaw.slice(2, -1));
          const token = findLiteralShellProducerPayload(processBodyTokens);
          if (token) payloads.push({ shellIndex: index, payloadIndex: cursor, token });
        }
        break;
      }
      if (value === '<<<') {
        if (tokens[cursor + 1] !== undefined) payloads.push({ shellIndex: index, payloadIndex: cursor + 1, token: tokens[cursor + 1] });
        break;
      }
      if (value === '-c' || value === '--command') {
        if (tokens[cursor + 1] !== undefined) payloads.push({ shellIndex: index, payloadIndex: cursor + 1, token: tokens[cursor + 1] });
        break;
      }
      if (/^-[^-]*c/u.test(value)) {
        if (tokens[cursor + 1] !== undefined) payloads.push({ shellIndex: index, payloadIndex: cursor + 1, token: tokens[cursor + 1] });
        break;
      }
      if (optionsWithValue.has(value)) {
        cursor += 1;
        continue;
      }
      if (optionsWithValue.has(value.split('=', 1)[0])) continue;
      if (value.startsWith('-')) continue;
      break;
    }
  }
  for (let index = 0; index < tokens.length; index += 1) {
    if (executableBasename(tokens[index].value) !== 'eval') continue;
    const parts = tokens.slice(index + 1);
    if (parts.length > 0) payloads.push({ shellIndex: index, payloadIndex: index + 1, token: makeShellPayloadToken(parts) });
  }
  return payloads;
}

function allowedDockerEnvTokens(tokens, dockerCommand) {
  const allowed = new Set();
  if (dockerCommand?.command !== 'run') return allowed;
  for (let index = dockerCommand.commandIndex + 1; index < tokens.length; index += 1) {
    const value = tokens[index].value;
    if (value === '--env' || value === '-e') {
      if (/^GF_SECURITY_ADMIN_PASSWORD=/u.test(tokens[index + 1]?.value ?? '')) allowed.add(index + 1);
      continue;
    }
    if (value.startsWith('--env=') && /^GF_SECURITY_ADMIN_PASSWORD=/u.test(value.slice('--env='.length))) {
      allowed.add(index);
    }
  }
  return allowed;
}

export function assertNoUnsafeLgtmDiagnostics(text, location = 'source', recursionDepth = 0) {
  if (recursionDepth > 8) throw new Error(`LGTM diagnostics contain an unresolved recursive shell payload in ${location}.`);
  const stateHealthFields = new Set(['.State.Status', '.State.Health', '.State.Health.Status']);
  const formatValueIsSafe = (formatToken) => {
    const templateActions = [...formatToken.value.matchAll(/\{\{([\s\S]*?)\}\}/gu)].map(([, action]) => action);
    const formatFields = templateActions.flatMap((action) => [...action
      .replace(/'(?:\\.|[^'])*'|"(?:\\.|[^"])*"/gu, '')
      .matchAll(/(?:^|[\s(])((?:\.[A-Za-z_][A-Za-z0-9_.]*)|\.)/gu)].map(([, field]) => field));
    const hasUnsafeTemplateRoot = templateActions.some((action) => action.includes('$'));
    return formatFields.some((field) => stateHealthFields.has(field))
      && formatFields.every((field) => stateHealthFields.has(field))
      && templateActions.length > 0
      && !hasUnsafeTemplateRoot
      && formatToken.expansions.length === 0
      && !/(?:GRAFANA_PASSWORD|GF_SECURITY_ADMIN_PASSWORD)/iu.test(formatToken.value)
      && !/\$[A-Za-z_][A-Za-z0-9_]*/u.test(formatToken.value)
      && !/(?:\bjson\b|\{\{\s*\.\s*\}\})/iu.test(formatToken.value);
  };
  const markdownSource = /(?:^|[\\/])[^\\/]+\.mdx?$/u.test(location)
    || /(?:^|[\\/])llms(?:-full)?\.txt$/u.test(location);
  const diagnosticText = maskNonExecutableInlineBackticks(normalizeArtifactVisibleText(maskInlineHtmlCode(text)), {
    markdownSource,
  });
  for (const line of joinShellContinuations(diagnosticText)) {
    const lineStdinPayloads = findPipelineShellStdinPayloads(tokenizeShellSegment(line)).map(({ token }) => token.value);
    for (const segment of splitShellCommandSegments(line)) {
      const nestedSegments = [
        ...shellCommandSubstitutions(segment).flatMap((nested) => splitShellCommandSegments(nested)),
        ...lineStdinPayloads,
      ];
      for (const executableSegment of [segment, ...nestedSegments]) {
        const tokens = tokenizeShellSegment(executableSegment);
        if (tokens.length === 0) continue;
        const dockerCommands = findDockerCommands(tokens);
        const outerTokens = tokenizeShellSegment(maskShellSubstitutionBodies(executableSegment));
        const outerDockerCommands = findDockerCommands(outerTokens);
        const hasOuterLgtmTarget = outerTokens.some((token) => hasLgtmExpansion(token));
        const hasOuterInspectWord = outerTokens.some((token) => executableBasename(token.value) === 'inspect');
        if (hasOuterLgtmTarget && hasOuterInspectWord && outerDockerCommands.length === 0) {
          throw new Error(`LGTM diagnostics contain an unresolved inspect command in ${location}: ${executableSegment.trim()}`);
        }
        const secretTokenIndexes = tokens.flatMap((token, index) => hasGrafanaSecretExpansion(token) ? [index] : []);
        if (secretTokenIndexes.length > 0) {
          const pureAssignment = tokens.length === 1 && /^GRAFANA_PASSWORD=\$\{GRAFANA_PASSWORD-local-admin\}$/iu.test(tokens[0].value);
          const allowedEnv = new Set(dockerCommands.flatMap((dockerCommand) => [...allowedDockerEnvTokens(tokens, dockerCommand)]));
          if (!pureAssignment && secretTokenIndexes.some((index) => !allowedEnv.has(index))) {
            throw new Error(`LGTM diagnostics must not expose Grafana credentials in ${location}: ${executableSegment.trim()}`);
          }
        }
        if (findEnvironmentCommands(tokens).length > 0 || findProtectedContextCommands(tokens).length > 0) {
          throw new Error(`LGTM diagnostics must not dump environment output in ${location}: ${executableSegment.trim()}`);
        }
        for (const dockerCommand of dockerCommands) {
          if (dockerCommand.command === 'exec' && tokens.slice(dockerCommand.commandIndex + 1).some((token) => ['env', 'printenv'].includes(executableBasename(token.value)))) {
            throw new Error(`LGTM diagnostics must not dump environment output in ${location}: ${executableSegment.trim()}`);
          }
          if (dockerCommand.command !== 'inspect') continue;
          const invocationTokens = tokens.slice(dockerCommand.dockerIndex);
          const targeted = invocationTokens.some((token) => hasLgtmExpansion(token));
          const formatTokens = [];
          for (let index = dockerCommand.commandIndex + 1; index < tokens.length; index += 1) {
            const value = tokens[index].value;
            if (value === '--format') {
              formatTokens.push(tokens[index + 1]);
            } else if (value.startsWith('--format=')) {
              formatTokens.push({ ...tokens[index], value: value.slice('--format='.length) });
            }
          }
          if (!targeted) {
            if (invocationTokens.some((token) => /(?:Config\.Env|\.Config\.Env|GF_SECURITY_ADMIN_PASSWORD)/iu.test(token.value))) {
              throw new Error(`LGTM diagnostics must not expose Config.Env in ${location}: ${executableSegment.trim()}`);
            }
            continue;
          }
         if (formatTokens.length !== 1 || !formatTokens[0] || !formatValueIsSafe(formatTokens[0])) {
           throw new Error(`LGTM diagnostics must use exactly one formatted State/Health output in ${location}: ${executableSegment.trim()}`);
         }
       }
        for (const payload of findShellPayloads(tokens)) {
          const payloadSegments = splitShellCommandSegments(payload.token.value);
          const payloadTokens = payloadSegments.flatMap((payloadSegment) => tokenizeShellSegment(payloadSegment));
          const hasKnownPayloadCommand = payloadTokens.some((token) => isDockerExecutableToken(token)
            || isEnvironmentExecutableToken(token)
            || ["inspect", "printf", "echo", "cat", "tee", "curl"].includes(executableBasename(token.value)));
          if (payload.token.expansions.length > 0 && !hasKnownPayloadCommand) {
            throw new Error(`LGTM diagnostics contain an unresolved shell payload in ${location}: ${executableSegment.trim()}`);
          }
          assertNoUnsafeLgtmDiagnostics(payload.token.value, `${location}: shell payload`, recursionDepth + 1);
        }
      }
    }
  }
}

export function validateSearchRouteInventory(search, expectedRoutes) {
  if (!Array.isArray(search)) throw new Error('Search artifact must be an array.');
  const expected = expectedRoutes instanceof Set ? expectedRoutes : new Set(expectedRoutes);
  const routes = new Map();
  for (const record of search) {
    if (typeof record?.route !== 'string') throw new Error('Search artifact record is missing a route.');
    if (routes.has(record.route)) throw new Error(`Search artifact contains a duplicate route: ${record.route}.`);
    routes.set(record.route, record);
  }
  const unknownRoutes = [...routes.keys()].filter((route) => route !== '/' && !expected.has(route));
  if (unknownRoutes.length > 0) throw new Error(`Search artifact contains unknown route(s): ${unknownRoutes.join(', ')}.`);
  const actual = new Set([...routes.keys()].filter((route) => route !== '/'));
  if (expected.size !== actual.size || [...expected].some((route) => !actual.has(route))) throw new Error('Search artifact route inventory does not match the 40-page Content Map.');
  return actual;
}

export function validateLlmRouteInventory(llms, expectedRoutes) {
  const expected = expectedRoutes instanceof Set ? expectedRoutes : new Set(expectedRoutes);
  const routes = new Map();
  for (const match of llms.matchAll(/^-\s+\[[^\]]+\]\(([^)]+)\)(?::\s*(.*))?$/gmu)) {
    const route = routeFromArtifactUrl(match[1]);
    if (route === '/') continue;
    if (!expected.has(route)) throw new Error(`llms.txt contains an unknown route: ${route}.`);
    if (routes.has(route)) throw new Error(`llms.txt contains a duplicate route: ${route}.`);
    routes.set(route, match[0]);
  }
  if (routes.size !== expected.size || [...expected].some((route) => !routes.has(route))) throw new Error('llms.txt route inventory does not match the 40-page Content Map.');
  return routes;
}

export function assertArtifactReaderText(text, { outcome, outcomes = [outcome], location = 'artifact' } = {}) {
  if (typeof text !== 'string' || typeof outcome !== 'string' || outcome === '') throw new Error(`Artifact reader fixture requires text and one outcome for ${location}.`);
  assertSingleOutcome([text], outcome, outcomes, location);
  assertNoProtectedDecode(text, location);
  assertNoCurrentMainOnly(text, location);
  assertNoInternalEvidenceVoice(text, location);
  assertNoUnsafeLgtmDiagnostics(text, location);
}

export function assertArtifactReaderFile(text, { location = 'artifact' } = {}) {
  const readerText = artifactReaderSurfaceText(text, location);
  if (readerText !== null) assertNoUnsafeLgtmDiagnostics(readerText, location);
}

function artifactReaderSurfaceText(text, location) {
  const normalizedLocation = location.replaceAll('\\', '/');
  if (/\.mdx?$/u.test(normalizedLocation) || /(?:^|\/)llms(?:-full)?\.txt$/u.test(normalizedLocation)) return text;
  if (normalizedLocation === 'blume-search.json') {
    try {
      return collectStrings(JSON.parse(text)).join('\n');
    } catch {
      return text;
    }
  }
  if (/\.html$/u.test(normalizedLocation)) return htmlReaderSurfaceText(text);
  return null;
}

function htmlStartTagEnd(text, start) {
  let quote = null;
  for (let index = start; index < text.length; index += 1) {
    const character = text[index];
    if (quote !== null) {
      if (character === quote) quote = null;
      continue;
    }
    if (character === '"' || character === "'") {
      quote = character;
      continue;
    }
    if (character === '>') return index;
  }
  return -1;
}

function htmlClosingTag(text, name, start) {
  const closePattern = new RegExp(`<\\/\\s*${name}\\s*>`, 'giu');
  closePattern.lastIndex = start;
  return closePattern.exec(text);
}

function htmlTagNameAt(text, start) {
  if (text[start] !== '<') return null;
  let cursor = start + 1;
  const closing = text[cursor] === '/';
  if (closing) cursor += 1;
  const nameStart = cursor;
  while (cursor < text.length && /[A-Za-z0-9:-]/u.test(text[cursor])) cursor += 1;
  if (cursor === nameStart) return null;
  return { closing, name: text.slice(nameStart, cursor).toLocaleLowerCase('en-US'), nameEnd: cursor };
}

function assertHtmlReaderMarkupStructure(html) {
  let cursor = 0;
  const foreignStack = [];
  while (cursor < html.length) {
    if (html.startsWith('<!--', cursor)) {
      const commentEnd = html.indexOf('-->', cursor + 4);
      cursor = commentEnd < 0 ? html.length : commentEnd + 3;
      continue;
    }
    if (foreignStack.length > 0 && /^<!\[CDATA\[/iu.test(html.slice(cursor))) {
      const cdataEnd = html.indexOf(']]>', cursor + 9);
      cursor = cdataEnd < 0 ? html.length : cdataEnd + 3;
      continue;
    }
    if (html[cursor] !== '<') {
      cursor += 1;
      continue;
    }
    const tag = htmlTagNameAt(html, cursor);
    if (tag === null) {
      cursor += 1;
      continue;
    }
    const tagEnd = htmlStartTagEnd(html, tag.nameEnd);
    if (tagEnd < 0) {
      if (tag.name === 'template' || tag.name === 'script' || tag.name === 'style') {
        throw new Error('Unterminated HTML start tag in reader surface.');
      }
      cursor = tag.nameEnd;
      continue;
    }
    if (!tag.closing && (tag.name === 'script' || tag.name === 'style')) {
      const close = htmlClosingTag(html, tag.name, tagEnd + 1);
      if (close === null) return;
      cursor = close.index + close[0].length;
      continue;
    }
    if (!tag.closing && (tag.name === 'svg' || tag.name === 'math') && !/\/\s*>$/u.test(html.slice(cursor, tagEnd + 1))) {
      foreignStack.push(tag.name);
    } else if (tag.closing && (tag.name === 'svg' || tag.name === 'math')) {
      const index = foreignStack.lastIndexOf(tag.name);
      if (index >= 0) foreignStack.splice(index, 1);
    }
    cursor = tagEnd + 1;
  }
}

function htmlReaderSurfaceText(html) {
  const metadata = [];
  const jsonLd = [];
  assertHtmlReaderMarkupStructure(html);
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', () => {});
  const dom = new JSDOM(html, { virtualConsole });
  try {
    const { document } = dom.window;
    for (const script of document.querySelectorAll('script')) {
      const type = script.getAttribute('type');
      if (type === null || type.split(';', 1)[0].trim().toLocaleLowerCase('en-US') !== 'application/ld+json') continue;
      const body = script.textContent ?? '';
      try {
        jsonLd.push(collectStrings(JSON.parse(body)).join('\n'));
      } catch {
        jsonLd.push(body);
      }
    }
    for (const meta of document.querySelectorAll('meta')) {
      const values = ['name', 'property', 'content', 'itemprop', 'value']
        .map((attribute) => meta.getAttribute(attribute))
        .filter((value) => value !== null);
      if (values.length > 0) metadata.push(values.join(' '));
    }
    const comments = [];
    const walker = document.createTreeWalker(document, 128);
    while (walker.nextNode()) comments.push(walker.currentNode);
    for (const comment of comments) comment.remove();
    for (const element of document.querySelectorAll('script, style, template')) element.remove();
    return [document.documentElement?.outerHTML ?? '', ...metadata, ...jsonLd].join('\n');
  } finally {
    dom.window.close();
  }
}

export async function assertSourceDerivedReferenceCoverage(contentMap, repositoryRoot = defaultRepositoryRoot) {
  const coreApi = await readFile(path.join(repositoryRoot, 'docs/guide/core-api.md'), 'utf8');
  const attributes = await readFile(path.join(repositoryRoot, 'docs/guide/attributes.md'), 'utf8');
  const cli = await readFile(path.join(repositoryRoot, 'docs/guide/project-cli.md'), 'utf8');
  const configuration = await readFile(path.join(repositoryRoot, 'docs/guide/configuration.md'), 'utf8');
  const publicTypes = [];
  const publicAttributes = [];
  const publicCommands = new Set();
  const configurationKeys = new Set();
  for (const file of await phpFiles(path.join(repositoryRoot, 'src'))) {
    const source = await readFile(file, 'utf8');
    if (!source.includes('#[PublicApi]')) continue;
    const namespace = source.match(/^namespace\s+([^;]+);/m)?.[1];
    const declaration = source.match(/^(?:(?:final|abstract|readonly)\s+)*(class|interface|enum)\s+([A-Za-z0-9_]+)(?:\s*:\s*([^\s{]+))?/m);
    if (!namespace || !declaration) continue;
    const fqcn = `${namespace}\\${declaration[2]}`;
    publicTypes.push({
      name: fqcn,
      kind: declaration[1],
      enumBacking: declaration[1] === 'enum' ? declaration[3] ?? null : null,
      enumCases: declaration[1] === 'enum' ? extractEnumCases(source) : [],
      constants: extractPublicConstants(source),
      methods: extractPublicMethods(source),
    });
    if (/\\(?:Attribute|Validation\\Attribute)$/.test(namespace) && declaration[2] !== 'SensitiveMode') publicAttributes.push(fqcn);
  }
  for (const file of await phpFiles(path.join(repositoryRoot, 'src'))) {
    const source = await readFile(file, 'utf8');
    for (const [, command] of source.matchAll(/public\s+const\s+NAME\s*=\s*'([^']+)'/gu)) {
      if (!command.startsWith('blackops:') && !['outbox-relay', 'retention'].includes(command)) publicCommands.add(command);
    }
    for (const [, key] of source.matchAll(/\$configuration\[['"]([^'"]+)['"]\]/gu)) configurationKeys.add(key);
  }
  if (publicTypes.length !== 216) throw new Error(`Source-derived PublicApi coverage expected 216 types; found ${publicTypes.length}.`);
  if (publicAttributes.length !== 25) throw new Error(`Source-derived Public Attribute coverage expected 25 attributes; found ${publicAttributes.length}.`);
  validateReferenceDocumentation({
    coreApi,
    attributes,
    cli,
    configuration,
    publicTypes,
    publicAttributes,
    publicCommands,
    configurationKeys,
  });
  if (!contentMap['core-api.md']?.reader || contentMap['core-api.md'].reader.type !== 'reference') throw new Error('Core API must be a Reference reader page.');
  if (!contentMap['attributes.md']?.reader || contentMap['attributes.md'].reader.type !== 'reference') throw new Error('Attributes must be a Reference reader page.');
  if (!contentMap['project-cli.md']?.reader || contentMap['project-cli.md'].reader.type !== 'reference') throw new Error('Project CLI must be a Reference reader page.');
  if (!contentMap['configuration.md']?.reader || contentMap['configuration.md'].reader.type !== 'reference') throw new Error('Configuration must be a Reference reader page.');
}

export function validateReferenceDocumentation({ coreApi, attributes, cli, configuration, publicTypes = [], publicAttributes = [], publicCommands = [], configurationKeys = [] } = {}) {
  const documents = [coreApi, attributes, cli, configuration];
  if (documents.some((document) => typeof document !== 'string' || document.trim() === '')) throw new Error('Reference documentation coverage requires all four public Reference documents.');

  const lookupText = documents.join('\n');
  const missingFields = referenceLookupFields.filter(([, pattern]) => !pattern.test(lookupText)).map(([field]) => field);
  if (missingFields.length > 0) throw new Error(`Reference lookup fields are incomplete: ${missingFields.join(', ')}.`);

  const legacyRows = markdownTableRows(coreApi).filter(({ cells }) => cells.length === 4 && cells[0]?.includes('`BlackOps\\'));
  if (legacyRows.length > 0) throw new Error('Core API contains a duplicate legacy per-type namespace catalog; keep one exact namespace-split catalog.');
  const namespaceSections = [...coreApi.matchAll(/^####\s+Namespace\s+`([^`]+)`\s*$/gmu)].map(([, namespace]) => namespace);
  if (namespaceSections.length === 0) throw new Error('Core API reader-oriented namespace-split index is missing.');
  if (new Set(namespaceSections).size !== namespaceSections.length) throw new Error('Core API namespace-split index contains duplicate namespace sections.');

  const indexRows = markdownTableRows(coreApi).filter(({ cells }) => cells.length === 9 && cells[0]?.includes('BlackOps\\'));
  if (indexRows.length !== publicTypes.length) throw new Error(`Core API exact signature index expected ${publicTypes.length} rows; found ${indexRows.length}.`);
  for (const contract of publicTypes) {
    const type = typeof contract === 'string' ? contract : contract.name;
    const rows = indexRows.filter(({ cells }) => cells[0].includes(`\`${type}\``));
    if (rows.length !== 1) throw new Error(`Core API exact signature index must expose exactly one row for source-derived type: ${type}.`);
    const [, signature, parameters, returns, defaults, error, typical, enumContract, constantsContract] = rows[0].cells;
    if (rows[0].cells.length !== 9 || [signature, parameters, returns, defaults, error, typical, enumContract, constantsContract].some((cell) => cell.trim() === '')) {
      throw new Error(`Core API exact signature lookup is incomplete for source-derived type: ${type}.`);
    }
    const methods = typeof contract === 'string' ? [] : contract.methods;
    const shortName = type.split('\\').pop();
    if (methods.length === 0 && !signature.includes(`marker／${typeof contract === 'string' ? 'class' : contract.kind}／value type`)) throw new Error(`Core API exact signature index is missing the marker shape for source-derived type: ${type}.`);
    const returnMethods = methods.filter((method) => method.returnType !== null);
    if (returnMethods.length > 0) {
      assertExactMethodLookupMap(returns, returnMethods, 'Return', type, (method) => method.returnType ?? 'なし');
    } else if (methods.length > 0 && !/^なし（Return Typeなし）$/u.test(returns)) {
      throw new Error(`Core API exact Return lookup is not the no-return marker for source-derived type: ${type}.`);
    }
    if (methods.length > 0) {
      assertExactMethodLookupMap(error, methods, 'Error／Safe Code', type, (method) => method.errorContract ?? 'Source body: no direct throw or bounded helper error observed (non-exhaustive)');
    }
    for (const method of methods) {
      const methodToken = `${shortName}::${method.name}(`;
      if (!signature.includes(methodToken)) throw new Error(`Core API exact signature index is missing ${methodToken} for source-derived type: ${type}.`);
      const normalizedParameters = normalizeReferenceToken(method.parameters ?? '');
      const expectedSignature = `${shortName}::${method.name}(${normalizedParameters}): ${normalizeReferenceToken(method.returnType ?? 'なし')}`;
      if (!signature.includes(expectedSignature)) throw new Error(`Core API exact signature drifted for ${type}::${method.name}.`);
      if (method.parameters.trim() !== '' && !parameters.includes(`${method.name}: ${normalizedParameters}`)) throw new Error(`Core API exact parameter lookup drifted for ${type}::${method.name}.`);
      for (const parameter of parameterDefaults(method.parameters)) {
        if (!defaults.includes(`${method.name}.${parameter.name}=${normalizeReferenceToken(parameter.value)}`)) throw new Error(`Core API exact default lookup is missing ${type}::${method.name}(${parameter.name}).`);
      }
    }
    const expectedEnum = enumContractFor(contract);
    const expectedConstants = constantsContractFor(contract);
    if (enumContract !== expectedEnum) throw new Error(`Core API enum backing／case lookup drifted for source-derived type: ${type}.`);
    if (constantsContract !== expectedConstants) throw new Error(`Core API public constant lookup drifted for source-derived type: ${type}.`);
  }

  const attributeRows = markdownTableRows(attributes).filter(({ cells }) => cells[0]?.includes('`BlackOps\\'));
  const expectedAttributeRows = publicAttributes.length === 25 ? 25 : publicAttributes.length;
  if (attributeRows.length !== expectedAttributeRows) throw new Error(`Attributes Reference table expected ${expectedAttributeRows} source-derived rows; found ${attributeRows.length}.`);
  for (const attribute of publicAttributes) {
    const rows = attributeRows.filter(({ cells }) => cells[0].includes(`\`${attribute}\``));
    if (rows.length !== 1) throw new Error(`Attributes Reference must expose exactly one lookup row for source-derived attribute: ${attribute}.`);
    if (rows[0].cells.length < 4 || rows[0].cells.some((cell) => cell.trim() === '')) throw new Error(`Attributes Reference lookup row is incomplete for source-derived attribute: ${attribute}.`);
  }

  const cliRows = markdownTableRows(cli);
  for (const command of publicCommands) {
    if (!cliRows.some(({ raw }) => raw.includes(`\`${command}\``)) && !cli.includes(command)) throw new Error(`CLI Reference is missing source-derived command: ${command}.`);
  }
  for (const key of configurationKeys) {
    const keyPattern = new RegExp(`(?:^|[^A-Za-z0-9_])${escapeRegExp(key)}(?:$|[^A-Za-z0-9_])`, 'u');
    const documentedKey = configuration.includes(`\`${key}\``) || configuration.includes(`\`${key}.php\``) || configuration.includes(`'${key}'`);
    if (!keyPattern.test(configuration) || !documentedKey) throw new Error(`Configuration Reference is missing source-derived key lookup: ${key}.`);
  }

  const methodCount = publicTypes.reduce((total, contract) => total + (contract.methods?.length ?? 0), 0);
  const parameterCount = publicTypes.reduce((total, contract) => total + (contract.methods ?? []).filter((method) => method.parameters.trim() !== '').length, 0);
  const returnCount = publicTypes.reduce((total, contract) => total + (contract.methods ?? []).filter((method) => method.returnType !== null).length, 0);
  if (methodCount < 1 || parameterCount < 1 || returnCount < 1) throw new Error('Source-derived Reference contract could not extract public method, parameter, and return coverage.');
  if (!/(?:Source-derived lookup fields|source-derived表)/iu.test(coreApi)) throw new Error('Core API Reference is missing its source-derived lookup-field contract.');
  return { types: publicTypes.length, namespaceSections: namespaceSections.length, indexRows: indexRows.length, attributes: attributeRows.length, methods: methodCount, parameters: parameterCount, returns: returnCount };
}

function validateEntryShape(source, metadata) {
  const reader = metadata?.reader;
  if (!reader || typeof reader !== 'object' || Array.isArray(reader)) throw new Error(`Reader metadata is missing for ${source}.`);
  if (!readerTypes.includes(reader.type)) throw new Error(`Reader type is unknown for ${source}: ${reader.type ?? '<missing>'}.`);
  if (typeof reader.outcome !== 'string' || reader.outcome.trim() === '' || placeholderPattern.test(reader.outcome) || reader.outcome.trim().length < 10) {
    throw new Error(`Reader outcome is empty, generic, or placeholder for ${source}.`);
  }
  if (reader.outcome.includes(source) || reader.outcome.includes(metadata.slug)) throw new Error(`Reader outcome must describe an outcome rather than repeat the route for ${source}.`);
  if (!Array.isArray(reader.next) || reader.next.length === 0) throw new Error(`Reader next-page contract is missing for ${source}.`);
  for (const kind of ['topic', 'recipe']) {
    const identity = reader[kind];
    if (!identity || typeof identity !== 'object' || Array.isArray(identity)) throw new Error(`Reader ${kind} identity is missing for ${source}.`);
    if (typeof identity.identity !== 'string' || identity.identity.trim() === '') throw new Error(`Reader ${kind} identity is missing for ${source}.`);
    if (!['owner', 'reference'].includes(identity.role)) throw new Error(`Reader ${kind} role is invalid for ${source}: ${identity.role ?? '<missing>'}.`);
    if (typeof identity.reference !== 'string' || identity.reference.trim() === '') throw new Error(`Reader ${kind} reference is missing for ${source}.`);
    if (/^(?:topic|recipe):/iu.test(identity.identity) || identity.identity === metadata.slug || identity.identity === source) {
      throw new Error(`Reader ${kind} identity must be semantic rather than route-derived for ${source}.`);
    }
  }
}

function validateReaderIdentity(source, identity, kind, contentMap, registry) {
  const key = identity.identity.trim().toLocaleLowerCase('ja');
  const referenceSource = identity.reference.split('#', 1)[0];
  if (!contentMap[referenceSource] || referenceSource === 'README.md') {
    throw new Error(`Reader ${kind} reference is broken for ${source}: ${identity.reference}.`);
  }
  if (!registry.has(key)) registry.set(key, []);
  registry.get(key).push({ source, role: identity.role, reference: identity.reference, referenceSource });
}

function validateRoleList(source, reader, role) {
  const values = reader.roles?.[role];
  if (!Array.isArray(values) || values.length === 0) throw new Error(`Reader role ${role} is missing for ${source}.`);
  for (const value of values) {
    const heading = typeof value === 'string' ? value : value?.heading;
    if (typeof heading !== 'string' || heading.trim() === '') throw new Error(`Reader role ${role} has an invalid heading for ${source}.`);
  }
}

function validateNextPages(source, next, contentMap, markdown, sourceTexts = new Map()) {
  for (const target of next) {
    if (typeof target !== 'string' || target.trim() === '') throw new Error(`Reader next-page target is invalid for ${source}.`);
    const [targetPath, fragment = ''] = target.split('#', 2);
    const resolved = path.posix.normalize(path.posix.join(path.posix.dirname(source), targetPath));
    const entry = contentMap[resolved];
    if (!entry) throw new Error(`Reader next-page target is broken for ${source}: ${target}.`);
    if (resolved === source) throw new Error(`Reader next-page target must not be self-referential for ${source}.`);
    if (fragment !== '') {
      const headings = headingEntries(sourceTexts.get(resolved) ?? '');
      if (headings.length === 0 || !headings.some(({ slug }) => slug === fragment)) throw new Error(`Reader next-page fragment is missing for ${source}: ${target}.`);
    }
    if (markdown !== '' && !hasMarkdownTarget(markdown, targetPath, fragment)) {
      throw new Error(`Reader next-page target is not linked from ${source}: ${target}.`);
    }
  }
}

function validateTypeSpecificRoles(source, metadata, markdown) {
  const { reader } = metadata;
  if (reader.type === 'troubleshooting') {
    validateTroubleshootingClassification(source, reader, markdown);
  }
  if (reader.type === 'concept') {
    const boundary = reader.roles.boundary.map((value) => typeof value === 'string' ? value : value.heading).map((heading) => sectionForHeading(markdown, heading)).join('\n');
    if (boundary !== '' && !/(?:責務|境界|提供|非提供|提供しない|目標外|所有|Error|Application|Framework)/u.test(boundary)) throw new Error(`Concept boundary is not explicit for ${source}.`);
  }
  if (reader.type === 'reference') {
    const boundary = reader.roles.boundary.map((value) => typeof value === 'string' ? value : value.heading).map((heading) => sectionForHeading(markdown, heading)).join('\n');
    if (boundary !== '' && !/(?:責務|境界|提供|非提供|提供しない|対象外|エラー|Error|Application|Framework|Session|Sensitive|Mask)/u.test(boundary)) throw new Error(`Reference boundary is not explicit for ${source}.`);
  }
}

function validateTroubleshootingClassification(source, reader, markdown) {
  const expectedClassification = { diagnostics: 'diagnostic', faq: 'faq', groups: 'group', auxiliary: 'auxiliary' };
  const assignments = new Map();
  for (const [role, classification] of Object.entries(expectedClassification)) {
    for (const value of reader.roles[role]) {
      const heading = typeof value === 'string' ? value : value.heading;
      if (role === 'diagnostics' && /^(?:FAQ|よくある質問|共通|Outcome Status)/iu.test(heading)) {
        throw new Error(`FAQ/group heading cannot be a diagnostic in ${source}: ${heading}.`);
      }
      if (typeof value === 'string' || value.classification !== classification) {
        throw new Error(`Troubleshooting heading ${heading} must be explicitly classified as ${classification} in ${source}.`);
      }
      if (assignments.has(heading)) throw new Error(`Troubleshooting heading is classified more than once in ${source}: ${heading}.`);
      assignments.set(heading, classification);
    }
  }
  if (markdown === '') return;
  const headings = headingEntries(markdown).filter(({ level }) => level === 2 || level === 3);
  const actual = new Map(headings.map(({ heading, level }) => [heading, level]));
  for (const heading of actual.keys()) {
    if (!assignments.has(heading)) throw new Error(`Troubleshooting heading is unclassified in ${source}: ${heading}.`);
  }
  for (const heading of assignments.keys()) {
    if (!actual.has(heading)) throw new Error(`Troubleshooting classified heading is missing in ${source}: ${heading}.`);
  }
  for (const diagnostic of reader.roles.diagnostics) {
    const heading = diagnostic.heading;
    const section = sectionForHeading(markdown, heading);
    for (const label of ['症状', '原因', '確認', '修正']) {
      if (!new RegExp(`(?:\\*\\*)?${label}(?:方法)?(?:\\*\\*)?\\s*[:：]`, 'u').test(section)) {
        throw new Error(`Troubleshooting diagnostic ${heading} is missing ${label} in ${source}.`);
      }
    }
  }
}

function validateReaderBody(source, metadata, markdown) {
  for (const role of roleRequirements[metadata.reader.type]) {
    for (const value of metadata.reader.roles[role]) {
      const heading = typeof value === 'string' ? value : value.heading;
      const section = sectionForHeading(markdown, heading);
      if (section === '') throw new Error(`Reader role heading is not found for ${source}: ${heading}.`);
      if (!/\S/u.test(section.replace(/^#{1,6}\s+.*$/m, ''))) throw new Error(`Reader role section is empty for ${source}: ${heading}.`);
      if (actionTypes.has(metadata.reader.type) && role === 'runnable' && !/(?:```|\b(?:php|docker|composer|curl|bash|pnpm|psql|SELECT)\b|\$\s)/iu.test(section)) {
        throw new Error(`Runnable reader role has no command or complete code for ${source}: ${heading}.`);
      }
      if (actionTypes.has(metadata.reader.type) && (role === 'success' || role === 'failure') && !/(?:HTTP\s*[2345]\d\d|Exit\s*`?\d|status|code|生成|作成|確認|観測|result|Error|成功|失敗|拒否|return|throws|EmptyOutcome|OperationRejectedException|skipped_|Terminal|Operation ID)/iu.test(section)) {
        throw new Error(`Observable ${role} role is incomplete for ${source}: ${heading}.`);
      }
    }
  }
  if (actionTypes.has(metadata.reader.type)) {
    if (!/(?:Host|Container|Runtime|Project Root|Docker|Terminal|Process)/iu.test(markdown)) {
      throw new Error(`Action reader is missing a Host/Container runtime boundary for ${source}.`);
    }
    if (!/(?:Application-owned|Applicationの|app\/|config\/|src\/|File|ファイル|生成)/iu.test(markdown)) {
      throw new Error(`Action reader is missing an Application-owned File boundary for ${source}.`);
    }
    const failure = metadata.reader.roles.failure.map((value) => sectionForHeading(markdown, typeof value === 'string' ? value : value.heading)).join('\n');
    if (!/(?:修正|回復|Recovery|再実行|再開|Rollback|retry|Retry|失敗|拒否)/iu.test(failure)) {
      throw new Error(`Action reader failure role is missing recovery guidance for ${source}.`);
    }
  }
}

function assertSingleOutcome(strings, expected, outcomes, location) {
  if (!strings.some((value) => value.includes(expected))) throw new Error(`${location} is missing its mapped reader outcome.`);
  const other = outcomes.filter((outcome) => outcome !== expected && strings.some((value) => value.includes(outcome)));
  if (other.length > 0) throw new Error(`${location} contains an adjacent or foreign reader outcome: ${other[0]}.`);
}

function collectStrings(value) {
  const strings = [];
  const collect = (candidate) => {
    if (typeof candidate === 'string') strings.push(candidate);
    else if (Array.isArray(candidate)) candidate.forEach(collect);
    else if (candidate !== null && typeof candidate === 'object') Object.values(candidate).forEach(collect);
  };
  collect(value);
  return strings;
}

function hasMarkdownTarget(markdown, targetPath, fragment) {
  for (const match of markdown.matchAll(markdownLinkPattern)) {
    const target = match[1].trim().split(/\s+/u, 1)[0];
    const [pathPart, targetFragment = ''] = target.split('#', 2);
    if (pathPart === targetPath && targetFragment === fragment) return true;
  }
  return false;
}

function sectionForHeading(markdown, expected) {
  const matches = [...markdown.matchAll(headingPattern)];
  const index = matches.findIndex(([, , heading]) => heading.trim() === expected.trim());
  if (index === -1) return '';
  const [, level] = matches[index];
  const start = matches[index].index ?? 0;
  const next = matches.slice(index + 1).find((match) => match[1].length <= level.length)?.index ?? markdown.length;
  return markdown.slice(start, next);
}

function headingEntries(markdown) {
  return [...markdown.matchAll(headingPattern)].map(([, level, heading]) => ({ level: level.length, heading: heading.trim(), slug: slugifyHeading(heading) }));
}

function markdownTableRows(markdown) {
  return markdown.split(/\r?\n/u)
    .filter((line) => /^\|/.test(line) && !/^\|\s*:?-{3,}/u.test(line))
    .map((raw) => ({ raw, cells: raw.split('|').slice(1, -1).map((cell) => cell.trim()) }))
    .filter(({ cells }) => cells.length > 0 && cells.some((cell) => cell !== ''));
}

function assertExactMethodLookupMap(cell, methods, field, type, expectedValue) {
  const actual = new Map();
  for (const entry of cell.split(/<br\s*\/?>/iu)) {
    const normalizedEntry = entry.trim();
    const match = normalizedEntry.match(/^([^:]+):\s*(.+)$/u);
    if (!match) throw new Error(`Core API exact ${field} Method mapping is malformed for source-derived type: ${type}.`);
    const methodName = match[1].trim();
    if (actual.has(methodName)) throw new Error(`Core API exact ${field} Method mapping duplicates ${type}::${methodName}.`);
    actual.set(methodName, normalizeReferenceToken(match[2]));
  }
  const expected = new Map(methods.map((method) => [method.name, normalizeReferenceToken(expectedValue(method))]));
  if (actual.size !== expected.size) throw new Error(`Core API exact ${field} Method mapping count drifted for source-derived type: ${type}.`);
  for (const [methodName, value] of actual) {
    if (!expected.has(methodName)) throw new Error(`Core API exact ${field} Method mapping contains an extra method for source-derived type: ${type}: ${methodName}.`);
    if (value !== expected.get(methodName)) throw new Error(`Core API exact ${field} Method mapping drifted for ${type}::${methodName}.`);
  }
  for (const methodName of expected.keys()) {
    if (!actual.has(methodName)) throw new Error(`Core API exact ${field} Method mapping is missing ${type}::${methodName}.`);
  }
}

export function extractPublicMethods(source) {
  const functions = extractFunctions(source);
  const contracts = new Map(functions.map((method) => [method.name, new Set([...method.directErrorTypes, ...method.producedErrorTypes])]));
  let changed = true;
  while (changed) {
    changed = false;
    for (const method of functions) {
      for (const helper of localHelperCalls(method.body)) {
        for (const errorType of contracts.get(helper) ?? []) {
          if (!contracts.get(method.name).has(errorType)) {
            contracts.get(method.name).add(errorType);
            changed = true;
          }
        }
      }
    }
  }
  return functions.filter(({ visibility }) => visibility === 'public' || visibility === null).map((method) => {
    const errorParts = [];
    for (const type of method.directErrorTypes) errorParts.push(`throws ${type}`);
    for (const helper of localHelperCalls(method.body)) {
      for (const type of contracts.get(helper) ?? []) errorParts.push(`propagates ${type} via ${helper}()`);
    }
    const uniqueErrors = [...new Set(errorParts)];
    return {
      name: method.name,
      parameters: method.parameters,
      returnType: method.returnType,
      errorContract: uniqueErrors.length > 0 ? uniqueErrors.join('; ') : 'Source body: no direct throw or bounded helper error observed (non-exhaustive)',
    };
  });
}

function extractFunctions(source) {
  const functions = [];
  const declarationPattern = /(?:^|\n)\s*(?:(public|protected|private)\s+)?(?:static\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gmu;
  for (const match of source.matchAll(declarationPattern)) {
    const open = (match.index ?? 0) + match[0].lastIndexOf('(');
    const close = closingParenthesis(source, open);
    if (close === -1) continue;
    const parameters = source.slice(open + 1, close).trim();
    const returnType = source.slice(close + 1).match(/^\s*:\s*([^\n{;]+)/u)?.[1]?.trim() ?? null;
    const bodyStart = source.indexOf('{', close);
    const declarationEnd = source.indexOf(';', close);
    const hasBody = bodyStart >= 0 && (declarationEnd < 0 || bodyStart < declarationEnd);
    const body = hasBody ? source.slice(bodyStart + 1, closingBrace(source, bodyStart)) : '';
    const directErrorTypes = [...new Set(
      [...body.matchAll(/\bthrow\s+new\s+([A-Za-z_][A-Za-z0-9_\\]*)/gu)].map(([, type]) => type.replace(/^\\/u, '')),
    )];
    const producedErrorTypes = [...new Set(
      [...body.matchAll(/\breturn\s+new\s+([A-Za-z_][A-Za-z0-9_\\]*)/gu)]
        .map(([, type]) => type.replace(/^\\/u, ''))
        .filter((type) => /(?:Exception|Error)$/u.test(type)),
    )];
    functions.push({ name: match[2], visibility: match[1] ?? null, parameters, returnType, body, directErrorTypes, producedErrorTypes });
  }
  return functions;
}

function localHelperCalls(body) {
  return [...body.matchAll(/(?:\$this->|\bself::|\bstatic::)([A-Za-z_][A-Za-z0-9_]*)\s*\(/gu)].map(([, name]) => name);
}

export function extractEnumCases(source) {
  return [...source.matchAll(/(?:^|\n)\s*case\s+([A-Za-z_]\w*)(?:\s*=\s*([^;]+))?\s*;/gmu)]
    .map(([, name, value]) => value === undefined ? name : `${name}=${normalizeReferenceToken(value)}`);
}

export function extractPublicConstants(source) {
  return [...source.matchAll(/(?:^|\n)\s*(?:(public|protected|private)\s+)?const\s+(?:(string|int|float|bool|array)\s+)?([A-Za-z_]\w*)\s*=\s*([^;]+);/gmu)]
    .filter(([, visibility]) => visibility !== 'protected' && visibility !== 'private')
    .map(([, , type, name, value]) => `${name}${type ? `: ${type}` : ''}=${normalizeReferenceToken(value)}`);
}

function parameterDefaults(parameters) {
  const defaults = [];
  for (const parameter of splitParameters(parameters)) {
    const match = parameter.match(/([A-Za-z_]\w*)\s*=\s*(.+)$/us);
    if (match) defaults.push({ name: match[1], value: match[2].trim().replace(/,$/u, '') });
  }
  return defaults;
}

function splitParameters(parameters) {
  const result = [];
  let start = 0;
  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = 0; index < parameters.length; index += 1) {
    const character = parameters[index];
    if (quote !== null) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"') {
      quote = character;
      continue;
    }
    if ('([{'.includes(character)) depth += 1;
    else if (')]}'.includes(character)) depth -= 1;
    else if (character === ',' && depth === 0) {
      result.push(parameters.slice(start, index).trim());
      start = index + 1;
    }
  }
  const tail = parameters.slice(start).trim();
  if (tail !== '') result.push(tail);
  return result;
}

function enumContractFor(contract) {
  if (contract.kind !== 'enum') return 'not an enum (source-derived)';
  const backing = contract.enumBacking ?? 'unit';
  return `${backing}; ${contract.enumCases.length > 0 ? contract.enumCases.join(', ') : 'no cases'}`;
}

function constantsContractFor(contract) {
  const constants = contract.constants ?? [];
  return constants.length > 0 ? constants.join(', ') : 'none (source-derived)';
}

function closingParenthesis(source, open) {
  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = open; index < source.length; index += 1) {
    const character = source[index];
    if (quote !== null) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"' || character === '`') {
      quote = character;
      continue;
    }
    if (character === '(') depth += 1;
    else if (character === ')' && --depth === 0) return index;
  }
  return -1;
}

function closingBrace(source, open) {
  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = open; index < source.length; index += 1) {
    const character = source[index];
    if (quote !== null) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"' || character === '`') {
      quote = character;
      continue;
    }
    if (character === '{') depth += 1;
    else if (character === '}' && --depth === 0) return index;
  }
  return source.length;
}

function normalizeReferenceToken(value) {
  return value.replace(/\|/gu, '／').replace(/\s+/gu, ' ').trim();
}

function slugifyHeading(value) {
  return value.replace(/[`*_~]/g, '').trim().toLocaleLowerCase('ja').replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s+/gu, '-');
}

function routeFor(slug) {
  return slug === 'index' ? '/' : `/${slug}`;
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function routeFromArtifactUrl(value) {
  const pathValue = /^https?:\/\//u.test(value) ? new URL(value).pathname : value.split(/[?#]/u, 1)[0];
  const normalized = pathValue.replace(/^\/+|\/+$/g, '');
  return normalized === '' ? '/' : `/${normalized}`;
}

async function firstExisting(...candidates) {
  for (const candidate of candidates) {
    try {
      const stat = await (await import('node:fs/promises')).stat(candidate);
      if (stat.isFile()) return candidate;
    } catch {
      // Try the next generated extension.
    }
  }
  return null;
}

async function markdownFiles(root) {
  const files = [];
  async function visit(directory, prefix = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) await visit(path.join(directory, entry.name), relative);
      else if (entry.isFile() && entry.name.endsWith('.md')) files.push(relative);
    }
  }
  await visit(root);
  return files.sort();
}

async function phpFiles(root) {
  const files = [];
  async function visit(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) await visit(absolute);
      else if (entry.isFile() && entry.name.endsWith('.php')) files.push(absolute);
    }
  }
  await visit(root);
  return files;
}

async function textFiles(root) {
  const files = [];
  async function visit(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) await visit(absolute);
      else if (entry.isFile() && !entry.name.endsWith('.map')) files.push(absolute);
    }
  }
  await visit(root);
  return files.sort();
}

async function artifactFiles(root, predicate) {
  const files = [];
  async function visit(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) await visit(absolute);
      else if (entry.isFile() && predicate(entry.name)) files.push(absolute);
    }
  }
  await visit(root);
  return files.sort();
}

function routeFromArtifactFile(file, root) {
  const relative = path.relative(root, file).split(path.sep).join('/');
  if (relative === 'index.html' || relative === 'index.md' || relative === 'index.mdx') return '/';
  if (/\/index\.html$/u.test(relative)) return `/${relative.replace(/\/index\.html$/u, '')}`;
  if (/\/index\.mdx?$/u.test(relative)) return `/${relative.replace(/\/index\.mdx?$/u, '')}`;
  return `/${relative.replace(/\.mdx?$/u, '')}`;
}

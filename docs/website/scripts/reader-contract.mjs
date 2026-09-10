import { mkdtemp, readFile, readdir, rm, stat } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { JSDOM, VirtualConsole } from 'jsdom';
import { pathToFileURL } from 'node:url';
import { generateContent } from './content-pipeline.mjs';
import {
  contentRoot as defaultContentRoot,
  distRoot as defaultDistRoot,
  manifestPath as defaultManifestPath,
  repositoryRoot as defaultRepositoryRoot,
  sourceRoot as defaultSourceRoot,
} from './website-paths.mjs';
import {
  diagramManifestPath as defaultDiagramManifestPath,
  loadDiagramManifest,
  registeredViewerRelativePaths,
} from './archify-diagrams.mjs';
import { loadReleaseAuthority } from './release-claim-checker.mjs';

const blumeRequire = createRequire(import.meta.resolve('blume/package.json'));
const blumePackageRoot = path.dirname(blumeRequire.resolve('blume/package.json'));
const { toPlainText: blumeToPlainText } = await import(pathToFileURL(path.join(blumePackageRoot, 'src/search/plain-text.mjs')).href);
const { createSatteriMarkdownProcessor } = await import(pathToFileURL(blumeRequire.resolve('@astrojs/markdown-satteri')).href);
const readerMarkdownProcessor = await createSatteriMarkdownProcessor({ syntaxHighlight: false });
const sourceBoundContentCache = new Map();
const readerBodyRenderCache = new Map();

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

export const stableReferenceExclusionPaths = Object.freeze([
  'src/Audit/AuditOpaqueIdKeyProvider.php',
  'src/Audit/AuditProtectionKeyProvider.php',
  'src/Internal/Console/AboutCommand.php',
  'src/Internal/Console/DiagnosticsCheckCommand.php',
  'src/Internal/Console/OutboxStatusCommand.php',
  'src/Internal/Console/QueueStatusCommand.php',
  'src/Internal/Projection/Route/RouteProjectionListCommand.php',
  'src/Internal/Projection/Schedule/ScheduleProjectionListCommand.php',
  'src/Internal/Application/ApplicationAuditConfiguration.php',
]);

const stableReferenceBoundary = Object.freeze({
  stableVersion: '1.2.1',
  stableReleaseState: 'experimental-stable',
  frameworkTag: '1.2.1',
  frameworkDirectRef: '1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3',
  frameworkPeeledSource: '4efee09f13bebedc1639333f79550a36a4e8ca91',
  roadmapVersion: '1.3.0',
  roadmapState: 'unreleased',
});

const stableReferenceMethodExclusion = Object.freeze({
  path: 'src/Application/ApplicationBuilder.php',
  fqcn: 'BlackOps\\Application\\ApplicationBuilder',
  name: 'withOperationalHealthQuery',
});

function stableReferenceAuthorityTuple(authority) {
  return {
    stableVersion: authority?.currentStable?.version ?? null,
    stableReleaseState: authority?.currentStable?.releaseState ?? null,
    frameworkTag: authority?.currentStable?.framework?.tag ?? null,
    frameworkDirectRef: authority?.currentStable?.framework?.directRef ?? null,
    frameworkPeeledSource: authority?.currentStable?.framework?.peeledSource ?? null,
    roadmapVersion: authority?.roadmap?.version ?? null,
    roadmapState: authority?.roadmap?.state ?? null,
  };
}

export function stableReferenceExclusionsFor(authority) {
  const actual = stableReferenceAuthorityTuple(authority);
  if (JSON.stringify(actual) !== JSON.stringify(stableReferenceBoundary)) {
    throw new Error('Stable-reference exclusions are bound to the current Stable 1.2.1 framework tuple and unreleased roadmap 1.3.0; release authority changed, so reevaluate the exact paths.');
  }
  return new Set(stableReferenceExclusionPaths);
}

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
const placeholderTokenPattern = /<(?<name>[A-Za-z][A-Za-z0-9/-]*(?:[ .][A-Za-z][A-Za-z0-9/-]*)?)>/gu;
const placeholderDanglingPattern = /<(?<name>[A-Za-z][A-Za-z0-9/-]*(?:[./][A-Za-z0-9/-]*)?)(?=$|[\s.,;:!?()[\]{}<>])/gu;
const placeholderBareClosingPattern = /(?<![<A-Za-z0-9_./-])(?<name>[A-Za-z][A-Za-z0-9/-]*(?:[./][A-Za-z0-9/-]*)?)>/gu;
const rawMarkdownImagePattern = /!\[[^\]]*\]\((?<target>(?:[^()\s]|\([^()\s]*\))+)(?<title>\s+"[^"]*")?\)/gu;
const rawInlineCodePattern = /`[^`]*`/gu;
const rawImageExtensions = new Set([
  '.apng', '.avif', '.bmp', '.gif', '.ico', '.jpeg', '.jpg', '.png', '.svg', '.tiff', '.webp',
]);

function maskRanges(text, ranges) {
  if (ranges.length === 0) return text;
  const masked = text.split('');
  for (const [start, end] of ranges) {
    for (let index = start; index < end; index += 1) {
      if (masked[index] !== '\r' && masked[index] !== '\n') masked[index] = ' ';
    }
  }
  return masked.join('');
}

export function* sourcePhysicalLines(text) {
  if (typeof text !== 'string') throw new Error('Source physical-line iteration requires text.');
  let start = 0;
  while (start < text.length) {
    let end = start;
    while (end < text.length && text[end] !== '\r' && text[end] !== '\n') end += 1;
    let terminator = '';
    if (end < text.length) {
      terminator = text[end] === '\r' && text[end + 1] === '\n' ? '\r\n' : text[end];
    }
    const nextStart = end + terminator.length;
    yield { line: text.slice(start, end), start, end, terminator, nextStart };
    start = nextStart;
  }
}

export function markdownFenceLine(line) {
  const normalized = line.replace(/\r\n?$/u, '');
  const match = normalized.match(/^ {0,3}(?<run>`{3,}|~{3,})(?<rawInfo>.*)$/u);
  if (match === null) return null;
  const run = match.groups.run;
  const rawInfo = match.groups.rawInfo;
  if (run[0] === '`' && rawInfo.includes('`')) return null;
  return {
    character: run[0],
    length: run.length,
    info: rawInfo.trim(),
    rawInfo,
  };
}

export function markdownFenceCloses(line, fence) {
  const marker = markdownFenceLine(line);
  return marker !== null
    && marker.character === fence.character
    && marker.length >= fence.length
    && /^[ \t]*$/u.test(marker.rawInfo);
}

export function nextMarkdownFenceState(line, fence) {
  const marker = markdownFenceLine(line);
  if (fence === null) return marker;
  return markdownFenceCloses(line, fence) ? null : fence;
}

const sourceNextTabColumn = (column) => column + (4 - (column % 4));

const sourceAdvanceColumn = (column, character) => character === '\t'
  ? sourceNextTabColumn(column)
  : column + 1;

const sourceBlockTag = /^<\/?(?:address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|pre|script|section|search|style|summary|table|tbody|td|textarea|tfoot|th|thead|title|tr|track|ul|xmp)(?:[ \t>]|\/>|$)/iu;

const sourceBlockTagPrefix = /^<\/?(?<name>address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|pre|script|section|search|style|summary|table|tbody|td|textarea|tfoot|th|thead|title|tr|track|ul|xmp)(?=[ \t>]|\/>|$)/iu;

const sourceBlockTagNames = new Set([
  'address', 'article', 'aside', 'base', 'basefont', 'blockquote', 'body', 'caption', 'center',
  'col', 'colgroup', 'dd', 'details', 'dialog', 'dir', 'div', 'dl', 'dt', 'fieldset',
  'figcaption', 'figure', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4',
  'h5', 'h6', 'head', 'header', 'hr', 'html', 'iframe', 'legend', 'li', 'link', 'main',
  'menu', 'menuitem', 'nav', 'noframes', 'ol', 'optgroup', 'option', 'p', 'param', 'pre',
  'script', 'section', 'search', 'style', 'summary', 'table', 'tbody', 'td', 'textarea',
  'tfoot', 'th', 'thead', 'title', 'tr', 'track', 'ul', 'xmp',
]);

const sourceCompleteHtmlTag = (value) => {
  const isSpace = (character) => character === ' ' || character === '\t';
  const isAlpha = (character) => /[A-Za-z]/u.test(character ?? '');
  const isAlphanumeric = (character) => /[A-Za-z0-9]/u.test(character ?? '');
  const isAttributeStart = (character) => isAlpha(character) || character === ':' || character === '_';
  const isAttributeContinuation = (character) => isAlphanumeric(character)
    || character === '-' || character === '.' || character === ':' || character === '_';
  if (!value.startsWith('<')) return null;
  let offset = 1;
  const closing = value[offset] === '/';
  if (closing) offset += 1;
  const nameStart = offset;
  if (!isAlpha(value[offset])) return null;
  offset += 1;
  while (isAlphanumeric(value[offset]) || value[offset] === '-') offset += 1;
  const name = value.slice(nameStart, offset).toLocaleLowerCase('en-US');
  if (closing) {
    while (isSpace(value[offset])) offset += 1;
    if (value[offset] !== '>') return null;
    offset += 1;
  } else {
    let state = 'after-name';
    let quote = null;
    while (true) {
      const character = value[offset];
      if (state === 'after-name') {
        if (isSpace(character)) {
          offset += 1;
          state = 'attribute-before';
        } else if (character === '/') {
          offset += 1;
          state = 'end';
        } else if (character === '>') {
          offset += 1;
          break;
        } else {
          return null;
        }
      } else if (state === 'attribute-before') {
        if (character === '/') {
          offset += 1;
          state = 'end';
        } else if (isAttributeStart(character)) {
          offset += 1;
          state = 'attribute';
        } else if (isSpace(character)) {
          offset += 1;
        } else if (character === '>') {
          offset += 1;
          break;
        } else {
          return null;
        }
      } else if (state === 'attribute') {
        if (isAttributeContinuation(character)) {
          offset += 1;
        } else {
          state = 'attribute-after';
        }
      } else if (state === 'attribute-after') {
        if (character === '=') {
          offset += 1;
          state = 'value-before';
        } else if (isSpace(character)) {
          offset += 1;
        } else {
          state = 'attribute-before';
        }
      } else if (state === 'value-before') {
        if (character === '"' || character === "'") {
          quote = character;
          offset += 1;
          state = 'quoted';
        } else if (isSpace(character)) {
          offset += 1;
        } else if (character === undefined || character === '<' || character === '='
          || character === '>' || character === '`') {
          return null;
        } else {
          state = 'unquoted';
        }
      } else if (state === 'unquoted') {
        if (character === undefined || character === '"' || character === "'"
          || character === '/' || character === '<' || character === '='
          || character === '>' || character === '`' || isSpace(character)) {
          state = 'attribute-after';
        } else {
          offset += 1;
        }
      } else if (state === 'quoted') {
        if (character === quote) {
          offset += 1;
          quote = null;
          state = 'quoted-after';
        } else if (character === undefined || character === '\r' || character === '\n') {
          return null;
        } else {
          offset += 1;
        }
      } else if (state === 'quoted-after') {
        if (character === '/' || character === '>' || isSpace(character)) {
          state = 'attribute-before';
        } else {
          return null;
        }
      } else if (state === 'end') {
        if (character !== '>') return null;
        offset += 1;
        break;
      }
    }
  }
  while (value[offset] === ' ' || value[offset] === '\t') offset += 1;
  if (value[offset] !== undefined && value[offset] !== '\r' && value[offset] !== '\n') return null;
  return { closing, name };
};

const sourceHtmlOpeningContinuation = (value, initialQuote = null, start = 0) => {
  let quote = initialQuote;
  for (let offset = start; offset < value.length; offset += 1) {
    const character = value[offset];
    if (quote !== null) {
      if (character === quote) quote = null;
      continue;
    }
    if (character === '"' || character === "'") {
      quote = character;
      continue;
    }
    if (character === '<') return { end: -1, quote };
    if (character === '>') return { end: offset, quote: null };
  }
  return { end: -1, quote };
};

const sourceHtmlFlowContext = (value, inherited) => {
  if (inherited?.container === undefined) {
    return {
      content: value.trim(),
      offset: value.match(/^[ \t]*/u)?.[0].length ?? 0,
    };
  }
  const context = sourceContainerContext(value, {
    ...inherited.container,
    htmlBlock: null,
    htmlBlockMask: false,
  }, false, inherited.container.blockquoteDepth ?? 0);
  const leading = context.fenceContent.match(/^[ \t]*/u)?.[0].length ?? 0;
  return {
    content: context.fenceContent.trim(),
    offset: context.fenceContentOffset + leading,
  };
};

const sourceRawHtmlIsInert = (tag) => tag === 'script' || tag === 'style';

const sourceHtmlBlockTransition = (value, inherited = null, paragraph = false, indent = 0, container = null) => {
  const flow = sourceHtmlFlowContext(value, inherited);
  const content = flow.content;
  const contentOffset = flow.offset;
  const canStart = indent <= 3;
  if (inherited?.kind === 'comment') {
    return {
      active: true,
      state: content.includes('-->') ? null : inherited,
      mask: true,
    };
  }
  if (inherited?.kind === 'processing') {
    return { active: true, state: content.includes('?>') ? null : inherited, mask: true };
  }
  if (inherited?.kind === 'declaration') {
    return { active: true, state: content.includes('>') ? null : inherited, mask: true };
  }
  if (inherited?.kind === 'cdata') {
    return { active: true, state: content.includes(']]>') ? null : inherited, mask: true };
  }
  if (inherited?.kind === 'raw') {
    if (!inherited.openingComplete) {
      const opening = sourceHtmlOpeningContinuation(content, inherited.openingQuote ?? null);
      if (opening.end < 0) return {
        active: true,
        state: { ...inherited, openingQuote: opening.quote },
        mask: true,
      };
      const close = new RegExp(`</${inherited.tag}[ \\t]*>`, 'iu');
      const inert = sourceRawHtmlIsInert(inherited.tag);
      if (close.test(content.slice(opening.end + 1))) return { active: true, state: null, mask: inert };
      return {
        active: true,
        state: { ...inherited, openingComplete: true, openingQuote: null },
        mask: true,
        ...(inert ? {} : { maskEnd: contentOffset + opening.end + 1 }),
      };
    }
    const close = new RegExp(`</${inherited.tag}[ \\t]*>`, 'iu');
    return {
      active: true,
      state: close.test(content) ? null : inherited,
      mask: sourceRawHtmlIsInert(inherited.tag),
    };
  }
  if (inherited?.kind === 'type-6') {
    if (content === '') return { active: true, state: null, mask: inherited.invisible };
    if (!inherited.openingComplete) {
      const opening = sourceHtmlOpeningContinuation(content, inherited.openingQuote ?? null);
      if (opening.end < 0) return {
        active: true,
        state: { ...inherited, openingQuote: opening.quote },
        mask: true,
      };
      return {
        active: true,
        state: { ...inherited, openingComplete: true, openingQuote: null, invisible: false },
        mask: true,
        maskEnd: contentOffset + opening.end + 1,
      };
    }
    return { active: true, state: inherited, mask: inherited.invisible };
  }
  if (inherited?.kind === 'blank-line') {
    if (content === '') return { active: true, state: inherited, mask: false };
    return { active: false, state: null, mask: false };
  }
  if (inherited?.kind === 'type-7') {
    return content === ''
      ? { active: true, state: null, mask: Boolean(inherited.invisible) }
      : { active: true, state: inherited, mask: Boolean(inherited.invisible) };
  }
  if (content === '' || !canStart) return { active: false, state: null, mask: false };
  if (content.startsWith('<!--')) {
    return {
      active: true,
      state: content.includes('-->') ? null : { kind: 'comment', container },
      mask: !content.includes('-->'),
    };
  }
  if (content.startsWith('<?')) {
    return {
      active: true,
      state: content.includes('?>') ? null : { kind: 'processing', container },
      mask: !content.includes('?>'),
    };
  }
  if (/^<!\[CDATA\[/u.test(content)) {
    return {
      active: true,
      state: content.includes(']]>') ? null : { kind: 'cdata', container },
      mask: !content.includes(']]>'),
    };
  }
  if (/^<![A-Za-z]/u.test(content)) {
    return {
      active: true,
      state: content.includes('>') ? null : { kind: 'declaration', container },
      mask: !content.includes('>'),
    };
  }
  const rawStarter = content.match(/^<(?<name>pre|script|style|textarea)(?=[ \t>]|\/>|$)/iu);
  const complete = sourceCompleteHtmlTag(content);
  const blockPrefix = content.match(sourceBlockTagPrefix);
  const openingScan = complete === null
    ? sourceHtmlOpeningContinuation(content, null, (rawStarter ?? blockPrefix)?.[0].length ?? 0)
    : { end: 0, quote: null };
  const opening = content.match(/^<(?<name>[A-Za-z][A-Za-z0-9-]*)(?<body>[^>]*)>/u);
  if (complete === null && rawStarter === null && blockPrefix === null) return { active: false, state: null };
  const name = (complete?.name ?? rawStarter?.groups.name ?? blockPrefix?.groups.name ?? opening?.groups.name).toLocaleLowerCase('en-US');
  const closing = complete?.closing ?? content.startsWith('</');
  if (['pre', 'script', 'style', 'textarea'].includes(name) && !closing) {
    const close = new RegExp(`</${name}[ \\t]*>`, 'iu');
    const closed = close.test(content);
    const inert = sourceRawHtmlIsInert(name);
    return {
      active: true,
      state: closed ? null : {
        kind: 'raw',
        tag: name,
        openingComplete: complete !== null,
        openingQuote: openingScan.quote,
        container,
      },
      mask: closed ? false : (complete === null || inert),
    };
  }
  if (blockPrefix !== null && complete === null && opening === null && rawStarter === null) {
    return {
      active: true,
      state: { kind: 'type-6', invisible: true, openingComplete: false, openingQuote: openingScan.quote, container },
      mask: true,
    };
  }
  if (sourceBlockTagNames.has(name)) {
    return {
      active: true,
      state: { kind: 'type-6', invisible: false, openingComplete: true, container },
      mask: false,
    };
  }
  if (paragraph) return { active: false, state: null, mask: false };
  if (complete === null) return { active: false, state: null, mask: false };
  return { active: true, state: { kind: 'type-7', invisible: false }, mask: false };
};

const sourceParagraphContent = (value, setextUnderline = false) => {
  const content = value.trim();
  if (content === '') return false;
  if (/^#{1,6}(?:[ \t]+|$)/u.test(content)) return false;
  if (/^(?:`{3,}|~{3,})/u.test(content)) return false;
  if (/^(?:(?:\*[ \t]*){3,}|(?:-[ \t]*){3,}|(?:_[ \t]*){3,})$/u.test(content)) return false;
  if (setextUnderline) return false;
  if (/^(?:<!--|<\?|<![A-Za-z]|<!\[CDATA\[)/u.test(content)
    || sourceBlockTag.test(content)) return false;
  return true;
};

const sourceReadWhitespace = (value, offset, column, limit = Number.POSITIVE_INFINITY) => {
  let end = offset;
  let current = column;
  while (end < value.length && (value[end] === ' ' || value[end] === '\t')) {
    const next = sourceAdvanceColumn(current, value[end]);
    if (next - column > limit) return { offset, column, width: 0, valid: false };
    current = next;
    end += 1;
  }
  return { offset: end, column: current, width: current - column, valid: true };
};

const sourceConsumeIndent = (value, width, column) => {
  let offset = 0;
  let current = column;
  while (offset < value.length && (value[offset] === ' ' || value[offset] === '\t') && current - column < width) {
    const next = sourceAdvanceColumn(current, value[offset]);
    if (next - column > width) {
      if (value[offset] !== '\t') return { offset: 0, column, width: 0, valid: false };
      return {
        offset: offset + 1,
        column: next,
        width,
        surplus: next - column - width,
        valid: true,
      };
    }
    current = next;
    offset += 1;
  }
  return current - column === width
    ? { offset, column: current, width, surplus: 0, valid: true }
    : { offset: 0, column, width: 0, valid: false };
};

const sourceListMarkerPattern = /^(?<token>[-+*]|\d{1,9}[.)])(?:(?<padding>[ \t]+)|(?=$))/u;
const sourceThematicBreakPattern = /^(?:(?:\*[ \t]*){3,}|(?:-[ \t]*){3,}|(?:_[ \t]*){3,})$/u;
const sourceIsThematicBreak = (value) => sourceThematicBreakPattern.test(value.trim());

const sourceCopyListItems = (items) => items.map((item) => ({ ...item }));

const sourceInheritedListDepth = (items, markerColumn) => {
  let depth = 0;
  for (const item of items) {
    if (item.contentColumn > markerColumn) break;
    depth += 1;
  }
  return depth;
};

const sourceContainerContext = (line, inherited = null, resolveHtml = true, maxBlockquoteDepth = Number.POSITIVE_INFINITY) => {
  let offset = 0;
  let column = 0;
  let blockquoteDepth = 0;
  const candidates = [];
  let thematicBreak = null;
  while (true) {
    const leading = sourceReadWhitespace(line, offset, column, 3);
    if (!leading.valid) break;
    const candidateStart = leading.offset;
    const candidateColumn = leading.column;
    const candidate = line.slice(candidateStart);
    if (candidate.startsWith('>') && blockquoteDepth < maxBlockquoteDepth) {
      blockquoteDepth += 1;
      offset = candidateStart + 1;
      column = candidateColumn + 1;
      if (line[offset] === ' ' || line[offset] === '\t') {
        column = sourceAdvanceColumn(column, line[offset]);
        offset += 1;
      }
      continue;
    }
    if (sourceIsThematicBreak(candidate)) {
      thematicBreak = { start: candidateStart, column: candidateColumn };
      offset = candidateStart;
      column = candidateColumn;
      break;
    }
    const marker = candidate.match(sourceListMarkerPattern);
    if (marker === null) break;
    const token = marker.groups.token;
    const padding = marker.groups.padding ?? '';
    const paddingStart = candidateStart + token.length;
    const paddingColumn = candidateColumn + token.length;
    const fullPadding = sourceReadWhitespace(line, paddingStart, paddingColumn);
    const consumedPadding = fullPadding.width <= 4
      ? fullPadding.offset - paddingStart
      : padding.length > 0 ? 1 : 0;
    const consumedPaddingColumn = fullPadding.width <= 4
      ? fullPadding.width
      : padding.length > 0 ? sourceAdvanceColumn(paddingColumn, line[paddingStart]) - paddingColumn : 0;
    candidates.push({
      start: candidateStart,
      column: candidateColumn,
      token,
      padding,
      consumedPadding,
      consumedPaddingColumn,
    });
    offset = paddingStart + consumedPadding;
    column = paddingColumn + consumedPaddingColumn;
  }

  const lazyBlockquote = inherited !== null
    && inherited.blockquoteDepth > blockquoteDepth
    && inherited.paragraph
    && blockquoteDepth === 0
    && candidates.length === 0
    && thematicBreak === null
    && line.slice(offset).trim() !== ''
    && !/^(?:#{1,6}(?:[ \t]+|$)|(?:`{3,}|~{3,})|(?:<!--|<\?|<![A-Za-z]|<!\[CDATA\[)|<\/?(?:address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|pre|script|section|search|style|summary|table|tbody|td|textarea|tfoot|th|thead|title|tr|track|ul|xmp)(?:[ \t>]|\/>|$))/iu.test(line.slice(offset).trim());
  if (lazyBlockquote) blockquoteDepth = inherited.blockquoteDepth;

  const inheritedItems = inherited !== null && inherited.blockquoteDepth === blockquoteDepth
    ? inherited.listItems
    : [];
  const baseDepth = sourceInheritedListDepth(inheritedItems, thematicBreak?.column ?? candidates[0]?.column ?? Number.POSITIVE_INFINITY);
  let listItems = sourceCopyListItems(inheritedItems.slice(0, baseDepth));
  let acceptedMarkers = 0;
  let rejectedMarkerStart = null;
  let rejectedMarkerColumn = null;
  for (const [index, candidate] of candidates.entries()) {
    const ordered = /^\d/u.test(candidate.token);
    const parentParagraph = index === 0
      ? inherited !== null
        && inherited.blockquoteDepth === blockquoteDepth
        && baseDepth === inheritedItems.length
        && inherited.paragraph
      : false;
    if (ordered && parentParagraph && Number.parseInt(candidate.token, 10) !== 1) {
      rejectedMarkerStart = candidate.start;
      rejectedMarkerColumn = candidate.column;
      break;
    }
    listItems.push({
      kind: ordered ? 'ordered' : 'unordered',
      marker: candidate.token,
      contentColumn: candidate.column + candidate.token.length + candidate.consumedPaddingColumn,
    });
    acceptedMarkers += 1;
  }

  const trailingMarker = candidates.at(-1);
  const trailingMarkerContent = trailingMarker === undefined
    ? ''
    : line.slice(trailingMarker.start + trailingMarker.token.length + trailingMarker.consumedPadding).trim();
  let explicitList = acceptedMarkers > 0;
  const inheritedSameContainerParagraph = inherited !== null
    && inherited.blockquoteDepth === blockquoteDepth
    && inherited.paragraph;
  const nestedBlockStartMarker = inheritedSameContainerParagraph
    && inheritedItems.length > 0
    && trailingMarker !== undefined
    && trailingMarker.column >= (inherited.listContinuationIndent ?? 0);
  const emptyListItem = thematicBreak === null
    && rejectedMarkerStart === null
    && trailingMarker !== undefined
    && trailingMarkerContent === ''
    && (!inheritedSameContainerParagraph || nestedBlockStartMarker);
  if (emptyListItem) {
    // Keep a marker with no body as a real list item when no paragraph is
    // active. This closes the preceding block before a following marker.
  } else if (thematicBreak === null && rejectedMarkerStart === null && trailingMarker !== undefined && trailingMarkerContent === '') {
    listItems = sourceCopyListItems(inheritedItems.slice(0, baseDepth + Math.max(0, acceptedMarkers - 1)));
    acceptedMarkers = Math.max(0, acceptedMarkers - 1);
    explicitList = acceptedMarkers > 0;
    offset = trailingMarker.start;
    column = trailingMarker.column;
  }
  if (rejectedMarkerStart !== null) {
    offset = rejectedMarkerStart;
    column = rejectedMarkerColumn;
    listItems = sourceCopyListItems(inheritedItems.slice(0, baseDepth));
  }
  const content = line.slice(offset);
  const contentColumn = column;
  const inheritedContinuation = inherited !== null
    && inherited.blockquoteDepth === blockquoteDepth
    && listItems.length > 0;
  let implicitList = false;
  let fenceContent = content;
  let fenceContentOffset = offset;
  let fenceContentColumn = contentColumn;
  const indent = sourceReadWhitespace(content, 0, contentColumn);
  let fenceIndentWidth = indent.width;
  let continuationSurplus = 0;
  let setextUnderline = false;
  const inheritedParagraph = inherited !== null
    && inherited.blockquoteDepth === blockquoteDepth
    && inherited.paragraph;
  if (thematicBreak === null && !explicitList && inheritedContinuation) {
    const requiredIndent = inherited.listContinuationIndent - contentColumn;
    const continuationCandidates = requiredIndent > 0
      ? [requiredIndent, ...inheritedItems
        .map(({ contentColumn: ancestorColumn }) => ancestorColumn - contentColumn)
        .filter((width) => width > 0)]
      : [];
    let continuation = content.trim() === ''
      ? { offset: 0, column: contentColumn, width: 0, valid: true }
      : { offset: 0, column: contentColumn, width: 0, valid: false };
    for (const [candidateIndex, width] of continuationCandidates.entries()) {
      const candidate = sourceConsumeIndent(content, width, contentColumn);
      if (candidate.offset === 0) continue;
      const remainingIndent = sourceReadWhitespace(
        content.slice(candidate.offset),
        0,
        contentColumn + candidate.width + (candidate.surplus ?? 0),
      ).width;
      const remainingOffset = sourceReadWhitespace(content.slice(candidate.offset), 0, contentColumn).offset;
      const remainingContent = content.slice(candidate.offset + remainingOffset).trim();
      if (candidateIndex > 0 && remainingContent !== '' && remainingIndent === 0
        && !/^(?:`{3,}|~{3,})/u.test(remainingContent)) continue;
      const inheritedFenceBody = (inherited?.fenceContentColumn ?? 0) > 0
        && (inherited?.allowFencedContinuation ?? false);
      if (remainingIndent + (candidate.surplus ?? 0) <= 3 || inheritedFenceBody) {
        continuation = candidate;
        break;
      }
    }
    if (content.trim() === '' || continuation.offset > 0) {
      implicitList = true;
      if (continuation.offset > 0) {
        fenceContent = content.slice(continuation.offset);
        fenceContentOffset = offset + continuation.offset;
        fenceContentColumn = contentColumn + continuation.width + (continuation.surplus ?? 0);
        continuationSurplus = continuation.surplus ?? 0;
        fenceIndentWidth = sourceReadWhitespace(fenceContent, 0, fenceContentColumn).width
          + continuationSurplus;
      }
    } else if (rejectedMarkerStart === null) {
      listItems = [];
    }
  }
  setextUnderline = inheritedParagraph
    && (!inheritedContinuation || implicitList)
    && sourceReadWhitespace(fenceContent, 0, fenceContentColumn).width <= 3
    && /^[-=]+[ \t]*$/u.test(fenceContent.trim());
  if (setextUnderline) {
    listItems = sourceCopyListItems(inheritedItems.slice(0, baseDepth));
    explicitList = false;
  }

  const listContinuationIndent = listItems.at(-1)?.contentColumn ?? 0;
  const inheritedAllow = inherited?.allowFencedContinuation ?? true;
  const inheritedHtmlBlock = inherited !== null
    && inherited.blockquoteDepth === blockquoteDepth
    ? inherited.htmlBlock ?? null
    : null;
  const htmlContainer = {
    blockquoteDepth,
    listItems: sourceCopyListItems(listItems),
    listKinds: listItems.map(({ kind }) => kind),
    listContinuationIndent,
    paragraph: inherited?.paragraph ?? false,
  };
  const htmlBlock = resolveHtml
    ? sourceHtmlBlockTransition(content, inheritedHtmlBlock, inherited?.paragraph ?? false, fenceIndentWidth, htmlContainer)
    : { active: false, state: null, mask: false };
  return {
    content,
    fenceContent,
    fenceContentOffset,
    fenceContentColumn,
    continuationSurplus,
    blockquoteDepth,
    listItems,
    listKinds: listItems.map(({ kind }) => kind),
    listContinuationIndent,
    explicitList,
    implicitList,
    indentWidth: indent.width,
    fenceIndentWidth,
    allowFencedContinuation: inheritedContinuation ? inheritedAllow : true,
    htmlBlock: htmlBlock.state,
    htmlBlockActive: htmlBlock.active,
    htmlBlockMask: Boolean(htmlBlock.mask),
    htmlBlockMaskEnd: htmlBlock.maskEnd === undefined ? null : offset + htmlBlock.maskEnd,
    paragraph: !emptyListItem
      && !htmlBlock.active && sourceParagraphContent(content, setextUnderline)
      && (implicitList ? inherited?.paragraph ?? true : true),
  };
};

const sourceFenceContainer = (context) => {
  if (context.htmlBlockActive || context.htmlBlock !== null) return null;
  if ((context.explicitList || context.implicitList) && !context.allowFencedContinuation) return null;
  if (context.explicitList && context.indentWidth > 3) return null;
  const indentation = context.fenceContent.match(/^[ \t]*/u)?.[0] ?? '';
  const marker = context.fenceContent.slice(indentation.length)
    .match(/^(?<run>`{3,}|~{3,})(?<rawInfo>.*)$/u);
  if (marker === null || context.fenceIndentWidth > 3) return null;
  const run = marker.groups.run;
  const rawInfo = marker.groups.rawInfo;
  if (run[0] === '`' && rawInfo.includes('`')) return null;
  return {
    character: run[0],
    length: run.length,
    info: rawInfo.trim(),
    rawInfo,
    container: {
      blockquoteDepth: context.blockquoteDepth,
      listItems: sourceCopyListItems(context.listItems),
      listKinds: context.listKinds,
      listContinuationIndent: context.listContinuationIndent,
      explicitList: context.explicitList,
      indentWidth: context.fenceIndentWidth,
    },
  };
};

const sourceSameListContainer = (left, right) => left.listKinds.length === right.listKinds.length
  && left.listKinds.every((kind, index) => kind === right.listKinds[index])
  && left.listItems.every((item, index) => item.contentColumn === right.listItems[index]?.contentColumn);

const sourceSameFenceContainer = (left, right) => {
  if (left.blockquoteDepth !== right.blockquoteDepth) return false;
  if (left.listKinds.length === 0 && right.listKinds.length === 0) {
    return left.indentWidth < 4 && right.indentWidth < 4;
  }
  if (left.listKinds.length === 0 || right.listKinds.length === 0) return false;
  return sourceSameListContainer(left, right)
    && left.listContinuationIndent === right.listContinuationIndent;
};

const sourceFenceCloses = (marker, open) => marker !== null
  && marker.character === open.character
  && marker.length >= open.length
  && /^[ \t]*$/u.test(marker.rawInfo)
  && !(open.container.listKinds.length > 0 && marker.container.explicitList)
  && sourceSameFenceContainer(marker.container, open.container);

const sourceFenceEnded = (open, context, fence) => {
  if (context.blockquoteDepth < open.container.blockquoteDepth) return true;
  if (context.blockquoteDepth > open.container.blockquoteDepth) return false;
  if (open.container.listKinds.length === 0) return false;
  if (context.listKinds.length < open.container.listKinds.length) {
    return !context.implicitList || context.listContinuationIndent < open.container.listContinuationIndent;
  }
  if (context.listKinds.length > open.container.listKinds.length) return false;
  if (!sourceSameListContainer(context, open.container)) return true;
  return fence !== null && context.explicitList;
};

const sourceStateFromContext = (context, paragraph = context.paragraph) => ({
  blockquoteDepth: context.blockquoteDepth,
  listItems: sourceCopyListItems(context.listItems),
  listKinds: [...context.listKinds],
  listContinuationIndent: context.listContinuationIndent,
  htmlBlock: context.htmlBlock,
  htmlBlockMask: context.htmlBlockMask,
  fenceContentColumn: context.fenceContentColumn,
  fenceIndentWidth: context.fenceIndentWidth,
  continuationSurplus: context.continuationSurplus,
  allowFencedContinuation: context.allowFencedContinuation && context.fenceIndentWidth <= 3,
  paragraph,
});

const sourceFenceRanges = (text) => {
  const ranges = [];
  let open = null;
  let listContext = {
    blockquoteDepth: 0,
    listItems: [],
    listKinds: [],
    listContinuationIndent: 0,
    htmlBlock: null,
    htmlBlockMask: false,
    fenceContentColumn: 0,
    fenceIndentWidth: 0,
    continuationSurplus: 0,
    allowFencedContinuation: true,
    paragraph: false,
  };
  let previousLineEnd = 0;
  for (const physical of sourcePhysicalLines(text)) {
    let processed = false;
    while (!processed) {
      const inherited = open === null ? listContext : open.state;
      const context = sourceContainerContext(physical.line, inherited);
      const fence = sourceFenceContainer(context);
      if (open === null) {
        if (fence !== null) {
          open = {
            ...fence,
            start: physical.start,
            state: sourceStateFromContext(context, false),
            after: {
              ...inherited,
              listItems: sourceCopyListItems(inherited.listItems),
              listKinds: [...inherited.listKinds],
            },
          };
        } else {
          listContext = sourceStateFromContext(context);
        }
        processed = true;
        continue;
      }
      if (sourceFenceCloses(fence, open)) {
        ranges.push([open.start, physical.end]);
        listContext = { ...open.after, paragraph: false };
        open = null;
        processed = true;
        continue;
      }
      if (sourceFenceEnded(open, context, fence)) {
        ranges.push([open.start, previousLineEnd]);
        listContext = { ...open.after, paragraph: false };
        open = null;
        continue;
      }
      processed = true;
    }
    previousLineEnd = physical.end;
  }
  if (open !== null) ranges.push([open.start, text.length]);
  return ranges;
};

export function markdownFenceRanges(text) {
  return sourceFenceRanges(text);
}

export function markdownHtmlBlockRanges(text) {
  const ranges = [];
  const fences = sourceFenceRanges(text);
  let fenceIndex = 0;
  let listContext = {
    blockquoteDepth: 0,
    listItems: [],
    listKinds: [],
    listContinuationIndent: 0,
    htmlBlock: null,
    htmlBlockMask: false,
    fenceContentColumn: 0,
    fenceIndentWidth: 0,
    continuationSurplus: 0,
    allowFencedContinuation: true,
    paragraph: false,
  };
  let rangeStart = null;
  let htmlState = null;
  for (const physical of sourcePhysicalLines(text)) {
    while (fenceIndex < fences.length && physical.start >= fences[fenceIndex][1]) fenceIndex += 1;
    const fence = fences[fenceIndex];
    if (fence !== undefined && physical.start >= fence[0] && physical.start < fence[1]) {
      if (rangeStart !== null) {
        ranges.push([rangeStart, physical.start]);
        rangeStart = null;
      }
      continue;
    }
    const context = htmlState === null ? sourceContainerContext(physical.line, listContext) : null;
    const transition = htmlState === null ? null : sourceHtmlBlockTransition(physical.line, htmlState);
    const htmlMask = transition?.mask ?? context?.htmlBlockMask ?? false;
    const htmlMaskEnd = transition?.maskEnd ?? context?.htmlBlockMaskEnd ?? null;
    const htmlBlock = transition?.state ?? context?.htmlBlock ?? null;
    if (htmlMask) {
      if (rangeStart === null) rangeStart = physical.start;
      if (htmlMaskEnd !== null) {
        ranges.push([rangeStart, physical.start + htmlMaskEnd]);
        rangeStart = null;
      } else if (htmlBlock === null) {
        ranges.push([rangeStart, physical.end]);
        rangeStart = null;
      }
    } else if (rangeStart !== null) {
      ranges.push([rangeStart, physical.start]);
      rangeStart = null;
    }
    if (transition !== null) {
      htmlState = transition.state;
      listContext = { ...listContext, htmlBlock: htmlState, htmlBlockMask: Boolean(transition.mask) };
    } else {
      htmlState = context.htmlBlockMask ? context.htmlBlock : null;
      listContext = sourceStateFromContext(context);
    }
  }
  if (rangeStart !== null) ranges.push([rangeStart, text.length]);
  return ranges;
}

export function contentPipelineTitle(markdown) {
  const masked = maskRanges(markdown, markdownFenceRanges(markdown));
  for (const { line } of sourcePhysicalLines(masked)) {
    const heading = line.match(/^#\s+(.+?)\s*$/u);
    if (heading !== null) {
      const title = heading[1].replace(/\s+#+\s*$/u, '').trim();
      if (title !== '') return title;
    }
  }
  throw new Error('Source reader title is missing a non-empty level-one heading.');
}

const sourceFenceMarker = (line) => line.match(/(?:^|[ \t])(?<run>`{3,}|~{3,})(?<info>.*)$/u);

const sourceFenceLineStates = (text) => {
  const physicalLines = [...sourcePhysicalLines(text)];
  const states = new Map();
  for (const [start, end] of sourceFenceRanges(text)) {
    const opener = physicalLines.find((physical) => physical.start === start);
    const marker = opener === undefined ? null : sourceFenceMarker(opener.line);
    if (marker === null) continue;
    const run = marker.groups.run;
    const info = marker.groups.info.trim().split(/\s+/u, 1)[0]?.toLocaleLowerCase('en-US') ?? '';
    for (const physical of physicalLines) {
      if (physical.start < start || physical.start >= end) continue;
      const closing = physical.end === end ? sourceFenceMarker(physical.line) : null;
      const isClosing = closing !== null
        && closing.groups.run[0] === run[0]
        && closing.groups.run.length >= run.length
        && /^[ \t]*$/u.test(closing.groups.info);
      states.set(physical.start, {
        first: physical.start === start,
        last: isClosing,
        end: physical.end === end,
        marker: run[0],
        runLength: run.length,
        executable: ['bash', 'sh', 'shell', 'zsh', 'console', 'shellsession'].includes(info),
      });
    }
  }
  return { physicalLines, states };
};

export function nextRawFenceState(line, fence) {
  return nextMarkdownFenceState(line, fence);
}

function rawImageTargetOffset(matched, target, title) {
  return matched.length - 1 - (title?.length ?? 0) - target.length;
}

function rawImageUrl({ projectRoot, sourceDir, target }) {
  if (target.startsWith('/') || target.startsWith('#') || URL.canParse(target)) return null;
  let decoded = target;
  try {
    decoded = decodeURI(target);
  } catch {
    // Keep the original target when its percent escapes are malformed.
  }
  if (!rawImageExtensions.has(path.extname(decoded).toLocaleLowerCase('en-US'))) return null;

  const absolutePath = path.resolve(sourceDir, decoded);
  const relativePath = path.relative(projectRoot, absolutePath);
  if (relativePath === '..' || relativePath.startsWith(`..${path.sep}`) || path.isAbsolute(relativePath)) {
    throw new Error(`Relative Raw image target escapes the website project root: ${target}.`);
  }
  return { absolutePath, relativePath };
}

async function existingRawImageUrl({ projectRoot, sourceDir, target }) {
  const resolved = rawImageUrl({ projectRoot, sourceDir, target });
  if (resolved === null) return null;
  try {
    if (!(await stat(resolved.absolutePath)).isFile()) return null;
  } catch {
    return null;
  }
  const encodedPath = resolved.relativePath
    .split(path.sep)
    .map((segment) => encodeURIComponent(segment))
    .join('/');
  return `/blume-assets/content/${encodedPath}`;
}

async function rewriteRawImageLine(line, { projectRoot, sourceDir }) {
  const masked = line.replaceAll(rawInlineCodePattern, (span) => ' '.repeat(span.length));
  let output = '';
  let cursor = 0;
  for (const match of masked.matchAll(rawMarkdownImagePattern)) {
    const target = match.groups?.target ?? '';
    const url = await existingRawImageUrl({ projectRoot, sourceDir, target });
    if (url === null) continue;
    const offset = (match.index ?? 0) + rawImageTargetOffset(match[0], target, match.groups?.title);
    output += line.slice(cursor, offset) + url;
    cursor = offset + target.length;
  }
  return output + line.slice(cursor);
}

export async function rewriteRelativeImageReferences({ source, sourcePath, projectRoot } = {}) {
  const sourceDir = path.dirname(sourcePath);
  const fences = markdownFenceRanges(source);
  let output = '';
  for (const physical of sourcePhysicalLines(source)) {
    const inFence = fences.some(([start, end]) => physical.start >= start && physical.start < end);
    const line = inFence
      ? physical.line
      : await rewriteRawImageLine(physical.line, { projectRoot, sourceDir });
    output += line + physical.terminator;
  }
  return output;
}

function stripPlaceholderCodeFences(text) {
  return maskRanges(text, markdownFenceRanges(text));
}

function htmlTagEnd(text, start) {
  let quote = null;
  let escaped = false;
  let expressionDepth = 0;
  let templateExpressionDepth = 0;
  for (let index = start; index < text.length; index += 1) {
    const character = text[index];
    if (escaped) {
      escaped = false;
      continue;
    }
    if (quote !== null) {
      if (character === '\\') {
        escaped = true;
      } else if (quote === '`' && character === '$' && text[index + 1] === '{') {
        templateExpressionDepth += 1;
        index += 1;
      } else if (quote === '`' && character === '}' && templateExpressionDepth > 0) {
        templateExpressionDepth -= 1;
      } else if (character === quote && (quote !== '`' || templateExpressionDepth === 0)) {
        quote = null;
      }
      continue;
    }
    if (character === '"' || character === "'" || character === '`') {
      quote = character;
      continue;
    }
    if (character === '{') {
      expressionDepth += 1;
      continue;
    }
    if (character === '}' && expressionDepth > 0) {
      expressionDepth -= 1;
      continue;
    }
    if (character === '<' && expressionDepth === 0) return -1;
    if (character === '>' && expressionDepth === 0) return index;
  }
  return -1;
}

function htmlTagAt(text, start) {
  if (text[start] !== '<') return null;
  const tag = text.slice(start).match(/^<\/?(?<name>[A-Za-z][A-Za-z0-9:.-]*)/u);
  if (tag === null) return null;
  const end = htmlTagEnd(text, start + tag[0].length);
  return { end, name: tag.groups.name.toLocaleLowerCase('en-US') };
}

function matchingInlineCodeEnd(text, start, runLength) {
  for (let index = start + runLength; index < text.length; index += 1) {
    if (text[index] !== '`') continue;
    let end = index + 1;
    while (text[end] === '`') end += 1;
    if (end - index === runLength) return end - 1;
    index = end - 1;
  }
  return -1;
}

function inlineCodeSections(text) {
  const sections = [];
  const visible = stripPlaceholderCodeFences(text);
  for (let index = 0; index < visible.length;) {
    const tag = htmlTagAt(visible, index);
    if (tag !== null) {
      index = tag.end < 0 ? (visible.indexOf('\n', index) + 1 || visible.length) : tag.end + 1;
      continue;
    }
    if (visible[index] !== '`') {
      index += 1;
      continue;
    }
    let openingEnd = index + 1;
    while (visible[openingEnd] === '`') openingEnd += 1;
    const runLength = openingEnd - index;
    const closingEnd = matchingInlineCodeEnd(visible, index, runLength);
    if (closingEnd < 0) {
      index = openingEnd;
      continue;
    }
    sections.push(visible.slice(openingEnd, closingEnd - runLength + 1));
    index = closingEnd + 1;
  }
  return sections;
}

function placeholderSections(text, inlineCodeOnly) {
  if (!inlineCodeOnly) return [text];
  return inlineCodeSections(text);
}

function placeholderCountMap(text, { inlineCodeOnly = false } = {}) {
  const counts = new Map();
  for (const section of placeholderSections(text, inlineCodeOnly)) {
    const decoded = decodeArtifactEntities(section);
    for (const match of decoded.matchAll(placeholderTokenPattern)) {
      const name = match.groups?.name ?? '';
      const normalizedName = name.toLocaleLowerCase('en-US');
      if (name === '' || name.startsWith('/') || normalizedName === 'br' || normalizedName === 'br/') continue;
      counts.set(`<${name}>`, (counts.get(`<${name}>`) ?? 0) + 1);
    }
  }
  return counts;
}

function placeholderInventoryFromMap(counts) {
  const entries = [...counts.entries()].sort(([left], [right]) => left.localeCompare(right, 'en-US'));
  return {
    total: entries.reduce((sum, [, count]) => sum + count, 0),
    counts: Object.fromEntries(entries),
  };
}

function mergePlaceholderInventories(inventories) {
  const counts = new Map();
  for (const inventory of inventories) {
    for (const [token, count] of Object.entries(inventory.counts)) counts.set(token, (counts.get(token) ?? 0) + count);
  }
  return placeholderInventoryFromMap(counts);
}

function expectedPlaceholderInventory(value, options) {
  return typeof value === 'string' ? placeholderInventory(value, options) : value;
}

function danglingPlaceholderFragments(text, { inlineCodeOnly = false } = {}) {
  const fragments = [];
  for (const section of placeholderSections(text, inlineCodeOnly)) {
    const decoded = decodeArtifactEntities(section);
    const masked = decoded.replaceAll(placeholderTokenPattern, (token) => ' '.repeat(token.length));
    for (const match of masked.matchAll(placeholderDanglingPattern)) {
      const index = match.index ?? 0;
      const tagStart = decoded.lastIndexOf('<', index);
      const tag = tagStart > decoded.lastIndexOf('>', index) ? htmlTagAt(decoded, tagStart) : null;
      if (tag === null || tag.end < index || !knownMarkupNames.has(tag.name)) fragments.push(match[0]);
    }
    for (const match of masked.matchAll(placeholderBareClosingPattern)) {
      const name = match.groups?.name ?? '';
      if (!/[./-]$/u.test(name) && !knownMarkupNames.has(name.toLocaleLowerCase('en-US'))) fragments.push(match[0]);
    }
  }
  return fragments;
}

const knownMarkupNames = new Set([
  'a', 'article', 'aside', 'blume-mermaid', 'blume-search', 'blume-toc', 'blume-webmcp',
  'body', 'br', 'button', 'circle', 'code', 'col', 'dd', 'details',
  'dialog', 'div', 'dl', 'em', 'fieldset', 'figure', 'figcaption', 'footer', 'form',
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html', 'img', 'input',
  'label', 'li', 'link', 'main', 'meta', 'nav', 'ol', 'option', 'p', 'path', 'pre',
  'g', 'kbd', 'rect', 'script', 'section', 'select', 'small', 'source', 'span', 'strong', 'style',
  'summary', 'svg', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th',
  'thead', 'title', 'track', 'tr', 'ul', 'wbr',
]);
const inertHtmlContentNames = ['head', 'script', 'style', 'template', 'title', 'pre'];

function insideInertHtmlContent(text, index) {
  for (const name of inertHtmlContentNames) {
    const opening = text.lastIndexOf(`<${name}`, index);
    const closing = text.lastIndexOf(`</${name}`, index);
    if (opening <= closing) continue;
    const tag = htmlTagAt(text, opening);
    if (tag?.name === name && tag.end < index) return true;
  }
  return false;
}

function danglingArtifactPlaceholderFragments(text) {
  const decoded = decodeArtifactEntities(stripPlaceholderCodeFences(text));
  const masked = decoded
    .replaceAll(placeholderTokenPattern, (token) => ' '.repeat(token.length));
  const fragments = [...masked.matchAll(placeholderDanglingPattern)]
    .filter((match) => {
      const name = match.groups?.name ?? '';
      const index = match.index ?? 0;
      const tagStart = decoded.lastIndexOf('<', index);
      const tag = tagStart > decoded.lastIndexOf('>', index) ? htmlTagAt(decoded, tagStart) : null;
      return !insideInertHtmlContent(decoded, index)
        && !knownMarkupNames.has(name.toLocaleLowerCase('en-US'))
        && (tag === null || tag.end < index);
    })
    .map((match) => match.groups?.name ?? '')
    .map((name) => `<${name}`);
  for (const match of masked.matchAll(placeholderBareClosingPattern)) {
    const name = match.groups?.name ?? '';
    const index = match.index ?? 0;
    const tagStart = decoded.lastIndexOf('<', index);
    const tag = tagStart > decoded.lastIndexOf('>', index) ? htmlTagAt(decoded, tagStart) : null;
    if (!/[./-]$/u.test(name)
      && !insideInertHtmlContent(decoded, index)
      && !(tag !== null && tag.end >= index)
      && !knownMarkupNames.has(name.toLocaleLowerCase('en-US'))) fragments.push(`${name}>`);
  }
  return fragments;
}

function assertNoDanglingArtifactPlaceholderFragments(text, location) {
  const fragments = danglingArtifactPlaceholderFragments(text);
  if (fragments.length > 0) throw new Error(`Artifact contains dangling ${fragments[0]} fragment in ${location}.`);
}

export function placeholderInventory(text, { inlineCodeOnly = false } = {}) {
  if (typeof text !== 'string') throw new Error('Placeholder inventory requires text.');
  return placeholderInventoryFromMap(placeholderCountMap(text, { inlineCodeOnly }));
}

export function assertPlaceholderParity(expected, actual, {
  expectedInlineCodeOnly = false,
  actualInlineCodeOnly = false,
  location = 'placeholder surface',
} = {}) {
  const expectedInventory = expectedPlaceholderInventory(expected, { inlineCodeOnly: expectedInlineCodeOnly });
  const actualInventory = typeof actual === 'string'
    ? expectedPlaceholderInventory(actual, { inlineCodeOnly: actualInlineCodeOnly })
    : actual;
  if (!expectedInventory || typeof expectedInventory !== 'object' || !expectedInventory.counts) {
    throw new Error(`Placeholder parity requires an expected inventory for ${location}.`);
  }
  if (!actualInventory || typeof actualInventory !== 'object' || !actualInventory.counts) {
    throw new Error(`Placeholder parity requires text for ${location}.`);
  }
  const dangling = typeof actual === 'string' ? danglingPlaceholderFragments(actual, { inlineCodeOnly: actualInlineCodeOnly }) : [];
  if (dangling.length > 0) throw new Error(`Placeholder surface contains dangling ${dangling[0]} fragment in ${location}.`);
  if (expectedInventory.total !== actualInventory.total || JSON.stringify(expectedInventory.counts) !== JSON.stringify(actualInventory.counts)) {
    throw new Error(`Placeholder surface drifted for ${location}: expected ${expectedInventory.total} exact occurrence(s), found ${actualInventory.total}.`);
  }
  return actualInventory;
}

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
  for (const [source, markdown] of sourceTexts) {
    const dangling = danglingPlaceholderFragments(markdown, { inlineCodeOnly: true });
    if (dangling.length > 0) throw new Error(`Source placeholder surface contains dangling ${dangling[0]} fragment in ${source}.`);
  }
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

function sourceBoundContentCacheKey({ sourceDirectory, repositoryRoot, sourceTexts, contentMap }) {
  const digest = createHash('sha256');
  digest.update(JSON.stringify({
    sourceDirectory: path.resolve(sourceDirectory),
    repositoryRoot: path.resolve(repositoryRoot),
    contentMap,
  }));
  for (const [source, markdown] of [...sourceTexts.entries()].sort(([left], [right]) => left.localeCompare(right, 'en'))) {
    digest.update(source);
    digest.update('\0');
    digest.update(markdown);
    digest.update('\0');
  }
  return digest.digest('hex');
}

async function generateSourceBoundContent({ sourceDirectory, repositoryRoot, contentMap }) {
  const temporary = await mkdtemp(path.join(tmpdir(), 'blackops-reader-source-bound-'));
  const generatedContentRoot = path.join(temporary, 'src', 'content', 'docs');
  const generatedManifestPath = path.join(temporary, '.generated', 'content-manifest.json');
  try {
    await generateContent({
      sourceRoot: sourceDirectory,
      contentRoot: generatedContentRoot,
      manifestPath: generatedManifestPath,
      repositoryRoot,
      contentMap,
    });
    const manifestBytes = await readFile(generatedManifestPath);
    const manifest = JSON.parse(manifestBytes.toString('utf8'));
    if (!Array.isArray(manifest.pages)) throw new Error('Source-bound generated manifest is missing its pages array.');
    const generatedByPath = new Map();
    const pagesBySource = new Map();
    for (const page of manifest.pages) {
      const generatedPath = path.join(generatedContentRoot, ...page.generated.split('/'));
      const bytes = await readFile(generatedPath);
      const value = { page, bytes };
      generatedByPath.set(page.generated, value);
      pagesBySource.set(page.source, value);
    }
    return { manifestBytes, generatedByPath, pagesBySource };
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
}

async function sourceBoundContent(options) {
  const key = sourceBoundContentCacheKey(options);
  let pending = sourceBoundContentCache.get(key);
  if (pending === undefined) {
    pending = generateSourceBoundContent(options).catch((error) => {
      sourceBoundContentCache.delete(key);
      throw error;
    });
    sourceBoundContentCache.set(key, pending);
  }
  return pending;
}

export async function validateArtifactReaderContract({
  contentMap,
  artifactDirectory = defaultDistRoot,
  sourceDirectory = defaultSourceRoot,
  manifestPath = null,
  contentRoot = null,
  diagramManifestPath = null,
} = {}) {
  if (contentMap === undefined) throw new Error('Artifact reader validation requires a Content Map.');
  const effectiveSourceDirectory = sourceDirectory ?? defaultSourceRoot;
  const sourceFiles = await markdownFiles(effectiveSourceDirectory);
  validateReaderContract(contentMap, { sourceFiles });
  const pages = Object.entries(contentMap).filter(([source]) => source !== 'README.md');
  const byRoute = new Map(pages.map(([source, metadata]) => [routeFor(metadata.slug), { source, metadata }]));
  const sourceTexts = new Map();
  for (const source of sourceFiles) sourceTexts.set(source, await readFile(path.join(effectiveSourceDirectory, ...source.split('/')), 'utf8'));
  const sourcePlaceholderInventories = new Map([...sourceTexts.entries()].map(([source, markdown]) => [
    source,
    placeholderInventory(markdown, { inlineCodeOnly: true }),
  ]));
  for (const [source, markdown] of sourceTexts) {
    const dangling = danglingPlaceholderFragments(markdown, { inlineCodeOnly: true });
    if (dangling.length > 0) throw new Error(`Source placeholder surface contains dangling ${dangling[0]} fragment in ${source}.`);
  }
  const selectedDiagramManifestPath = diagramManifestPath
    ?? (path.resolve(artifactDirectory) === path.resolve(defaultDistRoot) ? defaultDiagramManifestPath : null);
  const registeredViewers = selectedDiagramManifestPath === null
    ? new Set()
    : registeredViewerRelativePaths(await loadDiagramManifest(selectedDiagramManifestPath));
  await validateArtifactPageRouteInventory({
    artifactDirectory,
    expectedRoutes: new Set(byRoute.keys()),
    diagramManifestPath: selectedDiagramManifestPath,
  });
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
  const searchDangling = danglingPlaceholderFragments(search.map((record) => collectStrings(record).join('\n')).join('\n'));
  if (searchDangling.length > 0) throw new Error(`Search artifact contains dangling ${searchDangling[0]} fragment.`);
  const canonical = await readCanonicalContentManifest({
    artifactDirectory,
    manifestPath,
    contentRoot,
    expectedPages: pages,
    sourceTexts,
    contentMap,
    sourceDirectory: effectiveSourceDirectory,
    repositoryRoot: defaultRepositoryRoot,
  });
  const canonicalPublicRawBySource = new Map();
  for (const [route, { source, metadata }] of byRoute) {
    const record = searchByRoute.get(route);
    if (record.url !== undefined && record.url !== route) {
      throw new Error(`Search ${route} has a route/url mismatch: ${record.url}.`);
    }
    const expectedPlaceholders = sourcePlaceholderInventories.get(source);
    if (!expectedPlaceholders) throw new Error(`Source placeholder inventory is missing for ${source}.`);
    if (typeof record.content !== 'string') throw new Error(`Search ${route} is missing its content field.`);
    assertPlaceholderParity(expectedPlaceholders, record.content, { location: `Search content ${route}` });
    const manifestPage = canonical.pagesBySource.get(source);
    const canonicalRawPath = path.join(canonical.contentRoot, ...manifestPage.generated.split('/'));
    const rawPath = path.join(artifactDirectory, ...manifestPage.generated.split('/'));
    let canonicalRawBytes;
    let rawBytes;
    try {
      canonicalRawBytes = await readFile(canonicalRawPath);
      rawBytes = await readFile(rawPath);
    } catch {
      throw new Error(`Raw Markdown artifact is missing for ${source}: ${manifestPage.generated}.`);
    }
    const canonicalHash = createHash('sha256').update(canonicalRawBytes).digest('hex');
    if (canonicalHash !== manifestPage.hash) {
      throw new Error(`Canonical generated content hash drifted for ${source}: ${manifestPage.generated}.`);
    }
    const expectedRawBytes = Buffer.from(await rewriteRelativeImageReferences({
      source: canonicalRawBytes.toString('utf8'),
      sourcePath: canonicalRawPath,
      projectRoot: path.resolve(canonical.contentRoot, '../../..'),
    }), 'utf8');
    if (!rawBytes.equals(expectedRawBytes)) {
      throw new Error(`Raw ${route} is not byte-exact source-derived content for ${source}: ${manifestPage.generated}.`);
    }
    canonicalPublicRawBySource.set(source, expectedRawBytes);
    const expectedSearchContent = blumeToPlainText(canonicalGeneratedBody(expectedRawBytes.toString('utf8'), source));
    if (record.content !== expectedSearchContent) {
      throw new Error(`Search content ${route} is not source-derived; expected the exact patched Blume plain-text body for ${source}.`);
    }
    const strings = collectStrings(record);
    assertSingleOutcome(strings, metadata.reader.outcome, outcomes, `Search ${route}`);
    assertSearchRecordNonContentPlaceholderFree(record, `Search ${route}`);
    const raw = rawBytes.toString('utf8');
    const canonicalTitle = canonicalGeneratedFrontmatterTitle(canonicalRawBytes.toString('utf8'), source);
    if (canonicalTitle !== manifestPage.title) {
      throw new Error(`Canonical generated title drifted for ${source}: expected ${manifestPage.title}, found ${canonicalTitle}.`);
    }
    assertNoDanglingArtifactPlaceholderFragments(raw, `Raw ${route}`);
    assertPlaceholderParity(expectedPlaceholders, raw, { actualInlineCodeOnly: true, location: `Raw ${route}` });
    if (!raw.includes(`description: ${JSON.stringify(metadata.reader.outcome)}`)) {
      throw new Error(`Raw Markdown artifact outcome drifted for ${source}.`);
    }
    const htmlPath = path.join(artifactDirectory, ...metadata.slug.split('/'), 'index.html');
    const html = await readFile(htmlPath, 'utf8').catch(() => '');
    if (html === '') throw new Error(`HTML artifact is missing for ${source}: ${metadata.slug}.`);
    await assertHtmlReaderSemanticContract({
      html,
      expectedRaw: expectedRawBytes.toString('utf8'),
      title: manifestPage.title,
      outcome: metadata.reader.outcome,
      route,
      source,
    });
    assertSingleOutcome([html], metadata.reader.outcome, outcomes, `HTML ${route}`);
    assertPlaceholderParity(expectedPlaceholders, extractVisibleInlineCodeInventory(html, `HTML ${route}`), { location: `HTML inline code ${route}` });
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
    const sourceMatch = segment.match(/^# (?<title>[^\n]+)\nSource:\s*(?<url>https?:\/\/[^\s]+)$/mu);
    if (!sourceMatch) throw new Error('llms-full.txt contains a malformed title/Source segment.');
    const route = routeFromArtifactUrl(sourceMatch.groups.url);
    const bodyStart = (sourceMatch.index ?? 0) + sourceMatch[0].length;
    if (!segment.startsWith('\n\n', bodyStart)) throw new Error(`llms-full.txt is missing the body separator for ${route}.`);
    if (segmentByRoute.has(route)) throw new Error(`llms-full.txt contains a duplicate route: ${route}.`);
    segmentByRoute.set(route, {
      segment,
      title: sourceMatch.groups.title,
      sourceUrl: sourceMatch.groups.url,
      body: segment.slice(bodyStart + 2),
    });
  }
  const llmsFullRoutes = new Set([...segmentByRoute.keys()].filter((route) => route !== '/'));
  const unknownLlmFullRoutes = [...llmsFullRoutes].filter((route) => !expectedRoutes.has(route));
  if (unknownLlmFullRoutes.length > 0) throw new Error(`llms-full.txt contains unknown route(s): ${unknownLlmFullRoutes.join(', ')}.`);
  if (llmsFullRoutes.size !== expectedRoutes.size || [...expectedRoutes].some((route) => !llmsFullRoutes.has(route))) {
    throw new Error('llms-full.txt route inventory does not match the 40-page Content Map.');
  }
  for (const [route, { source, metadata }] of byRoute) {
    const entry = segmentByRoute.get(route);
    if (!entry) throw new Error(`llms-full.txt is missing the segment for ${source}: ${route}.`);
    const expectedPlaceholders = sourcePlaceholderInventories.get(source);
    if (!expectedPlaceholders) throw new Error(`Source placeholder inventory is missing for ${source}.`);
    const manifestPage = canonical.pagesBySource.get(source);
    const publicRaw = canonicalPublicRawBySource.get(source);
    if (!manifestPage || publicRaw === undefined) throw new Error(`Canonical generated content is missing for ${source}.`);
    if (entry.title !== manifestPage.title) {
      throw new Error(`llms-full ${route} title drifted from the canonical manifest for ${source}.`);
    }
    if (routeFromArtifactUrl(entry.sourceUrl) !== route) {
      throw new Error(`llms-full ${route} Source URL route drifted for ${source}.`);
    }
    const segment = entry.segment;
    assertNoDanglingArtifactPlaceholderFragments(segment, `llms-full ${route}`);
    const expectedBody = canonicalGeneratedBody(publicRaw.toString('utf8'), source).trim();
    if (entry.body.trim() !== expectedBody) {
      throw new Error(`llms-full ${route} body drifted from validated public Raw for ${source}.`);
    }
    const markers = [
      `<!-- blackops-reader-outcome: ${metadata.reader.outcome} -->`,
      `{/* blackops-reader-outcome: ${metadata.reader.outcome} */}`,
    ];
    if (!markers.some((marker) => segment.includes(marker))) throw new Error(`llms-full ${route} is missing its generated reader outcome marker.`);
    assertSingleOutcome([segment], metadata.reader.outcome, outcomes, `llms-full ${route}`);
    assertPlaceholderParity(expectedPlaceholders, segment, { actualInlineCodeOnly: true, location: `llms-full inline code ${route}` });
  }

  for (const file of await textFiles(artifactDirectory)) {
    const location = path.relative(artifactDirectory, file).split(path.sep).join('/');
    const content = await readFile(file, 'utf8');
    assertNoProtectedDecode(content, location);
    assertNoInternalEvidenceVoice(content, location);
    const readerText = artifactReaderSurfaceText(content, location);
    assertNoCurrentMainOnly(registeredViewers.has(location) ? readerText ?? '' : content, location);
    if (readerText !== null) assertNoUnsafeLgtmDiagnostics(readerText, location);
  }
  return { routes: expectedRoutes.size, searchRoutes: actualRoutes.size };
}

async function readCanonicalContentManifest({
  artifactDirectory,
  manifestPath,
  contentRoot,
  expectedPages,
  sourceTexts,
  contentMap,
  sourceDirectory,
  repositoryRoot,
}) {
  const fixtureRoot = path.dirname(artifactDirectory);
  const selectedManifestPath = manifestPath
    ?? await firstExisting(path.join(fixtureRoot, '.generated/content-manifest.json'), defaultManifestPath);
  if (selectedManifestPath === null) throw new Error('Reader artifact contract requires the canonical content manifest.');
  let manifest;
  try {
    manifest = JSON.parse(await readFile(selectedManifestPath, 'utf8'));
  } catch {
    throw new Error(`Canonical content manifest is unreadable: ${selectedManifestPath}.`);
  }
  if (manifest?.schemaVersion !== 1 || !Array.isArray(manifest.pages)) {
    throw new Error('Canonical content manifest must use schemaVersion 1 with a pages array.');
  }
  const nonLandingPages = manifest.pages.filter((page) => page?.source !== 'README.md');
  if (nonLandingPages.length !== expectedPages.length) {
    throw new Error(`Canonical content manifest must contain exactly ${expectedPages.length} non-Landing records.`);
  }
  const pagesBySource = new Map();
  const sources = new Set();
  const generatedPaths = new Set();
  const slugs = new Set();
  for (const page of manifest.pages) {
    if (page === null || typeof page !== 'object' || Array.isArray(page)
      || typeof page.source !== 'string' || typeof page.generated !== 'string' || typeof page.slug !== 'string'
      || typeof page.title !== 'string' || page.title.trim() === ''
      || typeof page.hash !== 'string' || !/^[0-9a-f]{64}$/u.test(page.hash)) {
      throw new Error('Canonical content manifest contains a malformed page record.');
    }
    const keys = Object.keys(page).sort();
    const expectedKeys = ['generated', 'hash', 'slug', 'source', 'title'];
    if (keys.length !== expectedKeys.length || keys.some((key, index) => key !== expectedKeys[index])) {
      throw new Error('Canonical content manifest page records must use the closed source/generated/slug/title/hash schema.');
    }
    if (sources.has(page.source) || generatedPaths.has(page.generated) || slugs.has(page.slug)) {
      throw new Error('Canonical content manifest contains a duplicate source, generated path, or slug.');
    }
    if (page.generated !== path.posix.normalize(page.generated)
      || page.generated.startsWith('/') || page.generated.startsWith('../') || page.generated.includes('/../')
      || !/\.mdx?$/u.test(page.generated)) {
      throw new Error(`Canonical content manifest contains an unsafe generated path: ${page.generated}.`);
    }
    if (page.source !== 'README.md') pagesBySource.set(page.source, page);
    sources.add(page.source);
    generatedPaths.add(page.generated);
    slugs.add(page.slug);
  }
  const expectedSources = new Set(expectedPages.map(([source]) => source));
  if (pagesBySource.size !== expectedSources.size || [...expectedSources].some((source) => !pagesBySource.has(source))) {
    throw new Error('Canonical content manifest sources must match the 40-page Content Map exactly.');
  }
  for (const [source, metadata] of expectedPages) {
    const page = pagesBySource.get(source);
    const extension = page.generated.endsWith('.mdx') ? '.mdx' : '.md';
    if (page.slug !== metadata.slug || page.generated !== `${metadata.slug}${extension}`) {
      throw new Error(`Canonical content manifest route mapping drifted for ${source}.`);
    }
    const sourceMarkdown = sourceTexts?.get(source);
    if (typeof sourceMarkdown !== 'string' || page.title !== contentPipelineTitle(sourceMarkdown)) {
      throw new Error(`Canonical content manifest title drifted from the Source H1 for ${source}.`);
    }
  }
  const selectedContentRoot = contentRoot
    ?? (await directoryExists(path.join(fixtureRoot, 'src/content/docs')) ? path.join(fixtureRoot, 'src/content/docs') : defaultContentRoot);
  if (!(await directoryExists(selectedContentRoot))) throw new Error(`Canonical generated content root is missing: ${selectedContentRoot}.`);
  const sourceBound = await sourceBoundContent({
    sourceDirectory,
    repositoryRoot,
    sourceTexts,
    contentMap,
  });
  const actualManifestBytes = await readFile(selectedManifestPath);
  if (!actualManifestBytes.equals(sourceBound.manifestBytes)) {
    throw new Error('Canonical generated manifest is not source-derived.');
  }
  const actualGeneratedFiles = await artifactFiles(selectedContentRoot, (name) => /\.mdx?$/u.test(name));
  const actualGeneratedByPath = new Map(actualGeneratedFiles.map((file) => [
    path.relative(selectedContentRoot, file).split(path.sep).join('/'),
    file,
  ]));
  if (actualGeneratedByPath.size !== sourceBound.generatedByPath.size
    || [...sourceBound.generatedByPath.keys()].some((generated) => !actualGeneratedByPath.has(generated))) {
    throw new Error('Canonical generated content inventory is not source-derived.');
  }
  for (const [generated, expected] of sourceBound.generatedByPath) {
    const actual = await readFile(actualGeneratedByPath.get(generated));
    if (!actual.equals(expected.bytes)) {
      throw new Error(`Canonical generated content is not source-derived for ${expected.page.source}: ${generated}.`);
    }
  }
  return {
    pagesBySource,
    contentRoot: selectedContentRoot,
    sourceBoundPagesBySource: sourceBound.pagesBySource,
  };
}

function canonicalGeneratedBody(markdown, source) {
  const frontmatter = markdown.match(/^---\n[\s\S]*?\n---\n/u)?.[0];
  if (frontmatter === undefined) throw new Error(`Canonical generated content is missing frontmatter for ${source}.`);
  return markdown.slice(frontmatter.length);
}

function stripGeneratedReaderOutcomeMarker(markdown) {
  return markdown.replace(/\n+(?:<!-- blackops-reader-outcome:[\s\S]*?-->|\{\/\* blackops-reader-outcome:[\s\S]*?\*\/\})\s*$/u, '');
}

function canonicalGeneratedReaderBody(markdown, source) {
  return stripGeneratedReaderOutcomeMarker(canonicalGeneratedBody(markdown, source));
}

function readerFenceMarker(line) {
  return markdownFenceLine(line);
}

function readerFenceCloses(line, fence) {
  return markdownFenceCloses(line, fence);
}

function readerMermaidBlock(markdown) {
  const lines = markdown.replace(/\r\n?/gu, '\n').split('\n');
  const output = [];
  const mermaid = [];
  let fence = null;
  let blockStart = -1;
  let blockBody = [];
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (fence === null) {
      const marker = readerFenceMarker(line);
      if (marker === null) {
        output.push(line);
        continue;
      }
      fence = marker;
      blockStart = index;
      blockBody = [];
      continue;
    }
    if (readerFenceCloses(line, fence)) {
      if (fence.info.split(/\s+/u)[0] === 'mermaid') mermaid.push(blockBody.join('\n'));
      else output.push(...lines.slice(blockStart, index + 1));
      fence = null;
      blockStart = -1;
      blockBody = [];
      continue;
    }
    blockBody.push(line);
  }
  if (fence !== null) output.push(...lines.slice(blockStart));
  return { markdown: output.join('\n'), mermaid };
}

const readerCalloutTypes = new Set(['danger', 'info', 'note', 'success', 'tip', 'warning', 'caution', 'error', 'important', 'warn']);

function normalizeReaderCallouts(markdown) {
  const lines = markdown.replace(/\r\n?/gu, '\n').split('\n');
  const output = [];
  let fence = null;
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (fence !== null) {
      output.push(line);
      if (readerFenceCloses(line, fence)) fence = null;
      continue;
    }
    const marker = readerFenceMarker(line);
    if (marker !== null) {
      fence = marker;
      output.push(line);
      continue;
    }
    const opening = line.match(/^ {0,3}:::(?<type>[A-Za-z][\w-]*)(?:\[(?<title>[^\]]*)\])?\s*$/u);
    if (opening === null || !readerCalloutTypes.has(opening.groups.type.toLocaleLowerCase('en-US'))) {
      output.push(line);
      continue;
    }
    let closeIndex = -1;
    let bodyFence = null;
    for (let candidate = index + 1; candidate < lines.length; candidate += 1) {
      const candidateLine = lines[candidate];
      if (bodyFence !== null) {
        if (readerFenceCloses(candidateLine, bodyFence)) bodyFence = null;
        continue;
      }
      const candidateMarker = readerFenceMarker(candidateLine);
      if (candidateMarker !== null) {
        bodyFence = candidateMarker;
        continue;
      }
      if (/^ {0,3}:::\s*$/u.test(candidateLine)) {
        closeIndex = candidate;
        break;
      }
    }
    if (closeIndex < 0) {
      output.push(line);
      continue;
    }
    const title = opening.groups.title?.trim() ?? '';
    if (title !== '') output.push(`> **${title}**`, '>');
    output.push(...lines.slice(index + 1, closeIndex).map((bodyLine) => bodyLine === '' ? '>' : `> ${bodyLine}`));
    index = closeIndex;
  }
  return output.join('\n');
}

export async function renderReaderBody(markdown) {
  if (typeof markdown !== 'string') throw new Error('Reader body rendering requires Markdown text.');
  const body = stripGeneratedReaderOutcomeMarker(markdown.replace(/\r\n?/gu, '\n'));
  const key = createHash('sha256').update(body).digest('hex');
  let pending = readerBodyRenderCache.get(key);
  if (pending === undefined) {
    pending = (async () => {
      const mermaidBlocks = readerMermaidBlock(body);
      const normalized = normalizeReaderCallouts(mermaidBlocks.markdown);
      const rendered = await readerMarkdownProcessor.render(normalized);
      return { html: rendered.code, mermaid: mermaidBlocks.mermaid };
    })().catch((error) => {
      readerBodyRenderCache.delete(key);
      throw error;
    });
    readerBodyRenderCache.set(key, pending);
  }
  return pending;
}

function canonicalGeneratedFrontmatterTitle(markdown, source) {
  const frontmatter = markdown.match(/^---\n([\s\S]*?)\n---\n/u)?.[1];
  const titleLine = frontmatter?.split('\n').find((line) => line.startsWith('title:'));
  if (titleLine === undefined) throw new Error(`Canonical generated content is missing its title frontmatter for ${source}.`);
  let title;
  try {
    title = JSON.parse(titleLine.slice('title:'.length).trim());
  } catch {
    throw new Error(`Canonical generated title frontmatter is malformed for ${source}.`);
  }
  if (typeof title !== 'string' || title.trim() === '') {
    throw new Error(`Canonical generated title frontmatter is not a non-empty string for ${source}.`);
  }
  return title;
}

export async function validateArtifactPageRouteInventory({
  artifactDirectory = defaultDistRoot,
  expectedRoutes = new Set(),
  diagramManifestPath = null,
} = {}) {
  const expected = expectedRoutes instanceof Set ? expectedRoutes : new Set(expectedRoutes);
  const selectedDiagramManifestPath = diagramManifestPath
    ?? (path.resolve(artifactDirectory) === path.resolve(defaultDistRoot) ? defaultDiagramManifestPath : null);
  const registeredViewers = selectedDiagramManifestPath === null
    ? new Set()
    : registeredViewerRelativePaths(await loadDiagramManifest(selectedDiagramManifestPath));
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

  const htmlFiles = await artifactFiles(artifactDirectory, (name) => name.endsWith('.html'));
  const supplementalHtmlFiles = [];
  const routeHtmlFiles = [];
  for (const file of htmlFiles) {
    const relative = path.relative(artifactDirectory, file).split(path.sep).join('/');
    if (registeredViewers.has(relative)) supplementalHtmlFiles.push(file);
    else if (relative === '404.html' || relative === 'index.html' || /\/index\.html$/u.test(relative)) routeHtmlFiles.push(file);
    else throw new Error(`HTML artifact contains unknown flat viewer path: ${relative}.`);
  }
  for (const file of supplementalHtmlFiles) {
    const relative = path.relative(artifactDirectory, file).split(path.sep).join('/');
    if (!/^diagrams\/[a-z0-9-]+\.html$/u.test(relative)) {
      throw new Error(`Registered diagram viewer has an unsafe artifact path: ${relative}.`);
    }
  }
  const htmlRoutes = new Map();
  for (const file of routeHtmlFiles) {
    const relative = path.relative(artifactDirectory, file).split(path.sep).join('/');
    const route = relative === '404.html' ? '/404' : routeFromArtifactFile(file, artifactDirectory);
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

function decodeArtifactEntities(text) {
  return text.replace(/&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos|nbsp);/giu, (entity, token) => {
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
  const listMarker = content.match(/^ {0,3}(?:[-+*]|\d{1,9}[.)])(?=$|[ \t])/u);
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

  const { physicalLines, states: sourceFenceStates } = sourceFenceLineStates(text);
  const lines = physicalLines.map(({ line }) => line);
  const output = [];
  let fence = null;
  let listContext = false;
  let listContinuationIndent = null;
  let lazyContainer = null;
  let previousBlock = 'blank';
  let chunkLines = [];
  let chunkMode = null;
  let nonExecutableFenceChunk = false;
  let chunkContinuationDumps = false;
  const flushChunk = () => {
    if (chunkLines.length === 0) return;
    const chunk = chunkLines.join('\n');
    const maskInlineCode = (body, { prefix, suffix } = {}) => {
      if (nonExecutableFenceChunk) return true;
      const continuationDump = chunkContinuationDumps && inlineCodeHasContinuationEnvironmentDump(body, { prefix, suffix });
      const includeEnvironmentCommands = chunkMode
        || inlineCodeHasProtectedEnvironmentCommand(body);
      return continuationDump || inlineCodeRequiresShellInspection(body, { includeEnvironmentCommands }) ? 'segment' : true;
    };
    output.push(replaceInlineBackticks(chunk, maskInlineCode));
    chunkLines = [];
    chunkMode = null;
    nonExecutableFenceChunk = false;
    chunkContinuationDumps = false;
  };
  const appendExecutableLine = (line) => {
    flushChunk();
    output.push(line);
  };

  for (const [lineIndex, line] of lines.entries()) {
    const sourceFenceLine = sourceFenceStates.get(physicalLines[lineIndex]?.start);
    if (sourceFenceLine !== undefined) {
      if (sourceFenceLine.first || sourceFenceLine.last) {
        flushChunk();
        output.push(line);
      } else if (sourceFenceLine.executable) {
        appendExecutableLine(line);
      } else {
        if (!nonExecutableFenceChunk) flushChunk();
        chunkMode = false;
        nonExecutableFenceChunk = true;
        chunkContinuationDumps = false;
        chunkLines.push(line);
      }
      fence = sourceFenceLine.end ? null : {
        marker: sourceFenceLine.marker,
        runLength: sourceFenceLine.runLength,
        blockquoteDepth: 0,
        executable: sourceFenceLine.executable,
      };
      previousBlock = sourceFenceLine.end ? 'fence' : 'fence-content';
      continue;
    }
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
        if (!nonExecutableFenceChunk) flushChunk();
        chunkMode = false;
        nonExecutableFenceChunk = true;
        chunkContinuationDumps = false;
        chunkLines.push(line);
      }
      previousBlock = 'fence-content';
      continue;
    }

    const hasIndent = /^(?: {4}|\t)/u.test(container.content);
    const leadingWhitespace = container.content.match(/^[ \t]*/u)?.[0] ?? '';
    const indentWidth = sourceReadWhitespace(leadingWhitespace, 0, 0).width;
    const listCodeBlock = listContext && previousBlock === 'blank' && listContinuationIndent !== null && indentWidth >= listContinuationIndent + 4;
    const listIndentedContinuation = hasIndent && listContext && listContinuationIndent !== null && indentWidth >= listContinuationIndent;
    const paragraphContinuation = container.blockquoteDepth === 0 && ['paragraph', 'paragraph-continuation'].includes(previousBlock);
    const blockquoteContinuation = container.blockquoteDepth > 0 && ['blockquote', 'blockquote-continuation'].includes(previousBlock);
    const content = container.content.trim();
    const startsBlock = content === ''
      || /^#{1,6}(?:[ \t]+|$)/u.test(content)
      || /^(?:`{3,}|~{3,})/u.test(content)
      || !sourceParagraphContent(content)
      || /^(?:[-+*]|\d{1,9}[.)])(?:[ \t]+|$)/u.test(content);
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
    } else if (!sourceParagraphContent(content)) {
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
  return htmlTagEnd(text, start);
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

const htmlVoidElements = new Set([
  'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta',
  'param', 'source', 'track', 'wbr',
]);

function assertBalancedHtmlReaderMarkup(html, location) {
  let cursor = 0;
  const stack = [];
  while (cursor < html.length) {
    if (html.startsWith('<!--', cursor)) {
      const commentEnd = html.indexOf('-->', cursor + 4);
      if (commentEnd < 0) throw new Error(`Unterminated HTML comment in ${location}.`);
      cursor = commentEnd + 3;
      continue;
    }
    if (/^<!\[CDATA\[/iu.test(html.slice(cursor))) {
      const cdataEnd = html.indexOf(']]>', cursor + 9);
      if (cdataEnd < 0) throw new Error(`Unterminated HTML CDATA section in ${location}.`);
      cursor = cdataEnd + 3;
      continue;
    }
    if (html[cursor] !== '<') {
      cursor += 1;
      continue;
    }
    if (/^<!/u.test(html.slice(cursor)) || /^<\?/u.test(html.slice(cursor))) {
      const declarationEnd = html.indexOf('>', cursor + 2);
      if (declarationEnd < 0) throw new Error(`Unterminated HTML declaration in ${location}.`);
      cursor = declarationEnd + 1;
      continue;
    }
    const tag = htmlTagNameAt(html, cursor);
    if (tag === null) {
      cursor += 1;
      continue;
    }
    const tagEnd = htmlStartTagEnd(html, tag.nameEnd);
    if (tagEnd < 0) throw new Error(`Unterminated HTML start tag in ${location}.`);
    if (!tag.closing && (tag.name === 'script' || tag.name === 'style')) {
      const close = htmlClosingTag(html, tag.name, tagEnd + 1);
      if (close === null) throw new Error(`Unterminated HTML ${tag.name} element in ${location}.`);
      cursor = close.index + close[0].length;
      continue;
    }
    const selfClosing = /\/\s*>$/u.test(html.slice(cursor, tagEnd + 1));
    if (tag.closing) {
      if (htmlVoidElements.has(tag.name) || stack.at(-1) !== tag.name) {
        throw new Error(`HTML closing tag does not match its opening tag in ${location}: ${tag.name}.`);
      }
      stack.pop();
    } else if (!selfClosing && !htmlVoidElements.has(tag.name)) {
      stack.push(tag.name);
    }
    cursor = tagEnd + 1;
  }
  if (stack.length > 0) throw new Error(`HTML reader surface has an unclosed <${stack.at(-1)}> in ${location}.`);
}

function inlineStyleProperty(element, property) {
  const normalizedProperty = property.trim().toLocaleLowerCase('en-US');
  const value = element.style.getPropertyValue(normalizedProperty);
  return value.trim() === '' ? null : value.trim().toLocaleLowerCase('en-US');
}

function firstDirectDetailsSummary(details) {
  return [...details.children].find((child) => child.localName?.toLocaleLowerCase('en-US') === 'summary') ?? null;
}

function isDirectDetailsSummary(details, element) {
  const summary = element.closest('summary');
  return summary?.parentElement === details && firstDirectDetailsSummary(details) === summary;
}

function hiddenReaderAncestor(element) {
  const ancestors = [];
  for (let current = element; current instanceof element.ownerDocument.defaultView.Element; current = current.parentElement) {
    ancestors.unshift(current);
  }

  let inheritedVisibilityHidden = false;
  for (const current of ancestors) {
    const name = current.localName?.toLocaleLowerCase('en-US');
    if (['head', 'script', 'style', 'template', 'title'].includes(name)) return true;

    const display = inlineStyleProperty(current, 'display');
    if (display?.split(/\s+/u)[0] === 'none') return true;
    if (inlineStyleProperty(current, 'content-visibility') === 'hidden') return true;

    // `hidden` and a closed dialog are native display rules. An explicit inline
    // display value is the product's documented escape hatch for either rule.
    if (current.hasAttribute('hidden') && display === null) return true;
    if (name === 'dialog' && !current.hasAttribute('open') && display === null) return true;
    if (name === 'details' && !current.hasAttribute('open') && !isDirectDetailsSummary(current, element)) return true;

    const visibility = inlineStyleProperty(current, 'visibility');
    if (visibility === 'hidden' || visibility === 'collapse') inheritedVisibilityHidden = true;
    else if (visibility === 'visible' || visibility === 'initial') inheritedVisibilityHidden = false;
  }
  return inheritedVisibilityHidden;
}

function readerWhitespace(text) {
  return (text ?? '').replace(/\s+/gu, ' ').trim();
}

function readerCodeText(text) {
  return (text ?? '').replace(/\r\n?/gu, '\n');
}

function readerPreText(text) {
  return readerCodeText(text)
    .replace(/[ \t]*\n+$/u, '')
    .replace(/[ \t]+$/u, '');
}

function readerExcludedElement(element, excluded) {
  for (let current = element; current !== null; current = current.parentElement) {
    if (excluded.has(current)) return true;
  }
  return false;
}

function readerVisibleElement(element, excluded) {
  return !readerExcludedElement(element, excluded) && !hiddenReaderAncestor(element);
}

function readerVisibleText(root, excluded) {
  const blockElements = new Set([
    'address', 'article', 'aside', 'blockquote', 'br', 'caption', 'dd', 'details', 'dialog', 'div',
    'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5',
    'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody',
    'td', 'tfoot', 'th', 'thead', 'tr', 'ul', 'blume-mermaid',
  ]);
  const parts = [];
  const visit = (node) => {
    if (node.nodeType === 3) {
      if (node.parentElement !== null && readerVisibleElement(node.parentElement, excluded)) {
        parts.push(node.nodeValue ?? '');
      }
      return;
    }
    if (node.nodeType !== 1) return;
    const element = node;
    const elementVisible = readerVisibleElement(element, excluded);
    if (element.localName === 'br') {
      if (elementVisible) parts.push('\n');
      return;
    }
    if (elementVisible && blockElements.has(element.localName)) parts.push('\n');
    for (const child of element.childNodes) visit(child);
    if (elementVisible && blockElements.has(element.localName)) parts.push('\n');
  };
  for (const child of root.childNodes) visit(child);
  return readerWhitespace(parts.join(''));
}

function readerSemanticInventory(root, excluded = new Set()) {
  const visible = (element) => readerVisibleElement(element, excluded);
  const headings = [...root.querySelectorAll('h1, h2, h3, h4, h5, h6')]
    .filter(visible)
    .map((element) => ({ level: Number(element.localName.slice(1)), text: readerWhitespace(element.textContent) }));
  const links = [...root.querySelectorAll('a[href]')]
    .filter(visible)
    .filter((element) => !(element.classList.contains('blume-heading-anchor')
      || (element.getAttribute('href')?.startsWith('#') && element.closest('h1, h2, h3, h4, h5, h6') !== null)))
    .map((element) => ({ href: element.getAttribute('href'), text: readerWhitespace(element.textContent) }));
  const pre = [...root.querySelectorAll('pre')]
    .filter(visible)
    .map((element) => readerPreText(element.textContent));
  const inlineCode = [...root.querySelectorAll('code')]
    .filter(visible)
    .filter((element) => element.closest('pre') === null && element.parentElement?.closest('code') === null)
    .map((element) => readerCodeText(element.textContent));
  const images = [...root.querySelectorAll('img')]
    .filter(visible)
    .map((element) => element.getAttribute('alt') ?? '');
  const mermaid = [...root.querySelectorAll('blume-mermaid')]
    .filter(visible)
    .map((element) => element.getAttribute('data-source') ?? '');
  return {
    text: readerVisibleText(root, excluded),
    headings,
    links,
    pre,
    inlineCode,
    images,
    mermaid,
  };
}

function readerSemanticRoot(html, location) {
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', () => {});
  const dom = new JSDOM(`<div data-reader-contract-root>${html}</div>`, { virtualConsole });
  const root = dom.window.document.querySelector('[data-reader-contract-root]');
  if (root === null) {
    dom.window.close();
    throw new Error(`HTML semantic reader root is missing in ${location}.`);
  }
  return { dom, root };
}

async function assertHtmlReaderSemanticContract({ html, expectedRaw, title, outcome, route, source }) {
  const location = `HTML ${route}`;
  assertBalancedHtmlReaderMarkup(html, location);
  const { dom, root } = readerSemanticRoot(html, location);
  try {
    const articles = [...root.querySelectorAll('article')];
    if (articles.length !== 1 || !articles[0].classList.contains('prose')) {
      throw new Error(`HTML ${route} must contain exactly one article.prose.`);
    }
    const article = articles[0];
    const visibleChildren = [...article.children].filter((element) => !hiddenReaderAncestor(element));
    const headings = [...article.querySelectorAll('h1')].filter((element) => !hiddenReaderAncestor(element));
    if (headings.length !== 1 || readerWhitespace(headings[0].textContent) !== title) {
      throw new Error(`HTML ${route} H1 drifted from the canonical manifest title.`);
    }
    const headingIndex = visibleChildren.indexOf(headings[0]);
    const description = headingIndex >= 0 ? visibleChildren[headingIndex + 1] : null;
    if (description?.localName !== 'p' || readerWhitespace(description.textContent) !== outcome) {
      throw new Error(`HTML ${route} lead description drifted from the Content Map reader outcome.`);
    }
    const excluded = new Set([headings[0], description]);
    const actual = readerSemanticInventory(article, excluded);
    const expectedRendered = await renderReaderBody(canonicalGeneratedReaderBody(expectedRaw, source));
    const expectedRootResult = readerSemanticRoot(expectedRendered.html, `${location} expected body`);
    try {
      const expected = readerSemanticInventory(expectedRootResult.root);
      expected.mermaid = expectedRendered.mermaid;
      for (const field of ['text', 'headings', 'links', 'pre', 'inlineCode', 'images', 'mermaid']) {
        if (JSON.stringify(actual[field]) !== JSON.stringify(expected[field])) {
          throw new Error(`HTML semantic body drifted for ${route} (${field}).`);
        }
      }
    } finally {
      expectedRootResult.dom.window.close();
    }
  } finally {
    dom.window.close();
  }
}

export function extractVisibleInlineCodeInventory(html, location = 'HTML artifact') {
  if (typeof html !== 'string') throw new Error(`HTML reader surface requires text in ${location}.`);
  assertBalancedHtmlReaderMarkup(html, location);
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', () => {});
  const dom = new JSDOM(html, { virtualConsole });
  try {
    const inventories = [];
    for (const element of dom.window.document.querySelectorAll('code')) {
      if (element.closest('pre') || element.parentElement?.closest('code') || hiddenReaderAncestor(element)) continue;
      const codeText = element.textContent ?? '';
      assertNoDanglingArtifactPlaceholderFragments(codeText, `${location} visible inline code`);
      inventories.push(placeholderInventory(codeText));
    }
    return mergePlaceholderInventories(inventories);
  } finally {
    dom.window.close();
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
  const authority = await loadReleaseAuthority(path.join(repositoryRoot, 'develop/spec/release-authority.json'));
  const stableReferenceExclusions = stableReferenceExclusionsFor(authority);
  const publicTypes = [];
  const publicAttributes = [];
  const publicCommands = new Set();
  const configurationKeys = new Set();
  for (const file of await phpFiles(path.join(repositoryRoot, 'src'))) {
    const relativePath = path.relative(repositoryRoot, file).split(path.sep).join('/');
    if (stableReferenceExclusions.has(relativePath)) continue;
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
      methods: extractPublicMethods(source).filter((method) => !(
        relativePath === stableReferenceMethodExclusion.path
        && fqcn === stableReferenceMethodExclusion.fqcn
        && method.name === stableReferenceMethodExclusion.name
      )),
    });
    if (/\\(?:Attribute|Validation\\Attribute)$/.test(namespace) && declaration[2] !== 'SensitiveMode') publicAttributes.push(fqcn);
  }
  for (const file of await phpFiles(path.join(repositoryRoot, 'src'))) {
    const relativePath = path.relative(repositoryRoot, file).split(path.sep).join('/');
    if (stableReferenceExclusions.has(relativePath)) continue;
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

function assertSearchRecordNonContentPlaceholderFree(record, location) {
  const visit = (value, field) => {
    if (typeof value === 'string') {
      const dangling = danglingPlaceholderFragments(value);
      if (dangling.length > 0) throw new Error(`Search ${location} contains dangling ${dangling[0]} fragment in ${field}.`);
      const inventory = placeholderInventory(value);
      if (inventory.total > 0) throw new Error(`Search ${location} contains a placeholder fragment outside record.content in ${field}.`);
      return;
    }
    if (Array.isArray(value)) {
      value.forEach((entry, index) => visit(entry, `${field}[${index}]`));
      return;
    }
    if (value !== null && typeof value === 'object') {
      Object.entries(value).forEach(([key, entry]) => visit(entry, `${field}.${key}`));
    }
  };
  Object.entries(record).forEach(([key, value]) => {
    if (key !== 'content') visit(value, key);
  });
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

async function directoryExists(candidate) {
  try {
    return (await stat(candidate)).isDirectory();
  } catch {
    return false;
  }
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

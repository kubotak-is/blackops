import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { repositoryRoot, sourceRoot } from './website-paths.mjs';

const staleVersion = /1\.1\.0/;
const releaseLanes = new Set(['framework-package', 'skeleton', 'repository-example', 'documentation-only']);
const semverPattern = /^(\d+)\.(\d+)\.(\d+)$/;

export function normalizeSentence(value) {
  return value
    .replace(/<[^>]*>/g, '')
    .replace(/&(?:amp|lt|gt|quot|#39);/g, (entity) => ({ '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'" })[entity] ?? entity)
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/[`*_]/g, '')
    .replace(/\|/g, ' | ')
    .replace(/\s+/gu, ' ')
    .replace(/^Stable 1\.2\.0と過去StableのCapability\s+/u, '')
    .trim();
}

export async function loadReleaseAuthority(authorityPath = path.join(repositoryRoot, 'develop/spec/release-authority.json')) {
  const authority = JSON.parse(await readFile(authorityPath, 'utf8'));
  validateAuthority(authority);
  return authority;
}

export function validateAuthority(authority) {
  if (authority.schemaVersion !== 1) throw new Error('Release Authority schemaVersion must be 1.');
  const stable = authority.currentStable;
  if (!stable || !semverPattern.test(stable.version) || stable.releaseState !== 'experimental-stable') {
    throw new Error('Release Authority must identify a semver Experimental Stable release.');
  }
  for (const packageName of ['framework', 'skeleton']) {
    const release = stable[packageName];
    if (!release || release.tag !== stable.version || !/^[0-9a-f]{40}$/.test(release.directRef) || !/^[0-9a-f]{40}$/.test(release.peeledSource)) {
      throw new Error(`Release Authority has an invalid ${packageName} immutable reference.`);
    }
  }
  const ids = new Set();
  for (const capability of authority.capabilities ?? []) {
    if (!capability.id || ids.has(capability.id) || !semverPattern.test(capability.since ?? '') || compareSemver(capability.since, stable.version) > 0 || !Array.isArray(capability.surfaces) || capability.surfaces.length === 0 || capability.surfaces.some((surface) => !releaseLanes.has(surface))) {
      throw new Error(`Release Authority has an invalid capability: ${capability.id ?? '<missing>'}.`);
    }
    ids.add(capability.id);
  }
  if (!authority.pageCapabilities || typeof authority.pageCapabilities !== 'object' || Array.isArray(authority.pageCapabilities) || Object.keys(authority.pageCapabilities).length === 0) {
    throw new Error('Release Authority pageCapabilities must be a non-empty object.');
  }
  for (const [pagePath, page] of Object.entries(authority.pageCapabilities)) {
    if (!/^docs\/guide\/(?:[^/]+\/)*[^/]+\.md$/.test(pagePath) || !page || typeof page !== 'object' || Array.isArray(page) || !releaseLanes.has(page.lane) || !Array.isArray(page.capabilities) || page.capabilities.length === 0) {
      throw new Error(`Release Authority has an invalid page capability mapping: ${pagePath}.`);
    }
    for (const capabilityId of page.capabilities) {
      const capability = authority.capabilities.find((candidate) => candidate.id === capabilityId);
      if (!capability || !capability.surfaces.includes(page.lane)) {
        throw new Error(`Release Authority page lane mismatch: ${pagePath} -> ${capabilityId} (${page.lane}).`);
      }
    }
  }
  const seenHistory = new Set();
  for (const entry of authority.historicalReferences ?? []) {
    for (const key of ['path', 'heading', 'normalizedSentence', 'category', 'reason']) {
      if (typeof entry[key] !== 'string' || entry[key] === '') throw new Error(`Historical allowlist entry is missing ${key}.`);
    }
    const key = `${entry.path}|${entry.heading}|${entry.normalizedSentence}`;
    if (seenHistory.has(key)) throw new Error(`Historical allowlist entry is duplicated: ${key}`);
    seenHistory.add(key);
  }
  if (!authority.roadmap || !semverPattern.test(authority.roadmap.version ?? '') || compareSemver(authority.roadmap.version, stable.version) <= 0 || authority.roadmap.state !== 'unreleased' || typeof authority.roadmap.label !== 'string' || authority.roadmap.label === '') {
    throw new Error('Release Authority roadmap must be a later unreleased semver.');
  }
}

export async function assertSourceClaims({ authorityPath, sourceDirectory = sourceRoot, contentMapPath = path.join(repositoryRoot, 'docs/website/content-map.mjs') } = {}) {
  const authority = await loadReleaseAuthority(authorityPath);
  const occurrences = [];
  const sourceTexts = [];
  const sourceByPath = new Map();
  for (const file of await markdownFiles(sourceDirectory)) {
    const content = await readFile(file, 'utf8');
    const relativePath = path.relative(repositoryRoot, file);
    sourceTexts.push(content);
    sourceByPath.set(relativePath, content);
    assertNoStaleCurrentPhrase(content, relativePath, authority);
    occurrences.push(...findOccurrences(content, relativePath, { currentVersion: authority.currentStable.version }));
  }
  assertMappedPageClaims(sourceByPath, authority);
  const contentMap = await readFile(contentMapPath, 'utf8');
  sourceTexts.push(contentMap);
  assertNoStaleCurrentPhrase(contentMap, path.relative(repositoryRoot, contentMapPath), authority);
  occurrences.push(...findOccurrences(contentMap, path.relative(repositoryRoot, contentMapPath), { currentVersion: authority.currentStable.version }));
  assertOccurrences(occurrences, authority, { source: true });
  const allText = sourceTexts.join('\n');
  assertCurrentAuthorityClaims(allText, authority);
  return occurrences;
}

export async function assertArtifactClaims({ authorityPath, artifactDirectory } = {}) {
  const authority = await loadReleaseAuthority(authorityPath);
  const occurrences = [];
  for (const file of await textFiles(artifactDirectory)) {
    const content = await readFile(file, 'utf8');
    const relativePath = path.relative(repositoryRoot, file);
    for (const segment of artifactSegments(content, relativePath, authority)) {
      assertNoStaleCurrentPhrase(segment, relativePath, authority);
      occurrences.push(...findOccurrences(segment, relativePath, { currentVersion: authority.currentStable.version }));
    }
  }
  assertOccurrences(occurrences, authority, { source: false });
  return occurrences;
}

export function assertOccurrences(occurrences, authority, { source }) {
  const history = authority.historicalReferences ?? [];
  const used = new Set();
  for (const occurrence of occurrences) {
    if (candidatePattern(authority.currentStable.version).test(occurrence.sentence) || currentPhrasePattern(authority.currentStable.version).test(occurrence.sentence)) {
      throw new Error(`Stale current release claim found in ${occurrence.path}: ${occurrence.sentence}`);
    }
    if (!staleVersion.test(occurrence.sentence)) continue;
    const normalized = normalizeSentence(occurrence.sentence);
    const match = history.find((entry) => {
      if (source && entry.path !== occurrence.path) return false;
      if (source && entry.heading !== occurrence.heading) return false;
      const expected = normalizeSentence(entry.normalizedSentence);
      const compactExpected = expected.replace(/\|/g, '').trim();
      const compactNormalized = normalized.replace(/\|/g, '').trim();
      return expected === normalized || (!source && compactExpected === compactNormalized);
    });
    if (!match) throw new Error(`Unexpected Stable 1.1.0 claim in ${occurrence.path}: ${occurrence.sentence}`);
    used.add(`${match.path}|${match.heading}|${match.normalizedSentence}`);
  }
  if (source) {
    const unused = history.filter((entry) => !used.has(`${entry.path}|${entry.heading}|${entry.normalizedSentence}`));
    if (unused.length > 0) throw new Error(`Historical release claim allowlist contains unused entries: ${unused.map((entry) => `${entry.path}|${entry.heading}`).join(', ')}`);
  }
}

export function assertCurrentAuthorityClaims(text, authority) {
  const version = authority.currentStable.version;
  if (!text.includes(`Latest Experimental Stable ${version}`) || !text.includes(`composer create-project blackops/skeleton my-app ${version}`)) {
    throw new Error(`Source current Stable claims do not match Release Authority ${version}.`);
  }
}

export function assertNoStaleCurrentPhrase(text, relativePath, authority = null) {
  const version = authority?.currentStable.version ?? '1.2.0';
  for (const line of text.split(/\r?\n/)) {
    if (candidatePattern(version).test(line)) throw new Error(`Stale candidate release claim found in ${relativePath}: ${line.trim()}`);
    if (currentPhrasePattern(version).test(line)) throw new Error(`Stale current main/candidate claim found in ${relativePath}: ${line.trim()}`);
    if (authority && roadmapStablePhrase(authority).test(line)) throw new Error(`Roadmap release was presented as Stable in ${relativePath}: ${line.trim()}`);
  }
}

function assertMappedPageClaims(sourceByPath, authority) {
  for (const [pagePath, page] of Object.entries(authority.pageCapabilities)) {
    const content = sourceByPath.get(pagePath);
    if (!content) throw new Error(`Release Authority page mapping has no source page: ${pagePath}`);
    if (!content.includes(authority.currentStable.version)) {
      throw new Error(`Mapped page ${pagePath} does not state current release ${authority.currentStable.version}.`);
    }
    const body = content.replace(/^\s*#{1,6}\s+.*$/gmu, '');
    if (/Stable(?:にはない|未提供)|main-only|main専用|Repository\s+`?main[^\n。]*(?:Preview|preview)/i.test(body)) {
      throw new Error(`Mapped page ${pagePath} contains an authority-disallowed main-only or Stable-absent claim.`);
    }
    if (!page.capabilities.every((capabilityId) => authority.capabilities.find((capability) => capability.id === capabilityId)?.surfaces.includes(page.lane))) {
      throw new Error(`Mapped page ${pagePath} does not satisfy its authority lane ${page.lane}.`);
    }
  }
}

function artifactSegments(content, relativePath, authority) {
  if (relativePath.endsWith('.json')) {
    try {
      const values = [];
      const collect = (value) => {
        if (typeof value === 'string') values.push(value);
        else if (Array.isArray(value)) value.forEach(collect);
        else if (value !== null && typeof value === 'object') Object.values(value).forEach(collect);
      };
      collect(JSON.parse(content));
      return values.flatMap((value) => splitSearchText(value, authority));
    } catch {
      return content.split(/\r?\n/);
    }
  }
  const tableRows = [...content.matchAll(/<tr\b[^>]*>([\s\S]*?)<\/tr>/gi)].map(([, row]) => row
    .replace(/<t[hd]\b[^>]*>/gi, ' | ')
    .replace(/<\/t[hd]>/gi, ' | ')
    .replace(/<[^>]+>/g, '')
    .replace(/\s+/gu, ' ')
    .replace(/\|\s*\|/g, '|')
    .trim());
  const withoutTables = content.replace(/<table\b[^>]*>[\s\S]*?<\/table>/gi, '');
  const visible = withoutTables
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<t[hd]\b[^>]*>/gi, ' | ')
    .replace(/<\/t[hd]>/gi, ' | ')
    .replace(/<\/(?:tr|p|h[1-6]|li|blockquote|pre|section|article|div|ul|ol|table)>/gi, '\n')
    .replace(/<[^>]+>/g, '');
  const attributes = [];
  for (const [, tag] of content.matchAll(/<([a-z][^>]*)>/gi)) {
    for (const [, value] of tag.matchAll(/\b(?:content|property|name|itemprop|value|alt|title)\s*=\s*["']([^"']*)["']/gi)) attributes.push(value);
  }
  const jsonLd = [...content.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)].map(([, value]) => value);
  return [...visible.split(/\r?\n|(?<=。)/u), ...tableRows, ...attributes, ...jsonLd];
}

export function findOccurrences(content, relativePath, { currentVersion = '1.2.0' } = {}) {
  const results = [];
  const lines = content.replace(/\r\n/g, '\n').split('\n');
  let offset = 0;
  let heading = '';
  let historicalAnchor = false;
  for (const line of lines) {
    if (/^<a\s+id="stableとmain"><\/a>$/u.test(line.trim())) historicalAnchor = true;
    if (/^#{1,6}\s+/.test(line)) {
      heading = historicalAnchor && /^##\s+Stable 1\.2\.0と過去StableのCapability$/u.test(line.trim())
        ? '## Stableとmain'
        : line.trim();
      historicalAnchor = false;
    }
    for (const match of line.matchAll(new RegExp(`1\\.1\\.0|${escapeRegExp(currentVersion)}\\s+candidate|unpublished\\s+${escapeRegExp(currentVersion)}|未公開(?:の)?\\s*(?:\\x60)?${escapeRegExp(currentVersion)}`, 'gi'))) {
      results.push({ path: relativePath, heading, sentence: sentenceAt(line, match.index ?? 0) });
    }
    offset += line.length + 1;
  }
  return results;
}

function sentenceAt(line, index) {
  const start = Math.max(line.lastIndexOf('。', index - 1), line.lastIndexOf('!', index - 1), line.lastIndexOf('?', index - 1)) + 1;
  if (!/[。!?]/.test(line)) return line.slice(start).trim();
  const endCandidates = ['。', '!', '?'].map((mark) => line.indexOf(mark, index)).filter((value) => value >= 0);
  const end = endCandidates.length > 0 ? Math.min(...endCandidates) + 1 : line.length;
  return line.slice(start, end).trim();
}

function splitSearchText(value, authority) {
  let text = value;
  const headings = [...new Set((authority.historicalReferences ?? []).map((entry) => entry.heading.replace(/^#+\s*/u, '')))].sort((left, right) => right.length - left.length);
  for (const heading of headings) text = text.replaceAll(heading, `\n${heading}\n`);
  text = text.replace(/\|\s*\|\s*---/gu, '\n| ---');
  return text.split(/\r?\n|(?<=。)/u);
}

function roadmapStablePhrase(authority) {
  const version = escapeRegExp(authority.roadmap.version);
  return new RegExp(`(?:Latest\\s+Experimental\\s+Stable|Stable)\\s+${version}[^\\n。]*(?:公開済み|current|Experimental\\s+Stable|です)`, 'i');
}

function candidatePattern(version) {
  const escaped = escapeRegExp(version);
  return new RegExp(`${escaped}\\s+candidate|unpublished\\s+${escaped}|未公開(?:の)?\\s*(?:\\x60)?${escaped}`, 'i');
}

function currentPhrasePattern(version) {
  const escaped = escapeRegExp(version);
  return new RegExp(`(?:Stable|Latest\\s+Experimental\\s+Stable)\\s+${escaped}[^\\n。]*?(?:main-only|main専用|Repository\\s+(?:\\x60)?main|candidate)[^\\n。]*?(?:Experimental|Preview|未公開|Surface|です)`, 'i');
}

function compareSemver(left, right) {
  const a = left.match(semverPattern).slice(1).map(Number);
  const b = right.match(semverPattern).slice(1).map(Number);
  return a[0] - b[0] || a[1] - b[1] || a[2] - b[2];
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function markdownFiles(root) {
  return visitFiles(root, (name) => name.endsWith('.md'));
}

async function textFiles(root) {
  return visitFiles(root, (name) => !name.endsWith('.map'));
}

async function visitFiles(root, predicate) {
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

if (path.resolve(process.argv[1] ?? '') === fileURLToPath(import.meta.url)) {
  const mode = process.argv[2] ?? 'source';
  if (mode === 'source') await assertSourceClaims();
  else if (mode === 'artifact') await assertArtifactClaims({ artifactDirectory: path.join(repositoryRoot, 'docs/website/dist') });
  else throw new Error(`Unknown release claim checker mode: ${mode}`);
  console.log(`Release claim ${mode} guard passed.`);
}

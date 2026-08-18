import { access, readFile, readdir } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { distRoot, repositoryRoot } from './website-paths.mjs';
import { assertArtifactClaims } from './release-claim-checker.mjs';
import {
  assertLinkedStylesheetContract,
  assertOverflowFocusContract,
  assertSearchFocusBoundaryArtifact,
  assertSearchFocusBoundarySourceContract,
  extractStylesheetHrefs,
} from './artifact-stylesheet-contract.mjs';

await assertArtifactClaims({ artifactDirectory: distRoot });

const forbidden = [
  [/docs\/internal/i, 'docs/internal'],
  [/develop\//i, 'develop/'],
  [/BlackOps\\Internal/, 'BlackOps\\Internal namespace'],
  [/P[0-9]+-[0-9]+/, 'orchestration identifier'],
  [/Acceptance Evidence/i, 'acceptance evidence'],
  [new RegExp(escapePattern(repositoryRoot)), 'repository absolute path'],
  [/cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com/i, 'external diagram CDN'],
  [/consumer-sensitive-value|consumer-report-value|inline-secret-token|deferred-secret-token/, 'test secret literal'],
];

let diagramCount = 0;
let mermaidCodeBlockCount = 0;
let accessibleTitleCount = 0;
let accessibleDescriptionCount = 0;
let landingStylesheetCount = 0;
let mermaidLegibilityStylesheetCount = 0;
let mermaidRuntimeCount = 0;
const stylesheetCache = new Map();
const layoutSource = await readFile(path.join(repositoryRoot, 'docs/website/components/NoEditLayout.astro'), 'utf8');
const landingSource = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');
const searchFocusBoundarySource = await readFile(path.join(repositoryRoot, 'docs/website/components/SearchFocusBoundary.astro'), 'utf8');
assertSearchFocusBoundarySourceContract({ component: searchFocusBoundarySource, landing: landingSource, detail: layoutSource });
const generatedConfig = await readFile(path.join(repositoryRoot, 'docs/website/.blume/astro.config.mjs'), 'utf8');
if (/fontProviders\.google|fonts\.googleapis\.com|fonts\.gstatic\.com/i.test(generatedConfig)) {
  throw new Error('Generated Blume config must not contain a Google or remote font provider.');
}
const generatedProviders = [...generatedConfig.matchAll(/fontProviders\.([A-Za-z0-9_]+)\s*\(/g)].map(([, provider]) => provider);
if (generatedProviders.length !== 2 || generatedProviders.some((provider) => provider !== 'local')) {
  throw new Error(`Generated Blume config must use only two local font providers; found ${generatedProviders.join(', ')}.`);
}
for (const localPath of ['fontProviders.local()', 'UbuntuSans.ttf', 'UbuntuMono.ttf']) {
  if (!generatedConfig.includes(localPath)) {
    throw new Error(`Generated Blume config is missing the local font contract: ${localPath}`);
  }
}
const localFontReferences = new Set();

for (const file of await files(distRoot)) {
  const content = (await readFile(file)).toString('utf8');
  if (/fonts\.googleapis\.com|fonts\.gstatic\.com/i.test(content)) {
    throw new Error(`Static artifact must not depend on a remote font provider: ${path.relative(distRoot, file)}`);
  }
  if (/@font-face\s*\{[^}]*?src\s*:\s*url\(\s*["']?(?:https?:|\/\/)/i.test(content)) {
    throw new Error(`Static artifact must not contain a remote @font-face source: ${path.relative(distRoot, file)}`);
  }
  for (const match of content.matchAll(/\/(?:_astro\/)?fonts\/[^"')\s]+/g)) localFontReferences.add(match[0]);
  for (const [pattern, label] of forbidden) {
    if (pattern.test(content)) {
      throw new Error(`Static artifact contains forbidden ${label}: ${path.relative(distRoot, file)}`);
    }
  }
  if (file.endsWith('.map')) {
    throw new Error(`Static artifact must not contain source maps: ${path.relative(distRoot, file)}`);
  }
  if (file.endsWith('.html')) {
    const artifactPath = path.relative(distRoot, file);
    if (artifactPath !== '404.html') {
      const stylesheetHrefs = extractStylesheetHrefs(content);
      if (stylesheetHrefs.length > 0) {
        await assertLinkedStylesheetContract(content, stylesheetHrefs, artifactPath, { readStylesheet: readCachedStylesheet });
      }
      assertOverflowFocusContract(content, artifactPath, {
        runtimeSource: layoutSource,
        requireLandingSurfaces: artifactPath === 'index.html',
      });
    }
    diagramCount += (content.match(/<blume-mermaid(?:\s|>)/g) ?? []).length;
    mermaidCodeBlockCount += (content.match(/data-language="mermaid"/g) ?? []).length;
    accessibleTitleCount += (content.match(/accTitle:/g) ?? []).length;
    accessibleDescriptionCount += (content.match(/accDescr:/g) ?? []).length;
  }
  if (file.endsWith('.js') && path.basename(file).startsWith('mermaid.core.')) {
    mermaidRuntimeCount += 1;
  }
  if (file.endsWith('.css') && content.includes('.landing-shell') && content.includes('prefers-reduced-motion:reduce')) {
    landingStylesheetCount += 1;
  }
  if (
    file.endsWith('.css') &&
    content.includes('blume-mermaid') &&
    content.includes('max-width:700px') &&
    content.includes('min-width:42rem') &&
    content.includes('height:auto') &&
    content.includes('width:100%')
  ) {
    mermaidLegibilityStylesheetCount += 1;
  }
}

const searchRecords = JSON.parse(await readFile(path.join(distRoot, 'blume-search.json'), 'utf8'));
if (searchRecords.length !== 41) {
  throw new Error(`Search focus boundary artifact contract requires exactly 41 generated routes; found ${searchRecords.length}.`);
}
for (const { route } of searchRecords) {
  const htmlFile = route === '/' ? path.join(distRoot, 'index.html') : path.join(distRoot, route, 'index.html');
  const html = await readFile(htmlFile, 'utf8');
  assertSearchFocusBoundaryArtifact(html, route);
  const stylesheetHrefs = extractStylesheetHrefs(html);
  await assertLinkedStylesheetContract(html, stylesheetHrefs, route, { readStylesheet: readCachedStylesheet });
  assertOverflowFocusContract(html, route, {
    runtimeSource: layoutSource,
    requireLandingSurfaces: route === '/',
  });
}

if (diagramCount !== 4 || mermaidCodeBlockCount !== 0 || accessibleTitleCount !== 4 || accessibleDescriptionCount !== 4) {
  throw new Error(
    `Static artifact must contain four native Mermaid targets, no Mermaid code blocks, and accessible metadata; found ${diagramCount}, ${mermaidCodeBlockCount}, ${accessibleTitleCount}, and ${accessibleDescriptionCount}.`,
  );
}
if (mermaidRuntimeCount !== 1) {
  throw new Error(`Static artifact must contain one local Mermaid renderer core; found ${mermaidRuntimeCount}.`);
}
if (landingStylesheetCount < 1) {
  throw new Error(`Static artifact must contain an accessible landing stylesheet; found ${landingStylesheetCount}.`);
}
if (mermaidLegibilityStylesheetCount < 1) {
  throw new Error(`Static artifact must contain Mermaid legibility CSS; found ${mermaidLegibilityStylesheetCount}.`);
}
if (localFontReferences.size < 2) {
  throw new Error(`Static artifact must contain emitted local font references; found ${localFontReferences.size}.`);
}
for (const reference of localFontReferences) {
  try {
    await access(path.join(distRoot, reference.slice(1)));
  } catch {
    throw new Error(`Static artifact references a missing local font: ${reference}`);
  }
}
const expectedAssets = [
  {
    source: 'docs/website/public/fonts/UbuntuSans.ttf',
    emitted: 'fonts/UbuntuSans.ttf',
    sha256: '28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73',
  },
  {
    source: 'docs/website/public/fonts/UbuntuMono.ttf',
    emitted: 'fonts/UbuntuMono.ttf',
    sha256: 'fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee',
  },
  {
    source: 'docs/website/public/licenses/Ubuntu-Font-License-1.0.txt',
    emitted: 'licenses/Ubuntu-Font-License-1.0.txt',
    sha256: 'bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf',
  },
];
for (const asset of expectedAssets) {
  try {
    const sourceBytes = await readFile(path.join(repositoryRoot, asset.source));
    const emittedBytes = await readFile(path.join(distRoot, asset.emitted));
    const sourceHash = createHash('sha256').update(sourceBytes).digest('hex');
    const emittedHash = createHash('sha256').update(emittedBytes).digest('hex');
    if (sourceHash !== asset.sha256 || emittedHash !== asset.sha256) {
      throw new Error(`Static artifact asset checksum mismatch: ${asset.emitted}`);
    }
    if (asset.emitted.endsWith('.txt') && !sourceBytes.toString('utf8').includes('Ubuntu-Font-Licence-1.0')) {
      throw new Error('Ubuntu font license must identify Ubuntu-Font-Licence-1.0.');
    }
  } catch {
    throw new Error(`Static artifact is missing or has an invalid local font/license asset: ${asset.emitted}`);
  }
}
function escapePattern(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

console.log('Static artifact boundary check passed.');

async function readCachedStylesheet(relative) {
  let content = stylesheetCache.get(relative);
  if (content === undefined) {
    content = await readFile(path.join(distRoot, relative), 'utf8');
    stylesheetCache.set(relative, content);
  }
  return content;
}

async function files(root) {
  const result = [];
  async function visit(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) await visit(absolute);
      else if (entry.isFile()) result.push(absolute);
    }
  }
  await visit(root);
  result.sort();
  return result;
}

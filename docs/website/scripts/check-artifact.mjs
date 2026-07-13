import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { distRoot, repositoryRoot } from './website-paths.mjs';

const forbidden = [
  [/docs\/internal/i, 'docs/internal'],
  [/develop\//i, 'develop/'],
  [/BlackOps\\Internal/, 'BlackOps\\Internal namespace'],
  [/P[0-9]+-[0-9]+/, 'orchestration identifier'],
  [/Acceptance Evidence/i, 'acceptance evidence'],
  [new RegExp(escapePattern(repositoryRoot)), 'repository absolute path'],
  [/cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com/i, 'external diagram CDN'],
];

let diagramCount = 0;
let accessibleTitleCount = 0;
let accessibleDescriptionCount = 0;
let rendererEntryCount = 0;
let mermaidCoreCount = 0;
let responsiveStylesheetCount = 0;
for (const file of await files(distRoot)) {
  const content = (await readFile(file)).toString('utf8');
  for (const [pattern, label] of forbidden) {
    if (pattern.test(content)) {
      throw new Error(`Static artifact contains forbidden ${label}: ${path.relative(distRoot, file)}`);
    }
  }
  if (file.endsWith('.map')) {
    throw new Error(`Static artifact must not contain source maps: ${path.relative(distRoot, file)}`);
  }
  if (file.endsWith('.html')) {
    diagramCount += (content.match(/<pre[^>]*class="mermaid"/g) ?? []).length;
    accessibleTitleCount += (content.match(/accTitle:/g) ?? []).length;
    accessibleDescriptionCount += (content.match(/accDescr:/g) ?? []).length;
  }
  if (file.endsWith('.js')) {
    if (content.includes('pre.mermaid') && content.includes('data-processed') && content.includes('mermaid.core')) {
      rendererEntryCount += 1;
    }
    if (path.basename(file).startsWith('mermaid.core.')) {
      mermaidCoreCount += 1;
    }
  }
  if (
    file.endsWith('.css') &&
    content.includes('pre.mermaid') &&
    content.includes('min-inline-size:0') &&
    content.includes('max-inline-size:100%') &&
    content.includes('min-inline-size:60rem') &&
    (content.includes('overflow-x:auto') || content.includes('overflow:auto hidden'))
  ) {
    responsiveStylesheetCount += 1;
  }
}

if (diagramCount !== 4) {
  throw new Error(`Static artifact must contain four Mermaid render targets; found ${diagramCount}.`);
}
if (accessibleTitleCount !== 4 || accessibleDescriptionCount !== 4) {
  throw new Error(
    `Static artifact must preserve four accTitle and accDescr values; found ${accessibleTitleCount} and ${accessibleDescriptionCount}.`,
  );
}
if (rendererEntryCount !== 1 || mermaidCoreCount !== 1) {
  throw new Error(
    `Static artifact must contain one local Mermaid renderer entry and core chunk; found ${rendererEntryCount} and ${mermaidCoreCount}.`,
  );
}
if (responsiveStylesheetCount !== 1) {
  throw new Error(`Static artifact must contain one responsive Mermaid stylesheet; found ${responsiveStylesheetCount}.`);
}

function escapePattern(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

console.log('Static artifact boundary check passed.');

async function files(root) {
  const result = [];
  async function visit(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        await visit(absolute);
      } else if (entry.isFile()) {
        result.push(absolute);
      }
    }
  }
  await visit(root);
  result.sort();
  return result;
}

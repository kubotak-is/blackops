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
  [/consumer-sensitive-value|consumer-report-value|inline-secret-token|deferred-secret-token/, 'test secret literal'],
];

let diagramCount = 0;
let mermaidCodeBlockCount = 0;
let accessibleTitleCount = 0;
let accessibleDescriptionCount = 0;
let landingStylesheetCount = 0;
let mermaidLegibilityStylesheetCount = 0;
let mermaidRuntimeCount = 0;

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

function escapePattern(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

console.log('Static artifact boundary check passed.');

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

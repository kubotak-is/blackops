import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { loadDiagramManifest } from './archify-diagrams.mjs';
import { repositoryRoot } from './website-paths.mjs';

const manifest = await loadDiagramManifest();
const entriesByOwner = new Map();
for (const entry of manifest.diagrams) {
  const entries = entriesByOwner.get(entry.ownerSource) ?? [];
  entries.push(entry);
  entriesByOwner.set(entry.ownerSource, entries);
}

const mermaidFence = /^\s*(?:`{3,}|~{3,})\s*mermaid(?:\s|$)/imu;
for (const [ownerSource, entries] of entriesByOwner) {
  const ownerPath = path.join(repositoryRoot, ownerSource);
  let markdown;
  try {
    markdown = await readFile(ownerPath, 'utf8');
  } catch (error) {
    throw new Error(`Registered diagram owner source is unreadable: ${ownerSource}`, { cause: error });
  }
  if (markdown.trim() === '') {
    throw new Error(`Registered diagram owner source must be non-empty: ${ownerSource}`);
  }
  if (mermaidFence.test(markdown)) {
    throw new Error(`Registered diagram owner source must not contain a Mermaid explanatory fence: ${ownerSource}`);
  }

  for (const entry of entries) {
    const expectedImage = path.posix.relative(path.posix.dirname(ownerSource), entry.pngPath);
    if (!hasMarkdownImage(markdown, expectedImage)) {
      throw new Error(`Registered diagram ${entry.id} is missing its owner image reference: ${ownerSource} -> ${expectedImage}`);
    }
    const sourceText = await readFile(path.join(repositoryRoot, entry.sourcePath), 'utf8');
    if (sourceText.trim() === '') {
      throw new Error(`Registered diagram source must be non-empty: ${entry.sourcePath}`);
    }
  }
}

console.log(`Registered Archify source and owner coverage check passed for ${manifest.diagrams.length} diagrams.`);

function hasMarkdownImage(markdown, target) {
  const escapedTarget = target.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const image = new RegExp(`!\\[[^\\]]*\\]\\(<?${escapedTarget}>?(?:\\s+[^)]*)?\\)`, 'u');
  let fence = null;
  for (const line of markdown.split(/\r?\n/u)) {
    const fenceMatch = line.match(/^\s*(`{3,}|~{3,})/u);
    if (fenceMatch !== null) {
      const marker = fenceMatch[1][0];
      fence = fence === null ? marker : fence === marker ? null : fence;
      continue;
    }
    if (fence === null && image.test(line)) return true;
  }
  return false;
}

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
];

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

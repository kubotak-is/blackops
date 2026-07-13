import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { distRoot, repositoryRoot } from './website-paths.mjs';

const forbidden = [
  ['docs/internal', 'docs/internal'],
  ['develop/', 'develop/'],
  [repositoryRoot, 'repository absolute path'],
];

for (const file of await files(distRoot)) {
  const content = (await readFile(file)).toString('utf8');
  for (const [needle, label] of forbidden) {
    if (content.includes(needle)) {
      throw new Error(`Static artifact contains forbidden ${label}: ${path.relative(distRoot, file)}`);
    }
  }
  if (file.endsWith('.map')) {
    throw new Error(`Static artifact must not contain source maps: ${path.relative(distRoot, file)}`);
  }
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

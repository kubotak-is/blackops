import { mkdtemp, readFile, readdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { generateContent } from './content-pipeline.mjs';
import { contentRoot, manifestPath, repositoryRoot, sourceRoot } from './website-paths.mjs';
import { contentMap, versionBanner } from '../content-map.mjs';
import { validateNavigation } from '../site-navigation.mjs';

validateNavigation(contentMap);
const temporary = await mkdtemp(path.join(tmpdir(), 'blackops-docs-check-'));

try {
  const before = await snapshot(sourceRoot);
  const first = await generateContent({
    sourceRoot,
    contentRoot: path.join(temporary, 'first/content'),
    manifestPath: path.join(temporary, 'first/manifest.json'),
    repositoryRoot,
    contentMap,
    banner: versionBanner,
  });
  const second = await generateContent({
    sourceRoot,
    contentRoot: path.join(temporary, 'second/content'),
    manifestPath: path.join(temporary, 'second/manifest.json'),
    repositoryRoot,
    contentMap,
    banner: versionBanner,
  });
  if (first !== second) {
    throw new Error('Content manifest generation is not deterministic.');
  }
  if ((await snapshot(path.join(temporary, 'first'))) !== (await snapshot(path.join(temporary, 'second')))) {
    throw new Error('Generated Starlight content is not byte-for-byte deterministic.');
  }

  await generateContent({
    sourceRoot,
    contentRoot,
    manifestPath,
    repositoryRoot,
    contentMap,
    banner: versionBanner,
  });
  if (before !== (await snapshot(sourceRoot))) {
    throw new Error('Content generation modified docs/guide source files.');
  }
} finally {
  await rm(temporary, { recursive: true, force: true });
}

console.log('Content validation and determinism checks passed.');

async function snapshot(root) {
  const files = [];

  async function visit(directory, prefix) {
    const entries = await readdir(directory, { withFileTypes: true });
    entries.sort((left, right) => left.name.localeCompare(right.name, 'en'));
    for (const entry of entries) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) {
        await visit(path.join(directory, entry.name), relative);
      } else if (entry.isFile()) {
        files.push(`${relative}\0${(await readFile(path.join(directory, entry.name))).toString('base64')}`);
      }
    }
  }

  await visit(root, '');
  return files.join('\n');
}

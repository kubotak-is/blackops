import { mkdtemp, readFile, readdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { generateContent } from './content-pipeline.mjs';
import { contentRoot, manifestPath, repositoryRoot, sourceRoot } from './website-paths.mjs';
import { contentMap } from '../content-map.mjs';
import { validateNavigation } from '../site-navigation.mjs';
import { validateEditorial } from './editorial-guard.mjs';

// A link label may intentionally describe a subsection rather than the target page.
// Keep these exceptions explicit and reject entries that no longer occur.
const linkLabelAllowList = new Set([]);

async function main() {
  validateNavigation(contentMap);
  await validateLinkLabels(sourceRoot);
  await validateEditorialSources(sourceRoot);
  validateEditorialDescriptions(contentMap);
  const temporary = await mkdtemp(path.join(tmpdir(), 'blackops-docs-check-'));

  try {
    const before = await snapshot(sourceRoot);
    const first = await generateContent({
      sourceRoot,
      contentRoot: path.join(temporary, 'first/content'),
      manifestPath: path.join(temporary, 'first/manifest.json'),
      repositoryRoot,
      contentMap,
    });
    const second = await generateContent({
      sourceRoot,
      contentRoot: path.join(temporary, 'second/content'),
      manifestPath: path.join(temporary, 'second/manifest.json'),
      repositoryRoot,
      contentMap,
    });
    if (first !== second) {
      throw new Error('Content manifest generation is not deterministic.');
    }
    if ((await snapshot(path.join(temporary, 'first'))) !== (await snapshot(path.join(temporary, 'second')))) {
      throw new Error('Generated Blume content is not byte-for-byte deterministic.');
    }

    await generateContent({
      sourceRoot,
      contentRoot,
      manifestPath,
      repositoryRoot,
      contentMap,
    });
    if (before !== (await snapshot(sourceRoot))) {
      throw new Error('Content generation modified docs/guide source files.');
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
  console.log('Content validation and determinism checks passed.');
}

if (path.resolve(process.argv[1] ?? '') === fileURLToPath(import.meta.url)) await main();

export async function validateLinkLabels(root, { allowList = linkLabelAllowList } = {}) {
  const files = [];
  async function visit(directory, prefix = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) await visit(path.join(directory, entry.name), relative);
      else if (entry.isFile() && entry.name.endsWith('.md')) files.push(relative);
    }
  }
  await visit(root);
  const titles = new Map();
  const headingMaps = new Map();
  const sources = new Map();
  for (const file of files) {
    const content = (await readFile(path.join(root, file), 'utf8')).replace(/\r\n/g, '\n');
    sources.set(file, content);
    titles.set(file, content.match(/^#\s+(.+)$/m)?.[1]?.trim() ?? '');
    const counts = new Map();
    const headings = new Map();
    for (const [, rawHeading] of content.matchAll(/^#{1,6}\s+(.+)$/gm)) {
      const heading = rawHeading.trim();
      const base = slugifyHeading(heading);
      const count = counts.get(base) ?? 0;
      counts.set(base, count + 1);
      headings.set(count === 0 ? base : `${base}-${count}`, heading);
    }
    headingMaps.set(file, headings);
  }
  const used = new Set();
  const linkPattern = /(?<!\!)\[([^\]]+)\]\(([^)]+)\)/g;
  for (const [source, content] of sources) {
    let match;
    while ((match = linkPattern.exec(content)) !== null) {
      const [, label, target] = match;
      if (/^(?:https?:|mailto:|#|\/)/i.test(target)) continue;
      const clean = target.split('#')[0].split('?')[0];
      if (clean === '') continue;
      const resolved = path.normalize(path.join(path.dirname(source), clean));
      const targetFile = resolved.endsWith('.md') ? resolved : `${resolved}.md`;
      const fragment = target.match(/#([^?]+)/)?.[1] ?? '';
      const pageTitle = titles.get(targetFile);
      if (pageTitle === undefined || pageTitle === '') throw new Error(`Internal link target does not exist: ${source}|${targetFile}`);
      const title = fragment === '' ? pageTitle : headingMaps.get(targetFile)?.get(fragment);
      if (fragment !== '' && (title === undefined || title === '')) {
        throw new Error(`Internal link fragment does not exist: ${source}|${targetFile}#${fragment}`);
      }
      if (label === title) continue;
      const key = `${source}|${targetFile}|${label}`;
      if (!allowList.has(key)) throw new Error(`Internal link text must match target heading: ${key} (target: ${title})`);
      used.add(key);
    }
  }
  const unused = [...allowList].filter((entry) => !used.has(entry));
  if (unused.length > 0) throw new Error(`Internal link text allow list contains unused entries: ${unused.join(', ')}`);
}

export async function validateEditorialSources(root) {
  for (const entry of await readdir(root, { withFileTypes: true })) {
    if (!entry.isFile() || !entry.name.endsWith('.md')) continue;
    const file = path.join(root, entry.name);
    validateEditorial(await readFile(file, 'utf8'), { file: entry.name });
  }
}

export function validateEditorialDescriptions(map) {
  for (const [source, metadata] of Object.entries(map)) {
    validateEditorial(metadata.description, { file: `${source} (content-map description)` });
  }
}

export function slugifyHeading(value) {
  return value
    .replace(/[`*_~]/g, '')
    .trim()
    .toLocaleLowerCase('ja')
    .replace(/[^\p{L}\p{N}\s-]/gu, '')
    .replace(/\s+/gu, '-');
}

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

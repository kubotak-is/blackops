import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { mkdtemp, mkdir, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { promisify } from 'node:util';
import { generateContent } from '../scripts/content-pipeline.mjs';
import { slugifyHeading, validateLinkLabels } from '../scripts/check-content.mjs';

const execFileAsync = promisify(execFile);

test('generates deterministic Blume content and manifest without changing source', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, {
    'README.md': '# Home\n\n[Guide](guide.md)\n\n```text\n# Preserved\n```\n\n```mermaid\nflowchart LR\n    accTitle: Example\n    accDescr: Example relationship\n    A --> B\n```\n\n| A | B |\n| - | - |\n',
    'guide.md': '# Guide\n\n## Next\n',
  });
  const before = await readFile(path.join(fixture.source, 'README.md'), 'utf8');

  const first = await generate(fixture, 'first');
  const second = await generate(fixture, 'second');

  assert.equal(first.manifest, second.manifest);
  assert.equal(first.index, second.index);
  assert.match(first.index, /^---\ntitle: "Home"\n---\n/);
  assert.doesNotMatch(first.index, /^# Home$/m);
  assert.match(first.index, /\[Guide\]\(\/guide\/\)/);
  assert.match(first.index, /```text\n# Preserved\n```/);
  assert.match(first.index, /```mermaid\nflowchart LR\n    accTitle: Example/);
  assert.match(first.index, /\| A \| B \|/);
  assert.equal(await readFile(path.join(fixture.source, 'README.md'), 'utf8'), before);

  const manifest = JSON.parse(first.manifest);
  assert.deepEqual(
    manifest.pages.map(({ source, generated, slug, title }) => ({ source, generated, slug, title })),
    [
      { source: 'guide.md', generated: 'guide.md', slug: 'guide', title: 'Guide' },
      { source: 'README.md', generated: 'index.mdx', slug: 'index', title: 'Home' },
    ],
  );
  assert.equal(await fileExists(path.join(fixture.root, 'first/content/index.mdx')), true);
  assert.equal(await fileExists(path.join(fixture.root, 'first/content/index.md')), false);
  assert.equal(await fileExists(path.join(fixture.root, 'first/content/guide.md')), true);
  assert.ok(manifest.pages.every(({ hash }) => /^[0-9a-f]{64}$/.test(hash)));
});

test('removes the previous generated extension when Mermaid content changes', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, {
    'README.md': '# Home\n\n```mermaid\nflowchart LR\n    A --> B\n```\n',
  });

  const first = await generate(fixture);
  assert.match(first.manifest, /"generated": "index\.mdx"/);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.mdx')), true);

  await sources(fixture.source, { 'README.md': '# Home\n\nNo diagram.\n' });
  const second = await generate(fixture);

  assert.match(second.manifest, /"generated": "index\.md"/);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.md')), true);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.mdx')), false);
});

test('uses MDX for native callouts while ignoring callout text inside fenced code', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, {
    'README.md': '# Home\n\n:::info[Stable]\nUse the stable install.\n:::\n\n```text\n:::warning\nnot a directive\n:::\n```\n',
  });

  const result = await generate(fixture);
  assert.match(result.manifest, /"generated": "index\.mdx"/);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.mdx')), true);

  await sources(fixture.source, { 'README.md': '# Home\n\n```text\n:::warning\nnot a directive\n:::\n```\n' });
  const plain = await generate(fixture);
  assert.match(plain.manifest, /"generated": "index\.md"/);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.md')), true);
  assert.equal(await fileExists(path.join(fixture.root, 'output/content/index.mdx')), false);
});

test('rejects a page without a level-one title', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'missing.md': '## Missing\n' });

  await assert.rejects(() => generate(fixture), /requires a non-empty level-one title/);
});

test('rejects duplicate public slugs', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n', 'index.md': '# Other\n' });

  await assert.rejects(() => generate(fixture), /Duplicate documentation slug "index"/);
});

test('applies explicit public slugs and page metadata', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n' });
  const contentRoot = path.join(fixture.root, 'mapped/content');
  const manifestPath = path.join(fixture.root, 'mapped/manifest.json');

  const manifest = await generateContent({
    sourceRoot: fixture.source,
    contentRoot,
    manifestPath,
    repositoryRoot: fixture.root,
    contentMap: {
      'README.md': {
        slug: 'getting-started/install',
        description: 'Install BlackOps.',
        template: 'splash',
      },
    },
    banner: { content: 'Channel: main' },
  });
  const content = await readFile(path.join(contentRoot, 'getting-started/install.md'), 'utf8');

  assert.equal(JSON.parse(manifest).pages[0].slug, 'getting-started/install');
  assert.match(content, /description: "Install BlackOps\."/);
  assert.doesNotMatch(content, /template:/);
  assert.doesNotMatch(content, /banner:/);
});

test('rejects incomplete or stale public metadata', async (context) => {
  const incomplete = await fixtureRoot(context);
  await sources(incomplete.source, { 'README.md': '# Home\n', 'guide.md': '# Guide\n' });
  await assert.rejects(
    () => generateContent({
      sourceRoot: incomplete.source,
      contentRoot: path.join(incomplete.root, 'content'),
      manifestPath: path.join(incomplete.root, 'manifest.json'),
      repositoryRoot: incomplete.root,
      contentMap: { 'README.md': { slug: 'index' } },
    }),
    /missing public metadata: guide\.md/,
  );

  const stale = await fixtureRoot(context);
  await sources(stale.source, { 'README.md': '# Home\n' });
  await assert.rejects(
    () => generateContent({
      sourceRoot: stale.source,
      contentRoot: path.join(stale.root, 'content'),
      manifestPath: path.join(stale.root, 'manifest.json'),
      repositoryRoot: stale.root,
      contentMap: {
        'README.md': { slug: 'index' },
        'missing.md': { slug: 'missing' },
      },
    }),
    /references missing documentation source: missing\.md/,
  );
});

test('rejects missing or unsafe mapped public slugs', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n' });
  const invalid = [undefined, '', '/absolute', '.', '..', 'guide/../secret', 'guide\\secret', 'Getting-Started'];

  for (const slug of invalid) {
    await assert.rejects(
      () => generateContent({
        sourceRoot: fixture.source,
        contentRoot: path.join(fixture.root, 'unsafe/content'),
        manifestPath: path.join(fixture.root, 'unsafe/manifest.json'),
        repositoryRoot: fixture.root,
        contentMap: { 'README.md': slug === undefined ? {} : { slug } },
      }),
      /public slug must use lowercase kebab-case path segments/,
    );
  }
});

test('rejects a broken internal link', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n\n[Missing](missing.md)\n' });

  await assert.rejects(() => generate(fixture), /Broken internal documentation link/);
});

test('link labels resolve Japanese and duplicate heading fragments and reject drift', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, {
    'README.md': '# Home\n\n[操作](glossary.md#操作)\n[同じ見出し](glossary.md#同じ見出し-1)\n',
    'glossary.md': '# Glossary\n\n## 操作\n\n## 同じ見出し\n\n## 同じ見出し\n',
  });

  assert.equal(slugifyHeading('Binding Failureは422'), 'binding-failureは422');
  await validateLinkLabels(fixture.source);

  await sources(fixture.source, { 'README.md': '# Home\n\n[Wrong](glossary.md)\n' });
  await assert.rejects(() => validateLinkLabels(fixture.source), /must match target heading/);

  await sources(fixture.source, { 'README.md': '# Home\n\n[Missing](glossary.md#存在しない)\n' });
  await assert.rejects(() => validateLinkLabels(fixture.source), /fragment does not exist/);

  await sources(fixture.source, { 'README.md': '# Home\n\n[Missing](missing.md)\n' });
  await assert.rejects(() => validateLinkLabels(fixture.source), /target does not exist/);

  await sources(fixture.source, { 'README.md': '# Home\n\n[Glossary](glossary.md)\n' });
  await assert.rejects(
    () => validateLinkLabels(fixture.source, { allowList: new Set(['README.md|glossary.md|Unused']) }),
    /allow list contains unused entries/,
  );
});

test('rejects a link outside docs guide', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n\n[Internal](../internal/architecture.md)\n' });

  await assert.rejects(() => generate(fixture), /resolves outside docs\/guide/);
});

test('copies a tracked PNG asset and rewrites its generated relative path', async (context) => {
  const fixture = await fixtureRoot(context);
  const image = Buffer.from('credential-free-png-fixture');
  await sources(fixture.source, {
    'README.md': '# Home\n\n![Board](assets/community-board/board.png)\n',
    'assets/community-board/board.png': image,
  });
  await track(fixture.root, ['docs/guide/README.md', 'docs/guide/assets/community-board/board.png']);

  const result = await generate(fixture);

  assert.match(result.index, /!\[Board\]\(\.\/assets\/community-board\/board\.png\)/);
  assert.deepEqual(
    await readFile(path.join(fixture.root, 'output/content/assets/community-board/board.png')),
    image,
  );
});

test('rejects untracked, escaping, and non-PNG documentation assets', async (context) => {
  const untracked = await fixtureRoot(context);
  await sources(untracked.source, {
    'README.md': '# Home\n\n![Board](assets/board.png)\n',
    'assets/board.png': Buffer.from('untracked'),
  });
  await track(untracked.root, ['docs/guide/README.md']);
  await assert.rejects(() => generate(untracked), /asset must be tracked by git/);

  const escaping = await fixtureRoot(context);
  await sources(escaping.source, { 'README.md': '# Home\n\n![Outside](../outside.png)\n' });
  await assert.rejects(() => generate(escaping), /resolves outside docs\/guide/);

  const unsupported = await fixtureRoot(context);
  await sources(unsupported.source, {
    'README.md': '# Home\n\n![Text](assets/board.txt)\n',
    'assets/board.txt': 'not an image',
  });
  await assert.rejects(() => generate(unsupported), /must reference a PNG below docs\/guide\/assets/);

  const external = await fixtureRoot(context);
  await sources(external.source, { 'README.md': '# Home\n\n![Remote](https://example.test/board.png)\n' });
  await assert.rejects(() => generate(external), /image must use a relative docs\/guide asset/);
});

test('rejects forbidden internal and development content', async (context) => {
  const internal = await fixtureRoot(context);
  await sources(internal.source, { 'README.md': '# Home\n\nSee docs/internal/architecture.md.\n' });
  await assert.rejects(() => generate(internal), /forbidden content "docs\/internal"/);

  const development = await fixtureRoot(context);
  await sources(development.source, { 'README.md': '# Home\n\nSee develop/STATE.md.\n' });
  await assert.rejects(() => generate(development), /forbidden content "develop\/"/);
});

test('rejects repository absolute paths', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': `# Home\n\n${fixture.root}/private.md\n` });

  await assert.rejects(() => generate(fixture), /repository absolute path/);
});

test('rejects source frontmatter', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '---\ntitle: Copied\n---\n# Home\n' });

  await assert.rejects(() => generate(fixture), /frontmatter is not supported/);
});

test('rejects symbolic links in the source tree', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n' });
  const outside = path.join(fixture.root, 'outside.md');
  await writeFile(outside, '# Outside\n');
  await symlink(outside, path.join(fixture.source, 'linked.md'));

  await assert.rejects(() => generate(fixture), /must not contain symbolic links/);
});

async function fixtureRoot(context) {
  const root = await mkdtemp(path.join(tmpdir(), 'blackops-docs-test-'));
  context.after(() => rm(root, { recursive: true, force: true }));
  const source = path.join(root, 'docs/guide');
  await mkdir(source, { recursive: true });

  return { root, source };
}

async function sources(root, files) {
  for (const [relative, content] of Object.entries(files)) {
    const target = path.join(root, relative);
    await mkdir(path.dirname(target), { recursive: true });
    await writeFile(target, content, typeof content === 'string' ? 'utf8' : undefined);
  }
}

async function track(root, files) {
  await execFileAsync('git', ['init', '--quiet'], { cwd: root });
  await execFileAsync('git', ['add', '--', ...files], { cwd: root });
}

async function generate(fixture, name = 'output') {
  const contentRoot = path.join(fixture.root, name, 'content');
  const manifestPath = path.join(fixture.root, name, 'manifest.json');
  const manifest = await generateContent({
    sourceRoot: fixture.source,
    contentRoot,
    manifestPath,
    repositoryRoot: fixture.root,
  });

  return {
    manifest,
    index: await readGeneratedIndex(contentRoot),
  };
}

async function readGeneratedIndex(contentRoot) {
  for (const extension of ['mdx', 'md']) {
    try {
      return await readFile(path.join(contentRoot, `index.${extension}`), 'utf8');
    } catch {
      // Try the other generated extension.
    }
  }

  throw new Error('Generated index page is missing.');
}

async function fileExists(file) {
  try {
    await readFile(file);
    return true;
  } catch {
    return false;
  }
}

import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { generateContent } from '../scripts/content-pipeline.mjs';

test('generates deterministic Starlight content and manifest without changing source', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, {
    'README.md': '# Home\n\n[Guide](guide.md)\n\n```text\n# Preserved\n```\n\n| A | B |\n| - | - |\n',
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
  assert.match(first.index, /\| A \| B \|/);
  assert.equal(await readFile(path.join(fixture.source, 'README.md'), 'utf8'), before);

  const manifest = JSON.parse(first.manifest);
  assert.deepEqual(
    manifest.pages.map(({ source, generated, slug, title }) => ({ source, generated, slug, title })),
    [
      { source: 'guide.md', generated: 'guide.md', slug: 'guide', title: 'Guide' },
      { source: 'README.md', generated: 'index.md', slug: 'index', title: 'Home' },
    ],
  );
  assert.ok(manifest.pages.every(({ hash }) => /^[0-9a-f]{64}$/.test(hash)));
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
  assert.match(content, /template: "splash"/);
  assert.match(content, /banner: \{"content":"Channel: main"\}/);
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

test('rejects a link outside docs guide', async (context) => {
  const fixture = await fixtureRoot(context);
  await sources(fixture.source, { 'README.md': '# Home\n\n[Internal](../internal/architecture.md)\n' });

  await assert.rejects(() => generate(fixture), /resolves outside docs\/guide/);
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
    await writeFile(target, content, 'utf8');
  }
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
    index: await readFile(path.join(contentRoot, 'index.md'), 'utf8'),
  };
}

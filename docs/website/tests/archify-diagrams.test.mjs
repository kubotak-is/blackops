import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFile, mkdir, mkdtemp, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { promisify } from 'node:util';
import {
  assertDiagramArtifacts,
  assertRegisteredDiagramLink,
  diagramContractsList,
  loadDiagramManifest,
  OFFLINE_TEMPLATE_PATCH_PATH,
  publicDiagramLinks,
  registeredViewerRelativePaths,
  validateDiagramManifest,
} from '../scripts/archify-diagrams.mjs';

const execFileAsync = promisify(execFile);

test('validates the ten registered Archify sources, outputs, copies, and receipts', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const manifestPath = path.join(fixture.root, 'docs/website/diagrams/manifest.json');
  const result = await assertDiagramArtifacts({
    manifestPath,
    repositoryRoot: fixture.root,
    artifactDirectory: path.join(fixture.root, 'dist'),
  });

  const expectedLinks = diagramContractsList().flatMap(({ id, svgPath }) => [
    `/diagrams/${id}.html`,
    `/diagrams/${id}.json`,
    ...(svgPath === undefined ? [] : [`/diagrams/${id}.svg`]),
  ]);
  assert.deepEqual([...publicDiagramLinks(manifest)].sort(), expectedLinks.sort());
  assert.equal(result.sourceHashes.size, 10);
  assert.equal(result.svgHashes.size, 1);
  await assert.doesNotReject(() => assertRegisteredDiagramLink('/diagrams/runtime.html', {
    manifest,
    repositoryRoot: fixture.root,
  }));
  await assert.doesNotReject(() => assertRegisteredDiagramLink('/diagrams/execution-overview.svg', {
    manifest,
    repositoryRoot: fixture.root,
  }));
});

test('allows only an explicit execution overview desktop variant without adding a logical diagram', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const overview = manifest.diagrams.find((entry) => entry.id === 'execution-overview');
  const variant = await addOverviewDesktopVariant(fixture.root, overview);
  overview.desktop = variant;
  const manifestPath = path.join(fixture.root, 'docs/website/diagrams/manifest.json');
  await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  await execFileAsync('git', ['add', '--', 'docs/website/diagrams/manifest.json', variant.sourcePath, variant.htmlPath, variant.svgPath], { cwd: fixture.root });

  const loaded = await loadDiagramManifest(manifestPath);
  assert.equal(loaded.diagrams.length, 10);
  assert.deepEqual([...publicDiagramLinks(loaded)].filter((link) => link.includes('execution-overview-desktop')).sort(), [
    '/diagrams/execution-overview-desktop.html',
    '/diagrams/execution-overview-desktop.json',
    '/diagrams/execution-overview-desktop.svg',
  ]);
  assert.ok(registeredViewerRelativePaths(loaded).has('diagrams/execution-overview-desktop.html'));
  await assert.doesNotReject(() => assertDiagramArtifacts({
    manifestPath,
    repositoryRoot: fixture.root,
    requireReceipts: false,
  }));
  await assert.doesNotReject(() => assertRegisteredDiagramLink('/diagrams/execution-overview-desktop.svg', {
    manifest: loaded,
    repositoryRoot: fixture.root,
  }));

  const misplaced = structuredClone(loaded);
  misplaced.diagrams.find((entry) => entry.id === 'runtime').desktop = structuredClone(variant);
  assert.throws(() => validateDiagramManifest(misplaced), /runtime has an unexpected desktop/);
});

test('rejects diagram hash drift, orphaned links, and unsafe manifest paths', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const manifestPath = path.join(fixture.root, 'docs/website/diagrams/manifest.json');
  await writeFile(path.join(fixture.root, 'docs/website/public/diagrams/runtime.html'), '<!doctype html>drift', 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({ manifestPath, repositoryRoot: fixture.root, requireReceipts: false }),
    /hash mismatch.*runtime/,
  );
  await assert.rejects(
    () => assertRegisteredDiagramLink('/diagrams/unknown.html', { manifest, repositoryRoot: fixture.root }),
    /not registered/,
  );

  const unsafe = structuredClone(manifest);
  unsafe.diagrams[0].htmlPath = 'docs/website/public/diagrams/../runtime.html';
  assert.throws(() => validateDiagramManifest(unsafe), /unexpected htmlPath/);
});

test('requires the exact ten diagram contracts and rejects untracked registered files', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  await execFileAsync('git', ['rm', '--cached', '--quiet', '--', 'docs/website/public/diagrams/outbox.json'], { cwd: fixture.root });
  await assert.rejects(
    () => assertRegisteredDiagramLink('/diagrams/outbox.json', { manifest, repositoryRoot: fixture.root }),
    /must be tracked by git/,
  );

  const missing = structuredClone(manifest);
  missing.diagrams = [missing.diagrams[0]];
  assert.throws(() => validateDiagramManifest(missing), /exactly 10 diagrams/);
  assert.equal(diagramContractsList().length, 10);
});

test('rejects missing and orphaned diagram assets', async (context) => {
  const fixture = await fixtureRoot(context);
  await createFixture(fixture.root);
  const manifestPath = path.join(fixture.root, 'docs/website/diagrams/manifest.json');
  await writeFile(path.join(fixture.root, 'docs/website/public/diagrams/orphan.html'), 'orphan', 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({ manifestPath, repositoryRoot: fixture.root }),
    /contains an unregistered file.*orphan\.html/,
  );
  await rm(path.join(fixture.root, 'docs/website/public/diagrams/orphan.html'));
  await rm(path.join(fixture.root, 'docs/website/public/diagrams/runtime.html'));
  await assert.rejects(
    () => assertDiagramArtifacts({ manifestPath, repositoryRoot: fixture.root }),
    /Diagram HTML is missing.*runtime\.html/,
  );
});

test('rejects remote font providers in registered source HTML', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const runtime = manifest.diagrams.find((entry) => entry.id === 'runtime');
  const htmlPath = path.join(fixture.root, runtime.htmlPath);
  const original = await readFile(htmlPath, 'utf8');
  const remote = original.replace('</head>', '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono" rel="stylesheet"></head>');
  await writeFile(htmlPath, remote, 'utf8');
  runtime.html.sha256 = digest(remote);
  runtime.receipts.deliver.artifact.sha256 = digest(remote);
  await writeFile(path.join(fixture.root, 'docs/website/diagrams/manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
    }),
    /remote font provider/,
  );
});

test('rejects a registered artifact without the exact embedded local Noto font', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const runtime = manifest.diagrams.find((entry) => entry.id === 'runtime');
  const htmlPath = path.join(fixture.root, runtime.htmlPath);
  const original = await readFile(htmlPath, 'utf8');
  const withoutFont = original.replace(
    /data:font\/woff2;base64,[A-Za-z0-9+/]+={0,2}/u,
    'data:font/woff2;base64,AA==',
  );
  await writeFile(htmlPath, withoutFont, 'utf8');
  runtime.html.sha256 = digest(withoutFont);
  runtime.receipts.deliver.artifact.sha256 = digest(withoutFont);
  await writeFile(
    path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
    `${JSON.stringify(manifest, null, 2)}\n`,
    'utf8',
  );
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
    }),
    /exact local Noto Sans JP font/,
  );
});

test('requires the canonical execution overview SVG and rejects hash drift', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const overview = manifest.diagrams.find((entry) => entry.id === 'execution-overview');
  const svgPath = path.join(fixture.root, overview.svg.path);
  await rm(svgPath);
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
      requireReceipts: false,
    }),
    /Diagram SVG is missing.*execution-overview\.svg/,
  );

  const secondFixture = await fixtureRoot(context);
  const secondManifest = await createFixture(secondFixture.root);
  const secondOverview = secondManifest.diagrams.find((entry) => entry.id === 'execution-overview');
  await writeFile(path.join(secondFixture.root, secondOverview.svg.path), '<svg role="img"><title>drift</title></svg>', 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(secondFixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: secondFixture.root,
      requireReceipts: false,
    }),
    /Diagram svg hash mismatch for execution-overview/,
  );
});

test('requires the SVG export receipt to bind both canonical hashes', async (context) => {
  const fixture = await fixtureRoot(context);
  const manifest = await createFixture(fixture.root);
  const overview = manifest.diagrams.find((entry) => entry.id === 'execution-overview');
  overview.receipts.svgExport.svg.sha256 = '0'.repeat(64);
  const manifestPath = path.join(fixture.root, 'docs/website/diagrams/manifest.json');
  await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({ manifestPath, repositoryRoot: fixture.root }),
    /Archify SVG export receipt for execution-overview must bind HTML\/SVG hashes/,
  );
});

test('binds the offline template patch to its tracked reviewed bytes', async (context) => {
  const fixture = await fixtureRoot(context);
  await createFixture(fixture.root);
  const patchPath = path.join(fixture.root, OFFLINE_TEMPLATE_PATCH_PATH);
  await writeFile(patchPath, `${await readFile(patchPath, 'utf8')}\n`, 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
      requireReceipts: false,
    }),
    /reviewed patch/,
  );
});

test('rejects a parent symlink before reading a registered PNG', async (context) => {
  const fixture = await fixtureRoot(context);
  await createFixture(fixture.root);
  const pngDirectory = path.join(fixture.root, 'docs/guide/assets/diagrams');
  const targetDirectory = path.join(fixture.root, 'docs/guide/assets/diagram-target');
  await mkdir(targetDirectory, { recursive: true });
  for (const { id } of diagramContractsList()) {
    await copyFile(path.join(pngDirectory, `${id}.png`), path.join(targetDirectory, `${id}.png`));
  }
  await rm(pngDirectory, { recursive: true });
  await symlink('diagram-target', pngDirectory, 'dir');
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
      requireReceipts: false,
    }),
    /symlink or repository escape/,
  );
});

test('rejects a registered diagram whose owner guide lost its image', async (context) => {
  const fixture = await fixtureRoot(context);
  await createFixture(fixture.root);
  const ownerPath = path.join(fixture.root, 'docs/guide/core-concepts.md');
  const owner = await readFile(ownerPath, 'utf8');
  await writeFile(ownerPath, owner.replace('![Runtime](assets/diagrams/runtime.png)', 'Runtime image removed'), 'utf8');
  await assert.rejects(
    () => assertDiagramArtifacts({
      manifestPath: path.join(fixture.root, 'docs/website/diagrams/manifest.json'),
      repositoryRoot: fixture.root,
      requireReceipts: false,
    }),
    /owner guide must reference assets\/diagrams\/runtime\.png/,
  );
});

async function addOverviewDesktopVariant(root, overview) {
  const sourcePath = 'docs/website/public/diagrams/execution-overview-desktop.json';
  const htmlPath = 'docs/website/public/diagrams/execution-overview-desktop.html';
  const svgPath = 'docs/website/public/diagrams/execution-overview-desktop.svg';
  await copyFile(path.join(root, overview.sourcePath), path.join(root, sourcePath));
  await copyFile(path.join(root, overview.htmlPath), path.join(root, htmlPath));
  await copyFile(path.join(root, overview.svg.path), path.join(root, svgPath));
  const source = await readFile(path.join(root, sourcePath));
  const html = await readFile(path.join(root, htmlPath));
  const svg = await readFile(path.join(root, svgPath));
  return {
    sourcePath,
    source: { path: sourcePath, sha256: digest(source) },
    htmlPath,
    html: { path: htmlPath, sha256: digest(html) },
    svgPath,
    svg: { path: svgPath, sha256: digest(svg) },
    receipts: structuredClone(overview.receipts),
  };
}

async function fixtureRoot(context) {
  const root = await mkdtemp(path.join(tmpdir(), 'blackops-archify-diagrams-'));
  context.after(() => rm(root, { recursive: true, force: true }));
  return { root };
}

async function createFixture(root) {
  const manifestDirectory = path.join(root, 'docs/website/diagrams');
  const publicDirectory = path.join(root, 'docs/website/public/diagrams');
  const fontDirectory = path.join(root, 'docs/website/public/fonts');
  const pngDirectory = path.join(root, 'docs/guide/assets/diagrams');
  await mkdir(manifestDirectory, { recursive: true });
  await mkdir(publicDirectory, { recursive: true });
  await mkdir(fontDirectory, { recursive: true });
  await mkdir(pngDirectory, { recursive: true });
  const patchBytes = await readFile(new URL('../diagrams/offline-fonts.patch', import.meta.url));
  const fontBytes = await readFile(new URL('../public/fonts/NotoSansJP.woff2', import.meta.url));
  const licenseBytes = await readFile(new URL('../public/fonts/NotoSansJP-OFL.txt', import.meta.url));
  const fontData = fontBytes.toString('base64');
  const fontCss = `@font-face { font-family: 'Noto Sans JP'; font-style: normal; font-weight: 100 900; src: url('data:font/woff2;base64,${fontData}') format('woff2'); } body, svg { font-family: 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif; }`;
  await writeFile(path.join(fontDirectory, 'NotoSansJP.woff2'), fontBytes);
  await writeFile(path.join(fontDirectory, 'NotoSansJP-OFL.txt'), licenseBytes);
  await writeFile(path.join(root, OFFLINE_TEMPLATE_PATCH_PATH), patchBytes);
  const manifest = {
    schemaVersion: 1,
    upstream: {
      repository: 'https://github.com/tt-a1i/archify',
      revision: '2ead014aa8ec91f104cd052f1a6ca82de5e26c31',
      version: '2.17.0-dev.1',
      updateChecks: 'disabled',
      templatePatch: {
        path: OFFLINE_TEMPLATE_PATCH_PATH,
        sha256: digest(patchBytes),
        originalTemplateSha256: digest('pristine template fixture'),
        patchedTemplateSha256: digest('patched template fixture'),
      },
    },
    regeneration: {
      validate: 'node archify validate',
      deliver: 'node archify deliver',
      exportPng: 'Archify Export Image',
    },
    diagrams: [],
  };
  const ownerImages = new Map();
  for (const contract of diagramContractsList()) {
    const title = contract.id.replaceAll('-', ' ').replace(/\b\w/gu, (letter) => letter.toUpperCase());
    const source = JSON.stringify({
      schema_version: 1,
      diagram_type: contract.type,
      meta: { title, quality_profile: 'showcase' },
    }, null, 2) + '\n';
    const html = `<!doctype html><html lang="en"><head><meta name="generator" content="archify 2.17.0-dev.1"><title>${title}</title><style id="archify-noto-font">${fontCss}</style></head><body><svg role="img"><title>${title}</title><desc>${title} diagram</desc></svg></body></html>`;
    const png = Buffer.from(`${contract.id}-png-fixture`);
    const svg = contract.svgPath === undefined
      ? null
      : Buffer.from(`<svg role="img"><style>${fontCss}</style><title>${title}</title><desc>${title} diagram</desc></svg>`);
    await writeFile(path.join(root, contract.sourcePath), source, 'utf8');
    await writeFile(path.join(root, contract.htmlPath), html, 'utf8');
    await writeFile(path.join(root, contract.pngPath), png);
    if (svg !== null) await writeFile(path.join(root, contract.svgPath), svg);
    const images = ownerImages.get(contract.ownerSource) ?? [];
    images.push(`![${title}](assets/diagrams/${contract.id}.png)`);
    ownerImages.set(contract.ownerSource, images);
    const entry = {
      id: contract.id,
      type: contract.type,
      ownerSource: contract.ownerSource,
      ownerRoute: contract.ownerRoute,
      sourcePath: contract.sourcePath,
      htmlPath: contract.htmlPath,
      pngPath: contract.pngPath,
      source: { path: contract.sourcePath, sha256: digest(source) },
      html: { path: contract.htmlPath, sha256: digest(html) },
      png: { path: contract.pngPath, sha256: digest(png) },
      receipts: {
        validate: validateReceipt(contract.type),
        deliver: deliverReceipt(contract.type, source, html),
        visualCheck: null,
      },
    };
    if (svg !== null) {
      entry.svg = { path: contract.svgPath, sha256: digest(svg) };
      entry.receipts.svgExport = svgExportReceipt(contract, html, svg);
    }
    manifest.diagrams.push(entry);
  }
  for (const [ownerSource, images] of ownerImages) {
    await writeFile(
      path.join(root, ownerSource),
      `# Diagram owner\n\n${images.join('\n\n')}\n`,
      'utf8',
    );
  }
  await writeFile(path.join(manifestDirectory, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
  await mkdir(path.join(root, 'dist/diagrams'), { recursive: true });
  await mkdir(path.join(root, 'dist/blume-assets/content/src/content/docs/assets/diagrams'), { recursive: true });
  for (const contract of diagramContractsList()) {
    await copyFile(path.join(root, contract.sourcePath), path.join(root, 'dist/diagrams', `${contract.id}.json`));
    await copyFile(path.join(root, contract.htmlPath), path.join(root, 'dist/diagrams', `${contract.id}.html`));
    if (contract.svgPath !== undefined) {
      await copyFile(path.join(root, contract.svgPath), path.join(root, 'dist/diagrams', `${contract.id}.svg`));
    }
    await copyFile(
      path.join(root, contract.pngPath),
      path.join(root, 'dist/blume-assets/content/src/content/docs/assets/diagrams', `${contract.id}.png`),
    );
  }
  await execFileAsync('git', ['init', '--quiet'], { cwd: root });
  await execFileAsync('git', ['add', '--', 'docs/website/diagrams/manifest.json', 'docs/website/diagrams/offline-fonts.patch', 'docs/website/public/diagrams', 'docs/website/public/fonts', 'docs/guide'], { cwd: root });
  return loadDiagramManifest(path.join(manifestDirectory, 'manifest.json'));
}

function digest(value) {
  return createHash('sha256').update(value).digest('hex');
}

function validateReceipt(type) {
  return {
    schemaVersion: 1,
    ok: true,
    command: 'validate',
    type,
    checks: Array.from({ length: 9 }, () => ({ ok: true })),
    composition: { profile: 'showcase', status: 'pass', summary: { errors: 0, warnings: 0 } },
  };
}

function deliverReceipt(type, source, html) {
  return {
    schemaVersion: 1,
    ok: true,
    command: 'deliver',
    type,
    specification: { sha256: digest(source), bytes: Buffer.byteLength(source) },
    artifact: { sha256: digest(html), bytes: Buffer.byteLength(html) },
    validation: {
      checksPassed: 9,
      checkCount: 9,
      compositionProfile: 'showcase',
      compositionStatus: 'pass',
      errors: 0,
      warnings: 0,
    },
  };
}

function svgExportReceipt(contract, html, svg) {
  return {
    command: 'Archify viewer Export > Image > SVG',
    html: { path: `/tmp/${contract.id}.html`, sha256: digest(html) },
    svg: { path: `/tmp/${contract.id}.svg`, sha256: digest(svg), bytes: svg.length },
    status: 'pass',
  };
}

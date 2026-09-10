import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { diagramContractsList } from '../scripts/archify-diagrams.mjs';
import {
  assertArtifactClaims,
  assertCurrentAuthorityClaims,
  assertNoStaleCurrentPhrase,
  assertOccurrences,
  assertSourceClaims,
  loadReleaseAuthority,
  validateAuthority,
} from '../scripts/release-claim-checker.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const authorityPath = path.join(repositoryRoot, 'develop/spec/release-authority.json');

test('release claim checker rejects current downgrade, candidate, and main-only fixtures', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  assert.throws(
    () => assertOccurrences([{ path: 'docs/guide/fixture.md', heading: '# Fixture', sentence: 'Latest Experimental Stable 1.1.0です。' }], authority, { source: true }),
    /Unexpected Stable 1.1.0 claim/,
  );
  assert.throws(
    () => assertOccurrences([{ path: 'docs/guide/fixture.md', heading: '# Fixture', sentence: 'Stable 1.2.1 candidateです。' }], authority, { source: true }),
    /Stale current release claim/,
  );
  assert.throws(
    () => assertNoStaleCurrentPhrase('Stable 1.2.1はRepository mainのExperimental Surfaceです。', 'fixture.md'),
    /Stale current main\/candidate claim/,
  );
  assert.throws(
    () => assertNoStaleCurrentPhrase('未公開の1.2.1です。', 'fixture.md'),
    /Stale candidate release claim/,
  );
});

test('authority page mappings are shaped, lane-bound, and enforced through the full source checker', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const fixture = await mkdtemp(path.join(repositoryRoot, 'docs/guide/.release-claim-page-'));
  const fixturePath = path.relative(repositoryRoot, path.join(fixture, 'fixture.md'));
  const fixtureAuthority = structuredClone(authority);
  fixtureAuthority.historicalReferences = [];
  fixtureAuthority.pageCapabilities = {
    [fixturePath]: { lane: 'framework-package', capabilities: ['framework-core'] },
  };
  const fixtureAuthorityPath = path.join(fixture, 'authority.json');
  const contentMapPath = path.join(fixture, 'content-map.mjs');
  const currentSource = '# Fixture\n\nLatest Experimental Stable 1.2.1 is documented here.\n\ncomposer create-project blackops/skeleton my-app 1.2.1\n\n### Repository main Preview\n\nこのAnchorは旧PreviewからのMigration Linkを壊さないために残しています。\n';
  try {
    await writeFile(path.join(fixture, 'fixture.md'), currentSource);
    await writeFile(contentMapPath, '');
    await writeFile(fixtureAuthorityPath, JSON.stringify(fixtureAuthority));
    await assert.doesNotReject(() => assertSourceClaims({ authorityPath: fixtureAuthorityPath, sourceDirectory: fixture, contentMapPath }));
    await writeFile(path.join(fixture, 'fixture.md'), `${currentSource}\nこのCapabilityはRepository mainのPreview Surfaceです。\n`);
    await assert.rejects(() => assertSourceClaims({ authorityPath: fixtureAuthorityPath, sourceDirectory: fixture, contentMapPath }), /authority-disallowed/);

    const withoutPages = structuredClone(fixtureAuthority);
    delete withoutPages.pageCapabilities;
    assert.throws(() => validateAuthority(withoutPages), /pageCapabilities/);
    const unknownCapability = structuredClone(fixtureAuthority);
    unknownCapability.pageCapabilities[fixturePath].capabilities = ['unknown-capability'];
    assert.throws(() => validateAuthority(unknownCapability), /lane mismatch/);
    const mainOnly = structuredClone(fixtureAuthority);
    mainOnly.capabilities.find((capability) => capability.id === 'framework-core').surfaces = ['main-only'];
    assert.throws(() => validateAuthority(mainOnly), /invalid capability/);
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

test('source release claims include registered diagram JSON, HTML, and responsive variants', async () => {
  const fixture = await mkdtemp(path.join(repositoryRoot, 'docs/guide/.release-claim-diagrams-'));
  const sourcePath = path.join(fixture, 'guide.md');
  const relativeSourcePath = path.relative(repositoryRoot, sourcePath);
  const authority = await loadReleaseAuthority(authorityPath);
  authority.historicalReferences = [];
  authority.pageCapabilities = {
    [relativeSourcePath]: { lane: 'framework-package', capabilities: ['framework-core'] },
  };
  const fixtureAuthorityPath = path.join(fixture, 'authority.json');
  const contentMapPath = path.join(fixture, 'content-map.mjs');
  const manifestPath = path.join(fixture, 'docs/website/diagrams/manifest.json');
  try {
    await writeFile(sourcePath, '# Diagram owner\n\nLatest Experimental Stable 1.2.1 is documented here.\n\ncomposer create-project blackops/skeleton my-app 1.2.1\n', 'utf8');
    await mkdir(path.join(fixture, 'docs/website/public/diagrams'), { recursive: true });
    await mkdir(path.dirname(manifestPath), { recursive: true });
    const manifest = diagramManifestFixture();
    const overview = manifest.diagrams.find((entry) => entry.id === 'execution-overview');
    const desktop = {
      sourcePath: 'docs/website/public/diagrams/execution-overview-desktop.json',
      htmlPath: 'docs/website/public/diagrams/execution-overview-desktop.html',
      svgPath: 'docs/website/public/diagrams/execution-overview-desktop.svg',
      source: { path: 'docs/website/public/diagrams/execution-overview-desktop.json', sha256: null },
      html: { path: 'docs/website/public/diagrams/execution-overview-desktop.html', sha256: null },
      svg: { path: 'docs/website/public/diagrams/execution-overview-desktop.svg', sha256: null },
      receipts: { validate: null, deliver: null, visualCheck: null },
    };
    overview.desktop = desktop;
    for (const contract of diagramContractsList()) {
      await writeFile(
        path.join(fixture, contract.sourcePath),
        JSON.stringify({ schema_version: 1, diagram_type: contract.type }),
        'utf8',
      );
      await writeFile(path.join(fixture, contract.htmlPath), `<title>${contract.id}</title>`, 'utf8');
      if (contract.svgPath !== undefined) {
        await writeFile(path.join(fixture, contract.svgPath), `<svg role="img"><title>${contract.id}</title></svg>`, 'utf8');
      }
    }
    await writeFile(path.join(fixture, desktop.sourcePath), '{"schema_version":1,"diagram_type":"architecture"}', 'utf8');
    await writeFile(path.join(fixture, desktop.htmlPath), '<title>execution-overview-desktop</title>', 'utf8');
    await writeFile(path.join(fixture, desktop.svgPath), '<svg role="img"><title>execution-overview-desktop</title></svg>', 'utf8');
    await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
    await writeFile(contentMapPath, '', 'utf8');
    await writeFile(fixtureAuthorityPath, JSON.stringify(authority), 'utf8');

    await assert.doesNotReject(() => assertSourceClaims({
      authorityPath: fixtureAuthorityPath,
      sourceDirectory: fixture,
      contentMapPath,
      diagramManifestPath: manifestPath,
      diagramRepositoryRoot: fixture,
    }));
    for (const relativePath of [desktop.sourcePath, desktop.htmlPath]) {
      const artifactPath = path.join(fixture, relativePath);
      const baseline = await readFile(artifactPath, 'utf8');
      await writeFile(artifactPath, 'Stable 1.1.0 is the current release.', 'utf8');
      try {
        await assert.rejects(() => assertSourceClaims({
          authorityPath: fixtureAuthorityPath,
          sourceDirectory: fixture,
          contentMapPath,
          diagramManifestPath: manifestPath,
          diagramRepositoryRoot: fixture,
        }), /Unexpected Stable 1.1.0 claim/);
      } finally {
        await writeFile(artifactPath, baseline, 'utf8');
      }
    }
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

test('release claim checker rejects unexpected, moved, and unused history', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const historical = authority.historicalReferences[0];
  const singleHistoryAuthority = { ...authority, historicalReferences: [historical] };
  const occurrence = { path: historical.path, heading: historical.heading, sentence: historical.normalizedSentence };
  assert.doesNotThrow(() => assertOccurrences([occurrence], singleHistoryAuthority, { source: true }));
  assert.throws(
    () => assertOccurrences([{ ...occurrence, sentence: `${historical.normalizedSentence} drift` }], singleHistoryAuthority, { source: true }),
    /Unexpected Stable 1.1.0 claim/,
  );
  assert.throws(
    () => assertOccurrences([{ ...occurrence, heading: '# Moved' }], singleHistoryAuthority, { source: true }),
    /Unexpected Stable 1.1.0 claim/,
  );
  assert.throws(
    () => assertOccurrences([], singleHistoryAuthority, { source: true }),
    /unused entries/,
  );
});

test('release claim checker rejects artifact-only stale injection and authority-only version bump', async () => {
  const authority = JSON.parse(await readFile(authorityPath, 'utf8'));
  const authorityBump = structuredClone(authority);
  authorityBump.currentStable.version = '1.3.0';
  assert.throws(() => assertCurrentAuthorityClaims('Latest Experimental Stable 1.2.1\ncomposer create-project blackops/skeleton my-app 1.2.1', authorityBump), /do not match/);

  const fixture = await mkdtemp(path.join(tmpdir(), 'blackops-release-claim-'));
  try {
    await mkdir(path.join(fixture, 'nested'));
    await writeFile(path.join(fixture, 'nested', 'stale.html'), '<html>Stable 1.1.0 is the current release.</html>');
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Unexpected Stable 1.1.0 claim/);
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

test('release claim checker rejects roadmap-as-stable source/artifact and metadata-only stale claims', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const sourceFixture = await mkdtemp(path.join(repositoryRoot, 'docs/guide/.release-claim-roadmap-'));
  const sourcePath = path.relative(repositoryRoot, path.join(sourceFixture, 'fixture.md'));
  const sourceAuthority = structuredClone(authority);
  sourceAuthority.pageCapabilities = { [sourcePath]: { lane: 'framework-package', capabilities: ['framework-core'] } };
  const sourceAuthorityPath = path.join(sourceFixture, 'authority.json');
  const contentMapPath = path.join(sourceFixture, 'content-map.mjs');
  try {
    await writeFile(path.join(sourceFixture, 'fixture.md'), '# Fixture\n\nLatest Experimental Stable 1.2.1 is documented here.\ncomposer create-project blackops/skeleton my-app 1.2.1\nLatest Experimental Stable 1.3.0として公開済みです。\n');
    await writeFile(contentMapPath, '');
    await writeFile(sourceAuthorityPath, JSON.stringify(sourceAuthority));
    await assert.rejects(() => assertSourceClaims({ authorityPath: sourceAuthorityPath, sourceDirectory: sourceFixture, contentMapPath }), /Roadmap release/);
  } finally {
    await rm(sourceFixture, { recursive: true, force: true });
  }

  const artifactFixture = await mkdtemp(path.join(tmpdir(), 'blackops-release-metadata-'));
  try {
    await writeFile(path.join(artifactFixture, 'metadata.html'), '<meta name="description" content="Stable 1.1.0 stale"><meta property="og:description" content="Stable 1.1.0 stale"><meta name="twitter:description" content="Stable 1.1.0 stale"><script type="application/ld+json">{"description":"Stable 1.1.0 stale"}</script>');
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: artifactFixture }), /Unexpected Stable 1.1.0 claim/);
    await writeFile(path.join(artifactFixture, 'metadata.html'), '<meta name="description" content="Latest Experimental Stable 1.3.0として公開済みです。">');
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: artifactFixture }), /Roadmap release/);
  } finally {
    await rm(artifactFixture, { recursive: true, force: true });
  }
});

test('artifact historical matching is exact after normalization', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const historical = authority.historicalReferences[0];
  assert.throws(
    () => assertOccurrences([{ path: 'fixture.html', heading: '', sentence: `${historical.normalizedSentence} suffix` }], authority, { source: false }),
    /Unexpected Stable 1.1.0 claim/,
  );
  assert.throws(
    () => assertOccurrences([{ path: 'fixture.html', heading: '', sentence: `prefix ${historical.normalizedSentence}` }], authority, { source: false }),
    /Unexpected Stable 1.1.0 claim/,
  );
});

test('full artifact checker preserves prefix/suffix boundaries across Search and LLM-like text', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const historical = authority.historicalReferences[0];
  const fixture = await mkdtemp(path.join(tmpdir(), 'blackops-release-exact-artifact-'));
  const expected = historical.normalizedSentence;
  try {
    await writeFile(path.join(fixture, 'search.json'), JSON.stringify([expected]));
    await writeFile(path.join(fixture, 'llms-full.txt'), expected);
    await assert.doesNotReject(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }));

    await writeFile(path.join(fixture, 'search.json'), JSON.stringify([`prefix ${expected}`]));
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Unexpected Stable 1.1.0 claim/);

    await writeFile(path.join(fixture, 'search.json'), JSON.stringify([`${expected.slice(0, -1)} suffix。`]));
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Unexpected Stable 1.1.0 claim/);

    await writeFile(path.join(fixture, 'search.json'), JSON.stringify([`${expected}\nStable 1.2.1 candidateです。`]));
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Stale candidate release claim/);

    await writeFile(path.join(fixture, 'search.json'), JSON.stringify([]));
    await writeFile(path.join(fixture, 'llms-full.txt'), `<p>${expected}</p><p>Stable 1.2.1 candidateです。</p>`);
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Stale candidate release claim/);
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

test('authority bump rejects candidate claims for the bumped version', async () => {
  const authority = await loadReleaseAuthority(authorityPath);
  const bumped = structuredClone(authority);
  bumped.currentStable.version = '1.3.0';
  bumped.currentStable.framework.tag = '1.3.0';
  bumped.currentStable.skeleton.tag = '1.3.0';
  bumped.roadmap.version = '1.4.0';
  const fixture = await mkdtemp(path.join(repositoryRoot, 'docs/guide/.release-claim-bump-'));
  const fixturePath = path.relative(repositoryRoot, path.join(fixture, 'fixture.md'));
  bumped.pageCapabilities = { [fixturePath]: { lane: 'framework-package', capabilities: ['framework-core'] } };
  const fixtureAuthorityPath = path.join(fixture, 'authority.json');
  const contentMapPath = path.join(fixture, 'content-map.mjs');
  try {
    await writeFile(path.join(fixture, 'fixture.md'), '# Fixture\n\nLatest Experimental Stable 1.3.0 is documented here.\ncomposer create-project blackops/skeleton my-app 1.3.0\n未公開の1.3.0です。\n');
    await writeFile(contentMapPath, '');
    await writeFile(fixtureAuthorityPath, JSON.stringify(bumped));
    await assert.rejects(() => assertSourceClaims({ authorityPath: fixtureAuthorityPath, sourceDirectory: fixture, contentMapPath }), /Stale candidate release claim/);
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

test('artifact claim guard separates minified search records before checking current phrases', async () => {
  const fixture = await mkdtemp(path.join(tmpdir(), 'blackops-release-search-'));
  try {
    await writeFile(
      path.join(fixture, 'blume-search.json'),
      JSON.stringify([
        { title: 'Stable／main境界' },
        { title: '公開済みExperimental Stable 1.2.1で案内します。' },
      ]),
    );
    assert.doesNotThrow(() => assertNoStaleCurrentPhrase('Stable／main境界', 'fixture-search.json'));
    assert.doesNotThrow(() => assertNoStaleCurrentPhrase('公開済みExperimental Stable 1.2.1で案内します。', 'fixture-search.json'));
    await assert.doesNotReject(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }));
    await writeFile(
      path.join(fixture, 'blume-search.json'),
      JSON.stringify([
        { title: 'Stable／main境界' },
        { title: 'Stable 1.1.0 is the current release.' },
      ]),
    );
    await assert.rejects(() => assertArtifactClaims({ authorityPath, artifactDirectory: fixture }), /Unexpected Stable 1.1.0 claim/);
  } finally {
    await rm(fixture, { recursive: true, force: true });
  }
});

function diagramManifestFixture() {
  const entry = ({ id, type, ownerSource, ownerRoute, svgPath }) => ({
    id,
    type,
    ownerSource,
    ownerRoute,
    sourcePath: `docs/website/public/diagrams/${id}.json`,
    htmlPath: `docs/website/public/diagrams/${id}.html`,
    pngPath: `docs/guide/assets/diagrams/${id}.png`,
    source: { path: `docs/website/public/diagrams/${id}.json`, sha256: null },
    html: { path: `docs/website/public/diagrams/${id}.html`, sha256: null },
    png: { path: `docs/guide/assets/diagrams/${id}.png`, sha256: null },
    ...(svgPath === undefined ? {} : { svg: { path: svgPath, sha256: null } }),
    receipts: { validate: null, deliver: null, visualCheck: null },
  });
  return {
    schemaVersion: 1,
    upstream: {
      repository: 'https://github.com/tt-a1i/archify',
      revision: '2ead014aa8ec91f104cd052f1a6ca82de5e26c31',
      version: '2.17.0-dev.1',
      updateChecks: 'disabled',
      templatePatch: {
        path: 'docs/website/diagrams/offline-fonts.patch',
        sha256: null,
        originalTemplateSha256: null,
        patchedTemplateSha256: null,
      },
    },
    regeneration: { validate: 'validate', deliver: 'deliver', exportPng: 'export' },
    diagrams: diagramContractsList().map(entry),
  };
}

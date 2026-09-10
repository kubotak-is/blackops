import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import {
  assertProductFramingArtifactContract,
  assertProductFramingSourceContract,
  APPLICATION_BOOTSTRAP_LIST_BOUNDARY_MARKERS,
  APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM,
  CLI_STABLE_LIST_BOUNDARY_MARKERS,
  normalizeVisibleText,
  P22_005E_TASK_PATH,
  SPEC_83_PATH,
} from '../scripts/product-framing-contract.mjs';
import { contentMap } from '../content-map.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guideRoot = path.join(repositoryRoot, 'docs/guide');
const websiteRoot = path.join(repositoryRoot, 'docs/website');
const sourceNames = ['README.md', 'why-blackops.md', 'project-cli.md', 'application-bootstrap.md', 'retention.md', 'journal.md', 'observability.md', 'security.md', 'glossary.md', 'mvp-sample.md'];

async function sourceFixture() {
  const documents = Object.fromEntries(await Promise.all(sourceNames.map(async (name) => [name, await readFile(path.join(guideRoot, name), 'utf8')])));
  return {
    documents,
    landingSource: await readFile(path.join(websiteRoot, 'pages/index.astro'), 'utf8'),
    themeSource: await readFile(path.join(websiteRoot, 'theme.css'), 'utf8'),
    retentionRuntimeSource: await readFile(path.join(repositoryRoot, 'src/Internal/Application/ApplicationConsoleKernel.php'), 'utf8'),
    spec83Source: await readFile(path.join(repositoryRoot, SPEC_83_PATH), 'utf8'),
    taskSource: await readFile(path.join(repositoryRoot, P22_005E_TASK_PATH), 'utf8'),
    repositoryPaths: await repositoryPathInventory(),
  };
}

async function repositoryPathInventory() {
  const paths = [];
  const ignoredDirectories = new Set(['.git', 'node_modules', 'vendor']);

  async function visit(directory, prefix = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) {
        if (ignoredDirectories.has(entry.name)) continue;
        await visit(path.join(directory, entry.name), relative);
      } else if (entry.isFile()) {
        paths.push(relative);
      }
    }
  }

  await visit(repositoryRoot);
  return paths.sort((left, right) => left.localeCompare(right, 'en'));
}

function artifactFixture(source) {
  const documents = source.documents;
  const boundary = [documents['journal.md'], documents['observability.md'], documents['security.md'], documents['glossary.md'], documents['mvp-sample.md']].join('\n');
  const cliMarkdown = documents['project-cli.md'];
  const cliHtml = htmlSurface(cliMarkdown);
  const bootstrapMarkdown = documents['application-bootstrap.md'];
  const bootstrapHtml = htmlSurface(bootstrapMarkdown);
  return {
    surfaces: new Map([
      ['landing-html', source.landingSource],
      ['landing-raw', documents['README.md']],
      ['landing-search', documents['README.md']],
      ['landing-llm', Object.values(documents).join('\n')],
      ['why-search', documents['why-blackops.md']],
      ['why-raw', documents['why-blackops.md']],
      ['why-html', documents['why-blackops.md']],
      ['why-llm', documents['why-blackops.md']],
      ['cli-search', cliMarkdown],
      ['cli-raw', cliMarkdown],
      ['cli-html', cliHtml],
      ['cli-llm', cliMarkdown],
      ['page:/reference/project-cli', cliHtml],
      ['raw:reference/project-cli.md', cliMarkdown],
      ['bootstrap-search', bootstrapMarkdown],
      ['bootstrap-raw', bootstrapMarkdown],
      ['bootstrap-html', bootstrapHtml],
      ['bootstrap-llm', bootstrapMarkdown],
      ['page:/reference/application-bootstrap', bootstrapHtml],
      ['raw:reference/application-bootstrap.md', bootstrapMarkdown],
      ['retention-search', documents['retention.md']],
      ['retention-raw', documents['retention.md']],
      ['retention-html', htmlSurface(documents['retention.md'])],
      ['retention-llm', documents['retention.md']],
      ['journal-search', boundary],
      ['journal-raw', boundary],
      ['journal-html', boundary],
      ['journal-llm', boundary],
      ['observability-search', documents['observability.md']],
      ['observability-raw', documents['observability.md']],
      ['observability-html', documents['observability.md']],
      ['observability-llm', documents['observability.md']],
    ]),
    css: source.themeSource,
    retentionRuntimeSource: source.retentionRuntimeSource,
    spec83Source: source.spec83Source,
    taskSource: source.taskSource,
    repositoryPaths: source.repositoryPaths,
  };
}

function productionBootstrapArtifactFixture(source) {
  const fixture = artifactFixture(source);
  for (const name of ['bootstrap-html', 'bootstrap-raw', 'bootstrap-search', 'bootstrap-llm']) {
    fixture.surfaces.delete(name);
  }
  const productionSearch = JSON.stringify([
    { route: '/', content: source.documents['README.md'] },
    { route: '/reference/application-bootstrap', content: source.documents['application-bootstrap.md'] },
    { route: '/database/retention', content: source.documents['retention.md'] },
  ]);
  const llmSegment = (heading, route, content) => {
    const body = content.replace(/^#[^\n]*\n(?:\n)?/u, '').trimStart();
    return `# ${heading}\nSource: https://blackops-php.pages.dev${route}\n\n${body}`;
  };
  const productionLlm = [
    llmSegment('BlackOps - The PHP Framework', '/', source.documents['README.md']),
    llmSegment('Application Bootstrap', '/reference/application-bootstrap', source.documents['application-bootstrap.md']),
    llmSegment('Retention', '/database/retention', source.documents['retention.md']),
  ].join('\n---\n\n');
  fixture.surfaces.set('search-all', productionSearch);
  fixture.surfaces.set('llm-full-all', productionLlm);
  return fixture;
}

const RETENTION_BOUNDARY = 'Project Rootの公開Retention Commandは4つの期間Optionだけを受け付けます。';
const RETENTION_FALLBACK_CLI = '`config/retention.php`の`idempotency_record_days`で管理し、省略時は4つの基本期間の最長値を使います。';
const RETENTION_FALLBACK_GUIDE = '`config/retention.php`の`idempotency_record_days`で管理し、省略した場合は4つの基本期間の最長値を使います。';
const RETENTION_OPTION = '--idempotency-record-days';
const RETENTION_ROUTE = '/database/retention';
const RETENTION_SOURCE = 'https://blackops-php.pages.dev/database/retention';

function retentionArtifactSurface(source, surface) {
  if (surface === 'html') return htmlSurface(source.documents['retention.md']);
  return source.documents['retention.md'];
}

function productionRetentionArtifactFixture(source) {
  const fixture = productionBootstrapArtifactFixture(source);
  for (const name of ['retention-html', 'retention-raw', 'retention-search', 'retention-llm']) fixture.surfaces.delete(name);
  fixture.surfaces.set('page:/database/retention', htmlSurface(source.documents['retention.md']));
  fixture.surfaces.set('raw:database/retention.md', source.documents['retention.md']);
  return fixture;
}

function removeRetentionMarker(text, marker) {
  const visibleMarker = marker.replaceAll('`', '');
  const sourceMarker = text.includes(marker) ? marker : visibleMarker;
  if (!text.includes(sourceMarker)) throw new Error(`Fixture cannot locate Retention marker: ${marker}`);
  return text.replaceAll(sourceMarker, '');
}

function htmlifyGuide(markdown) {
  return markdown
    .replace(/`([^`]+)`/gu, '<code>$1</code>')
    .replace(/\r?\n/gu, '<br>');
}

function encodeAsciiEntities(text, radix) {
  return [...text].map((character) => {
    const codePoint = character.codePointAt(0);
    if (codePoint < 0x21 || codePoint > 0x7e) return character;
    const digits = codePoint.toString(radix === 'hex' ? 16 : 10);
    return radix === 'hex' ? `&#x${digits.toUpperCase()};` : `&#${digits};`;
  }).join('');
}

function htmlSurface(markdown) {
  return htmlifyGuide(markdown);
}

function encodeHtmlLiteral(html, literal, radix) {
  const encoded = encodeAsciiEntities(literal, radix);
  if (!html.includes(literal)) throw new Error(`Fixture cannot locate HTML literal: ${literal}`);
  return html.replace(literal, encoded);
}

function removeStableListBoundary(surface, surfaceName, marker) {
  if (surfaceName === 'html') return surface.replace(/そのためGlobal[\s\S]*?診断Commandではありません。/u, '');
  return surface.replace(marker, '');
}

function splitAtExistingSpace(surface, surfaceName, value) {
  if (!surface.includes(value)) throw new Error(`Fixture cannot locate split value: ${value}`);
  const replacement = surfaceName === 'html' ? '</p><p>' : '\n';
  return surface.replace(value, value.replace(' ', replacement));
}

test('product framing source and artifact contracts accept the current bounded surfaces', async () => {
  const source = await sourceFixture();
  assert.doesNotThrow(() => assertProductFramingSourceContract({ ...source, contentMap }));
  assert.doesNotThrow(() => assertProductFramingArtifactContract(artifactFixture(source)));
});

test('Retention current truth is required independently in every CLI and page surface', async () => {
  const source = await sourceFixture();
  for (const surface of ['html', 'raw', 'search', 'llm']) {
    for (const [marker, message] of [
      [RETENTION_BOUNDARY, 'current four-option boundary'],
      [RETENTION_FALLBACK_CLI, 'CLI omission fallback'],
    ]) {
      const cliFixture = artifactFixture(source);
      const cliName = `cli-${surface}`;
      const cliText = cliFixture.surfaces.get(cliName);
      const cliMutated = surface === 'html'
        ? htmlSurface(removeRetentionMarker(source.documents['project-cli.md'], marker))
        : removeRetentionMarker(cliText, marker);
      cliFixture.surfaces.set(cliName, cliMutated);
      assert.throws(
        () => assertProductFramingArtifactContract(cliFixture),
        /current four-option boundary|omission fallback/,
        `CLI ${surface} must reject a missing Retention ${message}`,
      );

    }

    for (const [marker, message] of [
      [RETENTION_BOUNDARY, 'current four-option boundary'],
      [RETENTION_FALLBACK_GUIDE, 'guide omission fallback'],
    ]) {
      const retentionFixture = artifactFixture(source);
      const retentionName = `retention-${surface}`;
      const retentionText = retentionArtifactSurface(source, surface);
      const retentionMutated = surface === 'html'
        ? htmlSurface(removeRetentionMarker(source.documents['retention.md'], marker))
        : removeRetentionMarker(retentionText, marker);
      retentionFixture.surfaces.set(retentionName, retentionMutated);
      assert.throws(
        () => assertProductFramingArtifactContract(retentionFixture),
        /current four-option boundary|omission fallback/,
        `Retention ${surface} must reject a missing Retention ${message}`,
      );
    }
  }

  for (const [name, surface] of [['cli-raw', 'CLI'], ['retention-raw', 'Retention guide']]) {
    const unpublished = artifactFixture(source);
    unpublished.surfaces.set(name, `${unpublished.surfaces.get(name)}\n${RETENTION_OPTION}`);
    assert.throws(
      () => assertProductFramingArtifactContract(unpublished),
      /unpublished Retention option|publishes an inner-command-only retention option|exposes the unpublished idempotency option/,
      `${surface} must reject the unpublished idempotency option`,
    );
  }
});

test('Retention production aggregates bind search to one route and LLM to one page segment', async () => {
  const source = await sourceFixture();
  const production = productionRetentionArtifactFixture(source);
  assert.doesNotThrow(() => assertProductFramingArtifactContract(production));

  const searchRecords = JSON.parse(production.surfaces.get('search-all'));
  const withoutRoute = productionRetentionArtifactFixture(source);
  withoutRoute.surfaces.set('search-all', JSON.stringify(searchRecords.filter((record) => record.route !== RETENTION_ROUTE)));
  assert.throws(
    () => assertProductFramingArtifactContract(withoutRoute),
    /exactly one matching route/,
    'missing Retention search route must fail closed',
  );

  const wrongRoute = productionRetentionArtifactFixture(source);
  wrongRoute.surfaces.set('search-all', JSON.stringify(searchRecords.map((record) => record.route === RETENTION_ROUTE ? { ...record, route: '/database/retention-copy' } : record)));
  assert.throws(
    () => assertProductFramingArtifactContract(wrongRoute),
    /exactly one matching route/,
    'wrong Retention search route must fail closed',
  );

  const duplicateRoute = productionRetentionArtifactFixture(source);
  duplicateRoute.surfaces.set('search-all', JSON.stringify([...searchRecords, searchRecords.find((record) => record.route === RETENTION_ROUTE)]));
  assert.throws(
    () => assertProductFramingArtifactContract(duplicateRoute),
    /exactly one matching route/,
    'duplicate Retention search route must fail closed',
  );

  const llm = production.surfaces.get('llm-full-all');
  const llmSegments = llm.split(/\n---\n\n(?=# )/u);
  const retentionSegment = llmSegments.find((segment) => segment.startsWith('# Retention\n'));
  assert.ok(retentionSegment);

  const withoutSegment = productionRetentionArtifactFixture(source);
  withoutSegment.surfaces.set('llm-full-all', llmSegments.filter((segment) => segment !== retentionSegment).join('\n---\n\n'));
  assert.throws(
    () => assertProductFramingArtifactContract(withoutSegment),
    /exactly one # Retention segment/,
    'missing Retention LLM segment must fail closed',
  );

  const wrongSource = productionRetentionArtifactFixture(source);
  wrongSource.surfaces.set('llm-full-all', llm.replace(RETENTION_SOURCE, 'https://blackops-php.pages.dev/database/retention-copy'));
  assert.throws(
    () => assertProductFramingArtifactContract(wrongSource),
    /missing or incorrect Source line/,
    'wrong Retention LLM Source must fail closed',
  );

  const duplicateSegment = productionRetentionArtifactFixture(source);
  duplicateSegment.surfaces.set('llm-full-all', `${llm}\n---\n\n${retentionSegment}`);
  assert.throws(
    () => assertProductFramingArtifactContract(duplicateSegment),
    /exactly one # Retention segment/,
    'duplicate Retention LLM segment must fail closed',
  );
});

test('product framing source contract rejects old value, vague outcome, audit mapping, and audit authority', async () => {
  const source = await sourceFixture();
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, landingSource: source.landingSource.replace('HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。', 'HTTP、Deferred Worker、Journalを一つのOperation Modelで組み立てるためのFrameworkです。') }),
    /old Landing value|forbidden product/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'why-blackops.md': source.documents['why-blackops.md'].replace('同じIDで受付・再試行・完了を確認できる', '同期／非同期のOperationを一貫して追跡できるか判断する。') } }),
    /vague reader outcome|ordered marker|Why BlackOps outcome/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'why-blackops.md': source.documents['why-blackops.md'] + '\nAudit Log / Process History | Journal\n' } }),
    /Audit Log \/ Process History/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'journal.md': source.documents['journal.md'] + '\n監査正本\n' } }),
    /unqualified audit authority/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'why-blackops.md': source.documents['why-blackops.md'].replace('汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します。Retention／Replay／Rotationなどの個別運用Eventは、Lifecycle Journalとは別のFramework運用契約で扱います。', '汎用Business／Security Audit TrailはApplicationが所有します。Retention／Replay／Rotationなどの個別運用EventはApplicationが所有します。') } }),
    /Why BlackOps application audit boundary|Why BlackOps operational audit boundary|operational audit ownership boundary/,
  );
});

test('product framing source contract rejects roadmap CLI and visual/accessibility drift', async () => {
  const source = await sourceFixture();
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + '\nStable 1.2.1 current: route:list\n' } }),
    /roadmap command/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, landingSource: source.landingSource.replace('landing-demo', 'retired-hero') }),
    /Landing visual marker/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, themeSource: source.themeSource + '\n.landing-shell { background: linear-gradient(red, blue); }\n' }),
    /forbidden visual/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, themeSource: source.themeSource + '\n.retired-surface { background: radial-gradient(red, blue); }\n' }),
    /ambient gradient/,
  );
  const generatedAmbient = '@supports (color:color-mix(in lab, red, red)){.landing-shell:before{background:radial-gradient(circle at 78% 9%,var(--landing-accent),transparent 34rem)}}';
  const compiledAmbient = artifactFixture(source);
  compiledAmbient.css += generatedAmbient;
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(compiledAmbient),
    'the minified color-mix support wrapper around the landing ambient rule is permitted',
  );
  const generatedBackdropAmbient = [
    '.landing-backdrop__ribbon{background-image:radial-gradient(ellipse 49% 22% at 50% 50%,color-mix(in srgb,var(--landing-accent) 66%,transparent) 0%,color-mix(in srgb,var(--landing-accent) 48%,transparent) 22%,color-mix(in srgb,var(--landing-accent) 26%,transparent) 48%,color-mix(in srgb,var(--landing-accent) 8%,transparent) 76%,transparent 100%),radial-gradient(ellipse 50% 50% at 50% 50%,color-mix(in srgb,var(--landing-accent) 28%,transparent) 0%,color-mix(in srgb,var(--landing-accent) 22%,transparent) 20%,color-mix(in srgb,var(--landing-accent) 13%,transparent) 46%,color-mix(in srgb,var(--landing-accent) 5%,transparent) 72%,transparent 94%)}',
    '.landing-backdrop__ribbon--edge{background-image:radial-gradient(ellipse 50% 50% at 50% 50%,color-mix(in srgb,var(--landing-accent) 48%,transparent) 0%,color-mix(in srgb,var(--landing-accent) 30%,transparent) 28%,color-mix(in srgb,var(--landing-accent) 9%,transparent) 72%,transparent 94%)}',
  ].join('');
  const compiledBackdropAmbient = artifactFixture(source);
  compiledBackdropAmbient.css += generatedBackdropAmbient;
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(compiledBackdropAmbient),
    'the final backdrop ribbon gradients remain scoped to approved decorative selectors',
  );
  const compiledUnscopedAmbient = artifactFixture(source);
  compiledUnscopedAmbient.css += generatedAmbient.replace('.landing-shell:before', '.retired-surface');
  assert.throws(
    () => assertProductFramingArtifactContract(compiledUnscopedAmbient),
    /ambient gradient/,
    'the same generated wrapper still rejects an unrelated ambient rule',
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, landingSource: source.landingSource.replace('<div class="landing-resource-group">', '<div class="landing-resource-group"><strong>Succeeded</strong>') }),
    /Succeeded as a Lifecycle state/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'journal.md': source.documents['journal.md'].replace('HTTPの受付からWorkerの再試行まで', 'HTTP受付からWorker再試行まで') } }),
    /Journal newcomer concept order|ordered marker/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'].replace('Helpが本文表の全Optionを必ず列挙するとは限りません。', 'Optionの全量とDefaultは必ずHelpへ委ねます。') } }),
    /CLI Help limitation|CLI option reference boundary/,
  );
});

test('CLI list truth is required across normalized Source and Artifact surfaces', async () => {
  const source = await sourceFixture();
  const oldClaim = 'Global `list`とOperation Commandの`help`はManifest Metadataだけを使い、Handler、Database、Container、Actor Providerを解決しません。';
  const missingBoundary = 'そのためGlobal `list`は、Applicationの初期化やContainerの解決から完全に切り離された診断Commandではありません。';

  const oldSource = {
    ...source,
    documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + '\n' + oldClaim },
  };
  assert.throws(
    () => assertProductFramingSourceContract({ ...oldSource, contentMap }),
    /old absolute side-effect-free claim/,
  );

  const missingSource = {
    ...source,
    documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'].replace(missingBoundary, '') },
  };
  assert.throws(
    () => assertProductFramingSourceContract({ ...missingSource, contentMap }),
    /Stable list boundary/,
  );

  for (const surface of ['html', 'raw', 'search', 'llm']) {
    const artifactSurfaceWithOldClaim = artifactFixture(source);
    const currentSurface = surface === 'html' ? htmlSurface(source.documents['project-cli.md']) : source.documents['project-cli.md'];
    artifactSurfaceWithOldClaim.surfaces.set(`cli-${surface}`, `${currentSurface}\n${surface === 'html' ? `<p>${oldClaim}</p>` : oldClaim}`);
    assert.throws(
      () => assertProductFramingArtifactContract(artifactSurfaceWithOldClaim),
      /old absolute side-effect-free claim/,
      `old claim must be rejected in ${surface}`,
    );

    const artifactSurfaceMissingBoundary = artifactFixture(source);
    artifactSurfaceMissingBoundary.surfaces.set(`cli-${surface}`, removeStableListBoundary(currentSurface, surface, missingBoundary));
    assert.throws(
      () => assertProductFramingArtifactContract(artifactSurfaceMissingBoundary),
      /Stable list boundary/,
      `missing boundary must be rejected in ${surface}`,
    );

    const artifactMissingSurface = artifactFixture(source);
    artifactMissingSurface.surfaces.delete(`cli-${surface}`);
    assert.throws(
      () => assertProductFramingArtifactContract(artifactMissingSurface),
      new RegExp(`missing the CLI ${surface} artifact surface`),
      `missing ${surface} surface must be rejected`,
    );
  }

  const unknownCliSurface = artifactFixture(source);
  unknownCliSurface.surfaces.set('cli-side-channel', source.documents['project-cli.md']);
  assert.throws(
    () => assertProductFramingArtifactContract(unknownCliSurface),
    /Unknown CLI artifact surface kind/,
    'unknown CLI surface keys must fail closed',
  );

  const currentHtml = htmlSurface(source.documents['project-cli.md']);
  const sameHtmlHiddenOldClaim = artifactFixture(source);
  const hiddenOldClaimHtml = `${currentHtml}<script>${oldClaim}</script>`;
  sameHtmlHiddenOldClaim.surfaces.set('cli-html', hiddenOldClaimHtml);
  sameHtmlHiddenOldClaim.surfaces.set('page:/reference/project-cli', hiddenOldClaimHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(sameHtmlHiddenOldClaim),
    'the same HTML in primary and page alias accepts a hidden old claim',
  );

  const sameHtmlHiddenRequired = artifactFixture(source);
  const hiddenRequiredHtml = '<script>' + source.documents['project-cli.md'] + '</script>';
  sameHtmlHiddenRequired.surfaces.set('cli-html', hiddenRequiredHtml);
  sameHtmlHiddenRequired.surfaces.set('page:/reference/project-cli', hiddenRequiredHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(sameHtmlHiddenRequired),
    /Stable list boundary/,
    'the same hidden HTML in primary and page alias rejects required-only markers',
  );

  const sameHtmlVisibleOldClaim = artifactFixture(source);
  const visibleOldClaimHtml = `${currentHtml}<p>${oldClaim}</p>`;
  sameHtmlVisibleOldClaim.surfaces.set('cli-html', visibleOldClaimHtml);
  sameHtmlVisibleOldClaim.surfaces.set('page:/reference/project-cli', visibleOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(sameHtmlVisibleOldClaim),
    /old absolute side-effect-free claim/,
    'the same visible old claim in primary and page alias is rejected',
  );

  const sameHtmlVisibleCurrent = artifactFixture(source);
  sameHtmlVisibleCurrent.surfaces.set('cli-html', currentHtml);
  sameHtmlVisibleCurrent.surfaces.set('page:/reference/project-cli', currentHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(sameHtmlVisibleCurrent),
    'the same visible current HTML in primary and page alias is accepted',
  );

  const pairedPlainHiddenOldClaim = artifactFixture(source);
  const plainHiddenOldClaimHtml = `${currentHtml}<div hidden>${oldClaim}</div>`;
  pairedPlainHiddenOldClaim.surfaces.set('cli-html', plainHiddenOldClaimHtml);
  pairedPlainHiddenOldClaim.surfaces.set('page:/reference/project-cli', plainHiddenOldClaimHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(pairedPlainHiddenOldClaim),
    'plain hidden old claim is ignored in paired HTML surfaces',
  );

  for (const display of ['block', 'block!important']) {
    const pairedOverrideOldClaim = artifactFixture(source);
    const overrideOldClaimHtml = `${currentHtml}<div hidden style="display:${display}">${oldClaim}</div>`;
    pairedOverrideOldClaim.surfaces.set('cli-html', overrideOldClaimHtml);
    pairedOverrideOldClaim.surfaces.set('page:/reference/project-cli', overrideOldClaimHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(pairedOverrideOldClaim),
      /old absolute side-effect-free claim/,
      `hidden ${display} override keeps old claim visible in paired HTML surfaces`,
    );

    const pairedOverrideRequired = artifactFixture(source);
    const overrideRequiredHtml = `<div hidden style="display:${display}">${currentHtml}</div>`;
    pairedOverrideRequired.surfaces.set('cli-html', overrideRequiredHtml);
    pairedOverrideRequired.surfaces.set('page:/reference/project-cli', overrideRequiredHtml);
    assert.doesNotThrow(
      () => assertProductFramingArtifactContract(pairedOverrideRequired),
      `hidden ${display} override preserves required markers in paired HTML surfaces`,
    );
  }

  const headOnlyMarkers = artifactFixture(source);
  const headOnlyHtml = `<!doctype html><html><head><title>${source.documents['project-cli.md']}</title></head><body></body></html>`;
  headOnlyMarkers.surfaces.set('cli-html', headOnlyHtml);
  headOnlyMarkers.surfaces.set('page:/reference/project-cli', headOnlyHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(headOnlyMarkers),
    /Stable list boundary/,
    'required markers found only in head/title must be rejected in paired HTML surfaces',
  );

  const headOnlyOldClaim = artifactFixture(source);
  const headOnlyOldClaimHtml = `<!doctype html><html><head><title>${oldClaim}</title></head><body>${currentHtml}</body></html>`;
  headOnlyOldClaim.surfaces.set('cli-html', headOnlyOldClaimHtml);
  headOnlyOldClaim.surfaces.set('page:/reference/project-cli', headOnlyOldClaimHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(headOnlyOldClaim),
    'an old claim confined to head/title must not contaminate paired visible HTML',
  );

  const initialVisible = artifactFixture(source);
  const initialVisibleHtml = `<div style="visibility:hidden"><span style="visibility:initial">${source.documents['project-cli.md']}</span></div>`;
  initialVisible.surfaces.set('cli-html', initialVisibleHtml);
  initialVisible.surfaces.set('page:/reference/project-cli', initialVisibleHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(initialVisible),
    'visibility:initial restores the visible required-marker subtree',
  );

  const initialVisibleOldClaim = artifactFixture(source);
  const initialVisibleOldClaimHtml = `<div style="visibility:hidden"><span style="visibility:initial">${oldClaim}</span></div>${currentHtml}`;
  initialVisibleOldClaim.surfaces.set('cli-html', initialVisibleOldClaimHtml);
  initialVisibleOldClaim.surfaces.set('page:/reference/project-cli', initialVisibleOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(initialVisibleOldClaim),
    /old absolute side-effect-free claim/,
    'visibility:initial old claim remains visible and is rejected in paired HTML surfaces',
  );

  const detailsCurrent = artifactFixture(source);
  const detailsHtml = `${CLI_STABLE_LIST_BOUNDARY_MARKERS.map((marker) => `<details><summary>${marker}</summary></details>`).join('')}${currentHtml}`;
  detailsCurrent.surfaces.set('cli-html', detailsHtml);
  detailsCurrent.surfaces.set('page:/reference/project-cli', detailsHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(detailsCurrent),
    'details/summary boundaries preserve each required marker in paired HTML surfaces',
  );

  const detailsSplit = artifactFixture(source);
  const splitMarker = CLI_STABLE_LIST_BOUNDARY_MARKERS[0];
  const splitAt = splitMarker.indexOf(' ');
  const currentWithoutFirst = htmlSurface(source.documents['project-cli.md'].replace('Global `list`は、現在のApplicationで利用できるCommandを目的別に確認する入口です。一覧で名前と概要を確認してから、必要なCommandの個別Helpへ進みます。', ''));
  const detailsRemainder = detailsHtml.slice(detailsHtml.indexOf('</details>') + '</details>'.length).replace(currentHtml, currentWithoutFirst);
  const splitDetailsHtml = `<details><summary>${splitMarker.slice(0, splitAt)}</summary></details><details><summary>${splitMarker.slice(splitAt + 1)}</summary></details>${detailsRemainder}`;
  detailsSplit.surfaces.set('cli-html', splitDetailsHtml);
  detailsSplit.surfaces.set('page:/reference/project-cli', splitDetailsHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(detailsSplit),
    /Stable list boundary|ordered marker/,
    'details/summary boundaries must not concatenate a split required marker',
  );

  const nativeDetailsCases = [
    ['closed details', (value) => `<details><summary>Summary</summary><div>${value}</div></details>`],
    ['open details', (value) => `<details open><summary>Summary</summary><div>${value}</div></details>`],
  ];
  for (const [caseName, wrapper] of nativeDetailsCases) {
    const hiddenRequired = artifactFixture(source);
    const hiddenRequiredHtml = wrapper(source.documents['project-cli.md']);
    hiddenRequired.surfaces.set('cli-html', hiddenRequiredHtml);
    hiddenRequired.surfaces.set('page:/reference/project-cli', hiddenRequiredHtml);
    if (caseName === 'closed details') {
      assert.throws(
        () => assertProductFramingArtifactContract(hiddenRequired),
        /Stable list boundary/,
        `${caseName} body-only required markers must be rejected in paired HTML surfaces`,
      );
    } else {
      assert.doesNotThrow(
        () => assertProductFramingArtifactContract(hiddenRequired),
        `${caseName} required markers must remain visible in paired HTML surfaces`,
      );
    }
  }

  const closedDetailsOldClaim = artifactFixture(source);
  const closedDetailsOldClaimHtml = `${currentHtml}<details><summary>Summary</summary><div>${oldClaim}</div></details>`;
  closedDetailsOldClaim.surfaces.set('cli-html', closedDetailsOldClaimHtml);
  closedDetailsOldClaim.surfaces.set('page:/reference/project-cli', closedDetailsOldClaimHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(closedDetailsOldClaim),
    'a closed details body old claim must not contaminate visible paired HTML',
  );

  const openDetailsOldClaim = artifactFixture(source);
  const openDetailsOldClaimHtml = `${currentHtml}<details open><summary>Summary</summary><div>${oldClaim}</div></details>`;
  openDetailsOldClaim.surfaces.set('cli-html', openDetailsOldClaimHtml);
  openDetailsOldClaim.surfaces.set('page:/reference/project-cli', openDetailsOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(openDetailsOldClaim),
    /old absolute side-effect-free claim/,
    'an open details old claim must be rejected in paired HTML surfaces',
  );

  const detailsSummaryOldClaim = artifactFixture(source);
  const detailsSummaryOldClaimHtml = `${currentHtml}<details><summary>${oldClaim}</summary><div>Body</div></details>`;
  detailsSummaryOldClaim.surfaces.set('cli-html', detailsSummaryOldClaimHtml);
  detailsSummaryOldClaim.surfaces.set('page:/reference/project-cli', detailsSummaryOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(detailsSummaryOldClaim),
    /old absolute side-effect-free claim/,
    'the first direct details summary old claim must be rejected in paired HTML surfaces',
  );

  const closedDetailsVisibilityOverride = artifactFixture(source);
  const closedDetailsVisibilityOverrideHtml = `<details style="visibility:hidden"><summary style="visibility:visible">Summary</summary><div style="visibility:visible">${oldClaim}</div></details>${currentHtml}`;
  closedDetailsVisibilityOverride.surfaces.set('cli-html', closedDetailsVisibilityOverrideHtml);
  closedDetailsVisibilityOverride.surfaces.set('page:/reference/project-cli', closedDetailsVisibilityOverrideHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(closedDetailsVisibilityOverride),
    'closed details body remains hidden even when its descendants override visibility',
  );

  const closedDetailsVisibleSummaryOldClaim = artifactFixture(source);
  const closedDetailsVisibleSummaryOldClaimHtml = `<details style="visibility:hidden"><summary style="visibility:visible">${oldClaim}</summary><div style="visibility:visible">Body</div></details>${currentHtml}`;
  closedDetailsVisibleSummaryOldClaim.surfaces.set('cli-html', closedDetailsVisibleSummaryOldClaimHtml);
  closedDetailsVisibleSummaryOldClaim.surfaces.set('page:/reference/project-cli', closedDetailsVisibleSummaryOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(closedDetailsVisibleSummaryOldClaim),
    /old absolute side-effect-free claim/,
    'a visible first direct summary old claim must be rejected in paired HTML surfaces',
  );

  const closedDialogRequired = artifactFixture(source);
  const closedDialogRequiredHtml = `<dialog>${source.documents['project-cli.md']}</dialog>`;
  closedDialogRequired.surfaces.set('cli-html', closedDialogRequiredHtml);
  closedDialogRequired.surfaces.set('page:/reference/project-cli', closedDialogRequiredHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(closedDialogRequired),
    /Stable list boundary/,
    'a plain closed dialog must hide body-only required markers in paired HTML surfaces',
  );

  const openDialogOldClaim = artifactFixture(source);
  const openDialogOldClaimHtml = `${currentHtml}<dialog open>${oldClaim}</dialog>`;
  openDialogOldClaim.surfaces.set('cli-html', openDialogOldClaimHtml);
  openDialogOldClaim.surfaces.set('page:/reference/project-cli', openDialogOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(openDialogOldClaim),
    /old absolute side-effect-free claim/,
    'an open dialog old claim must be rejected in paired HTML surfaces',
  );

  const closedDialogOverrideOldClaim = artifactFixture(source);
  const closedDialogOverrideOldClaimHtml = `${currentHtml}<dialog style="display:block!important">${oldClaim}</dialog>`;
  closedDialogOverrideOldClaim.surfaces.set('cli-html', closedDialogOverrideOldClaimHtml);
  closedDialogOverrideOldClaim.surfaces.set('page:/reference/project-cli', closedDialogOverrideOldClaimHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(closedDialogOverrideOldClaim),
    /old absolute side-effect-free claim/,
    'an inline non-none display override must expose a closed dialog old claim',
  );

  const closedDialogDisplayNoneOldClaim = artifactFixture(source);
  const closedDialogDisplayNoneOldClaimHtml = `${currentHtml}<dialog style="display:none">${oldClaim}</dialog>`;
  closedDialogDisplayNoneOldClaim.surfaces.set('cli-html', closedDialogDisplayNoneOldClaimHtml);
  closedDialogDisplayNoneOldClaim.surfaces.set('page:/reference/project-cli', closedDialogDisplayNoneOldClaimHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(closedDialogDisplayNoneOldClaim),
    'display:none keeps a closed dialog old claim hidden in paired HTML surfaces',
  );

  const sameRawLiteralOldClaim = artifactFixture(source);
  const literalOldClaimRaw = `${source.documents['project-cli.md']}\n<script>${oldClaim}</script>`;
  sameRawLiteralOldClaim.surfaces.set('cli-raw', literalOldClaimRaw);
  sameRawLiteralOldClaim.surfaces.set('raw:reference/project-cli.md', literalOldClaimRaw);
  assert.throws(
    () => assertProductFramingArtifactContract(sameRawLiteralOldClaim),
    /old absolute side-effect-free claim/,
    'the same literal-tag raw content in primary and alias is rejected',
  );

  for (const unknownAlias of ['page:/reference/project-cli-copy', 'raw:reference/project-cli-copy.md']) {
    const unknownAliasArtifact = artifactFixture(source);
    unknownAliasArtifact.surfaces.set(unknownAlias, source.documents['project-cli.md']);
    assert.throws(
      () => assertProductFramingArtifactContract(unknownAliasArtifact),
      /Unknown CLI artifact surface kind/,
      `unknown alias ${unknownAlias} must fail closed`,
    );
  }

  for (const radix of ['decimal', 'hex']) {
    const numericRequiredMarkers = artifactFixture(source);
    const numericHtml = htmlSurface(source.documents['project-cli.md']);
    numericRequiredMarkers.surfaces.set('cli-html', encodeHtmlLiteral(encodeHtmlLiteral(numericHtml, '--raw', radix), 'Definition', radix));
    assert.doesNotThrow(() => assertProductFramingArtifactContract(numericRequiredMarkers), `numeric ${radix} markers must preserve visible text`);
  }

  for (const manifest of ['M&#97;nifest', 'M&#x61;nifest']) {
    const encodedOldClaim = oldClaim.replace('Manifest', manifest);
    const numericOldClaim = artifactFixture(source);
    numericOldClaim.surfaces.set('cli-html', `${htmlSurface(source.documents['project-cli.md'])}<p>${encodedOldClaim}</p>`);
    assert.throws(
      () => assertProductFramingArtifactContract(numericOldClaim),
      /old absolute side-effect-free claim/,
      `numeric old claim ${manifest} must be rejected in HTML Artifact`,
    );
  }

  const namedEntityAndInlineSpan = artifactFixture(source);
  namedEntityAndInlineSpan.surfaces.set(
    'cli-html',
    htmlSurface(source.documents['project-cli.md']).replace('Global <code>list</code>', 'Global&nbsp;<span>list</span>'),
  );
  assert.doesNotThrow(() => assertProductFramingArtifactContract(namedEntityAndInlineSpan));

  for (const entity of ['&#0;', '&#xD800;', '&#x110000;', '&#xZZ;', '&#97']) {
    const invalidNumericReference = artifactFixture(source);
    invalidNumericReference.surfaces.set('cli-html', `${htmlSurface(source.documents['project-cli.md'])}<p>${entity}</p>`);
    assert.throws(
      () => assertProductFramingArtifactContract(invalidNumericReference),
      /Invalid numeric character reference/,
      `invalid numeric reference ${entity} must fail closed`,
    );
  }
  assert.equal(normalizeVisibleText('M&#97;nifest'), 'Manifest');
  assert.equal(normalizeVisibleText('M&#x61;nifest'), 'Manifest');

  const defaultParagraph = 'Command Manifestから発見したLazy Commandは、通常表示と`--raw`では、Command実体を作る処理（factory）を呼び出さずに一覧します。`--raw`は装飾を省いたテキスト一覧です。';
  const structuredParagraph = '`--short`を付けない`--format=json|xml|md`では、引数とOptionの定義（Definition）やHelp取得でCommand実体を作る処理（factory）が呼び出されます。そのfactoryがBuild済みの依存関係を持つ仕組み（Container）からCommand実体を解決する場合があります。個別の`help`も同じようにLazy Commandを解決する場合があります。';
  const reordered = artifactFixture(source);
  reordered.surfaces.set('cli-html', htmlSurface(source.documents['project-cli.md'].replace(`${defaultParagraph}\n\n${structuredParagraph}`, `${structuredParagraph}\n\n${defaultParagraph}`)));
  assert.throws(() => assertProductFramingArtifactContract(reordered), /order|ordered marker/);

  const blockSplit = artifactFixture(source);
  blockSplit.surfaces.set('cli-html', htmlSurface(source.documents['project-cli.md'].replace('通常表示と`--raw`', '通常表示と<div>`--raw`')));
  assert.throws(() => assertProductFramingArtifactContract(blockSplit), /Stable list boundary/);

  for (const surface of ['html', 'raw', 'search', 'llm']) {
    const currentSurface = surface === 'html' ? htmlSurface(source.documents['project-cli.md']) : source.documents['project-cli.md'];
    const splitRequiredMarker = artifactFixture(source);
    splitRequiredMarker.surfaces.set(`cli-${surface}`, splitAtExistingSpace(currentSurface, surface, 'Command Manifest'));
    assert.throws(
      () => assertProductFramingArtifactContract(splitRequiredMarker),
      /Stable list boundary|ordered marker/,
      `required marker split must be rejected in ${surface}`,
    );

    const splitOldClaim = artifactFixture(source);
    const splitClaim = splitAtExistingSpace(oldClaim, surface, 'Manifest Metadata');
    splitOldClaim.surfaces.set(
      `cli-${surface}`,
      `${currentSurface}${surface === 'html' ? `<p>${splitClaim}</p>` : `\n${splitClaim}`}`,
    );
    assert.throws(
      () => assertProductFramingArtifactContract(splitOldClaim),
      /old absolute side-effect-free claim/,
      `old claim split across blocks must be rejected in ${surface}`,
    );
  }

  const transformedHtml = artifactFixture(source);
  transformedHtml.surfaces.set('cli-html', htmlSurface(source.documents['project-cli.md']));
  assert.doesNotThrow(() => assertProductFramingArtifactContract(transformedHtml));

  for (const [kind, wrapper] of [
    ['comment', (value) => `<!--${value}-->`],
    ['script', (value) => `<script>${value}</script>`],
    ['style', (value) => `<style>${value}</style>`],
    ['template', (value) => `<template>${value}</template>`],
  ]) {
    const hidden = artifactFixture(source);
    hidden.surfaces.set('cli-html', wrapper(source.documents['project-cli.md']));
    assert.throws(
      () => assertProductFramingArtifactContract(hidden),
      /Stable list boundary/,
      `required markers only inside a non-visible ${kind} must be rejected`,
    );
  }

  const hiddenOldClaim = artifactFixture(source);
  hiddenOldClaim.surfaces.set(
    'cli-html',
    `${htmlSurface(source.documents['project-cli.md'])}<script>${oldClaim}</script><!-- ${oldClaim} -->`,
  );
  assert.doesNotThrow(() => assertProductFramingArtifactContract(hiddenOldClaim));

  const hardHiddenMechanisms = [
    ['hidden', (value) => `<div hidden>${value}</div>`],
    ['display:none', (value) => `<div style="display: none">${value}</div>`],
    ['display:none!important', (value) => `<div style="display: none !important">${value}</div>`],
    ['content-visibility:hidden', (value) => `<div style="content-visibility: hidden">${value}</div>`],
  ];
  for (const [mechanism, wrapper] of hardHiddenMechanisms) {
    const hiddenRequired = artifactFixture(source);
    const hiddenRequiredHtml = wrapper(source.documents['project-cli.md']);
    hiddenRequired.surfaces.set('cli-html', hiddenRequiredHtml);
    hiddenRequired.surfaces.set('page:/reference/project-cli', hiddenRequiredHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(hiddenRequired),
      /Stable list boundary/,
      `${mechanism} required markers only must be rejected in paired HTML surfaces`,
    );

    const hiddenOld = artifactFixture(source);
    const hiddenOldHtml = `${currentHtml}${wrapper(oldClaim)}`;
    hiddenOld.surfaces.set('cli-html', hiddenOldHtml);
    hiddenOld.surfaces.set('page:/reference/project-cli', hiddenOldHtml);
    assert.doesNotThrow(
      () => assertProductFramingArtifactContract(hiddenOld),
      `${mechanism} hidden old claim must not contaminate visible contract`,
    );

    const visibleSibling = artifactFixture(source);
    const visibleSiblingHtml = `${wrapper(oldClaim)}${currentHtml}`;
    visibleSibling.surfaces.set('cli-html', visibleSiblingHtml);
    visibleSibling.surfaces.set('page:/reference/project-cli', visibleSiblingHtml);
    assert.doesNotThrow(
      () => assertProductFramingArtifactContract(visibleSibling),
      `${mechanism} hidden old claim with visible current sibling must pass`,
    );
  }

  for (const [mechanism, wrapper] of [
    ['visibility:hidden', (value) => `<div style="visibility: hidden">${value}</div>`],
    ['visibility:collapse', (value) => `<div style="visibility: collapse">${value}</div>`],
  ]) {
    const hiddenRequired = artifactFixture(source);
    const hiddenRequiredHtml = wrapper(source.documents['project-cli.md']);
    hiddenRequired.surfaces.set('cli-html', hiddenRequiredHtml);
    hiddenRequired.surfaces.set('page:/reference/project-cli', hiddenRequiredHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(hiddenRequired),
      /Stable list boundary/,
      `${mechanism} required markers only must be rejected in paired HTML surfaces`,
    );

    const hiddenOld = artifactFixture(source);
    const hiddenOldHtml = `${currentHtml}${wrapper(oldClaim)}`;
    hiddenOld.surfaces.set('cli-html', hiddenOldHtml);
    hiddenOld.surfaces.set('page:/reference/project-cli', hiddenOldHtml);
    assert.doesNotThrow(
      () => assertProductFramingArtifactContract(hiddenOld),
      `${mechanism} hidden old claim must not contaminate visible contract`,
    );

    const visibleChildOld = artifactFixture(source);
    const visibleChildOldHtml = `<div style="visibility:hidden"><span style="visibility:visible">${oldClaim}</span></div>${currentHtml}`;
    visibleChildOld.surfaces.set('cli-html', visibleChildOldHtml);
    visibleChildOld.surfaces.set('page:/reference/project-cli', visibleChildOldHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(visibleChildOld),
      /old absolute side-effect-free claim/,
      `${mechanism} visible child old claim must remain visible and be rejected`,
    );
  }

  for (const [mechanism, wrapper] of [
    ['aria-hidden', (value) => `<div aria-hidden=" TRUE ">${value}</div>`],
    ['inert', (value) => `<div inert>${value}</div>`],
  ]) {
    const accessibleRequired = artifactFixture(source);
    const accessibleRequiredHtml = wrapper(source.documents['project-cli.md']);
    accessibleRequired.surfaces.set('cli-html', accessibleRequiredHtml);
    accessibleRequired.surfaces.set('page:/reference/project-cli', accessibleRequiredHtml);
    assert.doesNotThrow(
      () => assertProductFramingArtifactContract(accessibleRequired),
      `${mechanism} does not remove visually rendered required markers`,
    );

    const accessibleOld = artifactFixture(source);
    const accessibleOldHtml = `${currentHtml}${wrapper(oldClaim)}`;
    accessibleOld.surfaces.set('cli-html', accessibleOldHtml);
    accessibleOld.surfaces.set('page:/reference/project-cli', accessibleOldHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(accessibleOld),
      /old absolute side-effect-free claim/,
      `${mechanism} old claim remains visible and is rejected`,
    );
  }

  for (const [mechanism, rootHtml] of [
    ['body hidden', `<!doctype html><html><body hidden>${source.documents['project-cli.md']}</body></html>`],
    ['body display:none!important', `<!doctype html><html><body style="display:none!important">${source.documents['project-cli.md']}</body></html>`],
    ['html visibility:hidden', `<!doctype html><html style="visibility:hidden"><body>${source.documents['project-cli.md']}</body></html>`],
  ]) {
    const hiddenRoot = artifactFixture(source);
    hiddenRoot.surfaces.set('cli-html', rootHtml);
    hiddenRoot.surfaces.set('page:/reference/project-cli', rootHtml);
    assert.throws(
      () => assertProductFramingArtifactContract(hiddenRoot),
      /Stable list boundary/,
      `${mechanism} must hide the complete required-marker subtree`,
    );
  }

  const rootAriaHidden = artifactFixture(source);
  const rootAriaHiddenHtml = `<!doctype html><html aria-hidden="true"><body>${source.documents['project-cli.md']}</body></html>`;
  rootAriaHidden.surfaces.set('cli-html', rootAriaHiddenHtml);
  rootAriaHidden.surfaces.set('page:/reference/project-cli', rootAriaHiddenHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(rootAriaHidden),
    'aria-hidden on html does not remove visually rendered text',
  );

  const visibleCascade = artifactFixture(source);
  const visibleCascadeHtml = `<div style="display:none;display:block">${currentHtml}</div>`;
  visibleCascade.surfaces.set('cli-html', visibleCascadeHtml);
  visibleCascade.surfaces.set('page:/reference/project-cli', visibleCascadeHtml);
  assert.doesNotThrow(
    () => assertProductFramingArtifactContract(visibleCascade),
    'the effective final visible inline declaration must be retained',
  );

  const hiddenImportantCascade = artifactFixture(source);
  const hiddenImportantCascadeHtml = `<div style="display:none!important;display:block">${currentHtml}</div>`;
  hiddenImportantCascade.surfaces.set('cli-html', hiddenImportantCascadeHtml);
  hiddenImportantCascade.surfaces.set('page:/reference/project-cli', hiddenImportantCascadeHtml);
  assert.throws(
    () => assertProductFramingArtifactContract(hiddenImportantCascade),
    /Stable list boundary/,
    'an earlier important hidden declaration must dominate a later non-important declaration',
  );

  for (const surface of ['raw', 'search', 'llm']) {
    const literalTagOldClaim = artifactFixture(source);
    const currentSurface = source.documents['project-cli.md'];
    literalTagOldClaim.surfaces.set(`cli-${surface}`, `${currentSurface}\n<script>${oldClaim}</script>`);
    assert.throws(
      () => assertProductFramingArtifactContract(literalTagOldClaim),
      /old absolute side-effect-free claim/,
      `literal script tags in reader-visible ${surface} must not hide the old claim`,
    );
  }

  const literalTagOldClaimInRawAlias = artifactFixture(source);
  literalTagOldClaimInRawAlias.surfaces.set(
    'raw:reference/project-cli.md',
    `${source.documents['project-cli.md']}\n<script>${oldClaim}</script>`,
  );
  assert.throws(
    () => assertProductFramingArtifactContract(literalTagOldClaimInRawAlias),
    /old absolute side-effect-free claim/,
    'literal script tags in the reader-visible raw alias must not hide the old claim',
  );

  const visibleInlineBoundary = artifactFixture(source);
  visibleInlineBoundary.surfaces.set(
    'cli-html',
    htmlSurface(source.documents['project-cli.md']).replace('Command Manifest', '<span>Command</span> Manifest'),
  );
  assert.doesNotThrow(() => assertProductFramingArtifactContract(visibleInlineBoundary));
  assert.ok(CLI_STABLE_LIST_BOUNDARY_MARKERS.length >= 7);
});

test('Application Bootstrap list truth is required across public Source and Artifact surfaces', async () => {
  const source = await sourceFixture();
  const bootstrap = source.documents['application-bootstrap.md'];
  const missingBoundary = APPLICATION_BOOTSTRAP_LIST_BOUNDARY_MARKERS.at(-1);

  const oldSource = {
    ...source,
    documents: { ...source.documents, 'application-bootstrap.md': `${bootstrap}\n${APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM}` },
  };
  assert.throws(
    () => assertProductFramingSourceContract({ ...oldSource, contentMap }),
    /old Application Bootstrap list claim/,
  );

  const missingSource = {
    ...source,
    documents: { ...source.documents, 'application-bootstrap.md': bootstrap.replace(missingBoundary.replace('Global list', 'Global `list`'), '') },
  };
  assert.throws(
    () => assertProductFramingSourceContract({ ...missingSource, contentMap }),
    /Application Bootstrap list boundary/,
  );

  const bootstrapSurfaceAlternatives = {
    html: ['bootstrap-html', 'page:/reference/application-bootstrap'],
    raw: ['bootstrap-raw', 'raw:reference/application-bootstrap.md'],
    search: ['bootstrap-search', 'search-all'],
    llm: ['bootstrap-llm', 'llm-full-all'],
  };
  for (const surface of ['html', 'raw', 'search', 'llm']) {
    const artifactSurfaceWithOldClaim = artifactFixture(source);
    const currentSurface = surface === 'html' ? htmlSurface(bootstrap) : bootstrap;
    artifactSurfaceWithOldClaim.surfaces.set(`bootstrap-${surface}`, `${currentSurface}${surface === 'html' ? `<p>${APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM}</p>` : `\n${APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM}`}`);
    assert.throws(
      () => assertProductFramingArtifactContract(artifactSurfaceWithOldClaim),
      /old Application Bootstrap list claim/,
      `old claim must be rejected in Application Bootstrap ${surface}`,
    );

    const artifactSurfaceMissingBoundary = artifactFixture(source);
    const withoutBoundary = bootstrap.replace(missingBoundary.replace('Global list', 'Global `list`'), '');
    artifactSurfaceMissingBoundary.surfaces.set(`bootstrap-${surface}`, surface === 'html' ? htmlSurface(withoutBoundary) : currentSurface.replace(missingBoundary.replace('Global list', 'Global `list`'), ''));
    assert.throws(
      () => assertProductFramingArtifactContract(artifactSurfaceMissingBoundary),
      /Application Bootstrap list boundary/,
      `missing boundary must be rejected in Application Bootstrap ${surface}`,
    );

    const artifactMissingSurface = artifactFixture(source);
    for (const alias of bootstrapSurfaceAlternatives[surface]) artifactMissingSurface.surfaces.delete(alias);
    assert.throws(
      () => assertProductFramingArtifactContract(artifactMissingSurface),
      new RegExp(`missing the Application Bootstrap ${surface} artifact surface`),
      `missing Application Bootstrap ${surface} surface must be rejected`,
    );
  }

  for (const unknownAlias of ['page:/reference/application-bootstrap-copy', 'raw:reference/application-bootstrap-copy.md', 'bootstrap-side-channel', 'search-all-copy', 'llm-full-all-copy']) {
    const unknownAliasArtifact = artifactFixture(source);
    unknownAliasArtifact.surfaces.set(unknownAlias, bootstrap);
    assert.throws(
      () => assertProductFramingArtifactContract(unknownAliasArtifact),
      /Unknown Application Bootstrap artifact surface kind/,
      `unknown Application Bootstrap surface ${unknownAlias} must fail closed`,
    );
  }

  const productionFixture = productionBootstrapArtifactFixture(source);
  assert.doesNotThrow(() => assertProductFramingArtifactContract(productionFixture));
  const productionAliases = {
    html: 'page:/reference/application-bootstrap',
    raw: 'raw:reference/application-bootstrap.md',
    search: 'search-all',
    llm: 'llm-full-all',
  };
  for (const [surface, alias] of Object.entries(productionAliases)) {
    const missingProductionSurface = productionBootstrapArtifactFixture(source);
    missingProductionSurface.surfaces.delete(alias);
    assert.throws(
      () => assertProductFramingArtifactContract(missingProductionSurface),
      new RegExp(`missing the Application Bootstrap ${surface} artifact surface`),
      `missing production Application Bootstrap ${surface} surface must be rejected`,
    );
  }
});

test('product framing source contract rejects audit, retention, tenant, README, and management drift', async () => {
  const source = await sourceFixture();
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'observability.md': source.documents['observability.md'] + '\nReplayはDefault JSONLへ出ます。' } }),
    /Default JSONL producer/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'observability.md': source.documents['observability.md'].replace('retention.purge.completed', 'storage.rotation.completed') } }),
    /retired Rotation event|storage\.rotation\.completed/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + `\n${RETENTION_OPTION}\n` } }),
    /unpublished Retention option|publishes an inner-command-only retention option/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, retentionRuntimeSource: source.retentionRuntimeSource.replace("                ->addOption('dead-letter-days', null, InputOption::VALUE_REQUIRED);", '') }),
    /outer Retention Definition must expose exactly the four public options/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + '\nstorage:protection:plan --tenant-type=account\n' } }),
    /one-sided Tenant Scope command/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, spec83Source: '' }),
    /Specification 83 source/,
  );
  const wrongAuthorityPath = 'develop/spec/84-documentation-learning-journey.md';
  const typoTask = source.taskSource.replace(SPEC_83_PATH, wrongAuthorityPath);
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, taskSource: typoTask }),
    /missing required authority path/,
  );
  const duplicateSectionTask = source.taskSource + '\n## Relevant Specifications\n- `develop/spec/57-documentation-website-delivery-contract.md`\n';
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, taskSource: duplicateSectionTask }),
    /exactly one Relevant Specifications section/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'README.md': source.documents['README.md'].replace('\n## Start Here', `\n${'HTTPやWorkerから受けた一つの処理を同じOperation IDで追跡し、受付・再試行・完了までの結果を確認できます。'}\n## Start Here`) } }),
    /duplicated Guide index value|old same-ID value statement/,
  );
});

test('product framing artifact contract rejects stale injection, missing CLI, visual, and accessibility boundaries', async () => {
  const source = await sourceFixture();
  const stale = artifactFixture(source);
  stale.surfaces.set('search-only-stale', 'Audit Log / Process History | Journal');
  assert.throws(() => assertProductFramingArtifactContract(stale), /Audit Log \/ Process History/);

  const missingCli = artifactFixture(source);
  missingCli.surfaces.set('landing-html', source.landingSource.replaceAll('/reference/project-cli', '/reference/missing'));
  assert.throws(() => assertProductFramingArtifactContract(missingCli), /CLI link/);

  const vagueOutcome = artifactFixture(source);
  vagueOutcome.surfaces.set('why-search', '同期／非同期を一貫して追跡できるか判断する。');
  assert.throws(() => assertProductFramingArtifactContract(vagueOutcome), /vague reader outcome|actionable outcome/);

  const missingVisual = artifactFixture(source);
  missingVisual.surfaces.set('landing-html', source.landingSource.replace('landing-demo', 'retired-hero'));
  assert.throws(() => assertProductFramingArtifactContract(missingVisual), /Landing visual marker/);

  const missingFocus = artifactFixture(source);
  missingFocus.css = source.themeSource.replace('.blackops-overflow-focus:focus-visible', '.blackops-overflow-focus:focus');
  assert.throws(() => assertProductFramingArtifactContract(missingFocus), /overflow focus/);

  const wrongOperationalOwner = artifactFixture(source);
  wrongOperationalOwner.surfaces.set('why-search', '受付・再試行・完了を確認。汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します。Retention／Replay／Rotationなどの個別運用EventはApplicationが所有します。');
  assert.throws(() => assertProductFramingArtifactContract(wrongOperationalOwner), /Why BlackOps application audit boundary|Why BlackOps operational audit boundary|operational audit ownership boundary/);

  const missingJournalConcept = artifactFixture(source);
  missingJournalConcept.surfaces.set('journal-search', 'Canonical JournalはOperation Lifecycleの正本です。');
  assert.throws(() => assertProductFramingArtifactContract(missingJournalConcept), /Journal concept boundary/);

  const staleCliHelp = artifactFixture(source);
  staleCliHelp.surfaces.set('cli-raw', source.documents['project-cli.md'].replace('Helpが本文表の全Optionを必ず列挙するとは限りません。', 'Optionの全量とDefaultは必ずHelpへ委ねます。'));
  assert.throws(() => assertProductFramingArtifactContract(staleCliHelp), /CLI Help limitation/);
});

test('product framing artifact contract rejects audit, retention, tenant, and management drift', async () => {
  const source = await sourceFixture();
  const staleAudit = artifactFixture(source);
  staleAudit.surfaces.set('observability-search', `${source.documents['observability.md']}\nRotationはDefault JSONLへ出ます。`);
  assert.throws(() => assertProductFramingArtifactContract(staleAudit), /Default JSONL producer/);

  const staleRetention = artifactFixture(source);
  staleRetention.surfaces.set('cli-raw', `${source.documents['project-cli.md']}\n${RETENTION_OPTION}`);
  assert.throws(() => assertProductFramingArtifactContract(staleRetention), /unpublished Retention option|publishes an inner-command-only retention option|exposes the unpublished idempotency option/);

  const oneSidedTenant = artifactFixture(source);
  oneSidedTenant.surfaces.set('cli-raw', `${source.documents['project-cli.md']}\nstorage:protection:plan --tenant-id=tenant-001`);
  assert.throws(() => assertProductFramingArtifactContract(oneSidedTenant), /one-sided Tenant Scope command/);

  const missingSpec = artifactFixture(source);
  missingSpec.spec83Source = '';
  assert.throws(() => assertProductFramingArtifactContract(missingSpec), /Specification 83 source/);
  const wrongAuthorityPath = 'develop/spec/84-documentation-learning-journey.md';
  const typoTask = artifactFixture(source);
  typoTask.taskSource = source.taskSource.replace(SPEC_83_PATH, wrongAuthorityPath);
  assert.throws(
    () => assertProductFramingArtifactContract(typoTask),
    /missing required authority path/,
  );
  const duplicateSectionArtifact = artifactFixture(source);
  duplicateSectionArtifact.taskSource = source.taskSource + '\n## Relevant Specifications\n- `develop/spec/57-documentation-website-delivery-contract.md`\n';
  assert.throws(
    () => assertProductFramingArtifactContract(duplicateSectionArtifact),
    /exactly one Relevant Specifications section/,
  );
});

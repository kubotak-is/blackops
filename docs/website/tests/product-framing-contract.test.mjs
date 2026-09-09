import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import {
  assertProductFramingArtifactContract,
  assertProductFramingSourceContract,
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
      /unpublished Retention option|publishes an outer-command-only retention option|exposes the unpublished idempotency option/,
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
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + '\nStable 1.2.0 current: route:list\n' } }),
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
    /unpublished Retention option|publishes an outer-command-only retention option/,
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
  assert.throws(() => assertProductFramingArtifactContract(staleRetention), /unpublished Retention option|publishes an outer-command-only retention option|exposes the unpublished idempotency option/);

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

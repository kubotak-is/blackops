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
const sourceNames = ['README.md', 'why-blackops.md', 'project-cli.md', 'retention.md', 'journal.md', 'observability.md', 'security.md', 'glossary.md', 'mvp-sample.md'];

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
      ['cli-search', documents['project-cli.md']],
      ['cli-raw', documents['project-cli.md']],
      ['cli-html', documents['project-cli.md']],
      ['cli-llm', documents['project-cli.md']],
      ['retention-search', documents['retention.md']],
      ['retention-raw', documents['retention.md']],
      ['retention-html', documents['retention.md']],
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

test('product framing source and artifact contracts accept the current bounded surfaces', async () => {
  const source = await sourceFixture();
  assert.doesNotThrow(() => assertProductFramingSourceContract({ ...source, contentMap }));
  assert.doesNotThrow(() => assertProductFramingArtifactContract(artifactFixture(source)));
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
    () => assertProductFramingSourceContract({ ...source, contentMap, landingSource: source.landingSource.replace('landing-lifecycle-panel', 'landing-removed') }),
    /Landing visual marker/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, themeSource: source.themeSource + '\n.landing-shell { background: linear-gradient(red, blue); }\n' }),
    /forbidden visual/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, landingSource: source.landingSource.replace('<strong>Finalizing</strong>', '<strong>Finalizing</strong><strong>Succeeded</strong>') }),
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
    () => assertProductFramingSourceContract({ ...source, contentMap, documents: { ...source.documents, 'project-cli.md': source.documents['project-cli.md'] + '\nretention:plan --idempotency-record-days=90\n' } }),
    /outer-command-only retention option|unpublished Retention option/,
  );
  assert.throws(
    () => assertProductFramingSourceContract({ ...source, contentMap, retentionRuntimeSource: source.retentionRuntimeSource.replace("->addOption('dead-letter-days'", "->addOption('idempotency-record-days'") }),
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
  missingVisual.surfaces.set('landing-html', source.landingSource.replace('landing-lifecycle-panel', 'landing-lifecycle-removed'));
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
  staleRetention.surfaces.set('cli-raw', `${source.documents['project-cli.md']}\nretention:plan --idempotency-record-days=90`);
  assert.throws(() => assertProductFramingArtifactContract(staleRetention), /outer-command-only retention option|unpublished Retention option/);

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

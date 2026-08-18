import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { blumeSidebar, requiredSections, sidebar, validateNavigation } from '../site-navigation.mjs';
import { contentMap } from '../content-map.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const itemSlug = (item) => typeof item === 'string' ? item : item.link;

test('uses the canonical seven-section order and public assignment', () => {
  assert.deepEqual(sidebar.map(({ label }) => label), [
    'Start Here',
    'Build',
    'Async and Lifecycle',
    'Data and Security',
    'Operate',
    'Reference',
    'Releases',
  ]);
  assert.deepEqual(sidebar.map(({ label }) => label), requiredSections);
  assert.deepEqual(sidebar.map(({ items }) => items.map(itemSlug)), [
    [
      'concepts/why-blackops',
      'getting-started/installation',
      'getting-started/quickstart',
      'getting-started/first-operation',
      'getting-started/directory-structure',
      'concepts/core-concepts',
      'getting-started/local-runtime',
    ],
    [
      'operations/authoring',
      'operations/generators',
      'operations/validation',
      'execution/http-and-deferred',
      'execution/console-command',
      'operations/scheduled-operation',
      'auth/authentication',
      'auth/authorization',
      'frontend',
      'testing/community-board',
    ],
    [
      'concepts/lifecycle',
      'execution/context',
      'database/outcomes',
      'execution/outbox',
      'concepts/journal',
    ],
    [
      'database/transactions',
      'database/migrations',
      'database/seeding',
      'database/retention',
      'security',
      'security/tenant-protection',
    ],
    [
      'reference/configuration',
      'deployment/worker-operations',
      'reference/observability',
      'testing',
      'troubleshooting',
    ],
    [
      'reference/project-cli',
      'reference/application-bootstrap',
      'reference/core-api',
      'reference/attributes',
      'reference/observer-replay',
      'reference/glossary',
    ],
    ['releases/current-status'],
  ]);
});

test('Content Map declares one canonical section for every source and keeps the Landing out of Sidebar', () => {
  assert.equal(Object.keys(contentMap).length, 41);
  for (const [source, metadata] of Object.entries(contentMap)) {
    assert.ok(requiredSections.includes(metadata.section), `${source} section`);
  }
  assert.equal(contentMap['README.md'].section, 'Start Here');
  assert.equal(contentMap['README.md'].slug, 'index');
  assert.equal(sidebar.flatMap(({ items }) => items.map(itemSlug)).includes('index'), false);
});

test('accepts every mapped public content exactly once in the sidebar', () => {
  assert.doesNotThrow(() => validateNavigation(contentMap));
});

test('rejects missing, invalid, duplicate, wrong-section, and reordered entries', () => {
  const duplicate = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  duplicate[1].items.push('operations/authoring');
  assert.throws(() => validateNavigation(contentMap, duplicate), /duplicate public slugs/);

  const unknown = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  unknown[1].items.push('operations/missing');
  assert.throws(() => validateNavigation(contentMap, unknown), /unknown public documentation/);

  const missing = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  missing[1].items = missing[1].items.slice(0, -1);
  assert.throws(() => validateNavigation(contentMap, missing), /Sidebar is missing public documentation/);

  const reordered = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  reordered[1].items.reverse();
  assert.throws(() => validateNavigation(contentMap, reordered), /Sidebar public entries must match/);

  const wrongSection = Object.fromEntries(Object.entries(contentMap).map(([source, metadata]) => [source, { ...metadata }]));
  wrongSection['why-blackops.md'].section = 'Build';
  assert.throws(() => validateNavigation(wrongSection), /wrong section/);

  const invalidSection = Object.fromEntries(Object.entries(contentMap).map(([source, metadata]) => [source, { ...metadata }]));
  delete invalidSection['why-blackops.md'].section;
  assert.throws(() => validateNavigation(invalidSection), /canonical section/);

  assert.throws(() => validateNavigation(contentMap, [...sidebar].reverse()), /required public sections in order/);
});

test('feeds native Blume navigation with canonical content roots', () => {
  const entries = blumeSidebar.flatMap((section) => section.items?.length ? section.items : [section]);
  assert.equal(entries.length, Object.keys(contentMap).length - 1);
  for (const entry of entries) {
    assert.match(entry.root, /^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/);
    assert.equal('href' in entry, false);
  }
  assert.deepEqual(blumeSidebar.at(-1), { label: 'Releases', root: 'releases/current-status', items: [], display: 'flat' });
  assert.equal(blumeSidebar.at(-1).items.length, 0, 'Releases keeps an empty flat-group child list.');
});

test('rejects a Releases projection that loses the empty-child flat-group contract', () => {
  const releases = blumeSidebar.at(-1);
  assert.equal(releases.root, 'releases/current-status');
  assert.equal(releases.display, 'flat');
  assert.deepEqual(releases.items, []);
  assert.notDeepEqual({ ...releases, display: 'group' }, releases);
  assert.notDeepEqual({ ...releases, items: [{ label: 'Releases', root: releases.root }] }, releases);
});

test('rejects a same-name Releases parent and child fixture', () => {
  const nested = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  nested.at(-1).items = [{ label: 'Releases', link: 'releases/current-status' }];
  assert.throws(() => validateNavigation(contentMap, nested), /direct singleton root/);
});

test('keeps every sidebar reader label synchronized with its source H1', async () => {
  const pages = new Map([
    ["What's BlackOps", 'why-blackops.md'], ['Install', 'installation.md'], ['Quickstart and Skeleton', 'mvp-sample.md'],
    ['First Operation', 'first-operation.md'], ['Directory', 'directory-structure.md'], ['Local Runtime', 'runtime-bootstrap.md'], ['Core Concepts', 'core-concepts.md'],
    ['Authoring', 'operations.md'], ['Generators', 'project-generators.md'], ['Value and Validation', 'validation.md'], ['Inline and Deferred', 'execution.md'],
    ['ConsoleCommand', 'console-command.md'], ['Scheduled Operation', 'scheduled-operation.md'], ['Authentication', 'authentication.md'], ['Authorization', 'authorization.md'],
    ['Frontend', 'frontend.md'], ['BlackOps Board Reference Application', 'community-board.md'], ['Lifecycle', 'operation-lifecycle.md'], ['Execution Context', 'execution-context.md'],
    ['Outcome', 'outcome-retrieval.md'], ['Outbox', 'outbox.md'], ['Journal', 'journal.md'], ['Transaction', 'database-and-transactions.md'], ['Migration', 'database-migrations.md'],
    ['Seeder', 'database-seeding.md'], ['Retention', 'retention.md'], ['Security', 'security.md'], ['Tenant and Storage Protection', 'tenant-protection.md'], ['Configuration', 'configuration.md'],
    ['Deployment', 'deployment.md'], ['Observability', 'observability.md'], ['Testing', 'testing.md'], ['Troubleshooting', 'troubleshooting.md'], ['BlackOps CLI', 'project-cli.md'],
    ['Application Bootstrap', 'application-bootstrap.md'], ['Core API', 'core-api.md'], ['Attributes', 'attributes.md'], ['Observer Replay', 'observer-replay.md'], ['Glossary', 'glossary.md'],
    ['Releases', 'mvp-status.md'],
  ]);
  for (const [label, source] of pages) {
    const content = await readFile(path.join(repositoryRoot, 'docs/guide', source), 'utf8');
    assert.match(content, new RegExp(`^# ${label.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}$`, 'm'), label);
  }
});

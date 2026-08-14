import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { blumeSidebar, sidebar, validateNavigation } from '../site-navigation.mjs';
import { contentMap } from '../content-map.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

test('matches the D117 section order and public labels', () => {
  assert.deepEqual(sidebar.map(({ label }) => label), [
    'Introduction',
    'Getting Started',
    'Operation',
    'Execution and Workers',
    'Database',
    'Auth',
    'Frontend',
    'Testing',
    'Tutorial',
    'Deployment',
    'Security',
    'Troubleshooting',
    'Releases',
    'Reference',
  ]);
  const labels = sidebar.flatMap(({ items }) => items.map((item) => typeof item === 'string' ? item : item.label));
  for (const label of ["What's BlackOps", 'Core Concepts', 'Quickstart and Skeleton', 'First Operation', 'Authoring', 'Scheduled Operation', 'Generators', 'Inline and Deferred', 'Execution Context', 'ConsoleCommand', 'Outbox', 'Lifecycle', 'Journal', 'Retention', 'Authentication', 'Authorization']) {
    assert.ok(labels.includes(label) || sidebar.some((section) => section.label === label), label);
  }
  assert.deepEqual(sidebar.find((section) => section.label === 'Tutorial')?.items, [
    { label: 'BlackOps Board Reference Application', link: 'testing/community-board' },
  ]);
});

test('accepts every mapped public content exactly once in the sidebar', () => {
  assert.doesNotThrow(() => validateNavigation(contentMap));
});

test('feeds native Blume navigation with canonical content roots', () => {
  const entries = blumeSidebar.flatMap((section) => section.items ?? [section]);
  assert.equal(entries.length, Object.keys(contentMap).length - 1);
  for (const entry of entries) {
    assert.match(entry.root, /^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/);
    assert.equal('href' in entry, false);
  }
});

test('rejects reordered, duplicate, and unknown sidebar entries', () => {
  const duplicate = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  duplicate[1].items.push('getting-started/installation');
  assert.throws(() => validateNavigation(contentMap, duplicate), /duplicate public slugs/);

  const unknown = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  unknown[1].items.push('getting-started/missing');
  assert.throws(() => validateNavigation(contentMap, unknown), /unknown public documentation/);

  const missing = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  missing[1].items = missing[1].items.slice(0, -1);
  assert.throws(() => validateNavigation(contentMap, missing), /Sidebar is missing public documentation/);

  const reordered = sidebar.map((section) => ({ ...section, items: [...section.items] }));
  reordered[1].items.reverse();
  assert.throws(() => validateNavigation(contentMap, reordered), /Sidebar public entries must match/);

  assert.throws(() => validateNavigation(contentMap, [...sidebar].reverse()), /required public sections in order/);
});

test('keeps every sidebar reader label synchronized with its source H1', async () => {
  const pages = new Map([
    ["What's BlackOps", 'why-blackops.md'], ['Core Concepts', 'core-concepts.md'], ['Install', 'installation.md'], ['Quickstart and Skeleton', 'mvp-sample.md'],
    ['First Operation', 'first-operation.md'], ['Directory', 'directory-structure.md'], ['Local Runtime', 'runtime-bootstrap.md'], ['Authoring', 'operations.md'], ['Scheduled Operation', 'scheduled-operation.md'],
    ['Generators', 'project-generators.md'], ['Value and Validation', 'validation.md'], ['Outcome', 'outcome-retrieval.md'], ['Lifecycle', 'operation-lifecycle.md'], ['Journal', 'journal.md'],
    ['Inline and Deferred', 'execution.md'], ['Execution Context', 'execution-context.md'], ['ConsoleCommand', 'console-command.md'], ['Outbox', 'outbox.md'], ['Transaction', 'database-and-transactions.md'],
    ['Retention', 'retention.md'],
    ['Migration', 'database-migrations.md'], ['Seeder', 'database-seeding.md'], ['Authentication', 'authentication.md'],
    ['Authorization', 'authorization.md'], ['Frontend', 'frontend.md'], ['Testing', 'testing.md'],
    ['BlackOps Board Reference Application', 'community-board.md'], ['Deployment', 'deployment.md'], ['Security', 'security.md'], ['Tenant and Storage Protection', 'tenant-protection.md'],
    ['Troubleshooting', 'troubleshooting.md'], ['Releases', 'mvp-status.md'], ['Core API', 'core-api.md'],
    ['Attributes', 'attributes.md'], ['Configuration', 'configuration.md'], ['Observability', 'observability.md'], ['BlackOps CLI', 'project-cli.md'],
    ['Observer Replay', 'observer-replay.md'], ['Application Bootstrap', 'application-bootstrap.md'], ['Glossary', 'glossary.md'],
  ]);
  for (const [label, source] of pages) {
    const content = await readFile(path.join(repositoryRoot, 'docs/guide', source), 'utf8');
    assert.match(content, new RegExp(`^# ${label.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}$`, 'm'), label);
  }
});

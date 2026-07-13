import assert from 'node:assert/strict';
import test from 'node:test';
import { sidebar, validateNavigation } from '../site-navigation.mjs';

test('puts the primary Quickstart first in the Getting Started journey', () => {
  const gettingStarted = sidebar.find(({ label }) => label === 'Getting Started');

  assert.deepEqual(gettingStarted?.items, [
    'getting-started/quickstart',
    'getting-started/installation',
    'getting-started/first-operation',
    'getting-started/directory-structure',
    'getting-started/local-runtime',
  ]);
});

test('accepts one placement for every mapped public page in six sections', () => {
  const contentMap = {
    'README.md': { slug: 'index' },
    'why.md': { slug: 'concepts/why' },
    'install.md': { slug: 'getting-started/install' },
    'operation.md': { slug: 'operations/authoring' },
    'execution.md': { slug: 'execution/http' },
    'database.md': { slug: 'database/migrations' },
    'reference.md': { slug: 'reference/configuration' },
  };
  const navigation = [
    { label: 'Overview', items: ['concepts/why'] },
    { label: 'Getting Started', items: ['getting-started/install'] },
    { label: 'Operations', items: ['operations/authoring'] },
    { label: 'Execution', items: ['execution/http'] },
    { label: 'Database', items: ['database/migrations'] },
    { label: 'Reference', items: ['reference/configuration'] },
  ];

  assert.doesNotThrow(() => validateNavigation(contentMap, navigation));
});

test('rejects missing, duplicate, unknown, or reordered sidebar placement', () => {
  const contentMap = {
    'README.md': { slug: 'index' },
    'install.md': { slug: 'getting-started/install' },
  };
  const sections = (gettingStarted) => [
    { label: 'Overview', items: [] },
    { label: 'Getting Started', items: gettingStarted },
    { label: 'Operations', items: [] },
    { label: 'Execution', items: [] },
    { label: 'Database', items: [] },
    { label: 'Reference', items: [] },
  ];

  assert.throws(() => validateNavigation(contentMap, sections([])), /not placed in the sidebar/);
  assert.throws(
    () => validateNavigation(contentMap, sections(['getting-started/install', 'getting-started/install'])),
    /duplicate public slugs/,
  );
  assert.throws(
    () => validateNavigation(contentMap, sections(['getting-started/install', 'getting-started/missing'])),
    /unknown public documentation/,
  );
  assert.throws(
    () => validateNavigation(contentMap, [...sections(['getting-started/install'])].reverse()),
    /six public sections in order/,
  );
});

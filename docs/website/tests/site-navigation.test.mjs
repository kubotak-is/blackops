import assert from 'node:assert/strict';
import test from 'node:test';
import { sidebar, validateNavigation } from '../site-navigation.mjs';

test('puts the five Getting Started pages in the required order with the Tutorial label', () => {
  const gettingStarted = sidebar.find(({ label }) => label === 'Getting Started');

  assert.deepEqual(gettingStarted?.items, [
    'getting-started/installation',
    'getting-started/quickstart',
    { label: 'Tutorial', link: 'getting-started/first-operation' },
    'getting-started/directory-structure',
    'getting-started/local-runtime',
  ]);
});

test('keeps all eleven sections, moved pages, and the six-page Reference in exact order', () => {
  assert.deepEqual(sidebar.map(({ label }) => label), [
    'Overview',
    'Getting Started',
    'Operations',
    'Execution & Workers',
    'Data & Retention',
    'Testing',
    'Deployment',
    'Security',
    'Troubleshooting',
    'Releases',
    'Reference',
  ]);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Overview')?.items, [
    'concepts/why-blackops',
    'concepts/core-concepts',
    'concepts/lifecycle',
  ]);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Operations')?.items, [
    'operations/authoring',
    'operations/generators',
    'operations/validation',
  ]);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Testing')?.items, [
    'testing',
    'testing/community-board',
  ]);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Reference')?.items, [
    'reference/core-api',
    'reference/attributes',
    'reference/configuration',
    'reference/project-cli',
    'reference/observer-replay',
    'reference/application-bootstrap',
    'reference/glossary',
  ]);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Security')?.items, ['security']);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Troubleshooting')?.items, ['troubleshooting']);
  assert.deepEqual(sidebar.find(({ label }) => label === 'Releases')?.items, ['releases/current-status']);
});

test('accepts one placement for every mapped public page in eleven sections', () => {
  const contentMap = {
    'README.md': { slug: 'index' },
    'why.md': { slug: 'concepts/why' },
    'install.md': { slug: 'getting-started/install' },
    'operation.md': { slug: 'operations/authoring' },
    'execution.md': { slug: 'execution/http' },
    'database.md': { slug: 'database/migrations' },
    'testing.md': { slug: 'testing' },
    'deployment.md': { slug: 'deployment/worker-operations' },
    'security.md': { slug: 'security' },
    'troubleshooting.md': { slug: 'troubleshooting' },
    'status.md': { slug: 'releases/current-status' },
    'reference.md': { slug: 'reference/configuration' },
  };
  const navigation = [
    { label: 'Overview', items: ['concepts/why'] },
    { label: 'Getting Started', items: ['getting-started/install'] },
    { label: 'Operations', items: ['operations/authoring'] },
    { label: 'Execution & Workers', items: ['execution/http'] },
    { label: 'Data & Retention', items: ['database/migrations'] },
    { label: 'Testing', items: ['testing'] },
    { label: 'Deployment', items: ['deployment/worker-operations'] },
    { label: 'Security', items: ['security'] },
    { label: 'Troubleshooting', items: ['troubleshooting'] },
    { label: 'Releases', items: ['releases/current-status'] },
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
    { label: 'Execution & Workers', items: [] },
    { label: 'Data & Retention', items: [] },
    { label: 'Testing', items: [] },
    { label: 'Deployment', items: [] },
    { label: 'Security', items: [] },
    { label: 'Troubleshooting', items: [] },
    { label: 'Releases', items: [] },
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
    /required public sections in order/,
  );
});

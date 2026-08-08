export const sidebar = [
  { label: 'Introduction', items: [
    { label: "What's BlackOps", link: 'concepts/why-blackops' },
    { label: 'Core Concepts', link: 'concepts/core-concepts' },
  ] },
  {
    label: 'Getting Started',
    items: [
      { label: 'Install', link: 'getting-started/installation' },
      { label: 'Quickstart and Skeleton', link: 'getting-started/quickstart' },
      { label: 'First Operation', link: 'getting-started/first-operation' },
      { label: 'Directory', link: 'getting-started/directory-structure' },
      { label: 'Local Runtime', link: 'getting-started/local-runtime' },
    ],
  },
  {
    label: 'Operation',
    items: [
      { label: 'Authoring', link: 'operations/authoring' },
      { label: 'Scheduled Operation', link: 'operations/scheduled-operation' },
      { label: 'Generators', link: 'operations/generators' },
      { label: 'Value and Validation', link: 'operations/validation' },
      { label: 'Outcome', link: 'database/outcomes' },
      { label: 'Lifecycle', link: 'concepts/lifecycle' },
      { label: 'Journal', link: 'concepts/journal' },
    ],
  },
  {
    label: 'Execution and Workers',
    items: [
      { label: 'Inline and Deferred', link: 'execution/http-and-deferred' },
      { label: 'Execution Context', link: 'execution/context' },
      { label: 'ConsoleCommand', link: 'execution/console-command' },
      { label: 'Outbox', link: 'execution/outbox' },
    ],
  },
  {
    label: 'Database',
    items: [
      { label: 'Transaction', link: 'database/transactions' },
      { label: 'Migration', link: 'database/migrations' },
      { label: 'Seeder', link: 'database/seeding' },
      { label: 'Retention', link: 'database/retention' },
    ],
  },
  {
    label: 'Auth',
    items: [
      { label: 'Authentication', link: 'auth/authentication' },
      { label: 'Authorization', link: 'auth/authorization' },
    ],
  },
  { label: 'Frontend', items: ['frontend'] },
  { label: 'Testing', items: ['testing'] },
  { label: 'Tutorial', items: [{ label: 'BlackOps Board Reference Application', link: 'testing/community-board' }] },
  { label: 'Deployment', items: ['deployment/worker-operations'] },
  { label: 'Security', items: ['security', { label: 'Tenant and Storage Protection', link: 'security/tenant-protection' }] },
  { label: 'Troubleshooting', items: ['troubleshooting'] },
  { label: 'Releases', items: ['releases/current-status'] },
  {
    label: 'Reference',
    items: [
      { label: 'Core API', link: 'reference/core-api' },
      { label: 'Attributes', link: 'reference/attributes' },
      { label: 'Configuration', link: 'reference/configuration' },
      { label: 'BlackOps CLI', link: 'reference/project-cli' },
      { label: 'Observer Replay', link: 'reference/observer-replay' },
      { label: 'Application Bootstrap', link: 'reference/application-bootstrap' },
      { label: 'Glossary', link: 'reference/glossary' },
    ],
  },
];

const requiredSections = [
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
];

const itemSlug = (item) => typeof item === 'string' ? item : item.link;

const blumeItem = (item, sectionLabel) => {
  if (typeof item === 'string') {
    return { label: sectionLabel, root: item };
  }

  return { label: item.label, root: item.link };
};

export const blumeSidebar = sidebar.map(({ label, items }) => ({
  label,
  ...(items.length === 1 && typeof items[0] === 'string'
    ? { root: items[0] }
    : { items: items.map((item) => blumeItem(item, label)) }),
}));

export function validateNavigation(contentMap, navigation = sidebar) {
  const labels = navigation.map(({ label }) => label);
  if (JSON.stringify(labels) !== JSON.stringify(requiredSections)) {
    throw new Error(`Sidebar must contain the required public sections in order: ${requiredSections.join(', ')}`);
  }

  const placed = navigation.flatMap(({ items }) => items.map(itemSlug));
  const duplicates = placed.filter((slug, index) => placed.indexOf(slug) !== index);
  if (duplicates.length > 0) {
    throw new Error(`Sidebar contains duplicate public slugs: ${[...new Set(duplicates)].join(', ')}`);
  }

  const mapped = Object.values(contentMap).map(({ slug }) => slug).filter((slug) => slug !== 'index');
  const missing = mapped.filter((slug) => !placed.includes(slug));
  if (missing.length > 0) {
    throw new Error(`Sidebar is missing public documentation: ${missing.join(', ')}`);
  }
  const unknown = placed.filter((slug) => !mapped.includes(slug));
  if (unknown.length > 0) {
    throw new Error(`Sidebar references unknown public documentation: ${unknown.join(', ')}`);
  }

  const expectedItems = sidebar.map(({ items }) => items.map(itemSlug));
  const actualItems = navigation.map(({ items }) => items.map(itemSlug));
  if (JSON.stringify(actualItems) !== JSON.stringify(expectedItems)) {
    throw new Error('Sidebar public entries must match the required learning order.');
  }
}

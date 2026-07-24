export const sidebar = [
  {
    label: 'Overview',
    items: ['concepts/why-blackops', 'concepts/core-concepts', 'concepts/lifecycle'],
  },
  {
    label: 'Getting Started',
    items: [
      'getting-started/installation',
      'getting-started/quickstart',
      { label: 'Tutorial', link: 'getting-started/first-operation' },
      'getting-started/directory-structure',
      'getting-started/local-runtime',
    ],
  },
  {
    label: 'Operations',
    items: ['operations/authoring', 'operations/generators', 'operations/validation'],
  },
  {
    label: 'Execution & Workers',
    items: ['execution/http-and-deferred', 'execution/context'],
  },
  {
    label: 'Data & Retention',
    items: ['database/transactions', 'database/migrations', 'database/seeding', 'database/outcomes', 'database/retention'],
  },
  { label: 'Testing', items: ['testing', 'testing/community-board'] },
  { label: 'Deployment', items: ['deployment/worker-operations'] },
  { label: 'Security', items: ['security'] },
  { label: 'Troubleshooting', items: ['troubleshooting'] },
  { label: 'Releases', items: ['releases/current-status'] },
  {
    label: 'Reference',
    items: [
      'reference/core-api',
      'reference/attributes',
      'reference/configuration',
      'reference/project-cli',
      'reference/observer-replay',
      'reference/application-bootstrap',
      'reference/glossary',
    ],
  },
];

export function validateNavigation(contentMap, navigation = sidebar) {
  const labels = navigation.map(({ label }) => label);
  const required = [
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
  ];
  if (JSON.stringify(labels) !== JSON.stringify(required)) {
    throw new Error(`Sidebar must contain the required public sections in order: ${required.join(', ')}`);
  }

  const mapped = Object.values(contentMap)
    .map(({ slug }) => slug)
    .filter((slug) => slug !== 'index')
    .sort();
  const placed = navigation.flatMap(({ items }) => items.map((item) => typeof item === 'string' ? item : item.link)).sort();
  const duplicates = placed.filter((slug, index) => placed.indexOf(slug) !== index);
  if (duplicates.length > 0) {
    throw new Error(`Sidebar contains duplicate public slugs: ${[...new Set(duplicates)].join(', ')}`);
  }

  const missing = mapped.filter((slug) => !placed.includes(slug));
  if (missing.length > 0) {
    throw new Error(`Public documentation is not placed in the sidebar: ${missing.join(', ')}`);
  }

  const unknown = placed.filter((slug) => !mapped.includes(slug));
  if (unknown.length > 0) {
    throw new Error(`Sidebar references unknown public documentation: ${unknown.join(', ')}`);
  }
}

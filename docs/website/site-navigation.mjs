export const sidebar = [
  {
    label: 'Overview',
    items: ['concepts/why-blackops', 'concepts/core-concepts'],
  },
  {
    label: 'Getting Started',
    items: [
      'getting-started/installation',
      'getting-started/directory-structure',
      'getting-started/first-operation',
      'getting-started/local-runtime',
      'getting-started/quickstart',
    ],
  },
  {
    label: 'Operations',
    items: ['operations/authoring', 'operations/generators', 'operations/lifecycle'],
  },
  {
    label: 'Execution',
    items: ['execution/http-and-deferred', 'execution/context'],
  },
  {
    label: 'Database',
    items: ['database/migrations', 'database/outcomes', 'database/retention'],
  },
  {
    label: 'Reference',
    items: [
      'reference/configuration',
      'reference/application-bootstrap',
      'reference/project-cli',
      'reference/current-status',
      'reference/glossary',
    ],
  },
];

export function validateNavigation(contentMap, navigation = sidebar) {
  const labels = navigation.map(({ label }) => label);
  const required = ['Overview', 'Getting Started', 'Operations', 'Execution', 'Database', 'Reference'];
  if (JSON.stringify(labels) !== JSON.stringify(required)) {
    throw new Error(`Sidebar must contain the six public sections in order: ${required.join(', ')}`);
  }

  const mapped = Object.values(contentMap)
    .map(({ slug }) => slug)
    .filter((slug) => slug !== 'index')
    .sort();
  const placed = navigation.flatMap(({ items }) => items).sort();
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

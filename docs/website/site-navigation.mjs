export const requiredSections = [
  'Start Here',
  'Build',
  'Async and Lifecycle',
  'Data and Security',
  'Operate',
  'Reference',
  'Releases',
];

export const sidebar = [
  {
    label: 'Start Here',
    items: [
      { label: "What's BlackOps", link: 'concepts/why-blackops' },
      { label: 'Install', link: 'getting-started/installation' },
      { label: 'Quickstart and Skeleton', link: 'getting-started/quickstart' },
      { label: 'First Operation', link: 'getting-started/first-operation' },
      { label: 'Directory', link: 'getting-started/directory-structure' },
      { label: 'Core Concepts', link: 'concepts/core-concepts' },
      { label: 'Local Runtime', link: 'getting-started/local-runtime' },
    ],
  },
  {
    label: 'Build',
    items: [
      { label: 'Authoring', link: 'operations/authoring' },
      { label: 'Generators', link: 'operations/generators' },
      { label: 'Value and Validation', link: 'operations/validation' },
      { label: 'Inline and Deferred', link: 'execution/http-and-deferred' },
      { label: 'ConsoleCommand', link: 'execution/console-command' },
      { label: 'Scheduled Operation', link: 'operations/scheduled-operation' },
      { label: 'Authentication', link: 'auth/authentication' },
      { label: 'Authorization', link: 'auth/authorization' },
      { label: 'Frontend', link: 'frontend' },
      { label: 'BlackOps Board Reference Application', link: 'testing/community-board' },
    ],
  },
  {
    label: 'Async and Lifecycle',
    items: [
      { label: 'Lifecycle', link: 'concepts/lifecycle' },
      { label: 'Execution Context', link: 'execution/context' },
      { label: 'Outcome', link: 'database/outcomes' },
      { label: 'Outbox', link: 'execution/outbox' },
      { label: 'Journal', link: 'concepts/journal' },
    ],
  },
  {
    label: 'Data and Security',
    items: [
      { label: 'Transaction', link: 'database/transactions' },
      { label: 'Migration', link: 'database/migrations' },
      { label: 'Seeder', link: 'database/seeding' },
      { label: 'Retention', link: 'database/retention' },
      { label: 'Security', link: 'security' },
      { label: 'Tenant and Storage Protection', link: 'security/tenant-protection' },
    ],
  },
  {
    label: 'Operate',
    items: [
      { label: 'Configuration', link: 'reference/configuration' },
      { label: 'Deployment', link: 'deployment/worker-operations' },
      { label: 'Observability', link: 'reference/observability' },
      { label: 'Testing', link: 'testing' },
      { label: 'Troubleshooting', link: 'troubleshooting' },
    ],
  },
  {
    label: 'Reference',
    items: [
      { label: 'BlackOps CLI', link: 'reference/project-cli' },
      { label: 'Application Bootstrap', link: 'reference/application-bootstrap' },
      { label: 'Core API', link: 'reference/core-api' },
      { label: 'Attributes', link: 'reference/attributes' },
      { label: 'Observer Replay', link: 'reference/observer-replay' },
      { label: 'Glossary', link: 'reference/glossary' },
    ],
  },
  {
    label: 'Releases',
    items: ['releases/current-status'],
  },
];

const itemSlug = (item) => typeof item === 'string' ? item : item.link;

const blumeItem = (item, sectionLabel) => {
  if (typeof item === 'string') {
    return { label: sectionLabel, root: item };
  }

  return { label: item.label, root: item.link };
};

export const blumeSidebar = sidebar.map(({ label, items }) => {
  if (items.length === 1 && typeof items[0] === 'string') {
    return label === 'Releases'
      ? { label, root: items[0], items: [], display: /** @type {'flat'} */ ('flat') }
      : { label, root: items[0] };
  }

  return { label, items: items.map((item) => blumeItem(item, label)) };
});

export function validateNavigation(contentMap, navigation = sidebar) {
  const labels = navigation.map(({ label }) => label);
  if (JSON.stringify(labels) !== JSON.stringify(requiredSections)) {
    throw new Error(`Sidebar must contain the required public sections in order: ${requiredSections.join(', ')}`);
  }

  const mapEntries = Object.entries(contentMap);
  const mappedEntries = mapEntries.filter(([, metadata]) => metadata.slug !== 'index');
  const mapped = mappedEntries.map(([, metadata]) => metadata.slug);
  const mapBySlug = new Map(mappedEntries.map(([source, metadata]) => [metadata.slug, { source, metadata }]));
  const invalidSections = mapEntries.filter(([, metadata]) => !requiredSections.includes(metadata.section));
  if (invalidSections.length > 0) {
    throw new Error(`Content Map entries must declare one canonical section: ${invalidSections.map(([source]) => source).join(', ')}`);
  }
  const landing = mapEntries.find(([, metadata]) => metadata.slug === 'index');
  if (landing?.[1].section !== 'Start Here') {
    throw new Error('Landing Content Map entry must belong to Start Here.');
  }
  const releases = navigation.find(({ label }) => label === 'Releases');
  if (releases?.items.length !== 1 || typeof releases.items[0] !== 'string' || itemSlug(releases.items[0]) !== 'releases/current-status') {
    throw new Error('Releases must be a direct singleton root for releases/current-status.');
  }

  const placed = navigation.flatMap(({ items }) => items.map(itemSlug));
  const duplicates = placed.filter((slug, index) => placed.indexOf(slug) !== index);
  if (duplicates.length > 0) {
    throw new Error(`Sidebar contains duplicate public slugs: ${[...new Set(duplicates)].join(', ')}`);
  }

  const missing = mapped.filter((slug) => !placed.includes(slug));
  if (missing.length > 0) {
    throw new Error(`Sidebar is missing public documentation: ${missing.join(', ')}`);
  }
  const unknown = placed.filter((slug) => !mapped.includes(slug));
  if (unknown.length > 0) {
    throw new Error(`Sidebar references unknown public documentation: ${unknown.join(', ')}`);
  }

  for (const section of navigation) {
    for (const item of section.items) {
      const slug = itemSlug(item);
      const entry = mapBySlug.get(slug);
      if (entry?.metadata.section !== section.label) {
        throw new Error(`Sidebar entry is in the wrong section: ${slug} (expected ${entry?.metadata.section ?? 'unknown'}, found ${section.label})`);
      }
    }
  }

  const expectedItems = sidebar.map(({ items }) => items.map(itemSlug));
  const actualItems = navigation.map(({ items }) => items.map(itemSlug));
  if (JSON.stringify(actualItems) !== JSON.stringify(expectedItems)) {
    throw new Error('Sidebar public entries must match the required learning order.');
  }
}

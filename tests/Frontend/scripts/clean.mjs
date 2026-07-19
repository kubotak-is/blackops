import { rm } from 'node:fs/promises';

const roots = [
  new URL('../.build', import.meta.url),
  new URL('../fixture/resources/js/blackops', import.meta.url),
  new URL('../fixture/var/build', import.meta.url),
];

await Promise.all(roots.map((root) => rm(root, { recursive: true, force: true })));
await import('./prepare.mjs');

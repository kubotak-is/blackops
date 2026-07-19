import { rm } from 'node:fs/promises';

await Promise.all([
  rm(new URL('../../.build/', import.meta.url), { recursive: true, force: true }),
  rm(new URL('../../resources/js/blackops/', import.meta.url), { recursive: true, force: true }),
]);

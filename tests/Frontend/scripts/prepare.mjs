import { mkdir } from 'node:fs/promises';

await mkdir(new URL('../fixture/var/build/', import.meta.url), { recursive: true });

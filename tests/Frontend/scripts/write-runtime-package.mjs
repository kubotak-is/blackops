import { mkdir, writeFile } from 'node:fs/promises';

const runtime = new URL('../.build/runtime/', import.meta.url);
await mkdir(runtime, { recursive: true });
await writeFile(new URL('package.json', runtime), '{"type":"commonjs"}\n', 'utf8');

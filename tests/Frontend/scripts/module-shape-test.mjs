import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const operationsRoot = new URL('../fixture/resources/js/blackops/operations/', import.meta.url);

async function sourceFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const path = new URL(`${entry.name}${entry.isDirectory() ? '/' : ''}`, directory);
    if (entry.isDirectory()) {
      files.push(...await sourceFiles(path));
    } else if (entry.name.endsWith('.ts')) {
      files.push(path);
    }
  }
  return files;
}

const operationFiles = await sourceFiles(operationsRoot);
assert.equal(operationFiles.length, 2);

for (const file of operationFiles) {
  const source = await readFile(file, 'utf8');
  const imports = [...source.matchAll(/from\s+'([^']+)'/g)].map((match) => match[1]);
  assert.match(source, /export const [A-Z][A-Za-z0-9]+ = Object\.freeze\(/);
  assert.doesNotMatch(source, /export default/);
  assert.ok(imports.length > 0);
  assert.ok(imports.every((specifier) => /\/(client|types)$/.test(specifier)));
  assert.ok(imports.every((specifier) => !specifier.includes('/operations/')));
}

process.stdout.write(`Frontend module shape assertions passed for ${operationFiles.length} operations.\n`);

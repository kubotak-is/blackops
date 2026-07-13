import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guide = (name) => readFile(path.join(repositoryRoot, 'docs/guide', name), 'utf8');

test('reader orientation explains the headless unified model and its journal boundary', async () => {
  const why = await guide('why-blackops.md');

  assert.match(why, /Headless Operation Framework/);
  assert.match(why, /HTTP Controller、CLI Command、Deferred Workerなどの入口から分離/);
  assert.match(why, /No operation stays in the dark/);
  assert.match(why, /Operationとして受理する前のProtocol Error/);
  assert.match(why, /一対一のAPI移植表ではありません/);
  for (const mapping of [
    'Controller / Action',
    'FormRequest / Request DTO',
    'API Resource / Response DTO',
    'Job / Messenger Message / Queue',
    'Audit Log / Process History',
  ]) {
    assert.match(why, new RegExp(mapping.replaceAll('/', '\\/')));
  }
});

test('four Mermaid diagrams include accessible source descriptions and prose alternatives', async () => {
  const sources = await Promise.all(
    ['core-concepts.md', 'execution.md', 'operation-lifecycle.md', 'execution-context.md'].map(guide),
  );
  const diagrams = sources.flatMap((source) => [...source.matchAll(/```mermaid\n([\s\S]*?)\n```/g)]);

  assert.equal(diagrams.length, 4);
  for (const [, diagram] of diagrams) {
    assert.match(diagram, /^\s*accTitle:\s*\S.+$/m);
    assert.match(diagram, /^\s*accDescr:\s*\S.+$/m);
  }
  for (const source of sources) {
    assert.match(source, /図のテキスト代替/);
  }
});

test('glossary defines every required BlackOps term', async () => {
  const glossary = await guide('glossary.md');
  const terms = [
    'Operation',
    'Attempt',
    'Claim',
    'Lease',
    'Fencing Token',
    'Heartbeat',
    'Projection',
    'Manifest',
    'Dead Letter',
    'Journal',
    'Outcome',
    'Correlation',
    'Causation',
    'Retention',
  ];

  for (const term of terms) {
    assert.match(glossary, new RegExp(`^## ${term}$`, 'm'));
  }
});

test('diagram renderer and syntax parser are exact local dependencies', async () => {
  const packageJson = JSON.parse(await readFile(path.join(repositoryRoot, 'docs/website/package.json'), 'utf8'));
  const config = await readFile(path.join(repositoryRoot, 'docs/website/astro.config.mjs'), 'utf8');
  const responsiveCss = await readFile(
    path.join(repositoryRoot, 'docs/website/src/styles/diagram-responsive.css'),
    'utf8',
  );

  assert.equal(packageJson.devDependencies['astro-mermaid'], '2.1.0');
  assert.equal(packageJson.devDependencies.jsdom, '29.1.1');
  assert.equal(packageJson.devDependencies.mermaid, '11.16.0');
  assert.equal(packageJson.scripts['diagrams:check'], 'node scripts/check-diagrams.mjs');
  assert.match(packageJson.scripts.check, /diagrams:check/);
  assert.match(packageJson.scripts.build, /diagrams:check/);
  assert.match(config, /mermaid\(\{/);
  assert.match(config, /autoTheme: true/);
  assert.match(config, /customCss: \['\.\/src\/styles\/diagram-responsive\.css'\]/);
  assert.doesNotMatch(config, /rehype-mermaid|playwright|@astrojs\/markdown-remark/);
  assert.doesNotMatch(config, /cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com/);
  assert.match(responsiveCss, /> pre\.mermaid/);
  assert.match(responsiveCss, /max-inline-size: 100%/);
  assert.match(responsiveCss, /min-inline-size: 0/);
  assert.match(responsiveCss, /overflow-x: auto/);
  assert.match(responsiveCss, /> pre\.mermaid > svg/);
  assert.match(responsiveCss, /max-inline-size: none !important/);
  assert.match(responsiveCss, /@media \(max-width: 50rem\)/);
  assert.match(responsiveCss, /min-inline-size: 60rem/);
});

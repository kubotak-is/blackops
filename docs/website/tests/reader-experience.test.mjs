import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guide = (name) => readFile(path.join(repositoryRoot, 'docs/guide', name), 'utf8');

async function phpFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const nested = await Promise.all(entries.map((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? phpFiles(target) : entry.name.endsWith('.php') ? [target] : [];
  }));
  return nested.flat();
}

async function publicApiTypes() {
  const files = await phpFiles(path.join(repositoryRoot, 'src'));
  const types = [];
  for (const file of files) {
    const source = await readFile(file, 'utf8');
    if (!source.includes('#[PublicApi]')) continue;
    const namespace = source.match(/^namespace ([^;]+);$/m)?.[1];
    const declaration = source.match(/^(?:final |abstract )?(?:readonly )?(?:class|interface|enum) ([A-Za-z0-9_]+)/m)?.[1];
    assert.ok(namespace && declaration, file);
    types.push(`${namespace}\\${declaration}`);
  }
  return types.sort();
}

function prose(markdown) {
  let fenced = false;
  return markdown
    .split('\n')
    .filter((line) => {
      if (/^```/.test(line)) {
        fenced = !fenced;
        return false;
      }
      return !fenced;
    })
    .join('\n');
}

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

test('guided tutorial pairs runnable inputs with parseable JSON and masked JSONL evidence', async () => {
  const tutorial = await guide('first-operation.md');
  const jsonBlocks = [...tutorial.matchAll(/```json\n([\s\S]*?)\n```/g)].map((match) => match[1]);
  const jsonlBlocks = [...tutorial.matchAll(/```jsonl\n([\s\S]*?)\n```/g)].map((match) => match[1]);

  assert.match(tutorial, /Operation Sourceを書く/);
  assert.match(tutorial, /HTTP 202/);
  assert.match(tutorial, /Outcome用HTTP endpointやCLI Commandを提供しません/);
  assert.match(tutorial, /OutcomeReader/);
  assert.ok(jsonBlocks.length >= 3);
  for (const block of jsonBlocks) JSON.parse(block);
  assert.equal(jsonlBlocks.length, 1);
  for (const line of jsonlBlocks[0].split('\n')) JSON.parse(line);
  assert.match(jsonlBlocks[0], /\[masked\]/);
  assert.doesNotMatch(jsonlBlocks[0], /REPORT_API_TOKENから入力/);
});

test('troubleshooting covers every required symptom with four-part guidance', async () => {
  const troubleshooting = await guide('troubleshooting.md');
  for (const symptom of [
    'Typed Self-handled Signature Error',
    'Operation Discovery／Manifest未登録',
    'Build Artifact不在／Build ID不一致',
    'Deferred HTTPが202だがOutcomeがない',
    'Migration未適用／PostgreSQL接続失敗',
    'journal.jsonlへ出力されない',
    'OutcomeがPending／Not Found／Expiredか判別できない',
    'Sensitive値がJournalで見えない',
  ]) {
    const start = troubleshooting.indexOf(`## ${symptom}`);
    assert.notEqual(start, -1, symptom);
    const next = troubleshooting.indexOf('\n## ', start + 4);
    const section = troubleshooting.slice(start, next === -1 ? undefined : next);
    for (const label of ['**Symptom:**', '**Likely Cause:**', '**How to Verify:**', '**Fix:**']) {
      assert.match(section, new RegExp(label.replaceAll('*', '\\*')));
    }
  }
});

test('security guide separates framework and application responsibilities', async () => {
  const security = await guide('security.md');
  assert.match(security, /Frameworkが提供する境界 \| Application／運用の責務/);
  for (const responsibility of [
    'Authentication',
    'Authorization',
    'Tenant Isolation',
    'TLS',
    '保存時暗号化',
    'Key管理',
    'Sink Access Control',
    'Backup',
    'Legal Hold',
    'Credential Rotation',
  ]) {
    assert.match(security, new RegExp(responsibility));
  }
  assert.match(security, /認証、認可、暗号化、Access Control、Retentionを代替しません/);
});

test('core API reference covers every source type marked PublicApi without exposing Internal types', async () => {
  const reference = await guide('core-api.md');
  const sourceTypes = await publicApiTypes();

  assert.equal(sourceTypes.length, 111);
  for (const type of sourceTypes) assert.match(reference, new RegExp(type.replaceAll('\\', '\\\\')));
  assert.doesNotMatch(reference, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
  assert.doesNotMatch(reference, /BlackOps\\Internal\\[A-Za-z]/);
});

test('attributes reference covers the eleven public authoring attributes and excludes the marker', async () => {
  const attributes = await guide('attributes.md');
  const expected = [
    'BlackOps\\Core\\Attribute\\Accepts',
    'BlackOps\\Core\\Attribute\\ExecuteWith',
    'BlackOps\\Core\\Attribute\\HandledBy',
    'BlackOps\\Core\\Attribute\\OperationType',
    'BlackOps\\Core\\Attribute\\Returns',
    'BlackOps\\Core\\Attribute\\Sensitive',
    'BlackOps\\Http\\Attribute\\FromBody',
    'BlackOps\\Http\\Attribute\\FromHeader',
    'BlackOps\\Http\\Attribute\\FromPath',
    'BlackOps\\Http\\Attribute\\FromQuery',
    'BlackOps\\Http\\Attribute\\Route',
  ];

  for (const attribute of expected) assert.match(attributes, new RegExp(attribute.replaceAll('\\', '\\\\')));
  const sourceTypes = (await publicApiTypes()).filter((type) => expected.includes(type));
  assert.deepEqual(sourceTypes, [...expected].sort());
  assert.match(attributes, /Public Attribute 11件/);
  assert.match(attributes, /SensitiveMode.*Attributeではなく/s);
  assert.doesNotMatch(attributes, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
});

test('every public guide uses Japanese prose and avoids specification-style sentence endings', async () => {
  const files = (await readdir(path.join(repositoryRoot, 'docs/guide')))
    .filter((file) => file.endsWith('.md'))
    .sort();

  for (const file of files) {
    const body = prose(await guide(file));
    assert.match(body, /[ぁ-んァ-ヶ一-龠]/, file);
    assert.doesNotMatch(body, /(?:する|される|である|ものとする)。$/m, file);
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
  assert.match(responsiveCss, /overflow-wrap: anywhere/);
  assert.match(responsiveCss, /:not\(pre\) > code/);
  assert.match(responsiveCss, /pre:not\(\.mermaid\)/);
  assert.match(responsiveCss, /> table/);
  assert.match(responsiveCss, /inline-size: max-content/);
  assert.doesNotMatch(responsiveCss, /overflow-x: clip/);
  assert.match(responsiveCss, /> pre\.mermaid > svg/);
  assert.match(responsiveCss, /max-inline-size: none !important/);
  assert.match(responsiveCss, /@media \(max-width: 50rem\)/);
  assert.match(responsiveCss, /min-inline-size: 60rem/);
});

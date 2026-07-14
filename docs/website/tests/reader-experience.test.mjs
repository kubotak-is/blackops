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

test('landing presents the product title, three feature links, and Installation as the primary action', async () => {
  const landing = await guide('README.md');
  const contentMapSource = await readFile(path.join(repositoryRoot, 'docs/website/content-map.mjs'), 'utf8');
  const links = [...landing.matchAll(/<a class="landing-feature-link" href="([^"]+)">/g)];

  assert.match(landing, /^# BlackOps — The PHP Framework$/m);
  assert.equal(links.length, 3);
  for (const value of [
    'Operationが中心',
    'Journalですべてを可視化',
    '非同期処理を標準装備',
  ]) assert.match(landing, new RegExp(value));
  assert.deepEqual(links.map((link) => link[1]), [
    '/operations/authoring/',
    '/concepts/lifecycle/',
    '/execution/http-and-deferred/',
  ]);
  assert.match(contentMapSource, /actions: \[\s*\{ text: 'Installation', link: '\/getting-started\/installation\/'/s);
  assert.match(contentMapSource, /\{ text: 'Why BlackOps'.*variant: 'secondary'/);
});

test('static redirects preserve all four moved public URLs', async () => {
  const redirects = await readFile(path.join(repositoryRoot, 'docs/website/public/_redirects'), 'utf8');

  assert.equal(redirects, [
    '/operations/lifecycle/* /concepts/lifecycle/:splat 301',
    '/reference/security/* /security/:splat 301',
    '/reference/troubleshooting/* /troubleshooting/:splat 301',
    '/reference/current-status/* /releases/current-status/:splat 301',
    '',
  ].join('\n'));
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
  for (const source of sources) assert.doesNotMatch(source, /図のテキスト代替/);
  assert.match(sources[0], /Operationは`OperationValue`を第一引数/);
  assert.match(sources[1], /InlineはHTTP Request内/);
  assert.match(sources[2], /\| Inline成功 \| Received → Running → Finalizing → Completed \|/);
  assert.match(sources[3], /\| Identifier \| 関係 \|/);
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

  assert.match(tutorial, /^# チュートリアル: Operationを作る$/m);
  assert.match(tutorial, /php blackops make:operation Billing\/CreateInvoice --type=billing\.invoice\.create/);
  assert.ok(tutorial.indexOf('make:operation') < tutorial.indexOf('```php'));
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

  assert.equal(sourceTypes.length, 119);
  for (const type of sourceTypes) assert.match(reference, new RegExp(type.replaceAll('\\', '\\\\')));
  assert.doesNotMatch(reference, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
  assert.doesNotMatch(reference, /BlackOps\\Internal\\[A-Za-z]/);
});

test('attributes reference covers the eighteen public authoring attributes and excludes the marker', async () => {
  const attributes = await guide('attributes.md');
  const expected = [
    'BlackOps\\Core\\Attribute\\Accepts',
    'BlackOps\\Core\\Attribute\\ExecuteWith',
    'BlackOps\\Core\\Attribute\\HandledBy',
    'BlackOps\\Core\\Attribute\\OperationType',
    'BlackOps\\Core\\Attribute\\Returns',
    'BlackOps\\Core\\Attribute\\Sensitive',
    'BlackOps\\Core\\Validation\\Attribute\\Choice',
    'BlackOps\\Core\\Validation\\Attribute\\Count',
    'BlackOps\\Core\\Validation\\Attribute\\Email',
    'BlackOps\\Core\\Validation\\Attribute\\Length',
    'BlackOps\\Core\\Validation\\Attribute\\NotBlank',
    'BlackOps\\Core\\Validation\\Attribute\\Range',
    'BlackOps\\Core\\Validation\\Attribute\\Regex',
    'BlackOps\\Http\\Attribute\\FromBody',
    'BlackOps\\Http\\Attribute\\FromHeader',
    'BlackOps\\Http\\Attribute\\FromPath',
    'BlackOps\\Http\\Attribute\\FromQuery',
    'BlackOps\\Http\\Attribute\\Route',
  ];

  for (const attribute of expected) assert.match(attributes, new RegExp(attribute.replaceAll('\\', '\\\\')));
  const sourceTypes = (await publicApiTypes()).filter((type) => expected.includes(type));
  assert.deepEqual(sourceTypes, [...expected].sort());
  assert.match(attributes, /Public Attribute 18件/);
  assert.match(attributes, /SensitiveMode.*Attributeではなく/s);
  assert.doesNotMatch(attributes, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
});

test('validation guide matches declarative and rejection lifecycle boundaries', async () => {
  const validation = await guide('validation.md');

  for (const attribute of ['NotBlank', 'Length', 'Range', 'Email', 'Regex', 'Count', 'Choice']) {
    assert.match(validation, new RegExp(`Validation\\\\Attribute\\\\${attribute}|\\b${attribute}\\b`));
  }
  assert.match(validation, /Symfony Validator/);
  assert.match(validation, /壊れたJSON.*400/s);
  assert.match(validation, /必須Field欠落.*422/s);
  assert.match(validation, /宣言的Value Validation.*422/s);
  assert.match(validation, /OperationRejectedException::validation/);
  assert.match(validation, /InlineとDeferredのどちらもHTTP受付中に422/);
  assert.match(validation, /Deferredでは一般Validationを通過した時点でHTTP 202/);
  assert.match(validation, /Array／Nested ObjectのHTTP Binding、宣言的DB照合、Cross-field Attribute、Custom Callback/);
  assert.match(validation, /Count.*現行HTTP Binder.*binding\.type/s);
});

test('runtime guide keeps Worker Mode default with request safety and Classic fallback', async () => {
  const runtime = await guide('runtime-bootstrap.md');

  assert.match(runtime, /Default Worker Mode/);
  assert.match(runtime, /docker compose up -d/);
  assert.match(runtime, /Application、Environment、Configuration、Compile済みRuntime/);
  assert.match(runtime, /Database Connection/);
  assert.match(runtime, /Operation Scope/);
  assert.match(runtime, /JSONL Journal.*flush/);
  assert.match(runtime, /\$_ENV/);
  assert.match(runtime, /FRANKENPHP_MAX_REQUESTS/);
  assert.match(runtime, /classic-mode/);
  assert.match(runtime, /http-classic/);
  assert.match(runtime, /Classic Modeは明示Fallback/);
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
  assert.match(responsiveCss, /aria-roledescription='sequence'/);
  assert.match(responsiveCss, /min-inline-size: 72rem/);
  assert.match(responsiveCss, /\.landing-feature-grid/);
  assert.match(responsiveCss, /\.landing-feature-link:focus-visible/);
  assert.match(responsiveCss, /html\[data-has-hero\]/);
  assert.match(responsiveCss, /grid-template-columns: repeat\(3, minmax\(0, 1fr\)\)/);
  assert.match(responsiveCss, /@media \(prefers-reduced-motion: reduce\)/);
  assert.match(responsiveCss, /transition: none/);
});

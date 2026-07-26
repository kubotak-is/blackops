import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
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
  assert.match(why, /HTTP Controller、BlackOps CLI、Deferred Workerなどの入口から分離/);
  assert.match(why, /Lifecycle Journalで実行事実を追跡する/);
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

test('guide landing source keeps the exact product title and three claims', async () => {
  const landing = await guide('README.md');

  assert.match(landing, /^# BlackOps - The PHP Framework$/m);
  for (const value of ['Operation', 'Journal', 'BlackOpsはフロントエンドを持ちません', 'JavaScript向けに接続クライアントのコードを自動生成します']) {
    assert.match(landing, new RegExp(value));
  }
  assert.match(landing, /\]\(frontend\.md\)/);
});

test('landing product contract keeps the why link and exact Deferred sample', async () => {
  const landing = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');

  assert.match(landing, /href="\/concepts\/why-blackops"/);
  assert.doesNotMatch(landing, /href="https:\/\/github\.com\/kubotak-is\/blackops"/);
  const sample = landing.match(/<pre><code>([\s\S]*?)<\/code><\/pre>/)?.[1] ?? '';
  const rendered = sample
    .replace(/<[^>]+>/g, '')
    .replace(/&#123;/g, '{')
    .replace(/&#125;/g, '}');
  assert.match(rendered, /#\[Route\(method: 'POST', path: '\/reports'\)\]/);
  assert.match(rendered, /#\[Deferred\]/);
  assert.match(rendered, /\n    public function handle\(\n        GenerateReportValue \$value,\n        ExecutionContext \$context,\n    \): ReportGenerated\n    \{\n        return new ReportGenerated\(\n            \$value->reportName,\n            '\/reports\/generated\/' \. \$value->reportName \. '\.json',\n        \);\n    \}/);
});

test('Journal guide is reachable from the landing CTA and explains its reader boundary', async () => {
  const [landing, journal] = await Promise.all([
    readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8'),
    guide('journal.md'),
  ]);

  const journalCard = landing.match(/<article class="landing-feature landing-feature-journal">([\s\S]*?)<\/article>/)?.[1] ?? '';
  assert.match(journalCard, /<h3>Journal<\/h3>[\s\S]*href="\/concepts\/journal"/);
  assert.equal((journalCard.match(/<a href=/g) ?? []).length, 1, 'Journal feature has one CTA');
  assert.match(journal, /^# Journal$/m);
  for (const phrase of [
    'Canonical Journal',
    'Observed Projection',
    'Canonical PostgreSQL Journalの公開シリアライズではありません',
    'Observer Replay',
    'Operation Replayとは別物',
    '絶対Path',
    'best_effort',
    "'path' => dirname(__DIR__) . '/var/log/journal.jsonl'",
    'Observer FailureをOperationの失敗にせず',
    '保存時暗号化',
    '鍵の生成・保管・Rotation・失効',
    'OpenTelemetryのAdapter、Exporter、Configurationは実装されていません',
    'Public Contractではありません',
  ]) assert.ok(journal.includes(phrase), phrase);
});

test('ephemeral outcome guides use implicit Inline authoring', async () => {
  const [authentication, operations, attributes, coreApi, communityBoard, glossary, security] = await Promise.all([
    guide('authentication.md'),
    guide('operations.md'),
    guide('attributes.md'),
    guide('core-api.md'),
    guide('community-board.md'),
    guide('glossary.md'),
    guide('security.md'),
  ]);

  assert.doesNotMatch(authentication, /ExecuteWith|Execution\\\\Inline/);
  assert.match(operations, /AttributeなしのExecution StrategyはInlineへ解決されます/);
  assert.doesNotMatch(operations, /明示的なInline Strategy|暗黙のInlineはBuild Error/);
  assert.match(attributes, /InlineはAttributeを省略する/);
  assert.match(coreApi, /InlineはAttributeを省略する/);
  assert.match(communityBoard, /AttributeなしでInlineへ解決される/);
  assert.match(glossary, /Execution Strategy Attributeを省略するとInlineへ解決され/);
  assert.match(security, /HTTP Route付きでInlineへ解決されたOperation/);
});

test('authoritative Ephemeral sources keep the implicit Inline contract', async () => {
  const files = [
    'develop/spec/04-handler-and-result.md',
    'develop/spec/17-core-api.md',
    'develop/spec/71-full-stack-reference-application.md',
    'develop/spec/74-application-ergonomics.md',
    'develop/spec/75-phase-18-delivery-plan.md',
    'develop/spec/87-documentation-second-review-and-feature-parity.md',
    'develop/spec/90-documentation-third-review-accuracy.md',
    'docs/internal/auth-generator.md',
    'docs/internal/ephemeral-outcome.md',
    'examples/community-board/README.md',
  ];
  const sources = await Promise.all(files.map((file) => readFile(path.join(repositoryRoot, file), 'utf8')));
  const [d112, specificationIndex] = await Promise.all([
    readFile(path.join(repositoryRoot, 'develop/decisions/112-authentication-credential-response-boundary.md'), 'utf8'),
    readFile(path.join(repositoryRoot, 'develop/spec/README.md'), 'utf8'),
  ]);

  for (const source of sources) {
    assert.doesNotMatch(source, /Route付き明示Inline|Inline実行を選ぶ場合は.*必要|Inline明示を必須/);
    assert.match(source, /Inlineへ解決|resolves to Inline|resolves to inline/);
  }
  assert.match(d112, /Status: Partially Superseded by D126/);
  assert.match(d112, /D126 supersedes Decision item 2/);
  assert.match(specificationIndex, /D112.*Partially Superseded by D126/);
});

test('Community Board guide presents the local full-stack journey and credential-free evidence', async () => {
  const [guideSource, testing, status, screenshot] = await Promise.all([
    guide('community-board.md'),
    guide('testing.md'),
    guide('mvp-status.md'),
    readFile(path.join(repositoryRoot, 'docs/guide/assets/community-board/blackops-board.png')),
  ]);

  assert.match(guideSource, /^# BlackOps Board Reference Application$/m);
  assert.match(guideSource, /!\[BlackOps BoardのCredential-free Landing画面\]\(assets\/community-board\/blackops-board\.png\)/);
  assert.equal(createHash('sha256').update(screenshot).digest('hex'), 'a7619b25d97b6ac1e4eba42888968d71fd1633102836a105a2d6c1c94501945d');
  assert.match(guideSource, /Quickstart.*Frameworkの最短Contract/s);
  assert.match(guideSource, /Browser[\s\S]*SvelteKit same-origin UI \/ BFF[\s\S]*Server-only Generated Operation Object/);
  assert.match(guideSource, /app\/Domain\/Board\/[\s\S]*app\/Domain\/Identity\/[\s\S]*app\/Infrastructure\/[\s\S]*app\/Feature\//);
  assert.match(guideSource, /PasswordとRaw Session Tokenは`#\[Sensitive\]`なEphemeral Value／Outcomeにだけ存在します/);
  assert.match(guideSource, /Outcome Store、Status API、Generated Artifact、Page Data、Browser Bundle、LogへCredentialを残しません/);
  for (const topic of ['Worker未起動', 'Seed Conflict', 'Port衝突', 'Generated Drift', 'Secure Cookie Local設定']) {
    assert.match(guideSource, new RegExp(`^### ${topic}$`, 'm'));
  }
  assert.match(testing, /BlackOps Board.*Application-owned Identity.*Framework Session Core.*SvelteKit .*BFF/s);
  assert.match(status, /Stable `1\.1\.0` Skeletonには含まれず、公開Hostも提供していません/);
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

test('Mermaid artifact guards require native elements and reject syntax-highlighted fences', async () => {
  const [artifact, site] = await Promise.all([
    readFile(path.join(repositoryRoot, 'docs/website/scripts/check-artifact.mjs'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/scripts/check-site.mjs'), 'utf8'),
  ]);

  assert.match(artifact, /<blume-mermaid/);
  assert.match(artifact, /mermaidCodeBlockCount/);
  assert.match(artifact, /mermaidCodeBlockCount !== 0/);
  assert.match(artifact, /mermaidLegibilityStylesheetCount/);
  assert.match(artifact, /max-width:700px/);
  assert.match(artifact, /min-width:42rem/);
  assert.match(artifact, /height:auto/);
  assert.match(artifact, /width:100%/);
  assert.match(site, /<blume-mermaid/);
  assert.match(site, /data-language="mermaid"/);
  assert.match(site, /must not contain a Mermaid syntax-highlighted code block/);
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

  assert.match(tutorial, /^# First Operation$/m);
  assert.match(tutorial, /php blackops make:operation Billing\/CreateInvoice --type=billing\.invoice\.create/);
  assert.ok(tutorial.indexOf('make:operation') < tutorial.indexOf('```php'));
  assert.match(tutorial, /HTTP 202/);
  assert.match(tutorial, /Public Status Resource/);
  assert.match(tutorial, /OutcomeReader/);
  assert.ok(jsonBlocks.length >= 3);
  for (const block of jsonBlocks) JSON.parse(block);
  assert.equal(jsonlBlocks.length, 1);
  for (const line of jsonlBlocks[0].split('\n')) JSON.parse(line);
  assert.match(jsonlBlocks[0], /\[masked\]/);
  assert.doesNotMatch(jsonlBlocks[0], /REPORT_API_TOKENから入力/);
  assert.match(tutorial, /HTTP ProcessのObserved Projection/);
  assert.match(tutorial, /Canonical PostgreSQL Journalは.*正本/s);
  assert.match(tutorial, /\.status\(\).*一回.*\.wait\(\).*有限待機/s);
});

test('troubleshooting covers every required symptom with four-part guidance', async () => {
  const troubleshooting = await guide('troubleshooting.md');
  for (const symptom of [
    'Typed Self-handled Signature Error',
    'Operation Discovery／Manifest未登録',
    'Build Artifact不在／Build ID不一致',
    'Frontend Contract ArtifactがInvalid／Stale',
    'Frontend Generated TreeがMissing／Drift',
    'Generated TypeScriptがCompileできない',
    '`.fetch()`がTransport Resultを返す',
    'Deferred HTTPが202だがOutcomeがない',
    'Statusが404 `operation_unavailable`を返す',
    'Statusが410 `operation_expired`を返す',
    '`.wait()`が`poll_timeout`を返す',
    '`.status()`／`.wait()`が`unexpected_response`を返す',
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
    'Frontend Contract',
  ]) {
    assert.match(security, new RegExp(responsibility));
  }
  assert.match(security, /認証、認可、暗号化、Access Control、Retentionを代替しません/);
  assert.match(security, /Header欠落.*Anonymousとして通過.*Operation ID付き401/s);
  assert.match(security, /Header不一致.*Operationを受け付けずJournalなし.*Operation IDなし401/s);
  assert.match(security, /Credential、Role、Permission、ClaimのSnapshotはTransportやJournalへ保存しません/);
  assert.match(security, /Generated Typeは認証、認可、暗号化、Access Control、Retentionを代替しません/);
  assert.match(security, /Global Mutable Clientへ保存しないでください/);
  assert.match(security, /OperationStatusAuthorizer/);
  assert.match(security, /Unknown／Deny.*404/s);
  assert.match(security, /Retention.*410/s);
});

test('core API reference covers every source type marked PublicApi without exposing Internal types', async () => {
  const reference = await guide('core-api.md');
  const sourceTypes = await publicApiTypes();

  assert.equal(sourceTypes.length, 175);
  assert.ok(sourceTypes.includes('BlackOps\\Core\\EphemeralOutcome'));
  assert.ok(sourceTypes.includes('BlackOps\\Http\\SapiRuntime'));
  assert.ok(sourceTypes.includes('BlackOps\\Identifier\\Uuidv7Generator'));
  assert.ok(sourceTypes.includes('BlackOps\\Idempotency\\IdempotencyKey'));
  assert.ok(sourceTypes.includes('BlackOps\\Idempotency\\IdempotencyKeyHash'));
  assert.ok(sourceTypes.includes('BlackOps\\Outbox\\TransactionalOutbox'));
  for (const type of sourceTypes) assert.match(reference, new RegExp(type.replaceAll('\\', '\\\\')));
  assert.doesNotMatch(reference, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
  assert.doesNotMatch(reference, /BlackOps\\Internal\\[A-Za-z]/);
});

test('attributes reference covers the twenty-four public authoring attributes and excludes the marker', async () => {
  const attributes = await guide('attributes.md');
  const expected = [
    'BlackOps\\Core\\Attribute\\Accepts',
    'BlackOps\\Core\\Attribute\\Authorize',
    'BlackOps\\Core\\Attribute\\ConsoleCommand',
    'BlackOps\\Core\\Attribute\\Deferred',
    'BlackOps\\Core\\Attribute\\ExecuteWith',
    'BlackOps\\Core\\Attribute\\HandledBy',
    'BlackOps\\Core\\Attribute\\ListOf',
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
    'BlackOps\\Database\\Attribute\\AfterCommit',
    'BlackOps\\Database\\Attribute\\Transactional',
    'BlackOps\\Http\\Attribute\\FromBody',
    'BlackOps\\Http\\Attribute\\FromHeader',
    'BlackOps\\Http\\Attribute\\FromPath',
    'BlackOps\\Http\\Attribute\\FromQuery',
    'BlackOps\\Http\\Attribute\\Route',
  ];

  for (const attribute of expected) assert.match(attributes, new RegExp(attribute.replaceAll('\\', '\\\\')));
  const sourceTypes = (await publicApiTypes()).filter((type) => expected.includes(type));
  assert.deepEqual(sourceTypes, [...expected].sort());
  assert.match(attributes, /Public Attribute 24件/);
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
    if (file === 'README.md') continue;
    assert.doesNotMatch(body, /(?:する|される|である|ものとする)。$/m, file);
  }
});

test('Blume runtime keeps diagrams local and the landing responsive', async () => {
  const packageJson = JSON.parse(await readFile(path.join(repositoryRoot, 'docs/website/package.json'), 'utf8'));
  const config = await readFile(path.join(repositoryRoot, 'docs/website/blume.config.ts'), 'utf8');
  const theme = await readFile(path.join(repositoryRoot, 'docs/website/theme.css'), 'utf8');
  const landing = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');

  assert.equal(packageJson.dependencies.blume, '1.1.4');
  assert.equal(packageJson.devDependencies.astro, '7.0.7');
  assert.equal(packageJson.devDependencies.jsdom, '29.1.1');
  assert.equal(packageJson.devDependencies.mermaid, '11.16.0');
  assert.equal(packageJson.scripts['diagrams:check'], 'node scripts/check-diagrams.mjs');
  assert.match(packageJson.scripts.check, /diagrams:check/);
  assert.match(packageJson.scripts.build, /diagrams:check/);
  assert.match(config, /search: \{ provider: 'orama' \}/);
  assert.match(config, /output: 'static'/);
  assert.match(config, /title: 'BlackOps - The PHP Framework'/);
  assert.match(config, /github: \{ owner: 'kubotak-is', repo: 'blackops' \}/);
  assert.doesNotMatch(config, /BlackOps — The PHP Framework/);
  assert.match(theme, /\.landing-shell/);
  assert.match(theme, /prefers-reduced-motion/);
  assert.match(theme, /blume-mermaid \{[\s\S]*display: block !important/);
  assert.match(theme, /blume-mermaid > div \{[\s\S]*min-width: 42rem/);
  assert.match(theme, /blume-mermaid svg \{[\s\S]*height: auto[\s\S]*width: 100%/);
  assert.match(theme, /\.landing-feature a:focus-visible/);
  assert.match(theme, /\.landing-feature a \{ margin-top: auto; padding-top: 1\.5rem; \}/);
  assert.match(theme, /\.landing-features-grid[\s\S]*grid-template-columns: repeat\(3, minmax\(0, 1fr\)\)/);
  assert.doesNotMatch(theme, /\.landing-feature-operation[\s\S]*grid-row: 1 \/ span 2/);
  assert.match(theme, /\.landing-brand[\s\S]*clamp\(4\.5rem, 9vw, 8\.5rem\)/);
  assert.match(theme, /@media \(max-width: 959px\)[\s\S]*\.landing-features-grid \{ grid-template-columns: 1fr; \}/);
  assert.match(theme, /\.landing-feature-operation \.landing-feature-copy a \{ margin-top: auto; \}/);
  assert.match(theme, /\.landing-tagline[\s\S]*white-space: nowrap/);
  assert.match(theme, /@media \(max-width: 700px\)[\s\S]*\.landing-features-grid \{ display: flex; flex-direction: column; \}/);
  for (const copy of [
    'BlackOps</span><span class="landing-tagline">The PHP Framework',
    'BlackOpsの特徴',
    'composer create-project blackops/skeleton my-app 1.1.0',
    'HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一されます。',
    '受理・試行・リトライ・拒否・完了をFrameworkが自動でJournalへ記録します。「なぜ失敗したか」をFrameworkが記録します。',
    'BlackOpsはフロントエンドを持ちません。代わりに、JavaScript向けに接続クライアントのコードを自動生成します。',
    'フロントエンドはNext.jsでもNuxtでもSvelteKitでもお好きなFrameworkと組み合わせられます。',
  ]) assert.ok(landing.includes(copy), copy);
  for (const forbidden of ['BlackOpsの3つの特徴', 'BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。', 'ONE MODEL / TWO PATHS', 'Operation ↔ Execution', 'Inline HTTP or durable Deferred', 'THE BLACKOPS SHAPE', 'Make the work explicit.', 'Nothing stays in the dark.', 'Bring your frontend.']) {
    assert.ok(!landing.includes(forbidden), forbidden);
  }
});

test('Blume strict validation is mandatory after content generation', async () => {
  const packageJson = JSON.parse(await readFile(path.join(repositoryRoot, 'docs/website/package.json'), 'utf8'));
  assert.match(packageJson.scripts['blume:validate'], /validate-content\.mjs/);
  assert.match(packageJson.scripts.check, /blume:validate/);
  assert.match(packageJson.scripts.build, /blume:validate/);
  assert.match(await readFile(path.join(repositoryRoot, 'docs/website/scripts/validate-content.mjs'), 'utf8'), /validate', '--strict/);
});

test('custom landing links have a permanent static-artifact guard', async () => {
  const checkSite = await readFile(path.join(repositoryRoot, 'docs/website/scripts/check-site.mjs'), 'utf8');
  assert.match(checkSite, /validateLandingLinks/);
  assert.match(checkSite, /Landing link does not resolve to a static artifact/);
  assert.match(checkSite, new RegExp('getting-started/first-operation'));
  assert.match(checkSite, new RegExp('concepts/lifecycle'));
  assert.match(checkSite, new RegExp('href="/frontend'));
  assert.ok(checkSite.includes('const githubAnchor'));
  assert.ok(checkSite.includes('github\\.com'));
  assert.match(checkSite, /aria-label=/);
  assert.match(checkSite, /target="_blank"/);
  assert.match(checkSite, /rel="noreferrer"/);
  assert.match(checkSite, /invalid GitHub edit link/);
});

test('documentation links and examples stay synchronized with current page contracts', async () => {
  const sources = await Promise.all(
    (await readdir(path.join(repositoryRoot, 'docs/guide')))
      .filter((file) => file.endsWith('.md'))
      .map((file) => readFile(path.join(repositoryRoot, 'docs/guide', file), 'utf8')),
  );
  const source = sources.join('\n');
  for (const retired of ['[Testing Overview](', '[DatabaseとTransaction](', '[Current Status](', '[チュートリアル: Operationを作る](']) {
    assert.doesNotMatch(source, new RegExp(retired.replace(/[()[\]]/g, '\\$&')), retired);
  }
  const quickstart = await guide('mvp-sample.md');
  const genericOutcome = await guide('outcome-retrieval.md');
  assert.match(quickstart, /outcome\.location/);
  assert.doesNotMatch(genericOutcome, /outcome\.location/);
  assert.match(source, /ValueまたはOutcome Property/);
  assert.match(source, /Canonical Deferredは引数なし`#\[Deferred\]`/);
  const outcomeExample = (await guide('outcome-retrieval.md')).match(/```ts\n([\s\S]*?)```/)?.[1] ?? '';
  assert.equal((outcomeExample.match(/outcome\.reportName/g) ?? []).length, 1);
  assert.equal((outcomeExample.match(/outcome\.operationId/g) ?? []).length, 1);
});

test('Database configuration example is a standalone Environment closure', async () => {
  const configuration = await guide('configuration.md');
  const database = configuration.match(/## Database[\s\S]*?```php\n([\s\S]*?)```/)?.[1] ?? '';
  assert.match(database, /use BlackOps\\Application\\Environment;/);
  assert.match(database, /return static fn \(Environment \$env\): array => \[/);
  assert.match(database, /\$env->string\('POSTGRES_HOST'\)/);
  assert.doesNotMatch(database, /return static fn[\s\S]*?\nreturn \[/);
});

test('Validation guide links the corrected Japanese anchors', async () => {
  const validation = await guide('validation.md');
  assert.match(validation, /application-bootstrap\.md#operationservicecommand/);
  assert.match(validation, /operations\.md#予期された業務拒否/);
});

test('Blume configuration fixes the public policy, locale, and preserved redirects', async () => {
  const config = await readFile(path.join(repositoryRoot, 'docs/website/blume.config.ts'), 'utf8');
  assert.match(config, /dismissible: false/);
  assert.match(config, /BlackOps1\.xは試験的なバージョンです。Production Readyは2\.xを予定しています。/);
  assert.doesNotMatch(config, /ドキュメントチャンネル/);
  assert.match(config, /link: \{ href: '\/releases\/current-status\/', text: 'Releases' \}/);
  assert.doesNotMatch(config, /<a href=/);
  assert.match(config, /locales: \[\{ code: 'ja'/);
  assert.match(config, /from: '\/reference\/security\//);
  assert.match(config, /from: '\/reference\/troubleshooting\//);
});

test('Blume navigation keeps the Tutorial Board entry and ConsoleCommand label', async () => {
  const navigation = await readFile(path.join(repositoryRoot, 'docs/website/site-navigation.mjs'), 'utf8');
  assert.match(navigation, /label: 'ConsoleCommand'/);
  assert.match(navigation, /BlackOps Board Reference Application/);
  assert.match(navigation, /testing\/community-board/);
});

test('new Blume guide pages describe only implemented boundaries', async () => {
  const pages = await Promise.all(['console-command.md', 'outbox.md', 'authentication.md', 'authorization.md', 'frontend.md'].map(guide));
  for (const page of pages) {
    assert.match(page, /^# /m);
    assert.doesNotMatch(page, /Exactly Onceを提供します|Next\.js Adapterを提供します|Nuxt Adapterを提供します|SvelteKit Adapterを提供します/);
  }
  assert.match(pages[4], /JavaScript／TypeScriptのGenerated Client/);
});

test('Blume content metadata carries the forward-looking 2.x notice without legacy page templates', async () => {
  const source = await readFile(path.join(repositoryRoot, 'docs/website/content-map.mjs'), 'utf8');
  assert.doesNotMatch(source, /Production Readyは2\.xから予定/);
  assert.doesNotMatch(source, /template:|hero:/);
});

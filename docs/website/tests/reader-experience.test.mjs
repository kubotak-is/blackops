import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readdir, readFile } from 'node:fs/promises';
import { JSDOM } from 'jsdom';
import path from 'node:path';
import test from 'node:test';
import { contentMap } from '../content-map.mjs';
import { displayedMarkdown, findEditorialViolations, validateEditorial } from '../scripts/editorial-guard.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guide = (name) => readFile(path.join(repositoryRoot, 'docs/guide', name), 'utf8');
const canonicalLandingSections = ['Start Here', 'Build', 'Async and Lifecycle', 'Data and Security', 'Operate', 'Reference', 'Releases'];

function levelTwoHeadings(markdown) {
  return [...markdown.matchAll(/^##\s+(.+)$/gm)].map(([, heading]) => heading.trim());
}

function assertLandingSections(markdown) {
  assert.deepEqual(levelTwoHeadings(markdown), canonicalLandingSections, 'Landing must expose only the canonical seven level-two sections in order.');
  assert.doesNotMatch(markdown, /^## Reference and Releases$/m, 'Reference and Releases must not be recombined.');
}

function renderedOperationSample(source) {
  return source
    .match(/<div\b(?=[^>]*\bclass=["'][^"']*\blanding-code-panel\b[^"']*["'])[^>]*>(?:\s*<div\b[^>]*>[\s\S]*?<\/div>\s*)*<pre\b[^>]*>\s*<code\b[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/)?.[1]
    ?.replace(/<[^>]+>/g, '')
    .replace(/&#123;/g, '{')
    .replace(/&#125;/g, '}') ?? '';
}

function assertOperationSample(source) {
  const sample = renderedOperationSample(source);
  const operationType = "#[OperationType('report.generate')]";
  assert.equal((sample.match(/#\[OperationType\('report\.generate'\)\]/g) ?? []).length, 1, 'Operation sample must contain exactly one report.generate OperationType.');
  assert.ok(sample.indexOf("#[Route(method: 'POST', path: '/reports')]") < sample.indexOf(operationType));
  assert.ok(sample.indexOf(operationType) < sample.indexOf('#[Deferred]'));
}

function assertLandingRoot(source) {
  assert.equal((source.match(/<main\b/g) ?? []).length, 0, 'Landing source must not introduce a second main landmark.');
  assert.match(source, /<div class="landing-shell">/);
}

function relativeLuminance(hex) {
  const channels = [0, 2, 4].map((offset) => Number.parseInt(hex.slice(offset + 1, offset + 3), 16) / 255);
  return channels
    .map((channel) => channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
    .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
}

function contrastRatio(first, second) {
  const light = Math.max(relativeLuminance(first), relativeLuminance(second));
  const dark = Math.min(relativeLuminance(first), relativeLuminance(second));
  return (light + 0.05) / (dark + 0.05);
}

function assertContrastAtLeast(first, second, minimum) {
  const ratio = contrastRatio(first, second);
  assert.ok(ratio >= minimum, `${first} against ${second} is ${ratio.toFixed(3)}:1; expected at least ${minimum}:1.`);
}

function cssVariable(css, selector, name) {
  const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const block = css.match(new RegExp(`${escapedSelector}\\s*\\{([^}]*)\\}`))?.[1] ?? '';
  return block.match(new RegExp(`${name}\\s*:\\s*(#[0-9a-f]{6})`, 'i'))?.[1] ?? '';
}

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
  assert.match(why, /HTTPやWorkerから受けた処理を一つの処理単位として、同じIDで受付・再試行・完了を確認/);
  assert.match(why, /この処理単位をOperationと呼びます/);
  assert.match(why, /HTTP Controller、BlackOps CLI、Deferred Workerなどの入口から分離/);
  assert.match(why, /Lifecycle Journalは受理された処理の実行事実/);
  assert.match(why, /汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します/);
  assert.match(why, /Retention／Replay／Rotationなどの個別運用Eventは、Lifecycle Journalとは別のFramework運用契約で扱います/);
  assert.doesNotMatch(why, /Business／Securityの監査証跡や任意のApplication LogはApplicationが所有します/);
  assert.match(why, /Operationとして受理する前のProtocol Error/);
  assert.match(why, /一対一のAPI移植表ではありません/);
  for (const mapping of [
    'Controller / Action',
    'FormRequest / Request DTO',
    'API Resource / Response DTO',
    'Job / Messenger Message / Queue',
    'Request／Jobの実行履歴',
  ]) {
    assert.match(why, new RegExp(mapping.replaceAll('/', '\\/')));
  }
});

test('guide landing source keeps the exact product title and three claims', async () => {
  const landing = await guide('README.md');

  assert.match(landing, /^# BlackOps - The PHP Framework$/m);
  assertLandingSections(landing);
  for (const value of ['Operation', 'Journal', 'BlackOpsはフロントエンドを持ちません', 'JavaScript向けに接続クライアントのコードを自動生成します']) {
    assert.match(landing, new RegExp(value));
  }
  assert.match(landing, /\]\(frontend\.md\)/);
});

test('landing product contract keeps the start links, current install, and exact Deferred sample', async () => {
  const landing = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');

  assertLandingRoot(landing);
  assert.match(landing, /href="\/concepts\/why-blackops"/);
  assert.match(landing, /href="\/getting-started\/installation"/);
  assert.match(landing, /href="\/getting-started\/quickstart"/);
  assert.match(landing, /href="\/getting-started\/first-operation"/);
  assert.doesNotMatch(landing, /href="https:\/\/github\.com\/kubotak-is\/blackops"/);
  const rendered = renderedOperationSample(landing);
  assert.match(rendered, /#\[Route\(method: 'POST', path: '\/reports'\)\]/);
  assertOperationSample(landing);
  assert.match(rendered, /#\[Deferred\]/);
  assert.match(rendered, /\n    public function handle\(\n        GenerateReportValue \$value,\n        ExecutionContext \$context,\n    \): ReportGenerated\n    \{\n        return new ReportGenerated\(\n            \$value->reportName,\n            '\/reports\/generated\/' \. \$value->reportName \. '\.json',\n        \);\n    \}/);
});

test('landing H1 keeps one literal word boundary and fails closed on drift', async () => {
  const landing = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');
  const exact = '<h1 id="landing-title"><span class="landing-brand">BlackOps</span> <span class="landing-tagline">The PHP Framework</span></h1>';
  const assertH1 = (source) => {
    assert.ok(source.includes(exact), 'Landing H1 must keep the two existing words with one literal boundary.');
    assert.doesNotMatch(source, /BlackOpsThe PHP Framework/);
    assert.doesNotMatch(source, /BlackOps\s{2,}The PHP Framework/);
    assert.doesNotMatch(source, /BlackOps\s+PHP Framework/);
  };
  assertH1(landing);
  assert.throws(() => assertH1(landing.replace('BlackOps</span> <span', 'BlackOps</span><span')), /literal boundary/);
  assert.throws(() => assertH1(landing.replace('The PHP Framework</span>', 'The Framework</span>')), /literal boundary/);
  assert.throws(() => assertH1(landing.replace('>BlackOps</span>', '>Black Ops</span>')), /literal boundary/);
});

test('landing guards the canonical IA, operation metadata, and focus contrast with fail-closed fixtures', async () => {
  const [landingSource, landingPage, theme] = await Promise.all([
    guide('README.md'),
    readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/theme.css'), 'utf8'),
  ]);

  assertLandingSections(landingSource);
  assert.throws(
    () => assertLandingSections(landingSource.replace('## Releases', '## Reference and Releases')),
    /canonical seven level-two sections|Reference and Releases/,
  );

  assertOperationSample(landingPage);
  assert.throws(
    () => assertOperationSample(landingPage.replace("#[OperationType('report.generate')]", '')),
    /exactly one report.generate OperationType/,
  );

  assertLandingRoot(landingPage);
  assert.throws(
    () => assertLandingRoot(landingPage.replace('<div class="landing-shell">', '<main class="landing-shell">')),
    /second main landmark/,
  );

  const lightAccent = cssVariable(theme, ':root', '--bo-accent');
  const lightPaper = cssVariable(theme, ':root', '--bo-paper');
  const lightSurface = cssVariable(theme, ':root', '--bo-surface');
  const darkAccent = cssVariable(theme, "[data-theme='dark']", '--bo-accent');
  const darkPaper = cssVariable(theme, "[data-theme='dark']", '--bo-paper');
  const darkSurface = cssVariable(theme, "[data-theme='dark']", '--bo-surface');
  const lightAction = cssVariable(theme, ':root', '--bo-action');
  assert.deepEqual({ lightAccent, lightPaper, darkAccent, darkPaper, lightAction }, {
    lightAccent: '#0f766e', lightPaper: '#f3f6f3', darkAccent: '#5eead4', darkPaper: '#0b1514', lightAction: '#f97316',
  });
  const { assertAccessibilityStylesheetContract } = await import('../scripts/artifact-stylesheet-contract.mjs');
  assert.doesNotThrow(() => assertAccessibilityStylesheetContract(theme, 'source theme', { requireLandingSurfaces: true }));
  assertContrastAtLeast(lightAccent, lightPaper, 3);
  assertContrastAtLeast(lightAccent, lightSurface, 3);
  assertContrastAtLeast(darkAccent, darkPaper, 3);
  assertContrastAtLeast(darkAccent, darkSurface, 3);
  assert.throws(() => assertContrastAtLeast(lightAction, lightPaper, 3), /expected at least 3:1/);
  assert.match(theme, /--bo-focus:\s*var\(--bo-accent\)/);
  assert.match(theme, /\.landing-text-link:focus-visible[\s\S]*outline:\s*3px solid var\(--bo-focus\)/);
  assert.match(theme, /\[data-blume-nav-tree\] a\[aria-current='page'\]:focus-visible[\s\S]*outline:\s*3px solid var\(--bo-focus\)/);
});

test('native code copy keeps focus and exposes Japanese success and failure status', async () => {
  const layout = await readFile(path.join(repositoryRoot, 'docs/website/components/NoEditLayout.astro'), 'utf8');

  assert.match(layout, /data-blume-copy/);
  assert.match(layout, /コピーしました/);
  assert.match(layout, /コピーできませんでした/);
  assert.match(layout, /aria-live/, 'status must be announced without moving focus');
  assert.match(layout, /MutationObserver/, 'native button mutation must be observed without replacing Clipboard');
  assert.match(layout, /M20 6 9 17l-5-5/, 'native check icon mutation must announce success');
  assert.match(layout, /dispatchResult\(button, false\)/, 'missing native check must announce Clipboard failures');
  assert.doesNotMatch(layout, /navigator\.clipboard\.writeText/, 'status adapter must not issue a second Clipboard write');
});

test('native copy status adapter observes one native write and preserves focus for success and failure', async () => {
  const layout = await readFile(path.join(repositoryRoot, 'docs/website/components/NoEditLayout.astro'), 'utf8');
  const script = layout.match(/<script is:inline>\s*([\s\S]*?)\s*<\/script>/)?.[1];
  assert.ok(script, 'NoEditLayout inline status adapter is present');

  for (const shouldResolve of [true, false]) {
    const dom = new JSDOM('<body><pre><code>const answer = 1;\n</code><button data-blume-copy aria-label="コードをコピー"><svg></svg></button></pre></body>', { runScripts: 'outside-only' });
    const writes = [];
    const timers = [];
    Object.defineProperty(dom.window.navigator, 'clipboard', {
      configurable: true,
      value: { writeText: async (value) => { writes.push(value); if (!shouldResolve) throw new Error('denied'); } },
    });
    dom.window.setTimeout = (callback, delay) => { timers.push({ callback, delay }); return timers.length; };
    dom.window.clearTimeout = () => {};
    dom.window.eval(script);
    const button = dom.window.document.querySelector('[data-blume-copy]');
    const code = dom.window.document.querySelector('code');
    button.addEventListener('click', async () => {
      try {
        await dom.window.navigator.clipboard.writeText(code.textContent);
        button.innerHTML = '<svg><path d="M20 6 9 17l-5-5"></path></svg>';
      } catch {}
    });
    button.focus();
    button.click();
    await new Promise((resolve) => globalThis.setTimeout(resolve, 0));
    if (!shouldResolve) timers.find(({ delay }) => delay === 2000)?.callback();
    assert.equal(writes.length, 1, shouldResolve ? 'success uses one native write' : 'failure uses one native write');
    assert.equal(writes[0], 'const answer = 1;\n', 'native handler receives exact code text');
    assert.equal(dom.window.document.activeElement, button, 'copy result keeps keyboard focus on the button');
    assert.equal(button.getAttribute('aria-label'), shouldResolve ? 'コピーしました' : 'コピーできませんでした');
    assert.equal(button.nextElementSibling.textContent, shouldResolve ? 'コピーしました' : 'コピーできませんでした');
  }
});

test('Blume visible chrome uses Japanese labels for copy, export, theme, navigation, and repository actions', async () => {
  const config = await readFile(path.join(repositoryRoot, 'docs/website/blume.config.ts'), 'utf8');
  for (const label of ['コピーしました', 'コードをコピー', 'エクスポート', '生成中', 'カラーテーマを切り替え', 'ナビゲーションを開閉', 'GitHub リポジトリ']) {
    assert.ok(config.includes(label), label);
  }
});

test('Journal guide is reachable from the landing model and explains its reader boundary', async () => {
  const [landing, journal] = await Promise.all([
    readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8'),
    guide('journal.md'),
  ]);

  const model = landing.match(/<section class="landing-model"[\s\S]*?<\/section>/)?.[0] ?? '';
  assert.match(model, /Lifecycle and Journal/);
  assert.match(model, /href="\/concepts\/journal"/);
  assert.equal((model.match(/<a class="landing-text-link"/g) ?? []).length, 2, 'model links have distinct purposes');
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
    '試験的なOpenTelemetry API-only Surface',
    'ApplicationBuilder::withTracerProvider()',
  ]) assert.ok(journal.includes(phrase), phrase);
});

test('Observability guide keeps the local Collector journey executable and isolated', async () => {
  const observability = await guide('observability.md');
  for (const phrase of [
    'Structured Record Version 1',
    'Application／Framework／Journal／Observed operational event',
    'Observed operational eventにはOperation、Attempt、Telemetryを出しません',
    'Monologの`datetime`、`level_name`',
    'Dual-write／Legacy Formatterはありません',
    '"kind":"application"',
    'open-telemetry/sdk:^1.15',
    'open-telemetry/exporter-otlp:^1.4',
    'php-http/guzzle7-adapter:^1.1',
    'ApplicationBuilder::withTracerProvider()',
    'ApplicationBuilder::withMeterProvider()',
    'MeterProvider::builder()',
    'OperationalHealthRequestHandler',
    'OperationalHealthCliAdapter',
    'Collectorを起動しただけではTrace／Metricは生成されません',
    'otel/opentelemetry-collector:0.158.0@sha256:',
    'Collector停止でReadinessがFailになる',
  ]) assert.ok(observability.includes(phrase), phrase);
  assert.doesNotMatch(observability, /otelcol:latest|api[_-]?key|password\s*:/i);
  assert.match(observability, /otel-collector-config\.yaml/);
  assert.doesNotMatch(observability, /-p 4318:4318/);
  assert.match(observability, /-p 127\.0\.0\.1:4318:4318/);
  assert.match(observability, /OTEL_EXPORTER_OTLP_ENDPOINT=http:\/\/127\.0\.0\.1:4318/);
  assert.match(observability, /OTEL_EXPORTER_OTLP_ENDPOINT=http:\/\/collector:4318/);
  assert.match(observability, /FINAL_STATUS=/);
  assert.match(observability, /LGTM final health passed: status=%s/);
  assert.match(observability, /Second Terminal Docker handoff: network=%s OTLP endpoint=http:\/\/collector:4318 OTLP port=4318/);
  assert.match(observability, /Copy-paste Docker emitter: docker run --rm --network %s --env OTEL_EXPORTER_OTLP_ENDPOINT=http:\/\/collector:4318/);
  assert.match(observability, /getenv\(\)/);
  assert.match(observability, /new Environment\(\$environmentSnapshot\)/);
  assert.match(observability, /withEnvironment\(\$environmentSnapshot\)/);
  assert.match(observability, /docker inspect --format '[^']*' \"\$COLLECTOR\"/);
  assert.match(observability, /grafana\/otel-lgtm:0\.29\.2@sha256:/);
  assert.doesNotMatch(observability, /docker compose up -d (?:collector|grafana|tempo|prometheus)/);
  assert.doesNotMatch(observability, /login as admin\/%s|printf[^\n]*GRAFANA_PASSWORD\"/);
  assert.match(observability, /trap cleanup EXIT INT TERM/);
});

test('observability specifications preserve the main API-only boundary and current wire split', async () => {
  const [spec10, spec94, observability] = await Promise.all([
    readFile(path.join(repositoryRoot, 'develop/spec/10-logging-and-traceability.md'), 'utf8'),
    readFile(path.join(repositoryRoot, 'develop/spec/94-journal-documentation.md'), 'utf8'),
    guide('observability.md'),
  ]);

  assert.doesNotMatch(spec94, /OpenTelemetry Adapter、Exporter、Configurationが未実装であると明記/);
  assert.match(spec94, /Stable `1\.1\.0`にはOpenTelemetry Adapter、Exporter、Operational Health Queryは含まれない/);
  assert.match(spec94, /ApplicationがSDK、Exporter、Resource、Endpoint、Credential、Provider Compositionを所有/);
  assert.match(spec10, /SDK／Exporter／Remote Delivery、Dashboardを所有しない/);
  assert.match(spec10, /`open-telemetry\/api`によるAPI-only Span／Metric境界/);
  assert.match(observability, /Application／Framework／Journal／Observed operational event/);
  assert.match(observability, /Observed operational eventにはOperation、Attempt、Telemetryを出しません/);
  assert.match(observability, /Application／Frameworkの`attempt`はnon-nullのAttempt Scope時だけ/);
  assert.match(observability, /Journalの`attempt`は常時存在して`null`/);
  const jsonl = observability.match(/```jsonl\n([\s\S]*?)\n```/)?.[1] ?? '';
  const records = jsonl.split('\n').map((line) => JSON.parse(line));
  assert.deepEqual(records.map(({ kind }) => kind), ['framework', 'journal', 'audit']);
  assert.equal('operation' in records[0], false);
  assert.equal(records[1].operation.schemaVersion, 1);
  assert.equal(records[1].attempt, null);
  assert.equal('telemetry' in records[2], false);
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
  assert.match(guideSource, /Community BoardはApplication-owned Reference Applicationとして、利用者がProject RootからApplicationのBrowser／API Testを実行し、公開Hostを前提にしない自己管理環境でJourneyを再現できることを確認します/);
  assert.doesNotMatch(guideSource, /Local／CIだけで検証|ローカル／CI検証|Repository CI/);
  assert.match(guideSource, /Outcome Store、Status API、Generated Artifact、Page Data、Browser Bundle、LogへCredentialを残しません/);
  for (const topic of ['Worker未起動', 'Seed Conflict', 'Port衝突', 'Generated Drift', 'Secure Cookie Local設定']) {
    assert.match(guideSource, new RegExp(`^### ${topic}$`, 'm'));
  }
  assert.match(testing, /BlackOps Board.*Application-owned Identity.*Framework Session Core.*SvelteKit .*BFF/s);
  assert.match(status, /BlackOps Boardは.*公開Hostも提供していません/);
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

test('Website fonts use local licensed variants and reject remote providers in artifacts', async () => {
  const [config, theme, packageJson, artifact] = await Promise.all([
    readFile(path.join(repositoryRoot, 'docs/website/blume.config.ts'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/theme.css'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/package.json'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/scripts/check-artifact.mjs'), 'utf8'),
  ]);

  assert.match(config, /localFont\('UbuntuSans\.ttf'\)/);
  assert.match(config, /localFont\('UbuntuMono\.ttf'\)/);
  assert.match(packageJson, /"blume": "1\.3\.0"/);
  assert.match(theme, /var\(--blume-font-body, ui-sans-serif\)/);
  assert.match(theme, /var\(--blume-font-mono, ui-monospace, monospace\)/);
  assert.match(artifact, /fontProviders\\\.google|fonts\\\.googleapis\\\.com\|fonts\\\.gstatic\\\.com/);
  assert.match(artifact, /generatedProviders\.length !== 2/);
  assert.match(artifact, /fontProviders.*A-Za-z0-9_.*\\s\*.*\(/);
  assert.match(artifact, /@font-face/);
  assert.match(artifact, /expectedAssets/);
  assert.match(artifact, /28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73/);
  assert.match(artifact, /fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee/);
  assert.match(artifact, /bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf/);
  assert.match(artifact, /Ubuntu-Font-Licence-1\.0/);
  assert.match(artifact, /localFontReferences/);
  assert.match(artifact, /astro\.config\.mjs/);
  assert.match(artifact, /Ubuntu-Font-License-1\.0\.txt/);

  const generatedLocalConfig = [
    'body: { provider: fontProviders.local(), src: UbuntuSans.ttf }',
    'mono: { provider: fontProviders.local(), src: UbuntuMono.ttf }',
  ].join('\n');
  const assertLocalProviderOnly = (source) => {
    const providers = [...source.matchAll(/fontProviders\.([A-Za-z0-9_]+)\s*\(/g)].map(([, provider]) => provider);
    assert.deepEqual(providers, ['local', 'local']);
  };
  assertLocalProviderOnly(generatedLocalConfig);
  assert.throws(
    () => assertLocalProviderOnly(generatedLocalConfig.replace('fontProviders.local()', "fontProviders.fontsource({ family: 'Inter' })")),
    assert.AssertionError,
  );
  assert.match(artifact, /fontProviders\.local\(\)/);
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
  assert.match(tutorial, /OperationOutcomeQuery/);
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

test('retention guide preserves the idempotency default and successful plan contract', async () => {
  const retention = await guide('retention.md');
  assert.match(retention, /`config\/retention\.php`の`idempotency_record_days`で管理し、省略した場合は4つの基本期間の最長値/);
  assert.doesNotMatch(retention, /--idempotency-record-days/);
  assert.match(retention, /--transport-payload-days=7[\s\S]*--journal-days=30[\s\S]*--outcome-days=14[\s\S]*--dead-letter-days=90/);
  assert.match(retention, /Planは候補を読むだけで、DatabaseやJournalを変更しません/);
  assert.match(retention, /成功時の終了Codeは0です。次の形式を返します/);
  assert.match(retention, /Retention plan[\s\S]*idempotency_record: N/);
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
    '`StorageKeyProvider`が未登録',
    'Unknown Key／Tag Tamper',
    '非空の旧Protected SchemaでMigrationが停止',
    'Rotationの`remaining`が0にならない',
    'journal.jsonlへ出力されない',
    'Local OpenTelemetry Collector／Traceが届かない',
    'OutcomeがPending／Not Found／Expiredか判別できない',
    'Sensitive値がJournalで見えない',
  ]) {
    const start = troubleshooting.indexOf(`## ${symptom}`);
    assert.notEqual(start, -1, symptom);
    const next = troubleshooting.indexOf('\n## ', start + 4);
    const section = troubleshooting.slice(start, next === -1 ? undefined : next);
    for (const label of ['**症状:**', '**考えられる原因:**', '**確認方法:**', '**修正方法:**']) {
      assert.match(section, new RegExp(label.replaceAll('*', '\\*')));
    }
  }
  assert.match(troubleshooting, /`build:compile`は`StorageKeyProvider`の必須Runtime検査を行わない/);
  assert.match(troubleshooting, /Storage Protectionを解決するRuntime composition/);
  assert.match(troubleshooting, /Prefix countと`operation:inspect`はClear lifecycle列のDiagnostics/);
  assert.match(troubleshooting, /FingerprintはRotation Auditの失敗記録だけ/);
  assert.doesNotMatch(troubleshooting, /build:compile`がConfiguration Errorで停止し/);
});

test('scheduled operation accuracy keeps metadata, misfire, runtime error, and runnable first-run guidance aligned', async () => {
  const [scheduled, troubleshooting, coreApi] = await Promise.all([
    guide('scheduled-operation.md'),
    guide('troubleshooting.md'),
    guide('core-api.md'),
  ]);

  assert.match(coreApi, /OperationMetadata::\$schedule/);
  assert.doesNotMatch(coreApi, /OperationMetadata::schedule\(\)/);
  assert.match(troubleshooting, /Cursorより後から現在のUTC Calendar Minuteまでに一致するSlotが複数/);
  assert.doesNotMatch(troubleshooting, /Misfire許容Windowを超えた/);
  assert.match(troubleshooting, /docker compose exec -T postgres psql -U blackops -d blackops/);
  assert.match(troubleshooting, /blackops\.schedule_occurrences/);
  assert.match(troubleshooting, /OccurrenceとJournalを安全に確認する/);
  assert.match(scheduled, /任意時刻に初回実行すると`accepted: 0`/);
  assert.match(scheduled, /`accepted: 2`はCountのShapeを示すサンプル/);
  assert.match(scheduled, /検証用OperationだけCronを`\* \* \* \* \*`/);
  assert.match(scheduled, /Top-level Runtime Error[\s\S]*runtime_error/);
  assert.match(scheduled, /Human CountまたはJSON `status: "failed"`/);
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
  assert.match(security, /Observability Signal Safety/);
  assert.match(security, /Operation／Attempt／Correlation／Causation/);
  assert.match(security, /高Cardinality自由文/);
  assert.match(security, /Primary Operation、Journal、Outcome、HTTP Response、Readinessを変えない/);
  assert.ok(security.indexOf('## Observability Signal Safety') < security.indexOf('## Production Check'));
  assert.match(security, /Global Mutable Clientへ保存しないでください/);
  assert.match(security, /OperationStatusAuthorizer/);
  assert.match(security, /Unknown／Deny.*404/s);
  assert.match(security, /Retention.*410/s);
});

test('tenant protection guide completes the protected-storage reader journey', async () => {
  const source = await guide('tenant-protection.md');
  const configuration = await guide('configuration.md');
  for (const phrase of [
    'Experimental Stable `1.2.0`', 'TenantRef', 'AuthenticationResult::authenticated',
    'ConsoleTenantProvider', 'ScheduledTenantProvider', 'StorageKeyProvider',
    'OperationDataReadAuthorizer', 'OperationOutcomeQuery', 'BOPD v1',
    'XChaCha20-Poly1305', 'OperationOutcomeUnavailable', 'database:migrate',
    'storage:protection:plan', 'storage:protection:rotate', '--confirm',
    '--checkpoint', '--actor', '--reason', 'remaining', 'Replica', 'Backup',
    'dead_letter_reason', 'idempotency_response', 'idempotency_result',
    'Exit Codeは成功（`0`）', '旧Keyを削除しない', 'Experimental Stable 1.2.0',
    '公開済みExperimental Stable `1.2.0`のFramework／Skeleton Surface', 'count(encoded_record)',
    'count(encoded_payload)', 'count(encoded_context)', 'count(encoded_reason)',
    'count(encoded_response)', 'count(encoded_result)',
    'all_non_null_rows_are_bopd', 'BOPD Envelope Header',
    '6. HTTPで受け付ける', '3. Human／JSONで実行する',
    'SampleStorageKeyProvider', 'BLACKOPS_STORAGE_KEY', 'base64_decode',
    'OperationDataReadAuthorizationDecision::allow',
    'SampleTokenAuthenticator.php', 'local-example', 'actor: new ActorRef',
  ]) assert.ok(source.includes(phrase), phrase);
  assert.match(configuration, /SampleTokenAuthenticator\.php/);
  assert.match(configuration, /AuthenticationResult::authenticated[\s\S]*TenantRef/);
  assert.doesNotMatch(source, /(?<![A-Za-z0-9_\/.-])[A-Za-z0-9+/]{40,}={0,2}(?![A-Za-z0-9_\/.-])/, 'guide must not contain key material');
  assert.doesNotMatch(source, /\/home\/kubotak|C:\\\\/, 'guide must not contain absolute repository paths');
});

test('core API reference covers every source type marked PublicApi without exposing Internal types', async () => {
  const reference = await guide('core-api.md');
  const sourceTypes = await publicApiTypes();

  assert.equal(sourceTypes.length, 216);
  assert.ok(sourceTypes.includes('BlackOps\\Core\\EphemeralOutcome'));
  assert.ok(sourceTypes.includes('BlackOps\\Http\\SapiRuntime'));
  assert.ok(sourceTypes.includes('BlackOps\\Identifier\\Uuidv7Generator'));
  assert.ok(sourceTypes.includes('BlackOps\\Idempotency\\IdempotencyKey'));
  assert.ok(sourceTypes.includes('BlackOps\\Idempotency\\IdempotencyKeyHash'));
  assert.ok(sourceTypes.includes('BlackOps\\Outbox\\TransactionalOutbox'));
  for (const type of sourceTypes) assert.match(reference, new RegExp(type.replaceAll('\\', '\\\\')));
  assert.doesNotMatch(reference, /`BlackOps\\Core\\Attribute\\PublicApi` \|/);
  assert.doesNotMatch(reference, /BlackOps\\Internal\\[A-Za-z]/);
  assert.doesNotMatch(reference, /\| `BlackOps\\Journal\\CanonicalJournalReader` \|/);
  assert.match(reference, /CanonicalJournalReader.*PublicApi markerを持たないInfrastructure SPI/);
  assert.match(reference, /`BlackOps\\Journal\\CanonicalJournalStore`/);
  assert.match(reference, /`BlackOps\\Outcome\\OutcomeStore`/);
});

test('attributes reference covers the twenty-five public authoring attributes and excludes the marker', async () => {
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
    'BlackOps\\Core\\Attribute\\ScheduledBy',
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
  assert.match(attributes, /Public Attribute 25件/);
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

  assert.match(runtime, /既定のWorker Mode/);
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

test('editorial guard rejects visible mixed-language labels while protecting contracts', () => {
  assert.throws(
    () => validateEditorial('## このPage\nJavascriptの説明です。\n**Symptom:** 失敗します。\nTask Report、Consumer Evidence、Phase 14を本文へ表示しません。', { file: 'fixture.md' }),
    /Editorial guard rejected fixture\.md/,
  );
  assert.throws(
    () => validateEditorial('```php\n// Latest Stable is visible in this comment\n```\n```mermaid\naccTitle: "Document Channel"\n```', { file: 'comment-fixture.md' }),
    /Editorial guard rejected comment-fixture\.md/,
  );

  assert.doesNotThrow(() => validateEditorial('Before <!-- Task Report\nConsumer Evidence --> after.'));
  assert.throws(() => validateEditorial('Before <!-- hidden --> Task Report after.', { file: 'comment-end.md' }), /Editorial guard rejected comment-end\.md/);
  assert.doesNotThrow(() => validateEditorial('<!--\n```php\n// NuxtJS historical example\n```\n-->\n本文です。'));
  assert.doesNotThrow(() => validateEditorial('<!--\n```bash\necho "NuxtJS"\n```\n```mermaid\naccTitle: "NuxtJS"\n```\n-->\n本文です。'));
  assert.throws(() => validateEditorial('<!--\n```php\n// NuxtJS historical example\n```\n-->\nNuxtJS本文です。', { file: 'comment-fence-end.md' }), /Editorial guard rejected comment-fence-end\.md/);

  const protectedFixture = [
    'Inline token: `NuxtJS` and `Project CLI` and `Phase 14`.',
    '[確認](Task%20Report.md)',
    '~~~bash',
    'echo "Latest Stable"',
    '~~~',
    '````json',
    '{"label":"Javascript","value":"NuxtJS"}',
    '````',
    '~~~~~~mermaid',
    'flowchart LR',
    '  Latest_Stable --> B["表示ラベル"]',
    '~~~~~~',
  ].join('\n');

  assert.deepEqual(findEditorialViolations(protectedFixture), []);
  assert.doesNotMatch(displayedMarkdown(protectedFixture), /NuxtJS|Project CLI|Latest Stable|Javascript/);
});

test('content-map descriptions use the same editorial guard as guide prose', async () => {
  const { validateEditorialDescriptions } = await import('../scripts/check-content.mjs');
  assert.doesNotThrow(() => validateEditorialDescriptions(contentMap));
  assert.throws(
    () => validateEditorialDescriptions({ 'fixture.md': { description: 'Task Reportを表示する' } }),
    /content-map description/,
  );
});

test('all guide sources are mapped once and Content Map owns reader classification', async () => {
  const files = (await readdir(path.join(repositoryRoot, 'docs/guide')))
    .filter((file) => file.endsWith('.md'))
    .sort();
  assert.equal(files.length, 41);
  assert.deepEqual(Object.keys(contentMap).sort(), files);

  const assigned = Object.entries(contentMap).filter(([source]) => source !== 'README.md').map(([source, metadata]) => [source, metadata.reader.type]);
  assert.deepEqual(assigned.map(([source]) => source).sort(), files.filter((file) => file !== 'README.md'));
  assert.deepEqual(Object.fromEntries(assigned), Object.fromEntries(Object.entries(contentMap).filter(([source]) => source !== 'README.md').map(([source, metadata]) => [source, metadata.reader.type])));
});

test('Blume runtime keeps diagrams local and the landing responsive', async () => {
  const packageJson = JSON.parse(await readFile(path.join(repositoryRoot, 'docs/website/package.json'), 'utf8'));
  const config = await readFile(path.join(repositoryRoot, 'docs/website/blume.config.ts'), 'utf8');
  const theme = await readFile(path.join(repositoryRoot, 'docs/website/theme.css'), 'utf8');
  const landing = await readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8');
  const layout = await readFile(path.join(repositoryRoot, 'docs/website/components/NoEditLayout.astro'), 'utf8');

  assert.equal(packageJson.dependencies.blume, '1.3.0');
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
  assert.match(theme, /contain: inline-size/);
  assert.match(theme, /prefers-reduced-motion/);
  assert.match(theme, /blume-mermaid \{[\s\S]*display: block !important/);
  assert.match(theme, /blume-mermaid > div \{[\s\S]*min-width: 42rem/);
  assert.match(theme, /blume-mermaid svg \{[\s\S]*height: auto[\s\S]*width: 100%/);
  for (const marker of ['#6d6d6d', '#707b87', '#d0d0d0', '#12201f', 'blackops-overflow-focus:focus-visible']) {
    assert.ok(theme.includes(marker), marker);
  }
  assert.match(landing, /class="landing-command blackops-overflow-focus" tabindex="0"/);
  assert.match(landing, /class="blackops-overflow-focus" tabindex="0"><code>/);
  assert.match(layout, /blume-mermaid[\s\S]*blackops-overflow-focus[\s\S]*tabIndex = 0/);
  assert.match(theme, /\.landing-journey a:focus-visible/);
  assert.match(theme, /\.landing-purpose-nav a:focus-visible/);
  assert.match(theme, /\.landing-brand[\s\S]*clamp\(4rem, 9vw, 8rem\)/);
  assert.match(theme, /@media \(max-width: 959px\)[\s\S]*\.landing-hero/);
  assert.match(theme, /@media \(max-width: 767px\)[\s\S]*\.landing-model-list[\s\S]*grid-template-columns: 1fr/);
  assert.match(theme, /overflow-x: auto/);
  assert.doesNotMatch(theme, /linear-gradient|radial-gradient/);
  assert.doesNotMatch(theme, /overflow-x: hidden/);
  assert.equal((landing.match(/class="landing-eyebrow"/g) ?? []).length, 1);
  for (const label of ['aria-label="ドキュメントの操作"', 'aria-label="Operationのソース"', '>同じID<', 'aria-label="ドキュメントのセクション"']) {
    assert.ok(landing.includes(label), label);
  }
  assert.doesNotMatch(landing, /aria-label="Documentation actions"|aria-label="Operation source context"|>same ID<|aria-label="Documentation sections"/);
  assert.match(landing, /<strong>Finalizing<\/strong><small>attempt\.succeeded — Handlerが成功した<\/small>/);
  assert.doesNotMatch(landing, /<strong>Succeeded<\/strong>/);
  assert.doesNotMatch(landing, /landing-journey-number|>01<|>02<|>03</);
  for (const copy of [
    'BlackOps</span> <span class="landing-tagline">The PHP Framework',
    'HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。',
    'landing-editor-chrome',
    'landing-lifecycle-panel',
    'Operation Lifecycle',
    '同じOperation IDで、受付、Attempt、完了までの実行事実を追跡します。',
    'Retryは同じIDの次のAttemptとして続きます。',
    'composer create-project blackops/skeleton my-app 1.2.0',
    'Stable 1.2.0 install',
    'Install',
    'Quickstart and Skeleton',
    'First Operation',
    'Inline and Deferred',
    'Lifecycle and Journal',
    'Async and Lifecycle',
    'Data and Security',
    'Operate',
    'Reference',
    'Releases',
  ]) assert.ok(landing.includes(copy), copy);
  for (const forbidden of ['BlackOpsの3つの特徴', 'BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。', 'ONE MODEL / TWO PATHS', 'Operation ↔ Execution', 'Inline HTTP or durable Deferred', 'THE BLACKOPS SHAPE', 'Make the work explicit.', 'Nothing stays in the dark.', 'Bring your frontend.', 'landing-feature', 'landing-hero-glow', 'landing-panel-dot']) {
    assert.ok(!landing.includes(forbidden), forbidden);
  }
});

test('the detail layout owns the existing theme and linked CSS guards fail closed', async () => {
  const [layout, landing, artifactGuard, stylesheetContract, searchFocusBoundary] = await Promise.all([
    readFile(path.join(repositoryRoot, 'docs/website/components/NoEditLayout.astro'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/pages/index.astro'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/scripts/check-artifact.mjs'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/scripts/artifact-stylesheet-contract.mjs'), 'utf8'),
    readFile(path.join(repositoryRoot, 'docs/website/components/SearchFocusBoundary.astro'), 'utf8'),
  ]);
  assert.match(layout, /import ['"]\.\.\/theme\.css['"];?/);
  assert.match(landing, /import ['"]\.\.\/theme\.css['"];?/);
  for (const marker of ['extractStylesheetHrefs', 'assertLinkedStylesheetContract']) {
    assert.ok(artifactGuard.includes(marker), marker);
  }
  for (const marker of ['overflow-wrap:anywhere', 'min-width:42rem', 'box-shadow:inset3px00']) {
    assert.ok(stylesheetContract.includes(marker), marker);
  }

  const {
    assertAccessibilityStylesheetContract,
    assertLinkedStylesheetContract,
    assertOverflowFocusContract,
    assertSearchFocusBoundaryArtifact,
    assertSearchFocusBoundarySourceContract,
    extractStylesheetHrefs,
  } = await import('../scripts/artifact-stylesheet-contract.mjs');
  const activeCss = '[data-blume-nav-tree] a[aria-current=page]{box-shadow:inset 3px 0 0 var(--blume-accent)}';
  const inlineCss = '.prose :not(pre)>code{overflow-wrap:anywhere;word-break:break-word}';
  const mermaidCss = 'blume-mermaid{width:100%} blume-mermaid>div{min-width:42rem;width:100%} blume-mermaid svg{height:auto}';
  const accessibilityCss = ":root{--bo-surface-deep:#e7eeea} [data-theme='light'] .not-prose[class~='bg-blue-500/10']>div>p:not(.text-foreground){color:#6d6d6d} [data-theme='light'] .landing-editor-language,[data-theme='light'] .landing-lifecycle-heading>span,[data-theme='light'] .landing-lifecycle-caption,[data-theme='light'] .landing-lifecycle-note,[data-theme='light'] .landing-lifecycle-rail small{color:#526966} [data-theme='dark'] .astro-code span[style*='--shiki-dark:#6A737D']{color:#707b87!important} [data-theme='dark'] blume-mermaid .edgeLabel p{color:#d0d0d0!important} [data-theme='dark'] body>a[href='#blume-content']{color:#12201f} .blackops-overflow-focus:focus-visible{outline:3px solid var(--bo-focus);outline-offset:3px}";
  const linkedCss = {
    'active.css': activeCss,
    'inline.css': inlineCss,
    'mermaid.css': mermaidCss,
    'accessibility.css': accessibilityCss,
    'other.css': 'body{color:black}',
  };
  const runFixture = async (html, label, styles = linkedCss) => assertLinkedStylesheetContract(
    html,
    extractStylesheetHrefs(html),
    label,
    {
      readStylesheet: async (href) => styles[href] ?? Promise.reject(new Error('missing fixture asset')),
      requireLandingSurfaces: true,
    },
  );
  await assert.doesNotReject(() => runFixture('<LINK href="/active.css" REL="stylesheet"><link rel="STYLESHEET" href="/inline.css"><link href="/mermaid.css" rel="stylesheet"><link href="/accessibility.css" rel="stylesheet">', 'linked fixture'));
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/other.css">', 'unlinked CSS fixture'), /active navigation/);
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/missing.css">', 'missing linked CSS fixture'), /missing linked stylesheet/);
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="https://example.test/theme.css">', 'non-local linked CSS fixture'), /non-local stylesheet/);
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/../active.css">', 'traversal linked CSS fixture'), /unsafe stylesheet/);
  const wrongRuleCss = {
    'bad-active.css': `[data-blume-nav-tree]a[aria-current=page]{color:red}body{box-shadow:inset 3px 0 0 black}${inlineCss}${mermaidCss}${accessibilityCss}`,
    'bad-inline.css': `${activeCss}.prose:not(pre)>code{overflow-wrap:anywhere;word-break:break-word}${mermaidCss}${accessibilityCss}`,
    'bad-mermaid.css': `${activeCss}${inlineCss}blume-mermaidsvg{height:auto}body{min-width:42rem;height:auto;width:100%}${accessibilityCss}`,
  };
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/bad-active.css">', 'wrong active declaration fixture', wrongRuleCss), /active navigation/);
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/bad-inline.css">', 'wrong inline declaration fixture', wrongRuleCss), /inline code/);
  await assert.rejects(() => runFixture('<link rel="stylesheet" href="/bad-mermaid.css">', 'wrong Mermaid declaration fixture', wrongRuleCss), /Mermaid/);

  for (const [oldColor, label] of [
    ['#6f6f6f', 'old Light callout color'],
    ['#6a737d', 'old Dark Shiki comment color'],
    ['#cccccc', 'old Dark Mermaid edge-label color'],
    ['#fff', 'old Dark skip-link color'],
  ]) {
    const oldCss = accessibilityCss.replace(
      oldColor === '#6f6f6f' ? '#6d6d6d' : oldColor === '#6a737d' ? '#707b87' : oldColor === '#cccccc' ? '#d0d0d0' : '#12201f',
      oldColor,
    );
    assert.throws(
      () => assertAccessibilityStylesheetContract(`${activeCss}${inlineCss}${mermaidCss}${oldCss}`, label, { requireLandingSurfaces: true }),
      /linked stylesheets must (own|not contain)/,
    );
  }
  const unscopedCalloutCss = `${accessibilityCss} .not-prose[class~='bg-blue-500/10']>div>p:not(.text-foreground){color:#6d6d6d}`;
  assert.throws(
    () => assertAccessibilityStylesheetContract(`${activeCss}${inlineCss}${mermaidCss}${unscopedCalloutCss}`, 'unscoped Light callout', { requireLandingSurfaces: true }),
    /linked stylesheets must (own|not contain)/,
  );
  const lowContrastLandingCss = accessibilityCss.replaceAll('#526966', '#5d7471');
  assert.throws(
    () => assertAccessibilityStylesheetContract(`${activeCss}${inlineCss}${mermaidCss}${lowContrastLandingCss}`, 'low-contrast Light Landing surface', { requireLandingSurfaces: true }),
    /Light Landing deep-surface muted/,
  );
  const unscopedLandingCss = `${accessibilityCss} .landing-editor-language{color:#526966}`;
  assert.throws(
    () => assertAccessibilityStylesheetContract(`${activeCss}${inlineCss}${mermaidCss}${unscopedLandingCss}`, 'unscoped Light Landing surface', { requireLandingSurfaces: true }),
    /unscoped Light Landing deep-surface muted/,
  );

  const validRuntime = "for (const element of document.querySelectorAll('.landing-command, .landing-code-panel > pre, blume-mermaid')) { element.classList.add('blackops-overflow-focus'); element.tabIndex = 0; }";
  assert.doesNotThrow(() => assertOverflowFocusContract(
    '<pre class="landing-command blackops-overflow-focus" tabindex="0"></pre><div class="landing-code-panel"><pre class="blackops-overflow-focus" tabindex="0"></pre></div><blume-mermaid></blume-mermaid>',
    'focusable overflow fixture',
    { runtimeSource: validRuntime, requireLandingSurfaces: true },
  ));
  assert.throws(
    () => assertOverflowFocusContract('<pre class="landing-command"></pre><div class="landing-code-panel"><pre></pre></div><blume-mermaid></blume-mermaid>', 'non-focusable overflow fixture', { requireLandingSurfaces: true }),
    /must be keyboard focusable/,
  );
  assert.throws(
    () => assertOverflowFocusContract('<div class="landing-shell"></div>', 'missing Landing surfaces', { requireLandingSurfaces: true }),
    /Landing must contain a keyboard-focusable command overflow surface/,
  );
  assert.throws(
    () => assertOverflowFocusContract('<blume-mermaid></blume-mermaid>', 'unrelated runtime fixture', { runtimeSource: 'otherElement.tabIndex = 0;' }),
    /exact shared overflow selector/,
  );
  assert.throws(
    () => assertOverflowFocusContract('<blume-mermaid></blume-mermaid>', 'separated runtime fixture', {
      runtimeSource: "document.querySelectorAll('.landing-command, .landing-code-panel > pre, blume-mermaid'); element.classList.add('blackops-overflow-focus'); element.tabIndex = 0;",
    }),
    /exact shared overflow selector/,
  );
  assert.throws(
    () => assertOverflowFocusContract('<div class="landing-code-panel"><div><pre tabindex="0"></pre></div></div>', 'nested code overflow fixture'),
    /landing code overflow must be keyboard focusable/,
  );

  assert.match(artifactGuard, /assertSearchFocusBoundaryArtifact/);
  assert.match(artifactGuard, /assertSearchFocusBoundarySourceContract/);
  assert.doesNotThrow(() => assertSearchFocusBoundarySourceContract({
    component: searchFocusBoundary,
    landing,
    detail: layout,
  }));
  for (const [missing, label] of [
    ['event.preventDefault()', 'preventDefault'],
    ['dialog.close()', 'dialog close'],
    ['trigger.focus()', 'trigger focus'],
  ]) {
    assert.throws(
      () => assertSearchFocusBoundarySourceContract({
        component: searchFocusBoundary.replace(missing, ''),
        landing,
        detail: layout,
      }, `missing ${label}`),
      /missing its bounded/,
    );
  }
  assert.throws(
    () => assertSearchFocusBoundarySourceContract({
      component: searchFocusBoundary,
      landing: landing.replace("import SearchFocusBoundary from '../components/SearchFocusBoundary.astro';\n", '').replace('  <SearchFocusBoundary />\n', ''),
      detail: layout,
    }, 'Landing-only fixture'),
    /Landing.*exactly once/,
  );
  assert.throws(
    () => assertSearchFocusBoundarySourceContract({
      component: `${searchFocusBoundary}\n${searchFocusBoundary}`,
      landing,
      detail: layout,
    }, 'duplicate component fixture'),
    /exactly one shared boundary marker/,
  );
  assert.throws(
    () => assertSearchFocusBoundarySourceContract({
      component: searchFocusBoundary.replace("dialog.closest('blume-search')?.querySelector", "document.querySelector"),
      landing,
      detail: layout,
    }, 'global trigger fixture'),
    /same-route search trigger lookup/,
  );
  assert.throws(
    () => assertSearchFocusBoundarySourceContract({
      component: searchFocusBoundary.replace('event.preventDefault();\n      event.stopPropagation();\n      dialog.close();', 'dialog.close();\n      event.preventDefault();\n      event.stopPropagation();'),
      landing,
      detail: layout,
    }, 'reordered handler fixture'),
    /required operation order/,
  );
  const boundaryScript = searchFocusBoundary.match(/<script\b[^>]*data-blackops-search-focus-boundary[^>]*>([\s\S]*?)<\/script>/i)?.[1] ?? '';
  const validArtifact = `<script data-blackops-search-focus-boundary>${boundaryScript}</script>`;
  assert.doesNotThrow(() => assertSearchFocusBoundaryArtifact(validArtifact, 'generated boundary fixture'));
  assert.throws(() => assertSearchFocusBoundaryArtifact(validArtifact.replace('data-blackops-search-focus-boundary', ''), 'missing marker fixture'), /exactly one generated/);
  assert.throws(() => assertSearchFocusBoundaryArtifact(`${validArtifact}${validArtifact}`, 'duplicate marker fixture'), /exactly one generated/);
  assert.throws(
    () => assertSearchFocusBoundaryArtifact(validArtifact.replace("dialog.closest('blume-search')?.querySelector", 'document.querySelector'), 'global artifact trigger fixture'),
    /same-route search trigger lookup/,
  );
  assert.throws(
    () => assertSearchFocusBoundaryArtifact(validArtifact.replace('event.preventDefault();\n      event.stopPropagation();\n      dialog.close();', 'dialog.close();\n      event.preventDefault();\n      event.stopPropagation();'), 'reordered artifact fixture'),
    /required operation order/,
  );
  assert.throws(
    () => assertSearchFocusBoundaryArtifact(validArtifact.replace("if (event.key !== 'Escape' || event.isComposing) return;", ''), 'missing artifact keydown filter fixture'),
    /generated boundary is missing.*Escape filtering/,
  );
  for (const missing of ['event.preventDefault()', 'dialog.close()', 'trigger.focus()']) {
    assert.throws(() => assertSearchFocusBoundaryArtifact(validArtifact.replace(missing, ''), `missing artifact ${missing}`), /generated boundary is missing/);
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
  assert.match(checkSite, new RegExp('database/transactions'));
  assert.match(checkSite, new RegExp('reference/project-cli'));
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

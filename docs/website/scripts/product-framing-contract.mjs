import { JSDOM } from 'jsdom';

export const LANDING_VALUE_SENTENCE = 'HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。';
export const ACTIONABLE_WHY_OUTCOME = '同期／非同期のOperationを同じOperation IDで追跡し、受付・再試行・完了を確認する。';
export const LIFECYCLE_CAPTION = '同じOperation IDで、受付、Attempt、完了までの実行事実を追跡します。';
export const LIFECYCLE_RETRY_NOTE = 'Retryは同じIDの次のAttemptとして続きます。';
export const LANDING_CLI_HREF = '/reference/project-cli';
export const SPEC_83_PATH = 'develop/spec/83-blume-documentation-experience.md';
export const P22_005E_TASK_PATH = 'develop/orchestration/tasks/P22-005E-documentation-product-framing-cli-and-audit-boundary.md';
export const P22_005E_REQUIRED_AUTHORITY_PATHS = new Set([
  'develop/decisions/143-documentation-release-truth-and-information-architecture.md',
  'develop/spec/57-documentation-website-delivery-contract.md',
  'develop/spec/59-documentation-reader-experience.md',
  'develop/spec/83-blume-documentation-experience.md',
  'develop/spec/92-documentation-review-agent.md',
  'develop/spec/100-structured-logging-and-opentelemetry.md',
  'develop/spec/104-documentation-release-lifecycle-and-information-architecture.md',
]);

const OLD_LANDING_VALUE = 'HTTP、Deferred Worker、Journalを一つのOperation Modelで組み立てるためのFrameworkです。';
const OLD_WHY_OUTCOME = '一貫して追跡できるか判断する。';
const OLD_AUDIT_MAPPING = /Audit Log\s*\/\s*Process History\s*(?:\||->)\s*Journal/u;
const OLD_APPLICATION_AUDIT_BOUNDARY = 'Business／Securityの監査証跡や任意のApplication LogはApplicationが所有します。';
const WRONG_OPERATIONAL_AUDIT_OWNER = /Retention／Replay／Rotationなどの個別運用EventはApplicationが所有/u;
const OLD_README_DUPLICATE = 'HTTPやWorkerから受けた一つの処理を同じOperation IDで追跡し、受付・再試行・完了までの結果を確認できます。';
const UNPUBLISHED_RETENTION_OPTION = '--idempotency-record-days';
const PUBLIC_RETENTION_OPTIONS = ['transport-payload-days', 'journal-days', 'outcome-days', 'dead-letter-days'];
const RETENTION_CLI_BOUNDARY = 'Project Rootの公開Retention Commandは4つの期間Optionだけを受け付けます。';
const RETENTION_FALLBACK_CLI = '`config/retention.php`の`idempotency_record_days`で管理し、省略時は4つの基本期間の最長値を使います。';
const RETENTION_FALLBACK_GUIDE = '`config/retention.php`の`idempotency_record_days`で管理し、省略した場合は4つの基本期間の最長値を使います。';
const RETENTION_ROUTE = '/database/retention';
const RETENTION_SOURCE = 'https://blackops-php.pages.dev/database/retention';
const README_OPERATION_DEFINITION = 'この処理単位をOperation、その識別子をOperation IDと呼びます。';
const AUDIT_PORT = 'LoggingRetentionPurgeAuditPort';
const AUDIT_EVENT = 'retention.purge.completed';
const ROADMAP_COMMANDS = /(?:^|[^a-z])(?:ls|route:list|route:ls|schedule:list|schedule:ls|worker:list|worker:ls)(?:[^a-z]|$)/iu;
const CLI_INTERNAL_NAMES = /ApplicationConfigurationSnapshot|ApplicationOperationDiscovery/u;
const CLI_TABLE_HEADER = '| 目的 | Command | 実行条件 | 出力／終了Code |';
const CLI_HELP_BOUNDARY = 'Helpが本文表の全Optionを必ず列挙するとは限りません。';
export const CLI_STABLE_LIST_BOUNDARY_MARKERS = [
  'Global listは、現在のApplicationで利用できるCommandを目的別に確認する入口です。一覧で名前と概要を確認してから、必要なCommandの個別Helpへ進みます。',
  'Applicationが明示登録したCommandをクラス名（class-string）で登録すると、Console Kernelを構成するときにCommand実体が生成され、コンストラクタが実行されます。',
  '呼び出し側があらかじめ生成したCommand instanceを登録した場合は、その同じinstanceを再利用し、Kernel構成で追加の生成は行いません。',
  'どの一覧形式を選んでも、この生成・再利用の境界は変わりません。',
  'Command Manifestから発見したLazy Commandは、通常表示と--rawでは、Command実体を作る処理（factory）を呼び出さずに一覧します。',
  '--rawは装飾を省いたテキスト一覧です。',
  '--shortを付けない--format=json|xml|mdでは、引数とOptionの定義（Definition）やHelp取得でCommand実体を作る処理（factory）が呼び出されます。そのfactoryがBuild済みの依存関係を持つ仕組み（Container）からCommand実体を解決する場合があります。',
  '個別のhelpも同じようにLazy Commandを解決する場合があります。',
  'そのためGlobal listは、Applicationの初期化やContainerの解決から完全に切り離された診断Commandではありません。',
];
export const CLI_OLD_ABSOLUTE_SIDE_EFFECT_FREE_CLAIM = 'Manifest Metadataだけを使い、Handler、Database、Container、Actor Providerを解決しません。';
export const APPLICATION_BOOTSTRAP_LIST_BOUNDARY_MARKERS = [
  'Global listの実行境界は一覧形式で異なります。',
  'Applicationが明示登録したCommandをクラス名（class-string）で登録すると、Console Kernelを構成するときにCommand実体が生成され、コンストラクタが実行されます。',
  '呼び出し側があらかじめ生成したCommand instanceを登録した場合は、その同じinstanceを再利用し、Kernel構成で追加の生成は行いません。',
  'どの一覧形式を選んでも、この生成・再利用の境界は変わりません。',
  'Command Manifestから発見したLazy Commandは、通常表示と--rawでは、Command実体を作る処理（factory）を呼び出さずに一覧します。',
  '--rawは装飾を省いたテキスト一覧です。',
  '--shortを付けない--format=json|xml|mdでは、引数とOptionの定義（Definition）やHelp取得でCommand実体を作る処理（factory）が呼び出されます。そのfactoryがBuild済みの依存関係を持つ仕組み（Container）からCommand実体を解決する場合があります。',
  '個別のhelpも同じようにLazy Commandを解決する場合があります。',
  'そのためGlobal listは、Applicationの初期化やContainerの解決から完全に切り離された診断Commandではありません。',
];
export const APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM = '`list`はCommand Manifestの名前／説明／Alias／Hiddenだけを読み、Command Constructor、Compiled Container、Database、PCNTL、Retention Serviceを構成しません。';
const APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM_FLAT = APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM.replaceAll('`', '');
const JOURNAL_CONCEPT_MARKERS = [
  'HTTPの受付からWorkerの再試行まで',
  'この処理単位をOperation、記録をJournalと呼びます。',
  '同じOperation IDと',
];
const WRONG_SUCCEEDED_STATE = /<strong>\s*Succeeded\s*<\/strong>/u;
const OLD_LANDING_ACCESSIBLE_LABELS = [
  'aria-label="Documentation actions"',
  'aria-label="Operation source context"',
  '>same ID<',
  'aria-label="Documentation sections"',
];
const LANDING_VISUAL_MARKERS = [
  'landing-demo',
  'landing-code-header',
  'landing-resources',
  'landing-resource-layout',
  'landing-journey',
];
const LANDING_ACCESSIBLE_LABELS = [
  'aria-label="ドキュメントの操作"',
  'aria-label="Operationのソース"',
];
const LANDING_JOURNEY_STEPS = [
  {
    href: '/getting-started/installation',
    label: '環境を用意する',
    description: 'PHPとComposerでProjectを作り、Local環境を準備する',
  },
  {
    href: '/getting-started/quickstart',
    label: 'サンプルを動かす',
    description: '認証付きHTTPとDeferred Workerを順に確認する',
  },
  {
    href: '/getting-started/first-operation',
    label: 'Operationを作る',
    description: 'CLIで生成し、HTTPとWorkerで完走する',
  },
];
const OLD_LANDING_SECTION_MARKERS = [
  'landing-authoring',
  'landing-contract-list',
  'landing-purpose-nav',
  '実際のPHPコードから始める',
  '必要な場所から読む',
  'aria-label="ドキュメントのセクション"',
];

export function assertProductFramingSourceContract({
  documents,
  landingSource,
  themeSource = '',
  contentMap,
  retentionRuntimeSource = '',
  spec83Source = '',
  taskSource = '',
  repositoryPaths = [],
  label = 'Product framing source',
} = {}) {
  const sources = normalizeDocuments(documents);
  const landing = required(landingSource, label + ' Landing source');
  const guide = document(sources, 'README.md', label);
  const why = document(sources, 'why-blackops.md', label);
  const cli = document(sources, 'project-cli.md', label);
  const bootstrap = document(sources, 'application-bootstrap.md', label);
  const journal = document(sources, 'journal.md', label);
  const observability = document(sources, 'observability.md', label);
  const retention = document(sources, 'retention.md', label);
  const security = document(sources, 'security.md', label);
  const glossary = document(sources, 'glossary.md', label);
  const quickstart = document(sources, 'mvp-sample.md', label);
  const combined = [...sources.values(), landing, themeSource].join('\n');

  noLandingDrift(landing, label + ' Landing');
  readmeContract(guide, label + ' guide index');
  retentionContract({ cli, retention, runtimeSource: retentionRuntimeSource }, label);
  storageProtectionContract(cli, label + ' CLI');
  taskSpecificationContract({ taskSource, repositoryPaths }, label);
  specification83Contract(spec83Source, label);
  observedAuditContract(observability, label + ' Observability');
  requireText(landing, LANDING_VALUE_SENTENCE, label + ' Landing value');
  requireText(landing, 'locale="ja"', label + ' Landing Japanese locale');
  requireText(landing, 'href="' + LANDING_CLI_HREF + '"', label + ' Landing CLI link');
  requireText(landing, '>BlackOps CLI<', label + ' Landing CLI label');
  for (const marker of [...LANDING_VISUAL_MARKERS, ...LANDING_ACCESSIBLE_LABELS]) {
    requireText(landing, marker, label + ' Landing visual marker');
  }
  requireText(landing, 'BlackOpsを動かす', label + ' Landing journey heading');
  requireText(landing, '環境を用意し、サンプルで動きを確かめてから、自分のOperationを作ります。', label + ' Landing journey lead');
  assertLandingJourneyContract(landing, label + ' Landing');
  for (const marker of OLD_LANDING_SECTION_MARKERS) if (landing.includes(marker)) throw new Error(label + ' Landing retains a retired section marker: ' + marker);
  if (WRONG_SUCCEEDED_STATE.test(landing)) throw new Error(label + ' Landing uses Succeeded as a Lifecycle state.');
  for (const marker of OLD_LANDING_ACCESSIBLE_LABELS) if (landing.includes(marker)) throw new Error(label + ' Landing retains an English accessible label.');
  ordered(landing, [LANDING_VALUE_SENTENCE, 'Deferred', 'BlackOpsを動かす'], label + ' Landing newcomer value order');

  requireText(guide, LANDING_VALUE_SENTENCE, label + ' guide index value');
  requireText(guide, 'Operation ID', label + ' guide index Operation definition');
  ordered(guide, ['Operation ID', 'Inline', 'Deferred Worker', 'Journal'], label + ' guide index concept order');
  requireText(why, '同じIDで受付・再試行・完了を確認', label + ' Why BlackOps outcome');
  if (why.includes(OLD_WHY_OUTCOME)) throw new Error(label + ' Why BlackOps keeps the vague reader outcome.');
  ordered(why, ['HTTPやWorker', '処理単位', 'Operation', 'Inline', 'Deferred Worker', 'Lifecycle Journal'], label + ' Why BlackOps concept order');

  requireText(cli, '# BlackOps CLI', label + ' CLI H1');
  for (const command of ['php blackops list', 'php blackops help <command>']) requireText(cli, command, label + ' CLI first command');
  for (const heading of ['Projectを作る・Buildする', 'Operationを実行する', 'Dataを管理する', '診断・復旧する']) requireText(cli, heading, label + ' CLI purpose group');
  for (const term of ['mutation', 'Runtime', 'Output', 'Exit', 'Help']) requireText(cli, term, label + ' CLI contract');
  requireText(cli, CLI_TABLE_HEADER, label + ' CLI scan-first table');
  requireText(cli, 'Optionの全量と既定値は本文の表と各詳細Guideを参照します。', label + ' CLI option reference boundary');
  requireText(cli, CLI_HELP_BOUNDARY, label + ' CLI Help limitation');
  requireText(cli, 'project-generators.md#seederを生成する', label + ' CLI Seeder link');
  cliStableListContract(cli, label + ' CLI Stable list boundary');
  applicationBootstrapContract(bootstrap, label + ' Application Bootstrap list boundary');
  if (CLI_INTERNAL_NAMES.test(cli)) throw new Error(label + ' CLI exposes an internal configuration class name.');
  const cliTable = cli.slice(cli.indexOf(CLI_TABLE_HEADER), cli.indexOf('「変更なし」'));
  if ((cliTable.match(/\| `build:compile`/g) ?? []).length !== 1) throw new Error(label + ' CLI table duplicates build:compile rows.');
  if ((cliTable.match(/\| `make:seeder/g) ?? []).length !== 1) throw new Error(label + ' CLI table must have one make:seeder row.');
  if (ROADMAP_COMMANDS.test(cli)) throw new Error(label + ' CLI presents a roadmap command as current.');

  ordered(journal, JOURNAL_CONCEPT_MARKERS, label + ' Journal newcomer concept order');
  requireText(journal, '同じOperation IDと`sequence`', label + ' Journal sequence model');
  requireText(journal, 'Canonical JournalはOperation Lifecycleの正本', label + ' Journal canonical boundary');
  requireText(journal, '汎用Business／Security Audit Trailではありません', label + ' Journal non-audit boundary');
  for (const boundary of ['Operation受理前のAuthentication／Protocol Error', 'Policy Version', 'Business Action、Resource、Reason', 'tamper-evident history', '署名付きExport']) requireText(journal, boundary, label + ' Journal non-provided boundary');
  requireText(observability, 'Canonical Audit TrailのRecordではありません', label + ' Observed non-audit boundary');
  requireText(security, 'Operation Lifecycleの正本', label + ' Security Journal boundary');
  requireText(glossary, '汎用Business／Security Audit Trailではなく', label + ' Glossary Journal boundary');
  requireText(quickstart, 'Canonical JournalはOperation Lifecycleの正本', label + ' Quickstart Journal boundary');
  requireText(why, '汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します。', label + ' Why BlackOps application audit boundary');
  requireText(why, 'Retention／Replay／Rotationなどの個別運用Eventは、Lifecycle Journalとは別のFramework運用契約で扱います。', label + ' Why BlackOps operational audit boundary');

  if (contentMap?.['why-blackops.md']?.reader?.outcome !== ACTIONABLE_WHY_OUTCOME) throw new Error(label + ' Content Map must expose the actionable Why BlackOps outcome.');
  noForbiddenBoundary(combined, label);
  if (themeSource !== '') themeContract(themeSource, label);
  return true;
}

export function assertProductFramingArtifactContract({
  surfaces,
  css = '',
  retentionRuntimeSource = '',
  spec83Source = '',
  taskSource = '',
  repositoryPaths = [],
  label = 'Product framing artifact',
} = {}) {
  const artifacts = normalizeDocuments(surfaces);
  const landingNames = names(artifacts, /^(?:landing-|page:\/$|raw:index\.md$|search-all$|llm-full-all$)/u);
  const landingValueNames = landingNames.filter((name) => !/llm-short/u.test(name));
  const landingVisualNames = landingNames.filter((name) => /(?:html|page:\/$)/iu.test(name));
  const whyNames = names(artifacts, /why|concepts\/why/iu);
  // The generated MDX companion is a source artifact, not one of the six
  // public CLI surface keys validated by this contract.
  const cliNames = names(artifacts, /cli|project-cli/iu).filter((name) => name !== 'raw:reference/project-cli.mdx');
  // The generated MDX companion is a source artifact, not the public raw page
  // surface validated by this contract.
  const bootstrapNames = names(artifacts, /bootstrap|application-bootstrap|search-all|llm-full-all/iu)
    .filter((name) => name !== 'raw:reference/application-bootstrap.mdx');
  const readmeNames = landingNames.filter((name) => /(?:landing-raw$|raw:index\.md$)/u.test(name));
  const cliSourceNames = cliNames.filter((name) => !/llm/iu.test(name));
  // The generated MDX companion is a source artifact, not the public raw page
  // surface validated by this contract.
  const retentionNames = names(artifacts, /retention/iu).filter((name) => name !== 'raw:database/retention.mdx');
  const observabilityNames = names(artifacts, /observability/iu);
  const boundaryNames = names(artifacts, /journal|observability|security|glossary|quickstart|mvp/iu);
  if (landingNames.length === 0) throw new Error(label + ' is missing Landing artifact surfaces.');
  if (landingValueNames.length === 0) throw new Error(label + ' is missing a Landing value artifact surface.');
  if (landingVisualNames.length === 0) throw new Error(label + ' is missing a Landing visual artifact surface.');
  if (readmeNames.length === 0) throw new Error(label + ' is missing a Guide index raw artifact surface.');
  if (observabilityNames.length === 0) throw new Error(label + ' is missing an Observability artifact surface.');
  const requiredBootstrapSurfaces = new Map([
    ['html', ['bootstrap-html', 'page:/reference/application-bootstrap']],
    ['raw', ['bootstrap-raw', 'raw:reference/application-bootstrap.md']],
    ['search', ['bootstrap-search', 'search-all']],
    ['llm', ['bootstrap-llm', 'llm-full-all']],
  ]);
  for (const [surface, alternatives] of requiredBootstrapSurfaces) {
    if (!alternatives.some((name) => artifacts.has(name))) {
      throw new Error(label + ' is missing the Application Bootstrap ' + surface + ' artifact surface.');
    }
  }
  for (const name of bootstrapNames) applicationBootstrapContract(artifacts.get(name), label + ' ' + name + ' Application Bootstrap list boundary', { html: classifyApplicationBootstrapArtifactSurface(name) === 'html' });
  for (const name of readmeNames) readmeContract(artifacts.get(name), label + ' ' + name);
  for (const name of landingNames) {
    requireText(artifacts.get(name), 'BlackOps CLI', label + ' ' + name + ' CLI label');
    if (/html/iu.test(name)) requireText(artifacts.get(name), LANDING_CLI_HREF, label + ' ' + name + ' CLI link');
  }
  for (const name of landingValueNames) requireText(artifacts.get(name), LANDING_VALUE_SENTENCE, label + ' ' + name + ' Landing value');
  for (const name of landingVisualNames) {
    if (/<html\b/iu.test(artifacts.get(name))) requireText(artifacts.get(name), 'lang="ja"', label + ' ' + name + ' Japanese locale');
    for (const marker of [...LANDING_VISUAL_MARKERS, ...LANDING_ACCESSIBLE_LABELS]) requireText(artifacts.get(name), marker, label + ' ' + name + ' Landing visual marker');
    requireText(artifacts.get(name), 'BlackOpsを動かす', label + ' ' + name + ' Landing journey heading');
    requireText(artifacts.get(name), '環境を用意し、サンプルで動きを確かめてから、自分のOperationを作ります。', label + ' ' + name + ' Landing journey lead');
    assertLandingJourneyContract(artifacts.get(name), label + ' ' + name);
    for (const marker of OLD_LANDING_SECTION_MARKERS) if (artifacts.get(name).includes(marker)) throw new Error(label + ' ' + name + ' retains a retired section marker: ' + marker);
    if (WRONG_SUCCEEDED_STATE.test(artifacts.get(name))) throw new Error(label + ' ' + name + ' uses Succeeded as a Lifecycle state.');
  }
  if (whyNames.length === 0) throw new Error(label + ' is missing Why BlackOps artifact surfaces.');
  for (const name of whyNames) {
    requireText(artifacts.get(name), '受付・再試行・完了を確認', label + ' ' + name + ' actionable outcome');
    if (artifacts.get(name).includes(OLD_WHY_OUTCOME)) throw new Error(label + ' ' + name + ' keeps the vague reader outcome.');
  }
  if (cliNames.length === 0) throw new Error(label + ' is missing CLI artifact surfaces.');
  for (const name of cliNames) classifyCliArtifactSurface(name);
  for (const surface of ['html', 'raw', 'search', 'llm']) {
    if (!cliNames.includes('cli-' + surface)) {
      throw new Error(label + ' is missing the CLI ' + surface + ' artifact surface.');
    }
  }
  const cliBoundary = cliSourceNames.map((name) => artifacts.get(name)).join('\n');
  const retentionSurfaceValues = [];
  const requiredRetentionSurfaces = new Map([
    ['html', ['retention-html', 'page:/database/retention']],
    ['raw', ['retention-raw', 'raw:database/retention.md']],
    ['search', ['retention-search', 'search-all']],
    ['llm', ['retention-llm', 'llm-full-all']],
  ]);
  for (const [surface, alternatives] of requiredRetentionSurfaces) {
    const present = alternatives.filter((name) => artifacts.has(name));
    if (present.length === 0) {
      throw new Error(label + ' is missing the Retention ' + surface + ' artifact surface.');
    }
    for (const name of present) {
      const extracted = retentionArtifactSurface(name, artifacts.get(name), label + ' ' + name);
      retentionSurfaceContract(extracted.text, label + ' ' + name + ' Retention contract', { html: extracted.surface === 'html' });
      retentionSurfaceValues.push(extracted.text);
    }
  }
  for (const name of retentionNames) classifyRetentionArtifactSurface(name);
  const retentionBoundary = retentionSurfaceValues.join('\n');
  retentionContract({ cli: cliBoundary, retention: retentionBoundary, runtimeSource: retentionRuntimeSource }, label);
  storageProtectionContract(cliBoundary, label + ' CLI');
  taskSpecificationContract({ taskSource, repositoryPaths }, label);
  specification83Contract(spec83Source, label);
  observedAuditContract(observabilityNames.map((name) => artifacts.get(name)).join('\n'), label + ' Observability');
  for (const name of cliNames) {
    const cliText = artifactCliSection(name, artifacts.get(name));
    const cliSurface = classifyCliArtifactSurface(name);
    cliStableListContract(cliText, label + ' ' + name + ' Stable list boundary', { html: classifyCliArtifactSurface(name) === 'html' });
    retentionSurfaceContract(cliText, label + ' ' + name + ' Retention contract', { html: cliSurface === 'html', cli: true });
    for (const marker of ['Projectを作る・Buildする', 'Operationを実行する', 'Dataを管理する', '診断・復旧する']) requireText(artifacts.get(name), marker, label + ' ' + name + ' CLI boundary');
    if (/(?:raw|llm)/iu.test(name)) requireText(cliText, 'php blackops list', label + ' ' + name + ' CLI first command');
    if (/(?:raw|llm)/iu.test(name)) {
      requireText(cliText, CLI_TABLE_HEADER, label + ' ' + name + ' CLI scan-first table');
      requireText(cliText, CLI_HELP_BOUNDARY, label + ' ' + name + ' CLI Help limitation');
      if (CLI_INTERNAL_NAMES.test(cliText)) throw new Error(label + ' ' + name + ' exposes an internal configuration class name.');
    }
    if (ROADMAP_COMMANDS.test(cliText)) throw new Error(label + ' ' + name + ' presents a roadmap command as current.');
  }
  if (boundaryNames.length === 0) throw new Error(label + ' is missing Journal boundary artifact surfaces.');
  const boundary = boundaryNames.map((name) => artifacts.get(name)).join('\n');
  const whyBoundary = whyNames.map((name) => artifacts.get(name)).join('\n');
  const journalNames = names(artifacts, /journal/iu);
  if (journalNames.length === 0) throw new Error(label + ' is missing Journal concept artifact surfaces.');
  for (const name of journalNames) {
    for (const marker of JOURNAL_CONCEPT_MARKERS) requireText(artifacts.get(name), marker.replaceAll('`', ''), label + ' ' + name + ' Journal concept boundary');
  }
  requireText(boundary, 'Canonical JournalはOperation Lifecycleの正本', label + ' Journal boundary');
  requireText(boundary, '汎用Business／Security Audit Trailではありません', label + ' non-audit boundary');
  requireRegex(boundary, /Observed[^\n]*kind=audit/u, label + ' Observed kind classification');
  requireText(boundary, 'Canonical Audit TrailのRecordではありません', label + ' Observed non-audit boundary');
  requireText(whyBoundary, '汎用Business／Security Audit Trailや任意のApplication LogはApplicationが所有します。', label + ' Why BlackOps application audit boundary');
  requireText(whyBoundary, 'Retention／Replay／Rotationなどの個別運用Eventは、Lifecycle Journalとは別のFramework運用契約で扱います。', label + ' Why BlackOps operational audit boundary');
  for (const [name, text] of artifacts) noForbiddenBoundary(text, label + ' ' + name);
  if (css !== '') themeContract(css, label);
  return true;
}

function normalizeDocuments(value) {
  if (value instanceof Map) return new Map([...value].map(([name, text]) => [String(name), required(text, String(name))]));
  if (value !== null && typeof value === 'object') return new Map(Object.entries(value).map(([name, text]) => [name, required(text, name)]));
  return new Map();
}

function document(sources, name, label) {
  const text = sources.get(name);
  if (text === undefined) throw new Error(label + ' is missing ' + name + '.');
  return text;
}

function required(value, label) {
  if (typeof value !== 'string' || value === '') throw new Error(label + ' must be a non-empty string.');
  return value;
}

function cliStableListContract(text, label, options = {}) {
  const visible = normalizeVisibleText(text, options);
  for (const marker of CLI_STABLE_LIST_BOUNDARY_MARKERS) requireText(visible, marker, label);
  // A forbidden claim remains forbidden when an artifact splits it across
  // block elements, but a required boundary marker must remain contiguous.
  const flattened = visible.replace(/\s+/gu, ' ');
  if (flattened.includes(CLI_OLD_ABSOLUTE_SIDE_EFFECT_FREE_CLAIM)) {
    throw new Error(label + ' retains the old absolute side-effect-free claim.');
  }
  ordered(visible, CLI_STABLE_LIST_BOUNDARY_MARKERS, label + ' order');
}

function applicationBootstrapContract(text, label, options = {}) {
  const visible = normalizeVisibleText(text, options);
  for (const marker of APPLICATION_BOOTSTRAP_LIST_BOUNDARY_MARKERS) requireText(visible, marker, label);
  const flattened = visible.replace(/\s+/gu, ' ');
  if (flattened.includes(APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM_FLAT)) {
    throw new Error(label + ' retains the old Application Bootstrap list claim.');
  }
  ordered(visible, APPLICATION_BOOTSTRAP_LIST_BOUNDARY_MARKERS, label + ' order');
}

export function normalizeVisibleText(text, { html = false } = {}) {
  const source = String(text);
  const normalized = html ? normalizeHtmlVisibleText(source) : decodeTextEntities(source);
  return normalized
    .replaceAll('`', '')
    .replace(/[^\S\r\n]+/gu, ' ')
    .trim();
}

const BLOCK_ELEMENTS = new Set(['address', 'article', 'aside', 'blockquote', 'dd', 'details', 'dialog', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul']);

function normalizeHtmlVisibleText(text) {
  validateNumericReferences(text);
  let dom;
  try {
    dom = new JSDOM(text, { contentType: 'text/html' });
  } catch (error) {
    throw new Error('Unable to parse HTML artifact visibility: ' + error.message);
  }
  const root = dom.window.document.documentElement;
  const chunks = [];
  visitVisibleNode(root, chunks, false);
  return chunks.join('');
}

function isHardNonVisibleElement(element) {
  const tagName = element.tagName.toLowerCase();
  if (tagName === 'script' || tagName === 'style' || tagName === 'template') return true;
  const display = element.style.getPropertyValue('display').trim().toLowerCase();
  const contentVisibility = element.style.getPropertyValue('content-visibility').trim().toLowerCase();
  if (display === 'none' || contentVisibility === 'hidden') return true;
  if (element.hasAttribute('hidden') && display === '') return true;
  if (tagName === 'dialog' && !element.hasAttribute('open') && display === '') return true;
  return false;
}

function inlineVisibilityState(element) {
  const visibility = element.style.getPropertyValue('visibility').trim().toLowerCase();
  if (visibility === 'hidden' || visibility === 'collapse') return 'hidden';
  if (visibility === 'visible' || visibility === 'initial') return 'visible';
  return null;
}

function visitVisibleNode(node, chunks, inheritedVisibilityHidden) {
  if (node.nodeType === 3) {
    if (!inheritedVisibilityHidden) chunks.push(node.nodeValue ?? '');
    return;
  }
  if (node.nodeType !== 1) return;
  const tagName = node.tagName.toLowerCase();
  if (tagName === 'head' || tagName === 'title') return;
  if (isHardNonVisibleElement(node)) return;
  const localVisibility = inlineVisibilityState(node);
  const visibilityHidden = localVisibility === 'visible'
    ? false
    : localVisibility === 'hidden'
      ? true
      : inheritedVisibilityHidden;
  const children = tagName === 'details' && !node.hasAttribute('open')
    ? [...node.children].filter((child) => child.tagName.toLowerCase() === 'summary').slice(0, 1)
    : [...node.childNodes];
  if (visibilityHidden && localVisibility !== 'visible') {
    for (const child of children) visitVisibleNode(child, chunks, true);
    return;
  }
  if (tagName === 'br') {
    chunks.push('\n');
    return;
  }
  const block = BLOCK_ELEMENTS.has(tagName);
  if (block) chunks.push('\n');
  for (const child of children) visitVisibleNode(child, chunks, visibilityHidden);
  if (block) chunks.push('\n');
}

function decodeTextEntities(text) {
  const named = text.replace(/&(?:amp|lt|gt|quot|apos|#39|nbsp);/giu, (entity) => ({
    '&amp;': '&',
    '&lt;': '<',
    '&gt;': '>',
    '&quot;': '"',
    '&apos;': "'",
    '&#39;': "'",
    '&nbsp;': ' ',
  }[entity.toLowerCase()] ?? entity));
  return decodeNumericReferences(named);
}

function decodeNumericReferences(text) {
  validateNumericReferences(text);
  return text.replace(/&#(?:[xX][0-9A-Fa-f]+|[0-9]+);/gu, (entity) => decodeNumericCharacterReference(entity));
}

function validateNumericReferences(text) {
  let offset = text.indexOf('&#');
  while (offset >= 0) {
    const semicolon = text.indexOf(';', offset + 2);
    const boundary = text.slice(offset + 2).search(/[\s<>&]/u);
    if (semicolon < 0 || (boundary >= 0 && offset + 2 + boundary < semicolon)) {
      throw new Error('Invalid numeric character reference in visible artifact text.');
    }
    const entity = text.slice(offset, semicolon + 1);
    if (!/^&#(?:[xX][0-9A-Fa-f]+|[0-9]+);$/u.test(entity)) {
      throw new Error('Invalid numeric character reference in visible artifact text.');
    }
    decodeNumericCharacterReference(entity);
    offset = text.indexOf('&#', semicolon + 1);
  }
}

function decodeNumericCharacterReference(entity) {
  const match = /^&#([xX])?([0-9A-Fa-f]+);$/u.exec(entity);
  if (match === null || (match[1] === undefined && /[^0-9]/u.test(match[2]))) {
    throw new Error('Invalid numeric character reference in visible artifact text.');
  }
  const codePoint = BigInt(match[1] === undefined ? match[2] : `0x${match[2]}`);
  if (codePoint === 0n || codePoint > 0x10ffffn || (codePoint >= 0xd800n && codePoint <= 0xdfffn)) {
    throw new Error('Invalid numeric character reference in visible artifact text.');
  }
  return String.fromCodePoint(Number(codePoint));
}

function readmeContract(text, label) {
  const openingEnd = text.indexOf('\n## Start Here');
  if (openingEnd < 0) throw new Error(label + ' is missing the Guide index opening boundary.');
  const opening = text.slice(0, openingEnd);
  if (count(opening, LANDING_VALUE_SENTENCE) !== 1) throw new Error(label + ' must have exactly one general-language value sentence.');
  requireText(opening, README_OPERATION_DEFINITION, label + ' Operation definition');
  ordered(opening, [LANDING_VALUE_SENTENCE, README_OPERATION_DEFINITION, '型付きInput', 'Outcome'], label + ' Guide index concept order');
  if (opening.includes(OLD_README_DUPLICATE)) throw new Error(label + ' repeats the old same-ID value statement.');
}

function retentionContract({ cli, retention, runtimeSource }, label) {
  requireText(cli, RETENTION_CLI_BOUNDARY, label + ' CLI retention boundary');
  requireText(cli, RETENTION_FALLBACK_CLI, label + ' CLI retention fallback');
  requireText(retention, RETENTION_CLI_BOUNDARY, label + ' retention guide boundary');
  requireText(retention, RETENTION_FALLBACK_GUIDE, label + ' retention guide fallback');
  for (const [name, text] of [['CLI', cli], ['Retention guide', retention]]) {
    if (text.includes(UNPUBLISHED_RETENTION_OPTION)) throw new Error(label + ' ' + name + ' publishes an inner-command-only retention option.');
  }
  const definition = runtimeSource.match(/\$retentionOptions\s*=\s*static function[\s\S]*?\n\s*\};/u)?.[0];
  if (definition === undefined) throw new Error(label + ' is missing the source-derived outer Retention Definition.');
  const options = [...definition.matchAll(/->addOption\('([^']+)'/gu)].map(([, name]) => name);
  if (JSON.stringify(options) !== JSON.stringify(PUBLIC_RETENTION_OPTIONS)) {
    throw new Error(label + ' outer Retention Definition must expose exactly the four public options.');
  }
  if (definition.includes(UNPUBLISHED_RETENTION_OPTION.replace(/^--/u, "'"))) {
    throw new Error(label + ' outer Retention Definition exposes the unpublished idempotency option.');
  }
}

function storageProtectionContract(text, label) {
  requireRegex(text, /storage:protection:plan[^\n]*\[--tenant-type=<type> --tenant-id=<id>\]/u, label + ' Tenant Scope command');
  requireText(text, 'Tenant Scopeは任意で、省略できます。', label + ' Tenant Scope optionality');
  requireText(text, '指定する場合は`--tenant-type`と`--tenant-id`を必ず同時に指定', label + ' Tenant Scope pair');
  requireText(text, '片方だけの入力はErrorになります。', label + ' Tenant Scope one-sided failure');
  for (const line of text.split(/\r?\n/u)) {
    if (!line.includes('storage:protection:plan')) continue;
    const hasType = line.includes('--tenant-type');
    const hasId = line.includes('--tenant-id');
    if (hasType !== hasId) throw new Error(label + ' exposes a one-sided Tenant Scope command.');
  }
}

function specification83Contract(text, label) {
  const source = required(text, label + ' Specification 83 source');
  requireText(source, '# Blume Documentation Experience', label + ' Specification 83 path');
  requireText(source, 'docs/guide/', label + ' Specification 83 delivery boundary');
}

function taskSpecificationContract({ taskSource, repositoryPaths }, label) {
  const task = required(taskSource, label + ' Task Packet source');
  if (!Array.isArray(repositoryPaths) && !(repositoryPaths instanceof Set)) {
    throw new Error(label + ' Repository path inventory must be an array or Set.');
  }
  const inventory = [...repositoryPaths];
  if (inventory.length === 0) throw new Error(label + ' Repository path inventory must not be empty.');
  const inventorySet = new Set(inventory.map((entry) => safeRepositoryInventoryPath(entry, label + ' Repository path inventory')));
  const sectionHeadings = [...task.matchAll(/^## Relevant Specifications(?: and Decisions)?[ \t]*$/gmu)];
  if (sectionHeadings.length === 0) {
    throw new Error(label + ' Task Packet is missing the Relevant Specifications section.');
  }
  if (sectionHeadings.length !== 1 || sectionHeadings[0].index === undefined) {
    throw new Error(label + ' Task Packet must contain exactly one Relevant Specifications section.');
  }
  const sectionHeading = sectionHeadings[0];
  const sectionStart = sectionHeading.index + sectionHeading[0].length;
  const remainder = task.slice(sectionStart);
  const nextHeading = remainder.search(/^##\s+/mu);
  const section = remainder.slice(0, nextHeading < 0 ? undefined : nextHeading);
  const paths = [];
  for (const line of section.split(/\r?\n/u)) {
    if (line.trim() === '') continue;
    const match = line.match(/^-\s+`([^`\r\n]+)`\s*$/u);
    if (match === null) throw new Error(label + ' Task Packet Relevant Specifications contains an unsafe or malformed entry.');
    const relativePath = safeRepositoryPath(match[1], label + ' Task Packet specification path');
    if (paths.includes(relativePath)) throw new Error(label + ' Task Packet Relevant Specifications contains a duplicate path: ' + relativePath);
    paths.push(relativePath);
    if (!inventorySet.has(relativePath)) {
      throw new Error(label + ' Task Packet Relevant Specifications path does not exist in the Repository inventory: ' + relativePath);
    }
  }
  if (paths.length === 0) throw new Error(label + ' Task Packet Relevant Specifications must list at least one path.');
  const missingAuthorityPaths = [...P22_005E_REQUIRED_AUTHORITY_PATHS].filter((path) => !paths.includes(path));
  if (missingAuthorityPaths.length > 0) {
    throw new Error(label + ' Task Packet Relevant Specifications is missing required authority path(s): ' + missingAuthorityPaths.join(', '));
  }
}

function safeRepositoryPath(value, label) {
  safeRepositoryInventoryPath(value, label);
  if (/[*?\[\]{}]/u.test(value)) throw new Error(label + ' contains an unsafe Repository path: ' + value);
  return value;
}

function safeRepositoryInventoryPath(value, label) {
  if (typeof value !== 'string' || value === '' || value.startsWith('/') || value.startsWith('\\') || /^[A-Za-z]:/u.test(value)) {
    throw new Error(label + ' must contain a relative Repository path.');
  }
  if (value.includes('\\') || value.includes('\0') || value.includes('://')) {
    throw new Error(label + ' contains an unsafe Repository path: ' + value);
  }
  const segments = value.split('/');
  if (segments.some((segment) => segment === '' || segment === '.' || segment === '..')) {
    throw new Error(label + ' contains an unsafe Repository path: ' + value);
  }
  return value;
}

function observedAuditContract(text, label) {
  requireRegex(text, /Observed `kind=audit`/u, label + ' kind classification');
  for (const marker of [AUDIT_PORT, AUDIT_EVENT, '既定Application CLIはPostgreSQL Audit Storeだけを使い', 'ReplayとRotationは専用Audit Storeに留まる', 'Default JSONLへは出ません', 'RotationのSafe Fingerprint／Scope HashもDefault Metricへ複製しません', '"audit_id"', '"operation_id"', '"target"', '"affected_count"', '"policy"', '"purged_at"', '"purged_by"', '"tenant":null']) {
    requireText(text, marker, label + ' safe Retention Audit shape and boundary');
  }
  if (text.includes('storage.rotation.completed')) throw new Error(label + ' publishes the retired Rotation event.');
}

function count(text, value) {
  return text.split(value).length - 1;
}

function requireText(text, expected, label) {
  if (!text.includes(expected)) throw new Error(label + ' is missing: ' + expected);
}

function requireRegex(text, pattern, label) {
  if (!pattern.test(text)) throw new Error(label + ' is missing the required pattern.');
}

function ordered(text, values, label) {
  let offset = -1;
  for (const value of values) {
    const index = text.indexOf(value, offset + 1);
    if (index < 0) throw new Error(label + ' is missing ordered marker: ' + value);
    offset = index;
  }
}

function noLandingDrift(landing, label) {
  for (const forbidden of [OLD_LANDING_VALUE, 'linear-gradient', 'radial-gradient', 'overflow-x: hidden', 'overflow-x:hidden']) {
    if (landing.includes(forbidden)) throw new Error(label + ' contains forbidden product or layout drift: ' + forbidden);
  }
}

function assertLandingJourneyContract(landing, label) {
  const journey = landing.match(/<ol\b[^>]*class="landing-journey"[^>]*>([\s\S]*?)<\/ol>/u)?.[1] ?? '';
  if (!journey) throw new Error(label + ' is missing the Landing tutorial journey.');
  let previous = -1;
  for (const step of LANDING_JOURNEY_STEPS) {
    const href = `href="${step.href}"`;
    if (count(journey, href) !== 1) throw new Error(label + ' must contain exactly one journey link: ' + step.href);
    const position = journey.indexOf(href);
    if (position <= previous) throw new Error(label + ' journey links must remain sequential: ' + step.href);
    previous = position;
    requireText(journey, `>${step.label}</strong>`, label + ' journey label');
    requireText(journey, `<small>${step.description}</small>`, label + ' journey description');
  }
}

function noForbiddenBoundary(text, label) {
  for (const [pattern, description] of [
    [OLD_LANDING_VALUE, 'old Landing value'],
    [OLD_WHY_OUTCOME, 'old reader outcome'],
    [OLD_README_DUPLICATE, 'duplicated Guide index value'],
    [OLD_AUDIT_MAPPING, 'Audit Log / Process History mapping'],
    [OLD_APPLICATION_AUDIT_BOUNDARY, 'legacy application audit boundary'],
    [WRONG_OPERATIONAL_AUDIT_OWNER, 'operational audit ownership boundary'],
    [UNPUBLISHED_RETENTION_OPTION, 'unpublished Retention option'],
    [APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM, 'old Application Bootstrap list claim'],
    [APPLICATION_BOOTSTRAP_OLD_LIST_CLAIM_FLAT, 'old Application Bootstrap list claim'],
    ['storage.rotation.completed', 'retired Rotation event'],
    [/監査正本/u, 'unqualified audit authority'],
  ]) {
    if ((pattern instanceof RegExp && pattern.test(text)) || (typeof pattern === 'string' && text.includes(pattern))) throw new Error(label + ' contains forbidden ' + description + '.');
  }
  for (const sentence of text.split(/[。\n]/u)) {
    const genericAuditClaim = /(?:Canonical Journal|Canonical Audit Trail)\s*(?:は|が|is|=|:)\s*[^。\n]{0,180}Audit Trail/iu.test(sentence);
    if (genericAuditClaim && !/(?:ではありません|ではない|提供しません|提供するものでもありません|表しません)/u.test(sentence)) throw new Error(label + ' presents Canonical Journal as a generic Audit Trail.');
  }
  for (const line of text.split(/\r?\n/u)) {
    for (const match of line.matchAll(/(?:kind[^a-z\n]{0,12}audit|"kind":"audit")/igu)) {
      const context = line.slice(Math.max(0, (match.index ?? 0) - 80), (match.index ?? 0) + 240);
      if (/(?:Canonical Audit|Canonical Journal[^。\n]{0,180}(?:Audit Trail|audit trail))/iu.test(context) && !/(?:ではありません|ではない|提供しません|提供するものでもありません|表しません)/u.test(context)) throw new Error(label + ' presents Observed kind=audit as a Canonical Audit Trail.');
    }
  }
  for (const sentence of text.split(/[。\n]/u)) {
    if (/(?:Replay|Rotation)[^。\n]{0,160}(?:Default JSONL|default JSONL)/u.test(sentence) && !/(?:出ません|複製しません|留まり|留まる|ではありません|しない)/u.test(sentence)) {
      throw new Error(label + ' presents Replay or Rotation as a Default JSONL producer.');
    }
    if (/(?:既定Application CLI|default application CLI|default CLI)[^。\n]{0,160}(?:JSONL|audit-log|Audit Log)/iu.test(sentence) && !/(?:だけを使い|ではなく|出ません|複製しません|留まり|留まる|ではありません|しない)/u.test(sentence)) {
      throw new Error(label + ' presents the default CLI audit store as a JSONL producer.');
    }
  }
}

function themeContract(css, label) {
  if (!css.includes('.blackops-overflow-focus:focus-visible')) throw new Error(label + ' CSS is missing overflow focus.');
  if (!css.includes('prefers-reduced-motion')) throw new Error(label + ' CSS is missing reduced motion.');
  if (!/\.landing-command[\s\S]*overflow-x:\s*auto/u.test(css) || !/\.landing-code-panel pre[\s\S]*overflow-x:\s*auto/u.test(css)) throw new Error(label + ' CSS is missing local code scrollers.');
  for (const forbidden of ['linear-gradient', 'overflow-x: hidden', 'overflow-x:hidden']) if (css.includes(forbidden)) throw new Error(label + ' CSS contains forbidden visual/layout drift: ' + forbidden);
  const allowedAmbientSelectors = new Set([
    '.landing-shell::before',
    '.landing-shell:before',
    "[data-theme='dark'] .landing-shell",
    '[data-theme="dark"] .landing-shell',
    '[data-theme=dark] .landing-shell',
    "[data-theme='dark'] .landing-shell::before",
    "[data-theme='dark'] .landing-shell:before",
    '[data-theme="dark"] .landing-shell::before',
    '[data-theme="dark"] .landing-shell:before',
    '[data-theme=dark] .landing-shell::before',
    '[data-theme=dark] .landing-shell:before',
    '.landing-backdrop__field',
    '.landing-backdrop__ribbon',
    '.landing-backdrop__ribbon--edge',
  ]);
  const isAllowedAmbientBlock = (block) => {
    const selectors = [];
    let offset = 0;
    while (true) {
      const openingBrace = block.indexOf('{', offset);
      if (openingBrace < 0) break;
      selectors.push(block.slice(offset, openingBrace).trim());
      offset = openingBrace + 1;
    }
    const selector = selectors.at(-1);
    if (!allowedAmbientSelectors.has(selector)) return false;
    return selectors.slice(0, -1).every((wrapper) => /^@supports\b[^{}]*$/u.test(wrapper));
  };
  for (const block of css.split('}')) {
    if (!block.includes('radial-gradient')) continue;
    if (!isAllowedAmbientBlock(block)) {
      throw new Error(label + ' CSS contains an ambient gradient outside the landing shell hero.');
    }
  }
}

function names(artifacts, pattern) {
  return [...artifacts.keys()].filter((name) => pattern.test(name));
}

function retentionSurfaceContract(text, label, options = {}) {
  const visible = normalizeVisibleText(text, options);
  requireText(visible, normalizeVisibleText(RETENTION_CLI_BOUNDARY), label + ' current four-option boundary');
  const fallback = options.cli ? RETENTION_FALLBACK_CLI : RETENTION_FALLBACK_GUIDE;
  requireText(visible, normalizeVisibleText(fallback), label + ' omission fallback');
  if (visible.includes(UNPUBLISHED_RETENTION_OPTION)) {
    throw new Error(label + ' exposes the unpublished idempotency option.');
  }
}

function retentionArtifactSurface(name, text, label) {
  const key = String(name);
  if (key === 'search-all') return { surface: 'search', text: retentionSearchContent(text, label) };
  if (key === 'llm-full-all') return { surface: 'llm', text: retentionLlmSegment(text, label) };
  return { surface: classifyRetentionArtifactSurface(key), text };
}

function retentionSearchContent(text, label) {
  let records;
  try {
    records = JSON.parse(text);
  } catch (error) {
    throw new Error(label + ' Retention search artifact is not valid JSON: ' + error.message);
  }
  if (!Array.isArray(records)) throw new Error(label + ' Retention search artifact must be a JSON array.');
  const matches = records.filter((record) => record !== null && typeof record === 'object' && record.route === RETENTION_ROUTE);
  if (matches.length !== 1) throw new Error(label + ' Retention search artifact must contain exactly one matching route.');
  if (typeof matches[0].content !== 'string') throw new Error(label + ' Retention search route content must be a string.');
  return matches[0].content;
}

function retentionLlmSegment(text, label) {
  const normalized = String(text).replace(/\r\n?/gu, '\n');
  const segments = normalized.split(/\n---\n\n(?=# )/u);
  const matches = segments.filter((segment) => /^# Retention(?:\n|$)/u.test(segment));
  if (matches.length !== 1) throw new Error(label + ' Retention LLM artifact must contain exactly one # Retention segment.');
  const segment = matches[0];
  const sourceLine = 'Source: ' + RETENTION_SOURCE;
  if (!segment.startsWith('# Retention\n' + sourceLine + '\n')) {
    throw new Error(label + ' Retention LLM segment has a missing or incorrect Source line.');
  }
  return segment;
}

function classifyRetentionArtifactSurface(name) {
  const key = String(name);
  const surface = new Map([
    ['retention-html', 'html'],
    ['page:/database/retention', 'html'],
    ['retention-raw', 'raw'],
    ['raw:database/retention.md', 'raw'],
    ['retention-search', 'search'],
    ['retention-llm', 'llm'],
  ]).get(key);
  if (surface === undefined) throw new Error('Unknown Retention artifact surface kind: ' + key);
  return surface;
}

function classifyCliArtifactSurface(name) {
  const key = String(name);
  const surface = new Map([
    ['cli-html', 'html'],
    ['page:/reference/project-cli', 'html'],
    ['cli-raw', 'raw'],
    ['raw:reference/project-cli.md', 'raw'],
    ['cli-search', 'search'],
    ['cli-llm', 'llm'],
  ]).get(key);
  if (surface === undefined) throw new Error('Unknown CLI artifact surface kind: ' + key);
  return surface;
}

function classifyApplicationBootstrapArtifactSurface(name) {
  const key = String(name);
  const surface = new Map([
    ['bootstrap-html', 'html'],
    ['page:/reference/application-bootstrap', 'html'],
    ['bootstrap-raw', 'raw'],
    ['raw:reference/application-bootstrap.md', 'raw'],
    ['bootstrap-search', 'search'],
    ['search-all', 'search'],
    ['bootstrap-llm', 'llm'],
    ['llm-full-all', 'llm'],
  ]).get(key);
  if (surface === undefined) throw new Error('Unknown Application Bootstrap artifact surface kind: ' + key);
  return surface;
}

function artifactCliSection(name, text) {
  if (!/llm/iu.test(name)) return text;
  const start = text.indexOf('\n# BlackOps CLI\n');
  if (start < 0) return text;
  const end = text.indexOf('\n---\n\n# ', start + 1);
  return text.slice(start, end < 0 ? undefined : end);
}

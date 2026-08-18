const page = (type, outcome, roles, next, extra = {}) => ({
  reader: { type, outcome, roles, next, ...extra },
});

export const contentMap = {
  'README.md': {
    slug: 'index',
    section: 'Start Here',
    description: 'BlackOpsをインストールし、最初のOperationからHTTP・Deferred・Database運用まで進むための利用者向けドキュメント。',
  },
  'why-blackops.md': {
    slug: 'concepts/why-blackops',
    section: 'Start Here',
    ...page('concept', '同期／非同期のOperationを同じOperation IDで追跡し、受付・再試行・完了を確認する。', {
      mentalModel: ['一つのOperation Model'],
      invariant: ['Lifecycle Journalで実行事実を追跡する'],
      boundary: ['Laravel／Symfony経験からの対応'],
    }, ['core-concepts.md']),
  },
  'core-concepts.md': {
    slug: 'concepts/core-concepts',
    section: 'Start Here',
    ...page('concept', 'Operation、Value、Outcome、Context、Journalの関係を説明し、実装時の境界を選択する。', {
      mentalModel: ['Operation'],
      invariant: ['Execution Strategy'],
      boundary: ['OperationValue'],
    }, ['execution.md']),
  },
  'installation.md': {
    slug: 'getting-started/installation',
    section: 'Start Here',
    ...page('how-to', 'Stable 1.2.0 Skeletonを作成し、認証付きHTTP 200まで確認する。', {
      prerequisites: ['Stable 1.2.0を作成する'],
      runnable: ['Stable 1.2.0を作成する'],
      success: ['Release Policy'],
      failure: ['Composer Scriptを使わない場合'],
    }, ['mvp-sample.md']),
  },
  'directory-structure.md': {
    slug: 'getting-started/directory-structure',
    section: 'Start Here',
    ...page('concept', 'Feature、Process、Config、生成物の所有境界をApplicationの配置判断へ変換する。', {
      mentalModel: ['Feature-first Source'],
      invariant: ['Process Boundary'],
      boundary: ['Generated State'],
    }, ['core-concepts.md']),
  },
  'first-operation.md': {
    slug: 'getting-started/first-operation',
    section: 'Start Here',
    ...page('tutorial', '独自Operationを生成・実装し、HTTP 202受付からWorker完了とTyped Outcome取得まで完走する。', {
      prerequisites: ['1. Generatorから始める'],
      runnable: ['4. RouteとDeferred Strategyを書く'],
      success: ['7. Workerで実行する'],
      failure: ['6. HTTPで受け付ける'],
    }, ['operations.md']),
  },
  'runtime-bootstrap.md': {
    slug: 'getting-started/local-runtime',
    section: 'Start Here',
    ...page('how-to', 'Docker Runtimeを起動し、Migration、HTTP、Workerの最初の応答を検証する。', {
      prerequisites: ['ImageとDependency'],
      runnable: ['Database Migration'],
      success: ['HTTPを起動する'],
      failure: ['WorkerとMaintenanceの失敗から戻す'],
    }, ['mvp-sample.md']),
  },
  'mvp-sample.md': {
    slug: 'getting-started/quickstart',
    section: 'Start Here',
    ...page('tutorial', 'InstallからInline、Transaction、Deferred、Worker、Outcomeまで公開StableのJourneyを完走する。', {
      prerequisites: ['1. 実行Channelを選ぶ'],
      runnable: ['2. Image、Artifact、Databaseを準備する'],
      success: ['9. Workerで完了させる'],
      failure: ['6. 失敗をOperation IDで調べる'],
    }, ['first-operation.md']),
  },
  'operations.md': {
    slug: 'operations/authoring',
    section: 'Build',
    ...page('how-to', 'Typed self-handled Operationと業務拒否をApplication-owned PHPへ実装する。', {
      prerequisites: ['標準形'],
      runnable: ['Contextが必要なOperation'],
      success: ['値のない成功'],
      failure: ['予期された業務拒否'],
    }, ['validation.md']),
  },
  'scheduled-operation.md': {
    slug: 'operations/scheduled-operation',
    section: 'Build',
    ...page('how-to', 'one-shot Scheduleを構成し、実行結果、Misfire、Overlap、Crash Recoveryを観測する。', {
      prerequisites: ['何を作るか'],
      runnable: ['Migration、Build、初回実行'],
      success: ['CLIの結果とExit Code'],
      failure: ['Misfire、Overlap、Crash Recovery'],
    }, ['deployment.md']),
  },
  'project-generators.md': {
    slug: 'operations/generators',
    section: 'Build',
    ...page('how-to', 'Operation、Migration、SeederをCLIで生成し、生成FileとBuild結果を確認する。', {
      prerequisites: ['安全確認とビルド'],
      runnable: ['Operationを生成する'],
      success: ['Migrationを生成する'],
      failure: ['Framework更新'],
    }, ['first-operation.md']),
  },
  'validation.md': {
    slug: 'operations/validation',
    section: 'Build',
    ...page('how-to', 'Value Validationの成功とHTTP 422 Rejectionを実装し、Protocol境界を検証する。', {
      prerequisites: ['動く完全例'],
      runnable: ['動く完全例'],
      success: ['Capability Matrix'],
      failure: ['HTTPとJournalの境界'],
    }, ['operations.md']),
  },
  'execution.md': {
    slug: 'execution/http-and-deferred',
    section: 'Build',
    ...page('concept', 'InlineとDeferredの受付、完了、耐久性の境界をOperation選択へ結び付ける。', {
      mentalModel: ['Inline HTTP'],
      invariant: ['Transactional Outboxへの登録'],
      boundary: ['Runtimeの境界'],
    }, ['operation-lifecycle.md']),
  },
  'console-command.md': {
    slug: 'execution/console-command',
    section: 'Build',
    ...page('how-to', 'OperationをCLIへ公開し、Help、Human／JSON結果、Exit Codeを確認する。', {
      prerequisites: ['実行手順'],
      runnable: ['2. BuildとHelpを確認する'],
      success: ['3. Human／JSONで実行する'],
      failure: ['Exit／Failure Contract'],
    }, ['project-cli.md']),
  },
  'authentication.md': {
    slug: 'auth/authentication',
    section: 'Build',
    ...page('how-to', 'Session Starterを生成し、Register、Login、Logoutと認証境界をHTTPで確認する。', {
      prerequisites: ['Application-owned Starterを生成する'],
      runnable: ['Autoload、Migration、Build、HTTP'],
      success: ['Register、Login、Logoutを確認する'],
      failure: ['Protected `GET /me`を追加する'],
    }, ['authorization.md']),
  },
  'authorization.md': {
    slug: 'auth/authorization',
    section: 'Build',
    ...page('how-to', 'Operation、Deferred、Status Policyを実装し、許可とDenyの結果を検証する。', {
      prerequisites: ['Applicationの責任'],
      runnable: ['Policyを実装してBindingする'],
      success: ['InlineとDeferred'],
      failure: ['PolicyのDenyを確認する'],
    }, ['frontend.md']),
  },
  'frontend.md': {
    slug: 'frontend',
    section: 'Build',
    ...page('how-to', 'Clientを生成し、Server-sideからOperationとStatusを呼び出して結果を確認する。', {
      prerequisites: ['Generated Clientの境界'],
      runnable: ['Clientを生成する'],
      success: ['Server Requestから呼ぶ'],
      failure: ['Frontend Frameworkの選択'],
    }, ['community-board.md']),
  },
  'community-board.md': {
    slug: 'testing/community-board',
    section: 'Build',
    ...page('tutorial', 'Reference Applicationを起動し、Authentication、Inline、Deferred、Browser outcomeまで完走する。', {
      prerequisites: ['Quickstartとの使い分け'],
      runnable: ['空のLocal Stateから起動する'],
      success: ['User Journeyを確認する'],
      failure: ['Troubleshooting'],
    }, ['testing.md']),
  },
  'outbox.md': {
    slug: 'execution/outbox',
    section: 'Async and Lifecycle',
    ...page('how-to', 'Dispatch、Commit、Relay、Worker、Retryを一続きで実行し、at-least-once境界を確認する。', {
      prerequisites: ['DispatchからCommitまで'],
      runnable: ['RelayとWorkerを分けて実行する'],
      success: ['確認とFailure Journey'],
      failure: ['確認とFailure Journey'],
    }, ['journal.md']),
  },
  'operation-lifecycle.md': {
    slug: 'concepts/lifecycle',
    section: 'Async and Lifecycle',
    ...page('concept', 'Lifecycle stateと遷移、不変条件、Terminal Outcomeの意味を説明する。', {
      mentalModel: ['共通Lifecycle'],
      invariant: ['Rejected'],
      boundary: ['Outcome'],
    }, ['execution-context.md']),
  },
  'execution-context.md': {
    slug: 'execution/context',
    section: 'Async and Lifecycle',
    ...page('concept', 'Operation ID、Actor、Tenant、Attemptの伝播とCorrelation／Causationを追跡する。', {
      mentalModel: ['読み取れる情報'],
      invariant: ['Identifierの関係'],
      boundary: ['Actor Contextの所有境界'],
    }, ['journal.md']),
  },
  'outcome-retrieval.md': {
    slug: 'database/outcomes',
    section: 'Async and Lifecycle',
    ...page('reference', 'Status、Outcome、404／410とPHP Query契約を必要な時に引く。', {
      scope: ['保存するOutcome'],
      lookup: ['認可済みPHP QueryからOutcomeを読む'],
      boundary: ['Retention'],
    }, ['operation-lifecycle.md']),
  },
  'journal.md': {
    slug: 'concepts/journal',
    section: 'Async and Lifecycle',
    ...page('concept', 'Canonical JournalとObserved Projectionの役割、Event、Replay、Securityの境界を説明する。', {
      mentalModel: ['CanonicalとObservedを分ける'],
      invariant: ['Lifecycle Event'],
      boundary: ['Replayの境界'],
    }, ['observer-replay.md']),
  },
  'database-and-transactions.md': {
    slug: 'database/transactions',
    section: 'Data and Security',
    ...page('concept', 'Transaction ownership、Nested、After Commit、Outbox保証の関係を選択できる。', {
      mentalModel: ['RepositoryとDefault Connection DI'],
      invariant: ['Operationとの保証差'],
      boundary: ['After Commitの責務境界'],
    }, ['database-migrations.md']),
  },
  'database-migrations.md': {
    slug: 'database/migrations',
    section: 'Data and Security',
    ...page('how-to', 'Migrationをinspect、dry-run、apply、verifyし、Framework／Application所有境界を保つ。', {
      prerequisites: ['Commandの登録境界'],
      runnable: ['Deployment手順'],
      success: ['Application Migrations'],
      failure: ['Deployment手順'],
    }, ['database-seeding.md']),
  },
  'database-seeding.md': {
    slug: 'database/seeding',
    section: 'Data and Security',
    ...page('how-to', 'Root／child Seederを明示順で適用し、Migration／Build／Seedの結果を安全に確認する。', {
      prerequisites: ['Root Seeder'],
      runnable: ['実行順序'],
      success: ['Applicationの責務'],
      failure: ['実行順序'],
    }, ['retention.md']),
  },
  'retention.md': {
    slug: 'database/retention',
    section: 'Data and Security',
    ...page('how-to', 'Retentionをplan、dry-run、confirmし、Purge Auditと保留対象を検証する。', {
      prerequisites: ['1. Planを確認する'],
      runnable: ['2. PurgeをDry-runする'],
      success: ['3. 承認済みのPurgeを実行する'],
      failure: ['Purge Audit'],
    }, ['deployment.md']),
  },
  'security.md': {
    slug: 'security',
    section: 'Data and Security',
    ...page('concept', 'FrameworkとApplicationのSecurity責任、非目標、Status／Data境界を判断する。', {
      mentalModel: ['責任分界'],
      invariant: ['Status参照の認可'],
      boundary: ['Operation受理前のError'],
    }, ['tenant-protection.md']),
  },
  'tenant-protection.md': {
    slug: 'security/tenant-protection',
    section: 'Data and Security',
    ...page('how-to', 'Tenant Provider、BOPD Protected Schema、認可、Key Rotationを導入し安全性を確認する。', {
      prerequisites: ['1. Tenantを入口で確定する'],
      runnable: ['3. Fresh Databaseを起動する'],
      success: ['6. Rotationを安全に完走する'],
      failure: ['Troubleshooting'],
    }, ['observability.md']),
  },
  'testing.md': {
    slug: 'testing',
    section: 'Operate',
    ...page('concept', '変更リスクに合う検証層とnegative pathを選び、Applicationの確認順を設計する。', {
      mentalModel: ['5つの検証層'],
      invariant: ['Negative Path Matrix'],
      boundary: ['利用者向け検証記録の扱い'],
    }, ['troubleshooting.md']),
  },
  'deployment.md': {
    slug: 'deployment/worker-operations',
    section: 'Operate',
    ...page('how-to', 'Build、Migration、配備、Smoke、Shutdown、Rollbackを運用手順として実行する。', {
      prerequisites: ['リリース手順'],
      runnable: ['プロセス一覧'],
      success: ['Smoke／Shutdown／Recovery'],
      failure: ['Rollback判断'],
    }, ['observability.md']),
  },
  'configuration.md': {
    slug: 'reference/configuration',
    section: 'Operate',
    ...page('reference', 'Config file、key、type、default、errorをApplicationの責務別に正確に調べる。', {
      scope: ['Environment'],
      lookup: ['Operations'],
      boundary: ['Tenant and Protected Storage'],
    }, ['project-cli.md']),
  },
  'observability.md': {
    slug: 'reference/observability',
    section: 'Operate',
    ...page('how-to', 'Provider、Health、Collectorを構成し、Trace／Metric／Correlationの観測結果を確認する。', {
      prerequisites: ['何をFrameworkが提供するか'],
      runnable: ['ProviderをApplicationで構成する'],
      success: ['DockerでLocal Collectorを確認する'],
      failure: ['失敗時の切り分け'],
    }, ['troubleshooting.md']),
  },
  'troubleshooting.md': {
    slug: 'troubleshooting',
    section: 'Operate',
    ...page('troubleshooting', '症状から原因、確認、修正へ進み、Operation、Storage、Observabilityの復旧を完了する。', {
      diagnostics: [
        '`database:seed`がArtifact Errorになる', 'Typed Self-handled Signature Error', '401にOperation IDがある場合とない場合',
        'Operation Discovery／Manifest未登録', 'Build Artifact不在／Build ID不一致', 'Frontend Contract ArtifactがInvalid／Stale',
        'Frontend Generated TreeがMissing／Drift', 'Generated TypeScriptがCompileできない', '`.fetch()`がTransport Resultを返す',
        'Deferred HTTPが202だがOutcomeがない', 'Statusが404 `operation_unavailable`を返す', 'Statusが410 `operation_expired`を返す',
        '`.wait()`が`poll_timeout`を返す', '`.status()`／`.wait()`が`unexpected_response`を返す', 'Operation ID付き500を調べる',
        'Scheduled Operationが`configuration_error`で停止する', 'Scheduled Occurrenceが`accepted`／`claimed`のまま',
        'Scheduled Occurrenceが`skipped_misfire`／`skipped_overlap`になる', 'Local Viewerが起動／表示できない',
        'Migration未適用／PostgreSQL接続失敗', '`StorageKeyProvider`が未登録', 'Unknown Key／Tag Tamper',
        '非空の旧Protected SchemaでMigrationが停止', 'Rotationの`remaining`が0にならない', 'journal.jsonlへ出力されない',
        'Local OpenTelemetry Collector／Traceが届かない', 'Grafana LGTMでTraceまたはMetricが見つからない', 'Sensitive値がJournalで見えない',
      ].map((heading) => ({ heading, classification: 'diagnostic' })),
      faq: [
        { heading: 'FAQ: 202は完了を意味しますか', classification: 'faq' },
        { heading: 'FAQ: 失敗をすべてRejectedへ変換できますか', classification: 'faq' },
      ],
      groups: [
        { heading: 'Outcome Status', classification: 'group' },
      ],
      auxiliary: [
        { heading: 'OutcomeがPending／Not Found／Expiredか判別できない', classification: 'auxiliary' },
      ],
    }, ['project-cli.md']),
  },
  'application-bootstrap.md': {
    slug: 'reference/application-bootstrap',
    section: 'Reference',
    ...page('reference', 'BuilderとProcess bootstrapの署名、登録順、HTTP／Consoleの失敗境界を引く。', {
      scope: ['EnvironmentとConfiguration'],
      lookup: ['HTTP Process'],
      boundary: ['Session AuthenticationをOpt-in登録する'],
    }, ['configuration.md']),
  },
  'project-cli.md': {
    slug: 'reference/project-cli',
    section: 'Reference',
    ...page('reference', 'BlackOps CLIのCommand、Option、mutation、output、exit、release laneを調べる。', {
      scope: ['コマンド実行一覧'],
      lookup: ['診断・復旧する'],
      boundary: ['Stable 1.2.0で利用できる範囲'],
    }, ['application-bootstrap.md']),
  },
  'core-api.md': {
    slug: 'reference/core-api',
    section: 'Reference',
    ...page('reference', '216 Public API型の署名、default、error、利用箇所をsource-derived lookupで調べる。', {
      scope: ['Application構成'],
      lookup: ['Source auditの読み方'],
      boundary: ['Tenant and Protected Storage'],
    }, ['attributes.md']),
  },
  'attributes.md': {
    slug: 'reference/attributes',
    section: 'Reference',
    ...page('reference', '25 Public Attributeのtarget、引数、default、制約、典型利用を調べる。', {
      scope: ['Operation Attributes'],
      lookup: ['Typed標準形の全体例'],
      boundary: ['Sensitive Mode'],
    }, ['core-api.md']),
  },
  'observer-replay.md': {
    slug: 'reference/observer-replay',
    section: 'Reference',
    ...page('how-to', 'Observer Replayをdry-run、confirm、resumeし、AuditとCanonical Journalの安全境界を検証する。', {
      prerequisites: ['Selector と実行モード'],
      runnable: ['Selector と実行モード'],
      success: ['Identity と安全な監査'],
      failure: ['Identity と安全な監査'],
    }, ['journal.md']),
  },
  'glossary.md': {
    slug: 'reference/glossary',
    section: 'Reference',
    ...page('reference', 'BlackOps固有語を正確に定義し、関連する実装と運用行動へ進む。', {
      scope: ['Operation'],
      lookup: ['Execution Strategy'],
      boundary: ['Canonical Journal'],
    }, ['core-concepts.md']),
  },
  'mvp-status.md': {
    slug: 'releases/current-status',
    section: 'Releases',
    ...page('reference', '現行Stable、履歴、Capability、制約、Upgrade境界をrelease authorityに照合して調べる。', {
      scope: ['Release NotesとMigration'],
      lookup: ['Available Runtime Surface'],
      boundary: ['Known Constraints'],
    }, ['installation.md']),
  },
};

const readerOwnership = {
  'why-blackops.md': { topic: ['operation-model', 'owner'], recipe: ['orientation-journey', 'owner'] },
  'core-concepts.md': { topic: ['operation-model', 'reference', 'why-blackops.md'], recipe: ['orientation-journey', 'reference', 'why-blackops.md'] },
  'installation.md': { topic: ['stable-installation', 'owner'], recipe: ['stable-install', 'owner'] },
  'directory-structure.md': { topic: ['application-boundaries', 'owner'], recipe: ['source-layout', 'owner'] },
  'first-operation.md': { topic: ['operation-authoring', 'owner'], recipe: ['operation-authoring', 'owner'] },
  'runtime-bootstrap.md': { topic: ['stable-installation', 'reference', 'installation.md'], recipe: ['runtime-bootstrap', 'owner'] },
  'mvp-sample.md': { topic: ['operation-model', 'reference', 'why-blackops.md'], recipe: ['quickstart-journey', 'owner'] },
  'operations.md': { topic: ['operation-authoring', 'reference', 'first-operation.md'], recipe: ['operation-authoring', 'reference', 'first-operation.md'] },
  'scheduled-operation.md': { topic: ['scheduling', 'owner'], recipe: ['scheduled-operation', 'owner'] },
  'project-generators.md': { topic: ['application-tooling', 'owner'], recipe: ['generator-flow', 'owner'] },
  'validation.md': { topic: ['validation', 'owner'], recipe: ['validation-flow', 'owner'] },
  'execution.md': { topic: ['execution-strategy', 'owner'], recipe: ['durable-dispatch', 'reference', 'outbox.md'] },
  'console-command.md': { topic: ['operation-tooling', 'owner'], recipe: ['console-operation', 'owner'] },
  'authentication.md': { topic: ['session-authentication', 'owner'], recipe: ['session-setup', 'owner'] },
  'authorization.md': { topic: ['authorization', 'owner'], recipe: ['authorization-policy', 'owner'] },
  'frontend.md': { topic: ['frontend-bridge', 'owner'], recipe: ['frontend-client', 'owner'] },
  'community-board.md': { topic: ['reference-application', 'owner'], recipe: ['reference-application-journey', 'owner'] },
  'outbox.md': { topic: ['execution-strategy', 'reference', 'execution.md'], recipe: ['durable-dispatch', 'owner'] },
  'operation-lifecycle.md': { topic: ['lifecycle-state', 'owner'], recipe: ['lifecycle-reading', 'owner'] },
  'execution-context.md': { topic: ['execution-context', 'owner'], recipe: ['context-correlation', 'owner'] },
  'outcome-retrieval.md': { topic: ['outcome-availability', 'owner'], recipe: ['outcome-query', 'owner'] },
  'journal.md': { topic: ['journal-projection', 'owner'], recipe: ['replay-reading', 'owner'] },
  'database-and-transactions.md': { topic: ['transaction-boundary', 'owner'], recipe: ['transaction-safety', 'owner'] },
  'database-migrations.md': { topic: ['schema-migration', 'owner'], recipe: ['migration-rollout', 'owner'] },
  'database-seeding.md': { topic: ['seed-order', 'owner'], recipe: ['seed-rollout', 'owner'] },
  'retention.md': { topic: ['retention-policy', 'owner'], recipe: ['retention-rollout', 'owner'] },
  'security.md': { topic: ['security-boundary', 'owner'], recipe: ['security-review', 'owner'] },
  'tenant-protection.md': { topic: ['protected-storage', 'owner'], recipe: ['tenant-protection', 'owner'] },
  'testing.md': { topic: ['verification-layers', 'owner'], recipe: ['verification-plan', 'owner'] },
  'deployment.md': { topic: ['release-operations', 'owner'], recipe: ['deployment-rollout', 'owner'] },
  'configuration.md': { topic: ['runtime-configuration', 'owner'], recipe: ['configuration-lookup', 'owner'] },
  'observability.md': { topic: ['telemetry-provider', 'owner'], recipe: ['telemetry-lane', 'owner'] },
  'troubleshooting.md': { topic: ['recovery-diagnostics', 'owner'], recipe: ['recovery-lane', 'owner'] },
  'application-bootstrap.md': { topic: ['bootstrap-contract', 'owner'], recipe: ['bootstrap-lookup', 'owner'] },
  'project-cli.md': { topic: ['cli-contract', 'owner'], recipe: ['cli-lookup', 'owner'] },
  'core-api.md': { topic: ['public-api', 'owner'], recipe: ['public-api-lookup', 'owner'] },
  'attributes.md': { topic: ['attributes-contract', 'owner'], recipe: ['attributes-lookup', 'owner'] },
  'observer-replay.md': { topic: ['journal-projection', 'reference', 'journal.md'], recipe: ['replay-reading', 'reference', 'journal.md'] },
  'glossary.md': { topic: ['blackops-vocabulary', 'owner'], recipe: ['glossary-lookup', 'owner'] },
  'mvp-status.md': { topic: ['release-truth', 'owner'], recipe: ['release-lookup', 'owner'] },
};

for (const [source, ownership] of Object.entries(readerOwnership)) {
  const metadata = contentMap[source];
  if (!metadata) throw new Error(`Reader ownership references an unknown source: ${source}.`);
  for (const [kind, [identity, role, reference = source]] of Object.entries(ownership)) {
    metadata.reader[kind] = { identity, role, reference };
  }
}

const EDIT_BASE = 'https://github.com/kubotak-is/blackops/edit/main/docs/guide/';

export function sourceForRoute(route) {
  const slug = route === '/' ? 'index' : route.replace(/^\/+|\/+$/g, '');
  return Object.entries(contentMap).find(([, metadata]) => metadata.slug === slug)?.[0] ?? null;
}

export function editUrlForRoute(route) {
  const source = sourceForRoute(route);
  return source === null || source === 'README.md' ? null : `${EDIT_BASE}${source}`;
}

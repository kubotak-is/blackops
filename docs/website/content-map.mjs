export const versionBanner = {
  content:
    '<strong>Document Channel:</strong> <code>main</code> · <strong>Latest Stable:</strong> <code>1.0.0</code> · mainの文書には未Releaseの変更が含まれる場合があります。',
};

export const contentMap = {
  'README.md': {
    slug: 'index',
    description: 'BlackOpsをインストールし、最初のOperationからHTTP・Deferred・Database運用まで進むための利用者向けドキュメント。',
    template: 'splash',
    hero: {
      tagline: 'PHP 8.5で、同期HTTPとDeferred処理を同じOperation Modelから構築する。',
      actions: [
        { text: 'Why BlackOps', link: '/concepts/why-blackops/', icon: 'open-book' },
        { text: 'インストール', link: '/getting-started/installation/', variant: 'secondary' },
      ],
    },
  },
  'why-blackops.md': {
    slug: 'concepts/why-blackops',
    description: 'BlackOpsが解決する分断、Headless Operation Frameworkの意味、設計原則を理解する。',
  },
  'core-concepts.md': {
    slug: 'concepts/core-concepts',
    description: 'Operation、Value、Outcome、Journal、Context、Execution Strategyの関係を理解する。',
  },
  'installation.md': {
    slug: 'getting-started/installation',
    description: 'ComposerからBlackOps Stable Skeletonをインストールし、初期セットアップを確認する。',
  },
  'directory-structure.md': {
    slug: 'getting-started/directory-structure',
    description: 'Feature-first SkeletonのDirectory構成とApplicationが所有する責務を理解する。',
  },
  'first-operation.md': {
    slug: 'getting-started/first-operation',
    description: 'ValueとOutcomeをNative型で宣言する最初のTyped Self-handled Operationを理解する。',
  },
  'runtime-bootstrap.md': {
    slug: 'getting-started/local-runtime',
    description: 'Docker ComposeでArtifact、Migration、HTTPを準備し、Install直後のApplicationを実行する。',
  },
  'mvp-sample.md': {
    slug: 'getting-started/quickstart',
    description: 'Inline WelcomeとDeferred Reportを使ってBlackOpsの主要Runtimeを確認する。',
  },
  'operations.md': {
    slug: 'operations/authoring',
    description: 'Typed Self-handled Operation、Value、Outcome、業務拒否の標準的な書き方を説明する。',
  },
  'project-generators.md': {
    slug: 'operations/generators',
    description: 'Project CLIからOperationとMigrationを安全に生成し、Framework更新後のStubを利用する。',
  },
  'operation-lifecycle.md': {
    slug: 'operations/lifecycle',
    description: 'Operationの受付から完了、拒否、再試行、失敗までのLifecycleを理解する。',
  },
  'execution.md': {
    slug: 'execution/http-and-deferred',
    description: '同じOperation ModelをInline HTTPとPostgreSQL Deferred実行へ接続する。',
  },
  'execution-context.md': {
    slug: 'execution/context',
    description: 'ExecutionContextからOperation ID、相関情報、Deferred Attemptを読み取る。',
  },
  'database-migrations.md': {
    slug: 'database/migrations',
    description: 'FrameworkとApplicationのPostgreSQL Migrationを明示Commandで確認・適用する。',
  },
  'outcome-retrieval.md': {
    slug: 'database/outcomes',
    description: 'Deferred OperationのTyped OutcomeをOperation IDから安全に取得する。',
  },
  'retention.md': {
    slug: 'database/retention',
    description: 'Payload、Journal、Outcome、Dead Letterの保持期間、Hold、Purgeを運用する。',
  },
  'configuration.md': {
    slug: 'reference/configuration',
    description: 'Application、Database、Execution、Journal、Operation、Retentionの設定責務を確認する。',
  },
  'application-bootstrap.md': {
    slug: 'reference/application-bootstrap',
    description: 'Public Application BuilderからHTTPとConsoleのProcess Boundaryを構成する。',
  },
  'project-cli.md': {
    slug: 'reference/project-cli',
    description: 'Project所有のbin/blackopsから利用できるBuild、Database、Worker、Retention Commandを確認する。',
  },
  'mvp-status.md': {
    slug: 'reference/current-status',
    description: 'main DocumentとStable 1.0.0で利用できる機能、既知の制約を確認する。',
  },
  'glossary.md': {
    slug: 'reference/glossary',
    description: 'Attempt、Claim、Lease、Fencing、Journal、Outcome等のBlackOps固有用語を確認する。',
  },
};

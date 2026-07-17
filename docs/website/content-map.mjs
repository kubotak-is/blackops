export const versionBanner = {
  content:
    '<strong>Document Channel:</strong> <code>main</code> · <strong>Latest Stable:</strong> <code>1.1.0</code> · Experimental: 1.x Minor間のBackward Compatibilityは保証しません。',
};

export const contentMap = {
  'README.md': {
    slug: 'index',
    description: 'BlackOpsをインストールし、最初のOperationからHTTP・Deferred・Database運用まで進むための利用者向けドキュメント。',
    template: 'splash',
    hero: {
      tagline: 'PHP 8.5で、同期HTTPとDeferred処理を同じOperation Modelから構築する。',
      actions: [
        { text: 'Installation', link: '/getting-started/installation/', icon: 'rocket' },
        { text: 'Why BlackOps', link: '/concepts/why-blackops/', variant: 'secondary' },
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
    description: 'QuickstartのSource作成からHTTP受付、Journal、Worker、Outcome取得までを完走する。',
  },
  'runtime-bootstrap.md': {
    slug: 'getting-started/local-runtime',
    description: 'Docker ComposeでArtifact、Migration、HTTPを準備し、Install直後のApplicationを実行する。',
  },
  'mvp-sample.md': {
    slug: 'getting-started/quickstart',
    description: 'Repository main PreviewでHeader認証、Inline Welcome、Deferred Report、Worker再認可を確認する。',
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
    slug: 'concepts/lifecycle',
    description: 'Operationの受付から完了、拒否、再試行、失敗までのLifecycleを理解する。',
  },
  'validation.md': {
    slug: 'operations/validation',
    description: 'Protocol、Binding、Value、Business ValidationのRejected境界と7 Attributeを理解する。',
  },
  'execution.md': {
    slug: 'execution/http-and-deferred',
    description: '同じOperation ModelをInline HTTPとPostgreSQL Deferred実行へ接続する。',
  },
  'execution-context.md': {
    slug: 'execution/context',
    description: 'ExecutionContextからOperation ID、相関情報、Actor Context、Deferred Attemptを読み取る。',
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
  'testing.md': {
    slug: 'testing',
    description: 'BlackOps Applicationを検証するときの層と、既存の実行例への入口を確認する。',
  },
  'deployment.md': {
    slug: 'deployment/worker-operations',
    description: 'HTTP WorkerとDeferred WorkerをProductionで運用するための責務と確認順を理解する。',
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
    description: 'Project Rootのblackopsから利用できるBuild、Database、Worker、Retention Commandを確認する。',
  },
  'troubleshooting.md': {
    slug: 'troubleshooting',
    description: 'Authentication、Build、Artifact、Worker、Journal、Outcomeの問題を症状から確認して解決する。',
  },
  'security.md': {
    slug: 'security',
    description: 'Authentication、Authorization、Actor、Sensitive Projectionの責任境界を確認する。',
  },
  'core-api.md': {
    slug: 'reference/core-api',
    description: '現在のPublic API型と通常のApplication／Adapterでの用途を確認する。',
  },
  'attributes.md': {
    slug: 'reference/attributes',
    description: '全Public Attributeの用途、付与対象、Typed Self-handled標準形での必要性を確認する。',
  },
  'mvp-status.md': {
    slug: 'releases/current-status',
    description: 'main DocumentとExperimental Stable 1.1.0で利用できる機能、既知の制約を確認する。',
  },
  'glossary.md': {
    slug: 'reference/glossary',
    description: 'Attempt、Claim、Lease、Fencing、Journal、Outcome等のBlackOps固有用語を確認する。',
  },
};

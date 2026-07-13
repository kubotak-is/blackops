# D080: Project Generator Command Contract

Status: Decided

## Context

Phase 9では、Install済みApplicationのProject所有`bin/blackops`から、Framework Packageが提供する`make:operation`と`make:migration`を実行できるようにする。

既存Quickstartの標準OperationはFeature／Action Directoryに、Typed Self-handled Operation、OperationValue、Outcomeを別Fileとして配置する。Operation Typeは必須であり、Value／OutcomeはNative `handle()` Signatureから推論される。HTTP RouteとDeferred ExecutionはOperationごとの選択事項である。

現在のDatabase CommandはFramework Package内部のMigrationだけを読み込む。Application固有の`migrations/`は任意Directoryとして設計されているが、作成したMigrationをStatus／Migrateへ読み込むRuntime Contractはまだない。そのため`make:migration`を実用的なCommandにするには、生成形式だけでなくApplication Migrationの実行境界も決める必要がある。

Framework Update後に既存Projectが新しいCommand実装とGenerator Stubを利用する所有境界はD063で決定済みである。ここでは、生成済みFileの扱いを含む具体的なCommand Contractを確定する。

## Question 1: Operation Command Input

Operationの配置と永続的なOperation Typeをどのように入力するか。

### Options

- A: `php bin/blackops make:operation Welcome/ShowWelcome --type=welcome.show`のようにFeature／Action Pathと`--type`を必須指定する
- B: Feature／Action Pathだけを受け取り、Class名からOperation Typeを自動生成する
- C: Interactive PromptでFeature、Action、Operation Typeを順に入力する

### Recommendation

Aを推奨する。

DirectoryとClass名は一つのPathから決定できるが、`Welcome/ShowWelcome`から`welcome.show`、`Billing/CreateInvoice`から`invoice.create`のようなDomain IDを一般化して安全に推論できない。Operation TypeはJournal、Deferred Transport、Outcome参照に残る識別子なので、生成時に明示して後から不用意に変更しない方がよい。

初期Versionは非対話Commandとし、Automation／Testから同じ入力で再現できるようにする。

[ANSWER]

A

[/ANSWER]

## Question 2: Generated Operation Files

`make:operation`が最初に生成する範囲をどうするか。

### Options

- A: `ShowWelcome.php`、`ShowWelcomeValue.php`、`ShowWelcomeOutcome.php`の3 Fileを生成し、すぐにBuildできる最小Typed Self-handled Operationにする
- B: Operation Fileだけを生成し、ValueとOutcomeは利用者が手動で追加する
- C: Route、HTTP Method、Inline／Deferred、ExecutionContextまでPromptまたはOptionで選ぶWizardにする

### Recommendation

Aを推奨する。

3 Fileすべてを空Propertyの有効なClassとして生成し、Operationの`handle()`は空Outcomeを返す。`#[OperationType]`以外の`#[Route]`、`#[ExecuteWith]`、`ExecutionContext`は生成しない。すべてのOperationがHTTP公開またはDeferred実行されるとは限らず、初期Generatorが推測すると削除作業と誤設定が増えるためである。

QuickstartのWelcome／ReportがRoute、Sensitive Value、Deferred Retryを具体例として補完する。

[ANSWER]

A

[/ANSWER]

## Question 3: Application Migration Execution

生成したApplication MigrationをどのCommandで管理するか。

### Options

- A: 既存の`blackops:database:status`／`blackops:database:migrate`がFramework Migrationと`migrations/`のApplication Migrationを同じ明示Deployment Stepで扱う
- B: `make:migration`はFileだけを生成し、Application MigrationはDoctrine CLIと別Configurationで実行する
- C: `make:migration`はFramework開発者向けMigrationだけを生成し、Installed Applicationでは提供しない

### Recommendation

Aを推奨する。

Applicationの`migrations/`をConventionで遅延検出し、存在しない場合は現在と同じFramework Migrationだけを扱う。生成Classは`App\Migrations` NamespaceのDoctrine `AbstractMigration`とし、Framework内部MigrationとApplication Migrationを同じDB ConnectionおよびMetadata TableでVersion管理する。Application Migrationは通常のDoctrine Constructorを使い、Framework専用Schema注入は受けない。

`make:migration`自身はDatabaseへ接続せず、Migrationを適用しない。HTTP／Worker起動時にも従来どおり暗黙Migrationを行わない。

[ANSWER]

A

[/ANSWER]

## Question 4: Existing Files and Framework Updates

Generatorの再実行とFramework Update時に既存Sourceをどう扱うか。

### Options

- A: 生成先が一つでも存在すれば何も変更せず失敗し、初期Versionでは`--force`を提供しない。Framework Updateは今後の生成に新Stubを使うが、生成済みFileは書き換えない
- B: `--force`で既存Fileを上書きできるようにする
- C: StubをSkeletonへCopyし、Projectごとに編集・更新する

### Recommendation

Aを推奨する。

生成済みSourceはApplication所有であり、Framework Updateによる書き換えは利用者の実装を破壊する。Framework PackageがStubを所有することで、既存ProjectもFramework Update直後から新しいCommand実装と新規生成用Stubを利用できる。複数File生成は事前検証し、途中失敗時に半端な生成結果を残さない。

[ANSWER]

A

[/ANSWER]

## Proposed Command Contract

回答がRecommendationどおりの場合、初期Contractは次となる。

```text
php bin/blackops make:operation Welcome/ShowWelcome --type=welcome.show
  -> app/Feature/Welcome/ShowWelcome/ShowWelcome.php
  -> app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php
  -> app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php

php bin/blackops make:migration CreateOrdersTable
  -> migrations/Version<UTC timestamp>.php
```

`make:migration`の名前は生成Class名ではなくMigration Descriptionへ使用する。生成時刻はUTC、File／Class名はDoctrine Version形式とし、同じ秒または既存Versionとの衝突では上書きせず失敗する。

## Decision

[DECISION]

1. Operation Generatorは`make:operation <Feature>/<Action> --type=<operation.type>`という非対話Commandにする。
2. Operation GeneratorはTyped Self-handled Operation、OperationValue、Outcomeの3 Fileを生成する。
3. 初期Stubは`#[OperationType]`だけを付与し、Route、Execution Strategy、ExecutionContextを推測しない。
4. Application Migrationは`App\Migrations` NamespaceとApplication Rootの`migrations/` Conventionを使用する。
5. `blackops:database:status`と`blackops:database:migrate`はFramework MigrationとApplication Migrationを同じConnection、Metadata Table、明示Deployment Stepで扱う。
6. `make:migration`はUTCのDoctrine Version Classを生成するだけで、Database接続、Migration適用、Buildを実行しない。
7. Generatorは既存Fileを上書きせず、初期Versionでは`--force`を提供しない。
8. Command実装とStubはFramework Packageが所有する。Framework Updateは新規生成へ反映するが、生成済みApplication Sourceは変更しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Project所有`bin/blackops`を変更せず、Framework Console Kernelへ2 Commandを追加する。
- Application Migration Directoryが存在しないProjectは現在と同じFramework Migrationだけを扱う。
- Doctrine Migration FinderがApplication Migration FileをDirectoryから読み込むため、生成のたびにComposer Autoload更新は要求しない。
- Database Commandの`list`／`help`はMigration Directory ScanまたはDB接続を行わず、対象Command実行時だけ解決する。
- 複数File生成は入力と衝突を事前検証し、途中失敗時は今回作成したFileだけを除去して半端な結果を残さない。
- Framework Update追従は、Project Entrypoint不変、Framework Package所有Command／Stub利用、生成済みSource不変をConsumer Smokeで検証する。

[/CONSEQUENCES]

## References

- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [D064 Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)
- [D074 Typed Self-handled Operation Signature](074-typed-self-handled-operation-signature.md)
- [D077 Implementation Worker Model Upgrade](077-implementation-worker-model-upgrade.md)
- [Developer Experience Roadmap](../spec/41-developer-experience-roadmap.md)
- [Installed Application Layout and Bootstrap](../spec/43-installed-application-layout-and-bootstrap.md)
- [Public Console Kernel Composition](../spec/48-public-console-kernel-composition.md)
- [Feature-first Quickstart Application](../spec/49-feature-first-quickstart-application.md)
- [Native Outcome and Rejection Exception](../spec/54-native-outcome-and-rejection-exception.md)

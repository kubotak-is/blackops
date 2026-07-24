# P18-005: Operation Console Adapter

Status: Accepted

## Goal

Public `#[ConsoleCommand]`を明示したOperationだけをBlackOps CLIへ公開し、OperationValueのScalar Constructor PropertyをNamed Optionへ写像する。CommandはHTTPと同じBuild済みOperation Metadata、Compiled Container、Validation、Authorization、Inline／Deferred Lifecycle、Journal、Transaction、Outcome Contractを再利用し、ApplicationがConsole入口用Actorを最小Public Providerから供給できるようにする。

CLIは入力値やThrowable Detailを反射せず、Human Outputと`--json`の双方でCompleted／Accepted／Rejected／Internalを安定して表示する。Framework Built-in、Symfony Application Command、Operation Commandの名前衝突をBuild／Bootstrapで拒否し、壊れたArtifactから`build:compile`できるP18-004のRecovery境界を維持する。

## In Scope

- Public `BlackOps\Core\Attribute\ConsoleCommand`
- Public `BlackOps\Console\ConsoleActorProvider`
- Operation Console MetadataのBuild-time CompileとCommand Manifest Schema 2
- Scalar／Nullable ScalarのNamed Option Metadata、Binding、Coercion
- Symfony Global Option／`--json`／Command Name Collision
- Lightweight Generic Operation Command Adapter
- Console ActorContext CompositionとDefault unauthenticated境界
- Inline Completed／Void、Deferred Accepted、Rejected、Validation、Internal Failure
- Human Output、Schema Version 1の一行JSON、安定Exit Code
- HTTP Runtimeと共通のOperation Runtime Composition／Cleanupの再利用
- Quickstart Inline Journey、Permanent Deferred／Failure Fixture
- Public API Inventory、Guide／Internal Documentation、Report、TODO、STATE

## Out of Scope

- Position Argument、Interactive Prompt、Secret Input、STDIN Payload
- Array、Object、Enum、Union、Intersection、Variadic Value
- Command Alias、Short Option、Custom Renderer、Shell Completion
- Deferred Wait／Status Poll、Worker自動起動
- CLI Actor ID／Actor Type Option、HTTP Credential再利用
- Application Symfony Command Discovery Contractの再設計
- Community Board Source／Config／Command／Service Provider変更（P18-007）
- Session Auth Package／Generator（P18-006）
- Documentation Website／Package／Community Boardの外部Publication／Deploy

## Relevant Specifications and Decisions

- `develop/decisions/110-application-ergonomics.md`
- `develop/spec/17-core-api.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Public API Contract

```php
namespace BlackOps\Core\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ConsoleCommand
{
    public function __construct(
        public string $name,
        public string $description = '',
    ) {}
}
```

```php
namespace BlackOps\Console;

use BlackOps\Core\ActorRef;

interface ConsoleActorProvider
{
    public function actor(): ?ActorRef;
}
```

- 両型へ`#[PublicApi]`を付ける
- AttributeはOperation Class自身への一つだけを扱い、継承やRepeatを許可しない
- `name`は空でないSymfony Canonical Command Name。Alias記法`|`、先頭`|`、Whitespace／Control Characterを拒否する
- `description`はOptionalでありCommand Listへだけ使う。Runtime ValueやConfigurationから組み立てない
- ProviderはApplication Service ProviderがCompiled ContainerへBindingするOptional Port
- Provider未登録または`actor() === null`ではOrigin／Authorizationなし
- Providerが返した`ActorRef`をOrigin／Authorizationに使い、ExecutionはFramework固定の`ActorRef('console-runtime', 'system')`
- Provider Throwable／Wrong Runtime TypeはSafe Internal Error。Class名、Message、CredentialをOutputしない
- CLIからActor ID／Typeを受け取らない

Public API Inventory、Attribute Reference、Core API表、Mago／PHPStan相当の型境界を同期する。Public Factory、Application Object Getter、Container Getterは追加しない。

## Value and Option Contract

Console公開するOperationのOperationValueは、Constructor Parameterと同名のPublic Constructor-promoted Instance Propertyだけから成るものとする。Parameter順をArgument順としてBuild Metadataへ固定する。

Supported Native Typeは`string`、`int`、`float`、`bool`と、そのNullable形だけである。

- Array、Object、Enum、Callable、Iterable、Resource、`mixed`、Literal Bool、Union、Intersection、Variadic、UntypedをBuild Error
- Static／Non-public／Non-promoted／Constructor外Public Property、Property／Parameter不一致をBuild Error
- `#[Sensitive]`がValue Rootの到達可能Propertyに一つでもあればBuild Error。本TaskはNested Inputを扱わない
- Value ConstructorやOperation HandlerをMetadata Compileのために実行しない
- OptionはすべてLong Named Option、値付き。Booleanも`--enabled=true|false`
- `userId -> --user-id`、`URLValue -> --url-value`、`user_id -> --user-id`
- ASCII Word Boundaryを決定的に変換し、空／Invalid／変換後CollisionをBuild Error
- Symfony Global Long Option、`--json`、同一Command内OptionとのCollisionをBuild Error
- Constructor DefaultがあるParameterだけ省略可能。Nullableだけでは省略可能にしない
- DefaultはSupported Scalar／nullだけを許可し、Manifestへ型を保って保存する
- Stringはそのまま、IntはCanonical Decimal、FloatはFinite Canonical HTTP Scalar Syntax、Boolは`true`／`false`だけを受理する
- Unknown Option、Position Argument、Missing Value、Missing Required Option、Invalid ScalarはNetwork／DB Handler実行前にCLI Shape／Binding Failureとする

Binding／Validation FailureはField、Rule、Stable Codeだけを返し、入力値を表示しない。Value構築後の既存Validation Failureは既存`ValidationRejectionRecorder`からOperation ID付きRejected Journalへ記録する。Value構築前Binding Failureも既存Pre-binding Rejection境界を再利用し、Receivedを作らない。

## Command Manifest Schema 2

P18-004 Command ManifestをSchema 2へ上げる。Top-level Exact Shapeは次とする。

```php
return [
    'schema_version' => 2,
    'application_build_id' => 'my-app',
    'commands' => [/* P18-004 application command entries */],
    'operation_commands' => [
        [
            'type_id' => 'order.create',
            'definition' => App\Feature\Order\CreateOrder::class,
            'value' => App\Feature\Order\CreateOrderValue::class,
            'outcome' => App\Feature\Order\OrderCreated::class,
            'strategy' => BlackOps\Core\Execution\Inline::class,
            'name' => 'order:create',
            'description' => 'Create an order.',
            'options' => [
                [
                    'property' => 'reference',
                    'name' => 'reference',
                    'type' => 'string',
                    'nullable' => false,
                    'required' => true,
                    'default' => null,
                ],
            ],
        ],
    ],
];
```

- `commands` Entry ContractはP18-004を維持する
- `operation_commands`はCommand Name、次にType IDで決定的にSortする
- OptionはConstructor Parameter順を保持する
- Entryは同じBuildでCompileしたOperation Registryと完全一致しなければならない
- Duplicate Type／Definition／Command Name、Option Property／Name、Invalid Shape／Type／Defaultを拒否する
- Source Path、Attribute Object、Runtime Value、Credential、Actor、Throwableを保存しない
- Application Command、Operation Command、Framework Built-inのCanonical Name／AliasをCase-sensitiveに全組合せ検証する
- Schema 1／Missing／Invalid／Stale Manifestは両Application Command群を未登録とし、Framework Commandと`build:compile`を維持する
- Valid Schema 2と現在のExplicit Command衝突はSilent RecoveryせずSafe Bootstrap Error
- Build失敗時は不完全Schema 2を公開せず旧Manifestを維持し、成功時だけAtomic Replaceする
- 空Console公開成功Buildは`operation_commands => []`で旧Entryを消す

RuntimeではSource Scan、Attribute Reflection Fallback、Operation Class名からのCommand名推測を行わない。

## Runtime Composition Contract

Operation CommandはManifest MetadataからGlobal List／Helpに必要なName、Description、Option Definitionを構成するLightweight Generic Adapterとする。Kernel構成とGlobal ListでOperation Handler、Compiled Container、Database Connectionを解決しない。HelpはOption Metadataだけで表示でき、Operation実行時に初めてRuntimeをComposeする。

Execution時は同じApplication Build IDのOperation Manifest、Command Manifest、Compiled Containerを使い、Operation Registry内のType ID／Definition／Value／Outcome／Strategy一致を再検証する。MismatchはSafe Bootstrap FailureでありSourceへFallbackしない。

HTTP Runtimeの責務をCopyせず、必要に応じてApplication-owned Operation Runtime Compositionを抽出する。

- Compiled Container LoadingとSynthetic Database／Logger／Transaction Injection
- Operation Definition／Handler Resolution
- PostgreSQL Clock、Identifier、Canonical Journal、Deferred Sender
- Validation、Authorization、Inline Dispatcher、Deferred Acceptor
- Transaction、Execution Scope、Failure Reporter
- Connection prepare／successful・failed finish、Journal Observer flush、Scope leak check

Console Command一回をHTTP Request一回と同じLifecycle Cleanup単位として扱う。Kernel／Command Instanceを別Application Instanceへ共有しない。Migration、Worker、Scheduler、Deferred Waitを暗黙実行しない。

Console Actor ProviderはCommand実行時だけCompiled ContainerからOptional解決する。Authorization PolicyなしOperationもExecution ActorをJournalへ持つ。Authorization PolicyありでOrigin／Authorizationがない場合は既存EvaluatorのSafe Denyを使い、独自Bypassを作らない。DeferredではOrigin／AuthorizationをMessageへ保持し、WorkerがExecution Actorだけを置換する既存Contractを維持する。

## Output and Exit Contract

`--json`はOperation Command共通のFlagで、一行JSON＋改行をSTDOUTへ出す。Key順は例の順で固定し、`schemaVersion`は1とする。

Completed:

```json
{"schemaVersion":1,"status":"completed","outcome":{"reference":"A-100","status":"created"}}
```

Empty／Void Completedは`outcome`を空Objectにする。

Accepted:

```json
{"schemaVersion":1,"status":"accepted","operationId":"019...","acceptedAt":"2026-07-22T00:00:00.000000Z"}
```

Rejected:

```json
{"schemaVersion":1,"status":"rejected","operationId":"019...","category":"validation","code":"validation.failed","violations":[{"field":"reference","rule":"not_blank","code":"validation.not_blank"}]}
```

`operationId`は存在する場合だけ含める。`violations`は常にListとし、Valueを含めない。

Internal:

```json
{"schemaVersion":1,"status":"error","code":"internal_error","operationId":"019..."}
```

Operation IDが発行前なら省略する。Raw Throwable、Path、SQL、Credentialを含めない。

Human Outputは同じSafe Fieldだけを使う。

- Completed Empty／Void: `Completed.`
- Completed Outcome: `Completed.`とStructured OutcomeのSafe JSON表示
- Accepted: `Accepted operation <operation-id>.`
- Rejected: Category／Code／Optional Operation ID、各ViolationのField／Rule／Code
- Internal: `Operation failed [internal_error].`とOptional Operation ID

Exit Code:

| Result | Exit |
| --- | ---: |
| Completed／Accepted | 0 |
| Unknown／Missing／Invalid Option、Binding、Validation Rejection | 2 |
| Authorization／Business／その他Rejected | 1 |
| Internal／Transport／Artifact／Provider Failure | 1 |

Outcomeは既存Structured Outcome ContractでNormalizeする。Console公開Outcomeに到達可能な`#[Sensitive]`があればBuild Errorとし、Runtimeも防御的に秘密値を表示しない。JSON Encode FailureやShape違反はSafe Internalへ閉じる。

## Files Allowed to Change

### Public API

- New `src/Core/Attribute/ConsoleCommand.php`
- New `src/Console/ConsoleActorProvider.php`
- `src/Core/Attribute/PublicApi.php` only if inventory mechanism requires no semantic change
- Public API／Attribute Inventory tests under `tests/Application/**`、`tests/Core/**`、`tests/Architecture/**`

### Build Metadata and Manifest

- `src/Internal/Registry/OperationMetadataCompiler.php`
- Existing Operation Discovery／Registry compiler only for reading the new attribute without changing non-console metadata semantics
- `src/Internal/Console/ApplicationCommandManifestArtifact.php`
- `src/Internal/Console/ApplicationCommandManifestFile.php`
- `src/Internal/Console/ApplicationCommandCollisionValidator.php`
- `src/Internal/Console/ApplicationCommandMetadata.php`
- New focused Operation Console metadata/compiler/option types under `src/Internal/Console/**`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Application/ApplicationCommandRuntimeManifest.php`
- `src/Internal/Application/ApplicationCommandRuntimeManifestLoader.php`
- Focused tests under `tests/Internal/Console/**`、`tests/Internal/Registry/**`、`tests/Internal/Application/**`

Do not add Console fields to Public `OperationMetadata` unless a smaller Internal Command Manifest model cannot satisfy the contract. Do not alter Operation Manifest Schema merely to duplicate Command Manifest metadata.

### Runtime and Binding

- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php` only for responsibility-neutral shared composition extraction
- `src/Internal/Application/ApplicationHttpRequestHandler.php` only for responsibility-neutral lifecycle extraction
- New focused Console Adapter／Binder／Output／Runtime Composition under `src/Internal/Console/**` or `src/Internal/Application/**`
- Existing `src/Http/Binding/HttpBoundScalarDecoder.php`／type matcher only if extracted to a transport-neutral scalar coercer without weakening HTTP behavior
- Existing Runtime Composer／Database／Logging／Transaction Injector only for reuse or responsibility-neutral extraction
- `src/Internal/Runtime/ProductionRuntimeComposition.php` or a new focused shared operation composition type if needed
- Tests under `tests/Internal/Console/**`、`tests/Internal/Application/**`、`tests/Internal/Runtime/**`

Do not call the HTTP Request Handler through a synthetic PSR-7 request. Reuse the shared operation lifecycle components directly.

### Installed Consumer

- `examples/quickstart/app/Feature/Welcome/ShowWelcome/ShowWelcome.php`
- `examples/quickstart/app/Feature/Order/CreateOrder/CreateOrder.php`
- New minimal `examples/quickstart/app/UserInterface/Console/SampleConsoleActorProvider.php`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- Quickstart tests and `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/framework-update-generators.sh`
- Skeleton Publication scripts only for mechanical inventory compatibility

Quickstartは少なくともActor付きInline CommandとNamed Option／JSON Outcomeを実PostgreSQL Journalまで完走する。Deferred、Default Deny、FailureはPermanent Framework Fixtureを優先し、Sensitiveな`GenerateReportValue`をConsole公開しない。Community Boardは変更しない。

### Documentation and Orchestration

- `docs/guide/application-bootstrap.md`
- `docs/guide/configuration.md`
- `docs/guide/project-cli.md`
- `docs/guide/core-api.md`
- `docs/guide/attributes.md`
- `docs/guide/security.md`
- `docs/guide/testing.md` only if CLI test guidance is required
- `docs/internal/application-bootstrap.md`
- `docs/internal/runtime-container.md`
- `docs/internal/installed-application-status.md`
- `develop/spec/17-core-api.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md` only for non-semantic implementation clarification
- `develop/spec/75-phase-18-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P18-005-operation-console-adapter.md`

許可外Fileが必要なら実装を広げずReportのBlockerとして返す。Community Board Source／Config／Testを変更しない。

## Required Evidence

- Public Attribute target／repeat／name／description validation
- Public Actor Provider inventory、null／Actor／Throwable
- AttributeなしOperation非公開、Command名をClassから推測しない
- Constructorを呼ばないScalar／Nullable／Default Option Metadata compile
- camelCase／Acronym／snake_case kebab変換とCollision
- Unsupported／Sensitive／Global Option／`--json`／Command Name Collision
- Schema 2 Exact Shape／Build ID／Determinism／Atomic Replace／Schema 1 Recovery／Empty Cleanup
- Application Command／Operation Command／Framework／Explicit Name・Alias Collision
- Global List／HelpでHandler／Container／DB／Actor Providerを解決しない
- Required／Default／String／Int／Float／Bool Binding、Unknown／Position／Missing Value
- Pre-binding Rejection、Validation RejectionのOperation ID／Journal／Value非表示
- ActorなしPublic Operation、ActorなしProtected Deny、Actor Provider Allow、Execution Actor固定
- Inline Structured／Empty／Void、Deferred Accepted、Authorization／Business Rejected
- JSON全Result Schema／Key順／Single Line、Human Output、Exit 0／1／2
- Internal Correlated／Uncorrelated Failure、Provider／DB／Transport FailureのSafe Output
- Same Lifecycle Journal、Transaction、Observation Flush、Connection Cleanup、Scope Leakなし
- Runtime Source Scan 0、Attribute Reflection Fallback 0
- Existing HTTP／PHP Dispatch、Application Command Discovery、Framework List／Helpが回帰しない
- Quickstartの`php blackops order:create --reference=... --json`を実DBで完走
- Community Board差分0、外部Publication／Deploy 0

## Acceptance Criteria

- [ ] `#[ConsoleCommand]`付きOperationだけがCLIへ現れる
- [ ] Supported ValueをNamed Optionへ決定的に写像し、Unsupported／Sensitive／CollisionをBuild Errorにする
- [ ] Command Manifest Schema 2がApplication／Operation Commandを同じBuild IDで決定的／Atomicに保持する
- [ ] Global List／HelpがSource／Container／Database／Actor Providerを解決しない
- [ ] RuntimeがBuild ArtifactだけからOperationを解決し、既存LifecycleをInline／Deferredで再利用する
- [ ] Console Actor Provider、Default unauthenticated、固定Execution ActorがAuthorization／Journalへ接続される
- [ ] Completed／Accepted／Rejected／Validation／InternalのHuman／JSON／Exit Codeが安定する
- [ ] Input／Sensitive Outcome／Credential／Raw ThrowableがOutput／Manifestへ露出しない
- [ ] Quickstart、Skeleton、Framework Update、HTTP／Worker Runtimeが回帰しない
- [ ] Community Board Source／Config／Command／Service Providerを変更しない
- [ ] Full PHP／Consumer／Website／Management ID／Diff Gateが成功する
- [ ] Documentation Website／Package／Community Boardを外部公開しない
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Application tests/Core tests/Internal/Application tests/Internal/Console tests/Internal/Registry tests/Internal/Runtime
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website test
mise exec -- pnpm --dir docs/website build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Quickstart E2Eは既存HTTP／Deferred／Worker Journeyを維持した上でOperation Commandを追加する。Generated／Build／Dependency ArtifactはTask完了前にCleanupする。Full Commandを環境理由で実行できない場合は未実行理由を記載し、代替で成功扱いにしない。

## Expected Report

`develop/orchestration/reports/P18-005-operation-console-adapter.md`へ次を記録する。

- Summary
- Changed Files
- Public Attribute and Actor Provider
- Value／Option Compiler Matrix
- Command Manifest Schema 2 and Recovery
- Runtime Composition and Lifecycle Reuse
- Output／Exit／Sensitive Matrix
- Quickstart／Compatibility Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

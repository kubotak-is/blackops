# P18-005 Operation Console Adapter Report

## Summary

明示`#[ConsoleCommand]`付きOperationをProject Root `blackops`へ公開するOperation Console Adapterを実装した。Build時にScalar `OperationValue`をNamed Option MetadataへCompileし、Command ManifestをSchema 2へ更新した。RuntimeはCompile済みOperation Manifest／Containerだけを使い、HTTPと共通のValidation、Authorization、Inline／Deferred、Journal、Transaction、Connection／Observation Cleanupを再利用する。

QuickstartへActor付き`order:create --reference=... --json` Journeyを追加し、実PostgreSQLのApplication Row、Transaction後Record、Canonical Journalまで完走した。Community Board、外部Publication／Deployは変更していない。WorkerはCommitしていない。

## Changed Files

- Public API: `src/Core/Attribute/ConsoleCommand.php`、`src/Console/ConsoleActorProvider.php`
- Build／Manifest: `src/Internal/Console/ApplicationBuildCompileCommand.php`、`ApplicationCommandManifest*`、`ApplicationCommandCollisionValidator.php`、新規`OperationConsole*Metadata*`
- Runtime: `src/Internal/Application/ApplicationOperation*`、HTTP Composer／Request Handler、Console Kernel、新規Console Adapter／Binder／Output／Runtime
- Registry: `src/Internal/Registry/OperationDefinitionFactory.php`
- Installed Consumer: Quickstart `CreateOrder`、`ApplicationServiceProvider`、`SampleConsoleActorProvider`、Consumer E2E／Framework Update Smoke
- Tests: Public API、Compiler、Manifest、Collision、Binding、Output、CLI Shape、Artifact Recovery、実DB Runtime、HTTP shared lifecycle
- Documentation／Specification: Guide、Internal docs、Spec 17／44／48／50／74／75、TODO、STATE
- Scope extension: Orchestrator承認により`docs/website/tests/reader-experience.test.mjs`のPublic API 150から152、Public Attribute 22から23、およびAttribute Inventoryへ`ConsoleCommand`／`ListOf`を機械同期した。Website Source／IAは変更していない。

## Public Attribute and Actor Provider

- `ConsoleCommand`は`#[PublicApi]`、final readonly、Class-only、Non-repeatableである。
- Command名は空、Whitespace／Control、`|`、先頭／末尾／連続Colonを拒否し、Canonical Segment形式だけを受理する。
- `ConsoleActorProvider::actor(): ?ActorRef`だけをPublic Portとし、Container GetterやCredential入力を追加していない。
- Provider未登録／nullではOrigin／Authorizationなし、Provider Actorは両Contextへ使用し、Execution Actorは`console-runtime:system`へ固定する。
- Provider Throwable／Wrong Runtime TypeはUncorrelated Safe Internalへ閉じ、入力、Credential、Class Messageを出力しない。

## Value／Option Compiler Matrix

| Contract | Result |
| --- | --- |
| Public constructor-promoted `string`／`int`／`float`／`bool` | Named value optionへCompile |
| Nullable scalar | 型Metadataへ保持。Nullableだけでは省略不可 |
| Constructor default | Scalar／nullを型保持し、省略可能OptionへCompile |
| camelCase／Acronym／snake_case | 決定的kebab-caseへ変換 |
| Array／Object／Enum／Union／Intersection／Variadic／Untyped | Build Error |
| Non-public／Non-promoted／Constructor不一致 | Build Error |
| `#[Sensitive]` Value／到達可能Outcome | Build Error |
| `--json`／Symfony Global／変換後重複 | Build Error |

BindingはCanonical Decimal Int、Finite Float、`true`／`false`だけを受理する。Unknown Option、Position Argument、Missing ValueはRuntime Composition前、Missing Required／Invalid ScalarはValue構築前のOperation ID付きRejected Journal境界でExit 2へ閉じ、入力値をViolationへ保存しない。

## Command Manifest Schema 2 and Recovery

- Top-level Exact Shapeを`schema_version`、`application_build_id`、`commands`、`operation_commands`へ固定した。
- Application Command Contractを維持し、Operation EntryへType ID、Definition、Value、Outcome、Strategy、Command名／説明、順序付きOptionを保存する。
- Operation CommandはCommand名、Type ID順、OptionはConstructor順で決定的に保存する。
- Duplicate Type／Definition／Name／Option、Invalid Class／Type／Default／OrderingをLoad／Writeの両側で拒否する。
- Framework／Application／Operation／Explicit Name・Alias CollisionをBuild／Bootstrapで検証する。
- WriteはTemporary FileのRead-back検証後だけAtomic Replaceし、失敗時は旧Artifactを保持する。空成功BuildはOperation Entryを消す。
- Schema 1／Missing／Invalid／Stale ArtifactはApplication／Operation Commandを登録せず、Built-in `build:compile`を維持する。壊れたOperation Entryも同じRecovery境界へ閉じる。

## Runtime Composition and Lifecycle Reuse

- `ApplicationOperationRuntimeComposer`へOperation Manifest、Compiled Container、DBAL、Logger、Transaction、Clock、Identifier、Journal、Authorization、Observationを共通化した。
- `ApplicationOperationInvocationLifecycle`へConnection prepare／successful・failed finish、Observation flush、Execution Scope leak検査を共通化し、HTTP RequestとConsole Commandが再利用する。
- Consoleは選択済みOperation MetadataだけからDefinition／Handlerを解決し、Source Scan、Attribute Reflection Fallback、HTTP Request合成を行わない。
- Global `list`／Operation `help`はCommand Manifest Metadataだけを使い、Handler、Container、Database、Actor Providerを解決しない。
- 実行時にCommand／Operation ManifestのBuild IDとType／Definition／Value／Outcome／Strategy一致を再検証する。
- Inline Structured／Void、Deferred Accepted、Validation／Authorization／Business Rejected、Correlated Handler Failure、Uncorrelated Provider Failureを実DB Fixtureで検証した。

## Output／Exit／Sensitive Matrix

| Result | JSON／Human | Exit |
| --- | --- | ---: |
| Completed Structured／Void | Version 1 Outcome／`Completed.` | 0 |
| Deferred Accepted | Operation ID、acceptedAt／受付Message | 0 |
| CLI Shape／Binding／Validation | Safe Code、Field／Rule／Code | 2 |
| Authorization／Business Rejected | Safe Category／Code、任意Operation ID | 1 |
| Internal／Provider／Artifact | `internal_error`、任意Operation ID | 1 |

JSONは固定Key順の一行＋改行、Humanは同じSafe Fieldだけを使う。Payload Exact Shape、Violation Shape、JSON Encodingを防御的に検査し、未知Top-level Key、Invalid Shape、Encoding FailureはSafe Internalへ閉じる。Raw入力、Sensitive Outcome、Credential、Throwable Message、Path、SQLを表示しない。

## Quickstart／Compatibility Evidence

- Quickstart `CreateOrder`を`#[ConsoleCommand('order:create')]`で公開した。
- `SampleConsoleActorProvider`をPublic PortへBindingし、Quickstart User Actorと固定Console Execution ActorをJournalへ接続した。
- `php blackops order:create --reference=<reference> --json`がTyped Outcomeを返し、Order／After-commit Rowと`received -> started -> succeeded -> completed` Journalを実PostgreSQLで生成した。
- Quickstart既存HTTP、Deferred Worker、Retry、Status／Wait、Diagnostics、Retention、Frontend Journeyを維持した。
- Skeleton create-projectとFramework `1.0.0 -> 1.1.0` Update後のBuild／Generator／Frontend／Operation Console Manifestを検証した。Framework UpdateはApplication所有Console Actor Providerをbytes保持する。

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app mago format --check src tests` | Success、全File formatted |
| `docker compose run --rm app mago lint` | Success、0 issues |
| `docker compose run --rm app mago analyze` | Success、0 issues |
| focused PHPUnit指定Suite | Success、692 tests／2033 assertions |
| Full PHPUnit | Success、1566 tests／6219 assertions |
| Deptrac | Success、0 violations／0 uncovered／0 errors |
| Root／Quickstart `composer validate --strict` | Success |
| `tests/Consumer/quickstart-setup.sh` | Success |
| `tests/Consumer/quickstart-e2e.sh` | Success、実DB Operation Consoleを含む |
| `tests/Consumer/skeleton-create-project.sh` | Success |
| `tests/Consumer/framework-update-generators.sh` | Success |
| Website frozen install／42 tests／build | Success、31 pages、Navigation／Search Check成功 |
| Management ID Guard／`git diff --check` | Success |
| Community Board diff Guard | Success、差分0 |
| Generated／Dependency Artifact Guard | Success、Website／Consumer Artifact cleanup済み |

途中、MagoがOutput Class Complexity、Full PHPUnitがHTTP Build ID後方互換、Quickstart E2Eが存在しないJournal列、Framework Update Fixtureが新規Source未収録を検出した。Output責務分割、Artifact由来HTTP Build ID維持、Operation ID単位Journal検証、Current Framework `src/`全体Fixture化で修正し、各Gateを再実行して成功した。

## Acceptance Criteria

- [x] `#[ConsoleCommand]`付きOperationだけがCLIへ現れる
- [x] Supported Valueを決定的Named Optionへ写像し、Unsupported／Sensitive／CollisionをBuild Errorにする
- [x] Command Manifest Schema 2を決定的／Atomicに生成しRecovery境界を維持する
- [x] Global List／HelpがSource／Container／Database／Actor Providerを解決しない
- [x] Artifact-only RuntimeがHTTP共通LifecycleをInline／Deferredで再利用する
- [x] Optional Console Actor、既定Unauthenticated、固定Execution Actorを接続する
- [x] Human／JSON／Exit 0／1／2 Contractを固定する
- [x] Input／Sensitive／Credential／Throwable Detailを出力／Artifactへ露出しない
- [x] Quickstart、Skeleton、Framework Update、HTTP／Worker Runtimeが回帰しない
- [x] Community Board差分0
- [x] Full PHP／Consumer／Website／Management ID／Diff Gate成功
- [x] 外部Publication／Deployなし
- [x] Worker Commitなし

## Remaining Issues

P18-005範囲内のRemaining Issue／Blockerはない。位置引数、Prompt、Secret Input、Array／Object Value、Deferred Wait、Session Auth、Community Board移行はTask Packetどおり対象外である。

## Suggested Next Action

OrchestratorがPublic API、Schema 2 Recovery、shared lifecycle、Safe Output、Quickstart／全Gateを独立Reviewし、問題がなければP18-005をAcceptedとしてCommitする。その後P18-006 Session Auth Package and Generatorへ進む。

## Orchestrator Review

Reviewed At: 2026-07-22T06:07:10+09:00

Status: Accepted

Public AttributeのCommand名検証、HTTP／Console間のRuntime CompositionとInvocation Lifecycle共有、JSON Encoding FailureのSafe Output、選択対象だけを解決するArtifact-only Runtime、Schema 2のInvalid Operation Entry Recovery、HTTP Build ID後方互換を重点Reviewした。Review中に検出したCommand名の端Colon／連続Colon、重複Runtime構成、Output Encoding、全Operation解決、Invalid Schema 2 Recovery、HTTP Build ID、Lifecycleの二重CleanupはWorkerが修正し、対応Testで固定した。

Orchestratorはfocused PHPUnit 692 tests／2033 assertions、`mago format --check src tests`、Deptrac 0 violations／2652 allowed、Management ID Guard、Community Board差分0、`git diff --check`、生成／Dependency Artifactが残留していないことを独立再確認した。`docs/website/tests/reader-experience.test.mjs`のPublic API／Attribute Inventory同期は、公開API追加に必要な機械的Scope Extensionとして受け入れた。

P18-005のAcceptance Criteriaはすべて満たされており、Remaining Issue／Blockerはない。

# P15-002 Frontend Contract Manifest Report

Status: Accepted

## Summary

Operation ManifestとHTTP Manifestを同じApplication Build IDで結合し、HTTP Routeを持つOperationだけを言語中立なPHP配列Frontend Contract ArtifactへCompileするInternal境界を実装した。

ContractはOperation Type、Definition、生成Module／Export名、Method／Path、Inline／Deferred、OperationValueのScalar／Nullable／Required／Binding／Sensitive／Validation Metadata、OutcomeのScalar／Nullable／Void Modeを保持する。Constructor Default実値、Credential、Environment、Example、Absolute Source Pathは保持しない。

Application-aware `build:compile`とInternal Legacy Build CommandはOperation／HTTP／Frontend Artifactへ同じBuild IDを書き、Fingerprint FreshnessはFrontend Artifactの存在、Schema、Build IDも検査する。Production HTTP／Worker Runtime Artifact LoaderにはFrontend Artifactを接続していない。

## Changed Files

### Production

- `src/Internal/Frontend/**`
- `src/Internal/Application/ApplicationBuildConfiguration.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/Console/BuildArtifactFreshnessChecker.php`

### Tests and Fixtures

- `tests/Internal/Frontend/**`
- `tests/Fixtures/Frontend/**`
- `tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`

Full Suiteで既存Fixture 4ファイルの必須`frontend_manifest`／Legacy引数不足を検出した。OrchestratorがTask Packetへ対象を追加した後、Production ContractをOptionalへ弱めずFixtureだけを同期した。

### Quickstart, Documentation, and Orchestration

- `examples/quickstart/config/app.php`
- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P15-002-frontend-contract-manifest.md`（Orchestrator Scope Correction）
- `develop/orchestration/reports/P15-002-frontend-contract-manifest.md`

Public PHP API、Migration、Database Schema、TypeScript／JavaScript Source、Guide／Website、Skeleton Publicationは変更していない。

## Decisions and Assumptions

- Compiler Inputは`OperationManifestArtifact`と`HttpOperationManifestArtifact`とし、Build ID不一致をContract化前に拒否する。
- Frontend CompilerはCompile済みRegistry／HTTP Manifestを結合し、Source Discoveryを再実行しない。ReflectionはValue Constructor、Outcome Public Property、Field AttributeのBuild-time型検査だけに使う。
- HTTP Manifestに重複保存されるDefinition／Value／Handler／Outcome／StrategyをOperation Metadataと完全比較する。Frontend出力へ不要なHandlerもArtifact corruption／staleness検出には使用する。
- Promoted Constructor FieldはParameterをBinding正本として一度だけ読み、Property側はSensitive／Validation Metadataだけを読む。Parameter／Propertyに現れる同じPromoted Attributeを二重計上しない。
- FieldとOperationは安定Sortし、Associative Key順はCodecで固定する。
- `EmptyOutcome`は`mode=void`、Fieldなしとする。その他Outcomeは`mode=outcome`とする。
- Runtime LoaderはFrontend Artifactを読まない。Backend-only Runtimeへの新しい起動依存を作らない。

## Artifact Schema and Build ID Evidence

Artifact Envelopeは次のShapeでSchema Version 1を持つ。

```text
schemaVersion: 1
applicationBuildId: non-empty string
payload
  operations[]
```

Operation Entryは次を決定的な順序でEncodeする。

```text
typeId, definition, exportName, module, method, path, strategy
value: class, fields[]
outcome: class, mode, fields[]
```

Application Build TestとLegacy Build TestはOperation／HTTP／Frontend ArtifactのBuild ID一致を検証した。Freshness Testは三Manifestが同じ要求Build IDかつ対応Schemaの場合だけFreshとし、Frontend Build IDだけを変更するとStaleへ戻ることを検証した。

Frontend Manifest Fileは同一DirectoryのTemporary Fileへ完全Writeし、Codec Decode後だけAtomic Renameする。Review修正後のTestは既存Valid Artifactにempty Build IDのinvalid writeを試し、既存Bytes不変、Temporary File cleanup、既存Artifact再Load成功を固定した。

## Type／Binding／Sensitive Matrix

| PHP Contract | Frontend Contract | Evidence |
| --- | --- | --- |
| `string` | `type=string` | Value／Outcome Compiler Test |
| `int`／`float` | `type=number` | Range付きQuick Contract Test |
| `bool` | `type=boolean` | Outcome Contract Test |
| `?T` | Base Type＋`nullable=true` | Optional Query／Nullable Outcome Test |
| Constructor Defaultあり | `required=false`、Default実値なし | Sensitive／通常Default Sentinel否定検証 |
| `#[FromPath]` | `source=path`＋Transport Name | Route Placeholder完全一致検証 |
| `#[FromQuery]` | `source=query` | Query Alias Test |
| `#[FromHeader]` | `source=header` | Header Alias Test |
| `#[FromBody]`／Attributeなし | `source=body` | Scalar Body Field Test |
| `#[Sensitive]` Value | `sensitive=true`、Mode／値なし | Quickstart Report／Failureと専用Fixture |
| Validation | Rule／Code／公開Parameter | NotBlank、Length、Range、Choice Test |
| `EmptyOutcome` | `mode=void`、Fieldなし | Deferred Report Contract Test |

Quickstart実Sourceを既存Operation Metadata Compiler／HTTP Route CompilerでCompileし、`diagnostics.failure.trigger`、`order.create`、`report.generate`、`welcome.show`の4 HTTP Operationが決定的な順で含まれることを検証した。ReportはDeferred、他はInlineであり、Report／FailureのSensitive Inputを保持する。

同Artifact Encode結果に`credential`、`local-example`、`sensitive-`、Quickstart Absolute RootがないことをAssertionで固定した。Gitignore対象の`examples/quickstart/var/build/frontend.php`をWorking Treeへ生成する代わりにTemporary Artifactの同等Guardを採用した。

## Unsupported／Collision Failure Matrix

| Failure | Result |
| --- | --- |
| Untyped／Mixed／Array／Object／Unsupported Union／Intersection | Scalar Type CompilerがBuild Error |
| GET／HEAD Body | Value CompilerがBuild Error |
| Path Placeholderと`FromPath`不一致／重複 | Value CompilerがBuild Error |
| 複数Binding Source／空Transport Name | Value CompilerがBuild Error |
| Sensitive Outcome Public Property | Outcome CompilerがBuild Error |
| Unsupported Execution Strategy | Contract CompilerがBuild Error |
| Operation／HTTP Build ID不一致 | Contract CompilerがBuild Error |
| Definition／Value／Handler／Outcome／Strategy不一致 | Contract CompilerがBuild Error |
| 同一Operationの複数Route | Contract CompilerがBuild Error |
| Invalid Export Identifier／Reserved Word | Naming CompilerがBuild Error |
| Case-insensitive Module／Export Collision | Naming CompilerがBuild Error |
| Missing／Invalid Schema／Payload | Artifact Codec／FileがBuild Error |

Unsupported Typeを`any`／`unknown`へFallbackしない。Naming Collision Testは異なるNamespaceの同一Short Class Nameが同一Module／Exportへ解決されるCaseを拒否する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P15-002 required targets>
Result: OK (62 tests, 424 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1243 tests, 4583 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2257 / Warnings 0 / Errors 0。

Management Comment ID、Runtime Frontend Artifact Import、TypeScript／JavaScript Addition、git diff --check Guard
Result: 成功。
```

初回Full PHPUnitはTask Packet外だった既存Fixtureの必須Config／引数不足により8 errors／1 failureになった。Orchestrator承認で変更可能Fileへ4 Fixtureを追加し、同期後のFull Suiteは成功した。

## Acceptance Criteria

- [x] Frontend Contract ArtifactがSchema Version、Application Build ID、Payloadを持つ
- [x] Operation／HTTP／Frontend Artifactが同じBuild IDを持つ
- [x] HTTP Routeを持つOperationだけを含み、RouteなしOperationを除外する
- [x] QuickstartのWelcome／Report／Order／Failureを決定的なContractへCompileする
- [x] Scalar、Nullable、Required、Default Optional、Path／Query／Header／Bodyを正しく表現する
- [x] Validation RuleとSensitive Input MetadataをRaw Valueなしで表現する
- [x] Inline／Deferred、Outcome／Voidを区別する
- [x] Unsupported Type、Sensitive Outcome、Manifest不整合、Naming Collisionを拒否する
- [x] Credential、Environment、Default実値、Example、Absolute Source PathをArtifactへ含めない
- [x] Application／Legacy BuildとFreshness CheckがFrontend Artifactを扱う
- [x] HTTP／Worker RuntimeはFrontend Artifactを読み込まない
- [x] Public PHP API、Migration、Database Schema、TypeScriptを追加しない
- [x] Required PHP Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

P15-002を妨げるBlockerはない。

Frontend ContractはInternal Build Artifactであり、TypeScript Tree、`frontend:generate`、`.url()`、`.toRequest()`、`.fetch()`、Result Union、Drift Checkはまだ実装していない。これらはP15-003以降のScopeである。

Documentation WebsiteはUser判断どおり未公開であり、Publication／Deployを実行していない。

## Suggested Next Action

OrchestratorがArtifact Schema、全Metadata整合、Sensitive／Unsupported Type、Atomic Write、Build ID／Freshness、Runtime非接続を独立Reviewする。Accepted後、P15-003 Operation Object and Request GenerationのTask Packetへ進む。

## Orchestrator Review

Artifact Schema、Operation／HTTP重複Metadataの完全一致、Scalar／Binding／Validation／Sensitive境界、Unsupported Type／Sensitive Outcome／Naming Collision、Atomic Write、三ManifestのBuild ID／Freshness、Production Runtime Loader非接続を独立Reviewし、Acceptance Criteriaを満たすと判断した。

Task Packet記載のTarget一式を再実行し、OK（71 tests、633 assertions）を確認した。Full PHPUnitはOK（1243 tests、4583 assertions）、Composer Root／Quickstart、Mago format／lint／analyze、Deptracも成功し、DeptracはViolations 0／Warnings 0／Errors 0だった。Management Comment ID、Runtime Frontend Import、TypeScript／JavaScript追加、`git diff --check`のGuardも成功した。

Internal Bootstrap DocumentationのManifest Envelope例が全ArtifactをSchema Version 1と誤読できたため、Schema VersionはArtifact Typeごとに所有し、現時点ではOperation／Frontendが1、HTTPが2であることを明記した。Production Codeの追加修正はない。

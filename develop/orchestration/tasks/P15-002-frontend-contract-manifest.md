# P15-002: Frontend Contract Manifest

Status: Ready

## Goal

Operation ManifestとHTTP Manifestを同じApplication Build IDで結合し、HTTP OperationのFrontend生成に必要な型、Binding、Outcome、Strategy、Sensitive／Validation Metadataだけを持つ言語中立なFrontend Contract Manifestを生成する。

TypeScript Source、Frontend Runtime、`frontend:generate`／`frontend:check`はまだ実装しない。後続TaskがSource Reflectionへ戻らず、このArtifactだけから安全かつ決定的に生成できる境界を作る。

## In Scope

- Internal Frontend Contract DTO／Compiler／Artifact Codec／File
- Frontend Manifest Schema VersionとApplication Build ID
- Operation RegistryとHTTP Manifestの結合
- HTTP Routeを持つOperationだけのContract化
- Definition、Type ID、Method、Path、Inline／Deferred Strategy、Value、OutcomeのMetadata
- OperationValue Constructor ParameterのScalar Type、Nullable、Required、Binding Source／Transport Name
- Value Propertyの`#[Sensitive]`とValidation Rule Metadata
- Outcome Public PropertyのScalar TypeとVoid Mode
- Module PathとPascalCase Export Nameの決定、Case-insensitive Collision検査
- Unsupported Type、Sensitive Outcome、不整合Metadataの安全なBuild Error
- `app.build.frontend_manifest` Configuration
- Application `build:compile`とInternal Legacy Build CommandのArtifact生成／Freshness同期
- Quickstart Build ConfigとArchitecture／Integration Test同期
- Internal Architecture Documentation、Report、STATE同期

## Out of Scope

- TypeScript／JavaScript File生成
- `frontend:generate`、`frontend:check`
- `config/frontend.php`とFrontend Output Directory
- `.fetch()`、`.toRequest()`、`.url()`、Client Runtime、Result Union
- Node／TypeScript Toolchain、Frontend Consumer E2E、GitHub Actions変更
- Public PHP API、Attribute、Migration、Database Schema
- Typed Array／Map、Nested DTO、Enum、Date／Time、Upload／Stream、Custom Responder
- Quickstart Frontend Source、Guide／Website、Skeleton Publication変更

## Relevant Specifications and Decisions

- `develop/spec/01-core-model.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`

## Files Allowed to Change

### Production

- New `src/Internal/Frontend/**`
- `src/Internal/Application/ApplicationBuildConfiguration.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/Console/BuildArtifactFreshnessChecker.php`
- `src/Internal/Console/BuildArtifactFingerprintInputs.php`（Frontend Artifact入力同期が必要な場合だけ）

### Tests and Fixtures

- New `tests/Internal/Frontend/**`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- New `tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- P15-002専用の新規`tests/Fixtures/**`

### Quickstart and Documentation

- `examples/quickstart/config/app.php`
- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`

### Specification and Orchestration

- `develop/spec/67-operation-frontend-bridge.md`（実装前提の誤りを発見した場合だけ）
- `develop/spec/68-phase-15-delivery-plan.md`（Task境界の誤りを発見した場合だけ）
- `develop/TODO.md`
- `develop/orchestration/reports/P15-002-frontend-contract-manifest.md`
- `develop/STATE.md`

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Artifact Contract

Frontend ArtifactはPHP配列Fileとし、既存Artifactと同様に少なくとも次を持つ。

```text
schemaVersion
applicationBuildId
payload
  operations[]
```

各Operation Entryは少なくとも次を保持する。

```text
typeId
definition
exportName
module
method
path
strategy: inline | deferred
value
  class
  fields[]
    name
    type: string | number | boolean
    nullable
    required
    source: path | query | header | body
    transportName
    sensitive
    validations[]
outcome
  class
  mode: outcome | void
  fields[]
    name
    type
    nullable
```

Field／Operation順とAssociative Key順を決定的にする。Reflection Object、Closure、Runtime Instance、Constructor Default実値、Credential、Environment、Absolute Source File Pathを含めない。

## Type and Binding Contract

- `string`は`string`、`int`／`float`は`number`、`bool`は`boolean`へ正規化する
- NullableはBase Typeと別Booleanで表現する
- Constructor Defaultありは`required=false`だがDefault実値を保持しない
- `#[FromPath]`、`#[FromQuery]`、`#[FromHeader]`、`#[FromBody]`とAttributeなしBodyを区別する
- Promoted PropertyのParameter／Property Attributeを二重計上しない
- ValidationはField、Rule、Code、公開可能なRule Parameterだけを保持し、Raw Valueを含めない
- Valueの`#[Sensitive]`は`true`として保持するがModeと値をFrontend出力へ流す必要がない。少なくともSensitive有無を保持する
- Outcome Public Propertyへ`#[Sensitive]`がある場合はBuild Errorにする
- Untyped、Mixed、Array、Object、Enum、DateTime、Unsupported Union／Intersectionを`any`／`unknown`へFallbackせずBuild Errorにする
- Void／`EmptyOutcome`は`mode=void`、Fieldなしとする

## Naming Contract

- Export NameはOperation DefinitionのShort Class Nameと同じPascalCase
- Module DirectoryはOperation Type IDの最終Segmentを除くPrefix
- File NameはShort Class Nameのkebab-case
- 例：`welcome.show`＋`ShowWelcome`は`operations/welcome/show-welcome.ts`
- 例：`order.create`＋`CreateOrder`は`operations/order/create-order.ts`
- 同一Module、同一Export、Case-insensitive Path Collisionを拒否する
- Invalid JavaScript／TypeScript IdentifierまたはReserved Wordへ解決する場合は暗黙RenameせずBuild Errorにする

## Build Integration Contract

- `ApplicationBuildConfiguration`は絶対Pathの`app.build.frontend_manifest`を必須Artifactとして扱う
- Quickstartは`var/build/frontend.php`を設定する
- `ApplicationBuildCompileCommand`はOperation／HTTP／Frontendを同じBuild IDで書く
- Frontend Contract CompilerはすでにCompile済みのOperation RegistryとHTTP Manifestを受け取り、Source Discoveryを再実行しない
- Internal `blackops:build:compile`も明示`frontend-manifest`引数で同じArtifactを生成する
- Freshness CheckはFrontend ArtifactのMissing／Schema／Build IDも検査する
- HTTP／Worker Production Runtime Artifact LoaderへFrontend Manifestを追加しない
- Artifact FileはTemporary Fileへ完全Write／Decode検証後にAtomic Renameする

## Acceptance Criteria

- [ ] Frontend Contract ArtifactがSchema Version、Application Build ID、Payloadを持つ
- [ ] Operation／HTTP／Frontend Artifactが同じBuild IDを持つ
- [ ] `#[Route]`を持つOperationだけを含み、RouteなしOperationを除外する
- [ ] QuickstartのWelcome／Report／Order／Failureを決定的なContractへCompileする
- [ ] Scalar、Nullable、Required、Default Optional、Path／Query／Header／Bodyを正しく表現する
- [ ] Validation RuleとSensitive Input MetadataをRaw Valueなしで表現する
- [ ] Inline／Deferred、Outcome／Voidを区別する
- [ ] Unsupported Type、Sensitive Outcome、Manifest不整合、Naming Collisionを拒否する
- [ ] Credential、Environment、Default実値、Example、Absolute Source PathをArtifactへ含めない
- [ ] Application／Legacy BuildとFreshness CheckがFrontend Artifactを扱う
- [ ] HTTP／Worker RuntimeはFrontend Artifactを読み込まない
- [ ] Public PHP API、Migration、Database Schema、TypeScriptを追加しない
- [ ] Required PHP Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Application/ApplicationHttpConfigurationTest.php \
  tests/Internal/Application/ApplicationConfigurationTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Internal/Console/CompileBuildArtifactsCommandTest.php \
  tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php \
  tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'credential|local-example|sensitive-' examples/quickstart/var/build/frontend.php
git diff --check
```

Quickstart Build ArtifactがWorking Tree外またはGitignore対象で存在しない場合、最後のArtifact Content GuardはApplication Console／Build Test内のTemporary Artifactへ同等Assertionを置き、Reportへ実行Evidenceを記録する。

## Expected Report

`develop/orchestration/reports/P15-002-frontend-contract-manifest.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Artifact Schema and Build ID Evidence
- Type／Binding／Sensitive Matrix
- Unsupported／Collision Failure Matrix
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

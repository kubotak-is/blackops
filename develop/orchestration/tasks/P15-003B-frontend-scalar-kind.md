# P15-003B: Frontend Scalar Kind

Status: Ready

## Goal

Frontend Contract ArtifactでPHP `int`と`float`を同じ`number`へ正規化している不整合を修正し、Native Scalar Kindを`string`／`integer`／`float`／`boolean`として保持する。

Artifact Schema Versionを2へ上げ、旧Version 1をFreshとして扱わない。後続P15-003のGenerated TypeScriptは`integer`／`float`をどちらも`number`へ型変換しつつ、D101のCanonical Request EncodeとP15-004のOutcome DecodeではScalar Kindを区別できるようにする。

## In Scope

- Frontend Value／Outcome FieldのNative Scalar Kind
- `int -> integer`、`float -> float`の非可逆正規化解消
- Frontend Contract Artifact Schema Version 2
- Codec Encode／DecodeとInvalid／Legacy Schema拒否
- Application／Legacy Build、Freshness Test同期
- Quickstart四Operation ContractとBuild ID回帰
- Internal Documentation、Report、STATE同期

## Out of Scope

- TypeScript Source生成、`.url()`、`.toRequest()`、`.fetch()`
- HTTP Server Scalar Binding変更
- Frontend Output Config、CLI、Atomic Tree
- Typed Collection、Nested DTO、Enum、DateTime、Upload
- Public PHP API、Attribute、Migration、Database Schema
- Quickstart／Skeleton Frontend Source、Guide／Website、Publication／Deploy

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/decisions/101-http-scalar-binding-coercion.md`
- `develop/orchestration/reports/P15-002-frontend-contract-manifest.md`
- `develop/orchestration/reports/P15-003A-http-scalar-binding-coercion.md`

## Files Allowed to Change

### Production

- `src/Internal/Frontend/FrontendScalarTypeCompiler.php`
- `src/Internal/Frontend/FrontendContractManifestCodec.php`
- `src/Internal/Frontend/FrontendContractManifestFile.php`（Schema Version同期が必要な場合だけ）
- `src/Internal/Frontend/FrontendValueFieldContract.php`（Property名／Invariant同期が必要な場合だけ）
- `src/Internal/Frontend/FrontendOutcomeFieldContract.php`（Property名／Invariant同期が必要な場合だけ）

### Tests

- `tests/Internal/Frontend/FrontendContractCompilerTest.php`
- `tests/Internal/Frontend/FrontendContractManifestFileTest.php`
- `tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`（Artifact回帰が必要な場合だけ）

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P15-003B-frontend-scalar-kind.md`

変更可能Fileの追加が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Artifact Contract

Frontend Value／Outcome Fieldの`type`は次だけを許可する。

| PHP | Artifact `type` | Future TypeScript |
| --- | --- | --- |
| `string` | `string` | `string` |
| `int` | `integer` | `number` |
| `float` | `float` | `number` |
| `bool` | `boolean` | `boolean` |

Property名`type`は維持し、Artifact Consumerが`number`だけからNative Kindを推測する必要をなくす。CodecはVersion 2で上記EnumだけをDecodeし、Version 1、旧`number`、未知Kindを拒否する。

Nullable／Required／Binding／Sensitive／Validation／Outcome Mode、Field順、Operation順、Key順は変更しない。Constructor Default実値、Credential、Runtime Value、Example、Absolute Source Pathを引き続き含めない。

## Acceptance Criteria

- [ ] Frontend Artifact Schema Versionが2になる
- [ ] PHP `int`を`integer`、`float`を`float`としてValue／Outcomeで区別する
- [ ] `string`／`boolean`、Nullable／Required、Binding／Sensitive／Validationを回帰させない
- [ ] CodecがVersion 1、旧`number`、未知Kindを拒否する
- [ ] Application／Legacy BuildがOperation／HTTP／Frontendへ同じBuild IDを書く
- [ ] FreshnessがVersion 1 Frontend ArtifactをStaleとして扱う
- [ ] Quickstart四HTTP Operationを決定的にCompileできる
- [ ] Credential、Default、Example、Absolute Source PathをArtifactへ含めない
- [ ] HTTP／Worker RuntimeはFrontend Artifactを読み込まない
- [ ] TypeScript／JavaScript、Public PHP API、Migration、Database Schemaを追加しない
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
  tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Internal/Console/CompileBuildArtifactsCommandTest.php \
  tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'FrontendContractManifest|frontendManifest|frontend_manifest' src/Internal/Runtime src/Internal/Application/ApplicationHttpRuntimeComposer.php src/Internal/Application/ApplicationWorkerComposer.php
! rg -n "'number'" tests/Internal/Frontend --glob '*.php'
git diff --check
```

最後の`number` Guardは旧Artifact Scalar Kind Assertionを残さないことだけを目的とする。説明用Test名やTypeScript将来表現へ一致する場合はCodec Fixtureへ対象を絞り、Reportへ理由を記録する。

## Expected Report

`develop/orchestration/reports/P15-003B-frontend-scalar-kind.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Schema Version／Scalar Matrix
- Build ID／Freshness Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

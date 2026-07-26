# P20-009B: Implicit Inline Ephemeral Outcome

Status: Accepted

## Goal

`EphemeralOutcome`を返すHTTP Operationから`#[ExecuteWith(Inline::class)]`の重複指定を不要にし、Generator、Example、Consumer Fixture、GuideをCanonicalな暗黙Inline Authoringへ統一する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/126-implicit-inline-ephemeral-outcome.md`
- `develop/spec/93-implicit-inline-ephemeral-outcome.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/decisions/115-deferred-authoring-and-operation-dispatch.md`

## In Scope

- Ephemeral Compiler Guardを明示Attributeではなく解決後Inline Strategyで判定する
- 暗黙Inline Typed／Legacy Ephemeral OperationのRegression Test
- Explicit Inline Compatibility、Deferred／Custom Strategy／Route／Console拒否のRegression
- Auth Generator StubとGenerator Test
- Community Board Identity Operation
- Auth Fresh Consumer Fixture
- Ephemeral Frontend／PostgreSQL Fixture
- Reader-facing Authentication／Operation／Attribute／Core API Guide
- Website Regression Testの必要な同期
- Specification／Decision／TODO／STATE／Report同期

## Out of Scope

- `ExecuteWith` Public APIの削除
- `#[Inline]`／`#[Ephemeral]` Attribute追加
- `EphemeralOutcome` MarkerやSensitive Shapeの変更
- Manifest Schema、Journal、Outcome Store、Status API、Frontend Contract変更
- Stable `1.1.0` Tag／Artifact変更
- Scheduled Operation、Ray.Aop、Transaction Interception
- Commit／Push／PR／External Publication

## Files Allowed to Change

- `src/Internal/Registry/OperationMetadataCompiler.php`
- `tests/Internal/Registry/EphemeralOutcomeContractCompilerTest.php`
- `tests/Internal/Generator/AuthGeneratorTest.php`
- `tests/Internal/Frontend/EphemeralFrontendContractTest.php`
- `tests/Transport/PostgreSql/PostgreSqlEphemeralOutcomeIntegrationTest.php`
- `tests/Frontend/fixture/app/Feature/Identity/IssueCredential/IssueCredential.php`
- `tests/Consumer/fixtures/auth-fresh/RotateSession/RotateSession.php`
- `resources/stubs/auth-register.php.stub`
- `resources/stubs/auth-login.php.stub`
- `resources/stubs/auth-logout.php.stub`
- `examples/community-board/app/Feature/Identity/Register/Register.php`
- `examples/community-board/app/Feature/Identity/Login/Login.php`
- `examples/community-board/app/Feature/Identity/Logout/Logout.php`
- `examples/community-board/README.md`
- `docs/guide/authentication.md`
- `docs/guide/operations.md`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/community-board.md`
- `docs/guide/glossary.md`
- `docs/guide/security.md`
- `docs/internal/auth-generator.md`
- `docs/internal/ephemeral-outcome.md`
- `docs/website/tests/*.test.mjs`
- `develop/decisions/112-authentication-credential-response-boundary.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/17-core-api.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/93-implicit-inline-ephemeral-outcome.md`
- `develop/decisions/126-implicit-inline-ephemeral-outcome.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009B-implicit-inline-ephemeral-outcome.md`

## Implementation Requirements

1. `assertEphemeralOperation()`はAttribute数ではなく、解決後Strategyが`Inline::class`であることを要求する。
2. AttributeなしEphemeral Operationを受理し、Manifest StrategyがInlineであることをTestする。
3. Explicit `#[ExecuteWith(Inline::class)]`はCompatibility Testとして少なくとも一例残す。
4. `#[Deferred]`、`#[ExecuteWith(Deferred::class)]`、Inline以外のCustom Strategyを拒否する。
5. exactly one Route、Console禁止、Sensitive Outcome Shapeを維持する。
6. Generator／Example／Consumer／Reader-facing GuideからEphemeralの明示Inlineと不要Importを削除する。
7. 一般的な非Ephemeral Inline Operationにも`ExecuteWith`を案内しない。
8. Stable `1.1.0`説明、Compatibility Reference、内部Test用Explicit Strategyを機械的に削除しない。

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry/EphemeralOutcomeContractCompilerTest.php tests/Internal/Generator/AuthGeneratorTest.php tests/Internal/Frontend/EphemeralFrontendContractTest.php
docker compose run --rm app vendor/bin/phpunit tests/Transport/PostgreSql/PostgreSqlEphemeralOutcomeIntegrationTest.php
bash tests/Consumer/auth-generator-fresh.sh
bash tests/Consumer/community-board-identity.sh
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n -F "#[ExecuteWith('BlackOps\\\\Core\\\\Execution\\\\Inline')]" resources/stubs examples/community-board/app tests/Consumer/fixtures docs/guide
! rg -n '明示Inline|Inlineを明示|明示的なInline|暗黙のInline.*(Error|エラー)|Ephemeral.*explicit.*Inline' docs/guide
! rg -n 'Route付き(の)?明示Inline|Inline実行を選ぶ場合は.{0,40}必要|Inline明示を必須|ExecuteWith\(Inline::class\).{0,32}(明示|必須)' develop/spec docs/internal examples/community-board/README.md --glob '!93-implicit-inline-ephemeral-outcome.md'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Website `check`と`build`は直列実行し、共有Blume Dev Runtimeを停止しない。

## Acceptance Criteria

- [x] Ephemeral OutcomeのAttributeなしInlineがCompileされる
- [x] Explicit Inline Compatibilityが維持される
- [x] Canonical `#[Deferred]`、互換Deferred、Custom Strategy、Routeなし、Consoleが拒否される
- [x] Credentialの非永続化、Sensitive、Frontend境界が維持される
- [x] Auth Generatorが`ExecuteWith`なしのStarterを生成する
- [x] Community Board／Auth Consumerが暗黙Inlineで完走する
- [x] Reader-facing Guideが明示Inlineを要求しない
- [x] Stable／Compatibility説明を誤って削除しない
- [x] Required Commandsを全て実行し、Task関連Gateの成功と既存Repository-wide FailureをReportへ記録する
- [x] ReportとSTATEを更新する
- [x] Commitしない

## Completion Report

`develop/orchestration/reports/P20-009B-implicit-inline-ephemeral-outcome.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

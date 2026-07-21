# P17-007A: AOP Class-constant Attributes

Status: Closed - Dependency Gap Accepted by D108

## Goal

Ray.Aop Build-time Proxyが、同一のTransactional Operationに複数のclass-level Attribute `::class`引数があるとcompileできないgapを最小再現し、dependency-nativeな解決を優先して修正する。

`GenerateWeeklyDigest`を公開ドキュメントと同じtyped表記へ戻す。

```php
#[ExecuteWith(Deferred::class)]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class GenerateWeeklyDigest implements Operation
```

## Context

P17-007 Reviewで次を確認した。

- method-level `#[Transactional]`を持つOperationで、class-level `Deferred::class`と`AuthenticatedUserPolicy::class`を併置するとRay.Aop `CompilationFailedException`になる
- normal class、`readonly class`、separate attribute、combined attribute groupのどれでも再現する
- どちらか一方をliteral class-stringへ変えるとcompileする
- P17-007はSecurity Policyをtypedに保ち、metadata-only Deferred Strategyだけをliteralにする回避でAcceptedとした
- locked dependencyは`ray/aop 2.20.0`である

## Source of Truth

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/orchestration/reports/P17-007-deferred-digest-and-progress.md`

## In Scope

- Root test fixtureによる複数typed class-constant Attributeの最小再現
- Ray.Aopが生成するProxy Sourceと元`ParseError`の特定
- Released Ray.Aopの現行版／一つ前の版での再現比較
- 安全なdependency update／downgrade pin、またはBlackOps Build-time Adapterの最小修正
- `GenerateWeeklyDigest`の`Deferred::class`への復帰
- Unit／Integration／Community compile／Digest E2E／Root Quality Gate
- Report、TODO、STATEの同期

## Out of Scope

- Ray.AopのForkをRepositoryへ取り込むこと
- `vendor/**`の直接修正
- Ray.Diへの移行
- Runtime Proxy生成またはProduction Runtime Source Scan
- `Transactional`、`Authorize`、`ExecuteWith`のPublic API変更
- Community Boardの業務仕様／UI変更
- Upstream Issue／PRの外部作成

## Resolution Priority

1. 現在公開済みのRay.Aop Releaseで修正済みなら、対応Versionへ更新する
2. 2.20.0固有のRegressionで、2.19.1がPHP 8.5／BlackOpsの全AOP Contractを満たすなら、理由と回帰Test付きで安全にpinする
3. Released dependencyだけで解決できない場合は、BlackOpsのBuild-time境界で安全に吸収できる最小修正を検討する
4. Attribute Semanticsを失う、Proxy Reflectionを変える、生成Sourceを文字列置換する、またはVendor Forkが必要な場合は実装せずBlockerとして返す

Dependency Versionを変更する場合はRoot `composer.json`／`composer.lock`を同期し、Root、Quickstart、Community Boardのlocked install／compileを確認する。

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `src/Internal/Aop/**`
- `tests/Internal/Aop/**`
- `tests/Fixtures/Aop/**`
- `examples/community-board/app/Feature/Digest/GenerateWeeklyDigest/GenerateWeeklyDigest.php`
- `examples/community-board/tests/Board/BoardBuildArtifactTest.php`（typed fixture確認が必要な場合だけ）
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-007A-aop-class-constant-attributes.md`
- `develop/spec/09-runtime-and-di.md`／`64-phase-13-delivery-plan.md`（公開制約の記述が事実と異なる場合だけ）

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Reproduction Contract

Root fixtureは少なくとも次を持つ。

- `Operation` implementation
- class-level `#[ExecuteWith(Deferred::class)]`
- class-level `#[Authorize(FixturePolicy::class)]`
- method-level `#[Transactional]`
- typed Value／Outcome
- AOP compilerでProxy生成され、Symfony Containerから解決できる

Testは回避前に2.20.0で失敗することを確認し、修正後は次を固定する。

- 両方のAttributeがtyped `::class`のままcompileする
- ProxyがOriginal Operationと`WeavedInterface`の両方として解決される
- Operation MetadataがDeferred StrategyとAuthorization Policyを正しく保持する
- Transactional OperationのFoundation pass-through保証は回帰しない
- readonly／normal class、class-level／method-level Transactional、AfterCommit、stale artifact cleanupを既存Testで回帰させる

## Acceptance Criteria

- [ ] 複数typed class-constant Attribute失敗をRoot fixtureで再現する
- [ ] 生成Proxyの正確なParseErrorと原因をReportへ記録する
- [ ] dependency-nativeな解決を優先し、Vendor Fork／直接修正を行わない
- [ ] Runtime Source ScanとRuntime Proxy生成を追加しない
- [ ] `GenerateWeeklyDigest`が`Deferred::class`と`AuthenticatedUserPolicy::class`のtyped表記でcompileする
- [ ] Root AOP TestとCommunity Board compile／PHPUnit／Digest E2Eが成功する
- [ ] Root Mago／PHPUnit／Deptracが成功する
- [ ] Composer locked installとArtifact／Scope Guardが成功する
- [ ] Report／TODO／STATEが実装と一致する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress
docker compose run --rm app mago format --check src tests examples/community-board/app
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --testsuite unit
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app vendor/bin/phpunit
bash tests/Consumer/community-board-digest.sh

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --exit-code -- examples/quickstart
```

## Completion Report

`develop/orchestration/reports/P17-007A-aop-class-constant-attributes.md`へ次を記録する。

- Summary
- Reproduction and Root Cause
- Dependency Version Comparison
- Resolution and Safety Boundary
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

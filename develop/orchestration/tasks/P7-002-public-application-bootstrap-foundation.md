# P7-002: Public Application Bootstrap Foundation

Status: Ready

## Goal

Installed Applicationが `BlackOps\Internal` を参照せず、Application Root、Process Environment、責務別Config、Operation／Service Provider、Application Commandを一つの検証済みConfiguration Snapshotへ構成できるPublic Application Builder Foundationを実装する。

## In Scope

- `BlackOps\Application\Application::configure()`
- Public `ApplicationBuilder` とBootstrap Exception
- Base Pathの正規化と検証
- Process EnvironmentのCaptureと明示Environment入力
- `config/*.php` の副作用を増やさない読み込みと型検証
- Operation Provider／Service Provider／Application Commandの追加と重複検証
- `create()` による再読込しないConfiguration Snapshotと `Application` の生成
- `#[PublicApi]` とPublic Signature Architecture Guard適合
- Unit Test、Architecture Test、Guide／Internals Documentation

## Out of Scope

- `Application::http()` とPSR-15 Runtime Composition
- `Application::console()` とConsole Kernel
- Framework標準CommandのPublic Composition
- Dotenv PackageのFramework依存または `.env` Fileの読込
- `examples/quickstart/` とStarter Feature
- Docker Compose／FrankenPHP／PostgreSQL Local Runtime
- Generator Command
- Existing Internal Runtime Classの一括Public化

## Relevant Specifications and Decisions

- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/spec/17-core-api.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`

## Files Allowed to Change

- `src/Application/**`
- `src/Internal/Application/**`
- `tests/Application/**`
- `tests/Internal/Application/**`
- `tests/Architecture/PublicApiArchitectureGuard.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/SourceTypeDiscovery.php`
- `deptrac.yaml`
- `docs/guide/application-bootstrap.md`
- `docs/guide/README.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/README.md`
- `develop/orchestration/tasks/P7-002-public-application-bootstrap-foundation.md`
- `develop/orchestration/reports/P7-002-public-application-bootstrap-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Required Public Contract

最低限、次のFluent Shapeを提供する。

```php
Application::configure(string $basePath): ApplicationBuilder

ApplicationBuilder::withEnvironment(?array $variables = null): self
ApplicationBuilder::withConfiguration(?string $directory = null): self
ApplicationBuilder::withOperations(iterable $providers = []): self
ApplicationBuilder::withServices(iterable $providers = []): self
ApplicationBuilder::withCommands(iterable $commands = []): self
ApplicationBuilder::create(): Application
```

詳細な補助型はTask Scope内で追加できるが、次を満たすこと。

- Public Signatureへ `BlackOps\Internal` 型を露出しない
- Raw PSR-11 Containerを取得するMethodを追加しない
- Environment／Config全体をSecret込みでDumpするPublic Methodを追加しない
- `Application` に未実装の `http()`／`console()` Placeholderを追加しない
- Future Runtime CompositionがSnapshotを再利用できる構造にする

## Configuration Rules

- Default Config Directoryは `<basePath>/config`
- Recognized Fileは `app.php`、`database.php`、`operations.php`、`execution.php`、`journal.php`
- Config Fileが存在する場合、配列以外の戻り値を拒否する
- Process Environmentは引数省略時に一度だけCaptureする
- Explicit Provider／CommandはConfig由来の登録へ追加する
- Providerは既存Public ContractのInstanceまたはClass Nameだけを受け入れる
- CommandはSymfony Console CommandのInstanceまたはClass Nameだけを受け入れる
- 同じIdentityの重複は二重登録しない
- 競合するCommand Nameを曖昧な上書きで解決しない
- Error MessageへEnvironment Value、Password、Token、Credentialを含めない

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Public APIはFramework都合のDirectory名をApplicationへ要求しない
- FrameworkからDotenv Libraryへ新規依存しない
- Config読込でGlobal Helper Functionを追加しない
- Config File変更を `Application` 作成後に暗黙再読込しない
- Runtime未実装を隠すNo-op、Dummy Handler、常時成功Placeholderを追加しない
- Existing Internal Typeを監査なしにRename／Move／Public化しない

## Acceptance Criteria

- [ ] `Application::configure()` が検証済みBase PathからBuilderを返す
- [ ] 不正なBase PathをPublic Bootstrap Exceptionで拒否する
- [ ] Environmentを明示入力またはProcessから一度だけCaptureできる
- [ ] Defaultまたは明示Config Directoryから責務別Configを読み込める
- [ ] Configの非配列戻り値と無効なProvider／Commandを安全に拒否する
- [ ] Operation Provider、Service Provider、Application Commandを追加できる
- [ ] 同一Provider／Commandを二重登録せず、Command Name競合を拒否する
- [ ] `create()` が以後再読込しないConfiguration Snapshotを持つ `Application` を返す
- [ ] 全Public Typeに `#[PublicApi]` が付き、Public SignatureにInternal型がない
- [ ] Container Service Locator、Dotenv読込、Runtime Placeholderを追加していない
- [ ] GuideとInternalsがApplication責務、Framework責務、未実装のProcess Boundaryを正しく説明する
- [ ] Required Commandsがすべて成功する
- [ ] Reportと`develop/STATE.md`が更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --testsuite unit
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-002-public-application-bootstrap-foundation.md` に次を記録する。

- Summary
- Public API Shape
- Configuration and Precedence Behavior
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

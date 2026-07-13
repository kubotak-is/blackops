# P7-002 Public Application Bootstrap Foundation Report

## Summary

Installed Applicationが `BlackOps\Internal` を参照せず、Application Root、Environment、責務別Config、Operation Provider、Service Provider、Application Commandを一つの検証済みSnapshotへ構成できるPublic Bootstrap Foundationを実装した。

ConfigとEnvironmentは明示した時点で一度だけCaptureし、`create()` では再読込しない。同一Class Identityは二重登録せず、異なるCommand ClassによるCommand Name競合を安全に拒否する。HTTP／Console Runtime、Container Getter、Dotenv読込は追加していない。

## Public API Shape

```php
Application::configure(string $basePath): ApplicationBuilder

ApplicationBuilder::withEnvironment(?array $variables = null): self
ApplicationBuilder::withConfiguration(?string $directory = null): self
ApplicationBuilder::withOperations(iterable $providers = []): self
ApplicationBuilder::withServices(iterable $providers = []): self
ApplicationBuilder::withCommands(iterable $commands = []): self
ApplicationBuilder::create(): Application
```

`Application`、`ApplicationBuilder`、`ApplicationBootstrapException` はすべて `#[PublicApi]` を持つ。Public Method SignatureはInternal型を露出しない。

`Application` と `ApplicationBuilder` のconstructorはprivateであり、利用者は `Application::configure()` 以外からBuilderを直接生成したり、Internal Snapshot Factoryを注入したりできない。

## Configuration and Precedence Behavior

- Base Pathは既存Directoryだけを受け入れ、`realpath()`で正規化する。
- `withEnvironment()` の引数省略時はProcess Environmentを呼出時に一度Captureする。
- 既定Config Directoryは `<basePath>/config`。存在しない既定Directoryは空Config、存在しない明示DirectoryはErrorとする。
- `app.php`、`database.php`、`operations.php`、`execution.php`、`journal.php` だけを読み、各Fileの非配列戻り値を拒否する。
- `operations.php` のProviderと `app.php` のService／CommandをConfig登録として扱う。
- 明示登録はConfig登録の後へ追加する。同一Class Identityは先行登録を保持する。
- Provider／CommandのClass Nameは引数なしで生成可能であることを検証する。
- Command Nameが別Classと競合する場合は曖昧に上書きせずErrorとする。
- ErrorへEnvironment ValueやConfig Valueを含めない。

## Changed Files

- `src/Application/**`
- `src/Internal/Application/**`
- `tests/Application/**`
- `tests/Internal/Application/**`
- `deptrac.yaml`
- `docs/guide/application-bootstrap.md`
- `docs/guide/README.md`
- `docs/internal/application-bootstrap.md`
- `docs/internal/README.md`
- `develop/orchestration/tasks/P7-002-public-application-bootstrap-foundation.md`
- `develop/orchestration/reports/P7-002-public-application-bootstrap-foundation.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Public `Application` はInternal Snapshotを保持するが、SnapshotやRaw Config／EnvironmentをPublic Methodから公開しない。
- Public型のprivate constructor呼出はPublic型内部のprivate生成Bridgeへ閉じ、Factory InjectionをPublic APIにしない。
- Provider／CommandのClass Nameは将来のRuntime Compositionで生成可能でなければならないため、Bootstrap時に引数なし生成可能性を検証する。
- Operation登録は `operations.php` がListを直接返す形と `providers` Keyを持つ形の両方を受け入れる。
- Runtime Compositionは後続Taskの責務であり、このTaskでは起動成功を装うPlaceholderを設けない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit --testsuite unit
Result: OK (602 tests, 1948 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (602 tests, 1948 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 329 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1349 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

Focused Application／Architecture Testも `OK (20 tests, 61 assertions)` を確認した。

## Acceptance Criteria

- [x] 検証済みBase PathからPublic Builderを生成する
- [x] Explicit／Process Environmentを一度だけCaptureする
- [x] Recognized Configを安全に一度だけ読み込む
- [x] Provider／Commandを検証、追加、Identity重複排除する
- [x] Command Name競合を拒否する
- [x] ImmutableなInternal Configuration Snapshotを持つApplicationを生成する
- [x] Public API MarkerとPublic Signature Architecture Guardへ適合する
- [x] Secret ValueをErrorへ含めない
- [x] Container Getter、Dotenv、HTTP／Console Placeholderを追加しない
- [x] Guide／Internalsを更新する
- [x] 全Required Commandが成功する

## Remaining Issues

Blockerはない。HTTP／Console Runtime CompositionとFramework標準CommandのPublic CompositionはTask Scope外として未実装である。

## Suggested Next Action

P7-003でPublic HTTP Runtime Compositionを実装し、Accepted Configuration SnapshotからCompile済みArtifact、Inline／Deferred Route、PostgreSQL Runtime Dependencyを構成する。

## Orchestrator Review

初回Reviewで、`ApplicationBuilder` のPublic constructorがInternal Snapshot FactoryをClosureとして受け取り、Required Contract外のFactory InjectionをPublic APIへ露出している点を指摘した。

修正後は `Application` と `ApplicationBuilder` のconstructorをprivateとし、Public surfaceを `Application::configure()` と指定Fluent Methodだけに限定した。Internal Snapshotはprivate Property／Method Signatureだけに閉じ、Container Getter、HTTP／Console Placeholder、Dotenv Loaderがないことを確認した。

Orchestratorは次を再実行し、Worker Reportと同じ成功結果を確認した。

```text
Focused Application／Architecture Test: OK (20 tests, 61 assertions).
Mago format: All files are already formatted.
Mago lint: No issues found.
Mago analyze: No issues found.
Full PHPUnit: OK (602 tests, 1948 assertions).
Deptrac: 329 files / Violations 0 / Uncovered 0 / Errors 0.
Composer validate: valid.
Management ID check: No matches.
git diff --check: No output.
```

Scope、Public API、Configuration Capture、Registration Validation、Secret非露出、Documentation、TestをAcceptedと判定した。

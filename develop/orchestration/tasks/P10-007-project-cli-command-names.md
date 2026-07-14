# P10-007: Project CLI Command Names

Status: Accepted

## Goal

Project Root EntrypointとFramework Command Namespaceの重複を解消し、公式CLIを`php blackops build:compile`のような短いCanonical名へ統一する。Stable `1.0.0`の旧Commandは互換Aliasとして維持する。

## In Scope

- Public Application Console Kernelの9 Framework CommandをPrefixなしCanonical名へ変更
- 旧`blackops:*`名の互換Alias
- Canonical名とAliasのApplication Command競合防止
- README、Guide、Internal Documentation、Quickstart、Compose、Setup、Consumer Testの同期
- Website Content Test／Check／Buildの同期
- Full PHP Quality SuiteとConsumer E2E

## Out of Scope

- `make:operation`／`make:migration`の改名
- Project Root `blackops` Entrypointの再配置
- Installed Applicationへ登録しない低レベルCompiler Commandの公開範囲変更
- Stable `1.0.0` Tag／配布物の変更
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/083-project-root-blackops-entrypoint.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/55-project-generators-and-application-migrations.md`

## Files Allowed to Change

- `src/Internal/Application/**`
- `src/Internal/Console/**`
- `tests/Internal/Application/**`
- `tests/Integration/**`
- `tests/Architecture/**`
- `tests/Consumer/**`
- `examples/quickstart/**`
- `README.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/P10-007-project-cli-command-names.md`
- `develop/orchestration/reports/P10-007-project-cli-command-names.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Canonical名は`build:compile`、`operation:list`、`database:status`、`database:migrate`、`worker:run`、`retention:plan`、`retention:purge`、`scheduler:run`、`scheduler:daemon`
- 従来の同じ9個の`blackops:*`名は互換Aliasとして実行可能にする
- `make:operation`と`make:migration`は変更しない
- 新しい利用者向けCommand例では`blackops:*` Aliasを案内しない
- Historical Decision／Task／Report内の旧表記は履歴として書き換えない
- Command Rename以外のRuntime／Lifecycle挙動を変更しない

## Acceptance Criteria

- [x] `php blackops list`がPrefixなしCanonical名をFramework Commandとして表示する
- [x] 9個のCanonical Commandが従来と同じ処理を実行する
- [x] 9個の旧`blackops:*`名が互換Aliasとして実行できる
- [x] Canonical名とAliasへApplication Commandが競合できない
- [x] `make:operation`と`make:migration`が不変である
- [x] README、全Public Guide、Quickstart、Compose、Setup、Consumer TestがCanonical名を使用する
- [x] Active利用者向けSourceに`php blackops blackops:`が残らない
- [x] Website Unit／Check／Buildが成功する
- [x] Composer／Mago／PHPUnit／Deptracが成功する
- [x] Quickstart／Worker Mode／Skeleton／Framework Update Consumer Testが成功する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'php blackops blackops:' README.md docs/guide examples tests/Consumer
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-007-project-cli-command-names.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

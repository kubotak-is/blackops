# P10-005C: Project Root CLI Entrypoint

Status: Ready

## Goal

次回Skeleton ReleaseのProject CLIを`bin/blackops`からProject Rootの`blackops`へ移し、公式実行形式を`php blackops`へ統一する。

## In Scope

- `examples/quickstart/blackops`へのEntrypoint移動
- `bin/blackops`削除
- Compose、Setup、Quickstart READMEのCommand更新
- Architecture／Consumer／Publication／Framework Update Test更新
- Stable `1.0.0`の既存Entrypoint互換性説明
- Internal Installed Status更新
- Report／STATE更新

## Out of Scope

- Framework Console Command実装変更
- Generator Stub変更
- Public Website Content変更
- Stable `1.0.0`再発行
- HTTP Configuration Lifecycle変更

## Relevant Specifications and Decisions

- `develop/decisions/083-project-root-blackops-entrypoint.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/55-project-generators-and-application-migrations.md`

## Files Allowed to Change

- `examples/quickstart/**`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/**`
- `docs/internal/installed-application-status.md`
- `develop/orchestration/reports/P10-005C-project-root-cli-entrypoint.md`
- `develop/STATE.md`

## Constraints

- 原則GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答`Y`により、本TaskでModel／Profile Metadataを確認できない現在利用可能なWorkerを使う例外を承認済み
- Root `blackops`はApplication所有の薄いEntrypointに保つ
- Framework Command／StubをSkeletonへ複製しない
- 新Skeletonに`bin/blackops` Aliasを残さない
- Stable `1.0.0`のRemote Packageを変更しない
- Framework Update前後でRoot Entrypointと既存生成Sourceが不変であることを検証する

## Acceptance Criteria

- [ ] `examples/quickstart/blackops`がExecutableでPublic APIだけを使う
- [ ] `examples/quickstart/bin/blackops`が存在しない
- [ ] Compose、Setup、READMEが`php blackops`を使う
- [ ] Distribution SmokeがRoot `blackops`を必須Fileとして検証する
- [ ] Create-project／Quickstart／Framework Update Consumer E2EがRoot Entrypointで成功する
- [ ] Stable `1.0.0`の既存`bin/blackops`がFramework Update可能なContractを壊さない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
! rg -n 'bin/blackops' examples/quickstart tests/Consumer docs/internal/installed-application-status.md
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005C-project-root-cli-entrypoint.md`へSummary、Changed Files、Compatibility Boundary、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

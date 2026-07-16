# P11-001: Release Surface Reset

Status: Accepted

## Goal

Experimental `1.1.0` Releaseに向け、Project CLIから旧`blackops:*`互換Aliasを削除し、`1.0.0`からのBreaking Surfaceを後続のCHANGELOG／UPGRADEへ使える形で監査する。

## In Scope

- Application Console KernelのLegacy Command Alias登録と予約を削除
- Legacy Command Constant、Alias Test、Integration Testの整理
- PrefixなしCanonical CommandとApplication Command競合境界の検証
- Project Root `blackops`とSkeleton内の旧Entrypoint不在の検証
- `1.0.0`からのPublic API、Entrypoint、Command、Database Metadata、Configuration、HTTP Surface差分監査
- Consumer TestまたはArchitecture TestによるRelease Surface固定
- Task ReportとSTATE更新

## Out of Scope

- Skeleton Constraint `^1.1`変更
- CHANGELOG／UPGRADE／利用者向けRelease Documentation
- 新Feature
- `1.1.0` Tag／Packagist／GitHub Release
- Framework Public APIの削除。ただし監査で必要性が見つかった場合はBlockerとして返す

## Relevant Specifications and Decisions

- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/*Command.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/**`
- `docs/internal/project-generators.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/orchestration/tasks/P11-001-release-surface-reset.md`
- `develop/orchestration/reports/P11-001-release-surface-reset.md`
- `develop/STATE.md`

範囲外Fileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。

## Constraints

- Production CodeはGPT-5.6 Luna High workerが変更し、Review前にCommitしない
- Internal Compiler Commandの`blackops:*`名をProject CLI Aliasと誤認して変更しない
- PrefixなしCanonical Command名は変更しない
- Applicationが旧`blackops:*`名をCustom Commandとして使用することは許可する
- `1.0.0`との互換性は成功条件ではない。差分を正確に記録する
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [x] Project CLI CommandがLegacy Aliasを持たない
- [x] Legacy Alias名がFramework予約名として拒否されない
- [x] PrefixなしCanonical Command名、または同名をAliasに持つApplication Commandは競合時にFail-fastする
- [x] Canonical Project CLI CommandのIntegration Testが成功する
- [x] SkeletonにProject Root `blackops`があり、`bin/blackops`がない
- [x] `1.0.0`からのBreaking／Additive SurfaceがReportへ分類される
- [x] Required Quality Commandsが成功する
- [x] ReportとSTATEが更新される

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
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P11-001-release-surface-reset.md`へ次を記録する。

- Summary
- Changed Files
- Release Surface Audit
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

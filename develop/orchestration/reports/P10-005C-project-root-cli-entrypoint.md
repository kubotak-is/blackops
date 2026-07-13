# P10-005C: Project Root CLI Entrypoint Report

## Summary

次回Skeleton ReleaseのProject所有CLIをProject Root `blackops`へ移し、Quickstart、Compose、Setup、Consumer Test、Publication Guardの公式実行形式を`php blackops`へ統一した。Root EntrypointはComposer Autoloaderと`bootstrap/app.php`を読み、Public Console Kernelの終了Codeを返すだけの薄いScriptである。

Create-project、Quickstart Runtime、Framework Update、Publication Working TreeのConsumer SmokeがRoot Entrypointで成功した。Framework Update SmokeはStable `1.0.0`の従来配置を一時Fixtureとして再現し、Framework Package更新前後でRoot／従来Entrypointと既存生成Sourceが不変かつ実行可能であることも検証する。

## Changed Files

- `examples/quickstart/blackops`
- `examples/quickstart/bin/blackops`（削除）
- `examples/quickstart/bin/setup`
- `examples/quickstart/compose.yaml`
- `examples/quickstart/README.md`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-publication.sh`
- `docs/internal/installed-application-status.md`
- `develop/orchestration/reports/P10-005C-project-root-cli-entrypoint.md`
- `develop/STATE.md`

## Decisions and Assumptions

- D083に従い、新SkeletonにはRoot `blackops`だけを配布し、従来Aliasは残さない。
- EntrypointはApplication所有、Command／Generator StubはFramework Package所有のままとし、Skeletonへ実装を複製しない。
- Local create-project smokeは公開済みStable `1.0.0`と同VersionではPackagist Packageが選択されるため、未公開の`1.0.1`をCurrent Skeleton Fixture Versionとして使い、Initial `create-project` Repositoryを明示する。Framework Dependencyは従来どおりLocal `1.0.0` Fixtureへ固定する。
- Publication Scriptの`--dry-run`は未コミットWorking Treeから次回`1.0.1`相当のDistributionを作り、Root File Allowlist、Executable、Composer Metadata、Source Cleanlinessを検証する。既存のVersion／Source Ref形式はCommitted Subtree Splitの決定性とTagを従来どおり検証する。
- D083はEntrypoint配置に限り以前のDecision／Spec表記を上書きする。Orchestrator Reviewで`develop/spec/43-installed-application-layout-and-bootstrap.md`のProject TreeもRoot `blackops`へ同期した。

## Compatibility Boundary

- GitHub／PackagistのStable `1.0.0`配布物は変更も再発行もしていない。
- Stable `1.0.0`で作成済みApplicationの従来Project所有CLIは、Framework `1.0.0`相当から`1.1.0`相当への更新前後で同じFile Hashを維持し、更新後のFramework Commandを実行できる。
- 次回SkeletonのRoot EntrypointもFramework Update前後で同じFile Hashを維持する。
- Update前に生成したOperation／Migrationは不変で、Update後に新規生成するSourceだけが更新済みFramework Command／Stubを利用する。
- Framework PackageはProject Entrypoint Pathに依存しない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php
Result: OK (18 tests, 168 assertions).

bash tests/Consumer/quickstart-e2e.sh
Result: Root CLIでGenerator、Build、Migration、HTTP、Worker Retry、Outcome、Retentionを完走し、Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: Local Current Skeleton 1.0.1の通常／--no-scripts Copy Install、Root CLI Generator／Build、Setup再実行、Source／Docker State不変を検証し、Skeleton create-project smoke passed.

bash tests/Consumer/framework-update-generators.sh
Result: Root／Stable 1.0.0従来Entrypoint、既存生成Source、非Framework Dependencyの不変性と更新後Command／Stubを検証し、Framework update generator smoke passed.

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: version=1.0.1、split=working-treeでRoot Distribution GuardとComposer Validationが成功。

! rg -n 'bin/blackops' examples/quickstart tests/Consumer docs/internal/installed-application-status.md
Result: No matches.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: Success.
```

create-project smokeの初回確認では公開Stable `1.0.0`が選択され、新Root Entrypoint不在を正しく検出した。Current Fixtureを`1.0.1`へ分離した後もInitial Package解決には明示Repositoryが必要であることを確認し、最終形ではLocal Path Packageが`Mirroring from /smoke/package`として選択されたうえで成功した。

## Acceptance Criteria

- [x] `examples/quickstart/blackops`がExecutableでPublic APIだけを使う。
- [x] 新Skeletonに従来CLI Aliasが存在しない。
- [x] Compose、Setup、READMEが`php blackops`を使う。
- [x] Distribution SmokeがRoot `blackops`を必須Executableとして検証する。
- [x] Create-project／Quickstart／Framework Update Consumer E2EがRoot Entrypointで成功する。
- [x] Stable `1.0.0`の従来Project所有CLIがFramework Update可能なContractを維持する。

## Remaining Issues

- Stable `1.0.0` Remote Packageは意図どおり従来配置のままである。Root Entrypointは次回Skeleton ReleaseまでRemote `composer create-project`の標準にはならない。

## Suggested Next Action

OrchestratorがAllowed File差分とConsumer Fixture境界をReviewする。Accept後にTask単位でCommitし、P10-005E1へ進む。

## Orchestrator Review

OrchestratorはEntrypointのPublic API境界、Executable mode、旧Alias不在、Compose／Setup／Consumer参照、Stable従来Entrypoint Fixture、Local Current Skeleton `1.0.1`分離、Publication Working Tree GuardをReviewした。

`develop/spec/43-installed-application-layout-and-bootstrap.md`のProject Treeに残っていた旧配置をRoot `blackops`へ同期した。Shell Syntax、Legacy Path／PHP Management ID Guard、`git diff --check`、Composer Strict Validation 2本、Mago Format、Focused PHPUnit 18 tests／168 assertions、Skeleton Publication `--dry-run`を独立再実行し、すべて成功した。Worker実行済みQuickstart E2E、Create-project、Framework Updateの結果と合わせ、P10-005CをAcceptedとする。

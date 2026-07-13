# P9-002: Operation Generator Report

Status: Accepted

## Summary

Installed ApplicationのProject所有`bin/blackops`から実行できる`make:operation <Feature>/<Action> --type=<operation.type>`を実装した。Framework Package所有の3 StubからTyped Self-handled Operation、Value、Outcomeを生成し、生成直後のApplication Build成功まで統合Testで確認した。

Generatorは全入力、Project Relative Path、既存ancestor、全Target衝突をWrite前に検証する。Temporary Write後に既存Targetを置換しない形で公開し、失敗時は今回作成したTemporary File、Target、新規DirectoryだけをRollbackする。Application Root外を指す既存ancestor symlinkと、Directoryではないancestorも拒否する。

Orchestrator Review後、Filesystem Transaction内のPHP warningを捕捉し、Absolute Application／Temporary Pathを出力せず既存のgeneric／Project-relative `InvalidArgumentException`へ正規化した。Preflight後に別actorがTargetを作るPublish Raceでは、そのTargetを上書き／削除せず、先に公開した今回Targetと全Temporary FileをRollbackする。Stub存在確認後にFileが消えるRaceもwarningとFramework Stub Absolute Pathを公開せずgeneric Errorへ変換する。

## Changed Files

- `resources/stubs/operation.php.stub`
- `resources/stubs/operation-value.php.stub`
- `resources/stubs/operation-outcome.php.stub`
- `src/Internal/Console/MakeOperationCommand.php`
- `src/Internal/Generator/OperationGenerator.php`
- `src/Internal/Generator/OperationGeneratorInput.php`
- `src/Internal/Generator/ProjectFileWriter.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `tests/Internal/Console/MakeOperationCommandTest.php`
- `tests/Internal/Generator/OperationGeneratorTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/README.md`
- `docs/guide/project-generators.md`
- `docs/internals/README.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/project-generators.md`
- `examples/quickstart/README.md`
- `develop/orchestration/reports/P9-002-operation-generator.md`
- `develop/orchestration/tasks/P9-002-operation-generator.md`
- `develop/orchestration/tasks/P9-003-application-migration-generator.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/STATE.md`

## Decisions and Assumptions

- 生成Class名はActionを正本として`<Action>`、`<Action>Value`、`<Action>Outcome`とした。
- Feature／ActionはASCII PascalCase PHP Class Identifierとし、PHP KeywordおよびReserved Type Nameを拒否する。
- Operation Type検証はPublic `OperationType`と同じContractを再利用する。
- StubはFramework Rootの`resources/stubs/`だけに置き、Quickstartへ複製しない。
- Multi-file公開には同一DirectoryのTemporary FileとHard Linkを使い、既存Targetを置換しない。Filesystem Transaction中のPHP warningは外へ出さず、Framework Errorへ正規化する。
- Targetのdeepest existing ancestorを`realpath()`で解決し、Application Root外へ向くsymlinkをWrite前に拒否する。Directory作成後にも同じ境界を再検証する。
- Framework Stub Absolute PathはErrorへ含めず、成功と衝突はProject Relative Pathで報告する。
- GeneratorはFile生成だけを行い、Source Discovery、Build、Composer、Database、Networkを呼ばない。

## Commands and Results

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Generator tests/Internal/Console/MakeOperationCommandTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (36 tests, 218 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: Orchestrator final rerun OK (746 tests, 2469 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Orchestrator final rerun: 365 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1558 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Orchestrator final rerun completed successfully with final success line and Exit 0.

! rg -n 'Accepts|Returns|OperationResult|@implements' resources/stubs
Result: No matches; negated command exited 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches; negated command exited 0.

git diff --check
Result: No output.
```

Development中の最初のFocused RunではManifest Testの読取Shape不一致を1件検出し、Public Load APIを使うAssertionへ修正した。Review補強後、WorkerのFocused Suiteに加え、OrchestratorがFull Suite、Deptrac、Quickstart Consumer E2Eを再実行して受け入れた。Quickstart Consumer E2Eの最初のWorker Runはclaim 0後に最終成功行なしで終了したため成功扱いにせず再実行し、その後のWorker／Orchestrator Runではclaim処理、最終成功行、Exit 0を確認した。

## Acceptance Criteria

- [x] `make:operation Welcome/ShowWelcome --type=welcome.show`が仕様どおり3 Fileを生成する
- [x] 生成ClassがTyped Self-handled Contractを満たしApplication Buildに成功する
- [x] Route／Deferred／Context／Legacy Metadataを生成しない
- [x] 不正Segment／Type／Traversal／追加Segmentを拒否する
- [x] Targetの一つでも存在する場合は全Target不変で失敗する
- [x] Write／Publish失敗時に今回の部分生成を残さない
- [x] Publish Raceで別actorのTargetを上書き／削除せず、今回の公開済みTargetとTemporary FileをRollbackする
- [x] Application Root外を指す既存ancestor symlinkを拒否する
- [x] Filesystem／Stub Read RaceのwarningとAbsolute Pathを公開しない
- [x] `list`／`help`がSource ScanやRuntime設定なしで成功する
- [x] Application Commandが`make:operation`を上書きできない
- [x] Guide／Internals／Quickstart READMEがCommand Contractと一致する

## Remaining Issues

P9-002 Scope内の既知問題はない。`make:migration`とApplication Migration RuntimeはP9-003のScopeである。P9-003開始前にWorker Model／Profile境界のUser確認が必要である。

## Suggested Next Action

P9-002をCommit／Pushする。P9-003で現在利用可能なWorkerを使用してよいかUser確認後、Application Migration Generator／Runtime実装を開始する。

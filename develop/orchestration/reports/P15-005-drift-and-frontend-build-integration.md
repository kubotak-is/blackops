# P15-005 Drift and Frontend Build Integration Report

Status: Accepted

## Summary

Project ConsoleへPrefixなしの`frontend:check`をLazy登録した。CommandはApplication Build Configuration／Expected Build ID、Frontend Contract Artifact／Build ID、Frontend Output Configuration、Expected Generated Tree、Existing Outputの順で解決する。BuildまたはGenerateを暗黙実行せず、FreshはExit 0、Missing／DriftはExit 1、InvalidはExit 2として固定したstdout／stderrへ分離する。

Internal `FrontendTreeChecker`はExpected TreeとExisting Regular FileのPath Set、Bytes、余剰FileをRead-only比較する。空Directoryは無視し、Nested Symlinkを追跡せずDriftとする。Status／Directory Listing／File Readの失敗、Unsafe Entry、Cycle、Inspection中のFile／Directory Identity変化はInvalid Inspectionとする。

`tests/Frontend/`へWebsiteから独立したTypeScript 6.0.3 Toolchainと最小Application Fixtureを追加した。FixtureはInline OutcomeとDeferred OperationをBuild-time Discoveryし、`build:compile -> frontend:generate -> frontend:check`を実行する。DOMなしStrict ES2022 ESM、Operation固有Result Narrowing、Node Injected Fetch Runtime、Named ESM／Sibling非Import／Import-time HTTP Side Effect不在を検証する。

GitHub Actionsへ独立Frontend Jobを追加し、Pinned Node／pnpm、Root Composer、Frozen Frontend Install、Canonical CLI Chain、TypeScript／Runtime Test、Tracking／Sensitive Guard、Frontend／Dockerの独立Always Cleanupを構成した。Framework Update SmokeはApplication所有Frontend Config／Sourceを更新前後でbyte一致させ、更新後のProject Root `blackops`から新しい`frontend:check`へ到達する。

## Changed Files

### Production

- `src/Internal/Console/FrontendCheckCommand.php`
- `src/Internal/Frontend/Generation/FrontendTreeCheckFilesystem.php`
- `src/Internal/Frontend/Generation/NativeFrontendTreeCheckFilesystem.php`
- `src/Internal/Frontend/Generation/FrontendTreeCheckInspectionException.php`
- `src/Internal/Frontend/Generation/FrontendTreeCheckState.php`
- `src/Internal/Frontend/Generation/FrontendTreeChecker.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`

### Tests and Fixtures

- `tests/Internal/Console/FrontendCheckCommandTest.php`
- `tests/Internal/Frontend/Generation/FrontendTreeCheckerTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Frontend/package.json`
- `tests/Frontend/pnpm-lock.yaml`
- `tests/Frontend/tsconfig.json`
- `tests/Frontend/tsconfig.runtime.json`
- `tests/Frontend/types/narrowing.ts`
- `tests/Frontend/scripts/*.mjs`
- `tests/Frontend/fixture/blackops`
- `tests/Frontend/fixture/bootstrap/app.php`
- `tests/Frontend/fixture/config/*.php`
- `tests/Frontend/fixture/app/Feature/**/*.php`
- `tests/Consumer/framework-update-generators.sh`
- `.gitignore`

### CI, Documentation, and Orchestration

- `.github/workflows/ci.yml`
- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P15-005-drift-and-frontend-build-integration.md`

Quickstart／Skeleton／Guide／Website、Public PHP API、Attribute、Migration、Database Schemaは変更していない。

## Decisions and Assumptions

- Check InputはTask ContractどおりBuild Configuration／Expected ID、Artifact／Build ID、Frontend Output、Expected Tree、Inspectionの順で分類する。Frontend Outputも不正な複合Failureでは、先行するArtifact Failureを返す。
- Artifact LoadはMissing、Unsupported Schema、Malformed PHP／Payload、Build ID不一致を同じ安全なInvalid Artifactへ閉じる。Exception Class／Message／Previous／Absolute Path／Payloadを出力しない。
- Expected Tree生成の`InvalidArgumentException`だけをInvalid Generated Contractへ変換する。Inspection外のProgramming ErrorをFresh／Driftへ変換しない。
- Actual Treeは`lstat`のFile Typeで分類する。SymlinkはFile／Directoryとして開かず、Expected位置でも余剰位置でもDriftにする。
- Native Filesystem WarningはInspection Adapter内で一時的に`ErrorException`へ変換し、`finally`で既存Error Handlerを復元する。Boundary外へWarning Detailを表示しない。
- Regular FileはRead前後のdev／inode／mode／size／mtime／ctime、DirectoryはList前後のdev／inode／mode／mtime／ctimeを一致必須とし、TOCTOU差替えをInvalid Inspectionにする。
- Missing OutputはDirectory自体が存在しない場合だけである。既存Tree内の不足、Bytes差、余剰Regular File、Nested SymlinkはDriftである。
- TypeScript ESM Source CheckはTask指定の`strict`、ES2022、Bundler Resolution、DOM Libなしを正本とする。RuntimeだけCommonJSへ`.build/runtime`にEmitし、Source ESM Checkと分離する。
- Runtime／Build／Generated TreeはRepositoryへ固定しない。`pnpm run clean`はArtifactを削除した後、次のCanonical Build用に空`var/build` Directoryだけ再準備する。

## Check State, Exit, and Output Matrix

| State | Exit | stdout | stderr |
| --- | ---: | --- | --- |
| Fresh | 0 | `Frontend generated tree is fresh in <relative-output>.` | empty |
| Missing | 1 | `Frontend generated tree is missing in <relative-output>.` | empty |
| Drift | 1 | `Frontend generated tree has drift in <relative-output>.` | empty |
| Invalid Configuration | 2 | empty | `Frontend check failed: configuration is invalid.` |
| Invalid Artifact／Build ID | 2 | empty | `Frontend check failed: contract artifact is invalid.` |
| Invalid Generated Contract | 2 | empty | `Frontend check failed: generated contract is invalid.` |
| Invalid Inspection | 2 | empty | `Frontend check failed: generated tree could not be inspected.` |

全Messageは末尾Newlineを持つ。実Consoleの`ConsoleOutputInterface`ではInvalidをError Outputへ書き、Unit TestのSplit Outputでstdout／stderrを別々に検証した。表示PathはApplication RootからのSlash区切りRelative Pathだけである。

## Read-only, Symlink, and I/O Failure Evidence

- Fresh Check前後で全Directory／Fileのinode、mode、mtime、bytes Snapshotが一致する。
- Missing Output、Expected File不足、Bytes差、余剰Fileを個別に検証した。
- Expected File位置のSymlinkと余剰Directory SymlinkがDriftになり、Link Targetの内容を変更しない。
- Empty Nested DirectoryがFreshnessへ影響しない。
- Injected status／list／read FailureがInvalid Inspectionとなる。
- Regular FileのRead前後Identity差替えがInvalid Inspectionとなる。
- Unreadable Generated FileをCLIがExit 2／固定stderrへ変換し、Absolute Pathを出さない。
- Missing／Malformed／Stale Artifact、Invalid Generated Contract、Drift Checkの全CaseでApplication-owned Existing Fileを保持する。

## Frontend Fixture and Type Narrowing Matrix

| Fixture | Contract | Type Evidence |
| --- | --- | --- |
| `CreateOrder` | Inline Outcome、Path integer、Query boolean／nullable string、Header string、Body string／float／Sensitive string、Validation、4 Scalar Outcome | `ok=true`で`kind=completed`／`status=200`／`OrderCreated`だけへNarrowing。Validationは422、Transportはstatus nullへNarrowing |
| `GenerateReport` | Deferred、Body string／Sensitive string、Validation、Outcome Contract | `ok=true`で`kind=accepted`／`status=202`／Deferred AcknowledgementだけへNarrowing |

TypeScriptは`types.ts`、`client.ts`、Operation Modules、`types/narrowing.ts`を同じDOMなしStrict ESM ProgramとしてCompileする。Inline成功へ202／204、Deferred成功へ200／204を混ぜない。

## Node Runtime Cases

- Operation Module Import時にGlobal Fetchが呼ばれない。
- Injected FetchへBase URL、Percent-encoded Path／Query、POST Method、Operation／Application Header、JSON Body、`Content-Type`を渡す。
- Inline 200 OutcomeをTyped Completed ResultへDecodeする。
- Deferred 202をTyped Accepted ResultへDecodeする。
- Validation 422をOperation固有Field付きResultへDecodeする。
- Fetch ThrowをRaw Error Detailなしの`network_error`へ変換する。
- Operation Object、Success Result、Outcome、Deferred Resultがfrozenである。
- Operation ModuleはNamed ESM Exportだけを持ち、Default Export／Sibling Operation Importを持たず、共通`client.ts`／`types.ts`だけへ依存する。

## CI Build Chain

Frontend JobはWebsite／Quality Jobから独立し、次を実行する。

1. Credential非保持Checkout
2. Host UID／GID設定
3. Repository `mise.toml`によるNode 24.18.0／pnpm 11.12.0 InstallとVersion確認
4. Development Image BuildとRoot Composer Install
5. `tests/Frontend` Frozen Lockfile Install
6. Fixture `php blackops build:compile`
7. Fixture `php blackops frontend:generate`
8. Fixture `php blackops frontend:check`
9. Strict ESM／Narrowing／Node Runtime／Module Shape Test
10. Generated Tree／Build Artifact／Runtime EmitのTracking GuardとGenerated Sensitive Guard
11. Frontend FixtureとDocker Resourceを別々のAlways StepでCleanup

## Framework Update Preservation Evidence

一時ConsumerへApplication所有`config/frontend.php`と`resources/js/application/client.ts`を追加し、Framework `1.0.0`相当からCurrent `1.1.0`相当へのComposer Update前に他のApplication Sourceと同じSHA-256 Setへ含めた。Update後の`sha256sum --check`が全Fileで成功した。

Current Framework Packageは新しいCommand／Factory／Checker／Filesystem Adapterを含む。Update後に既存Project Root `blackops`からBuild、Frontend Generate、Frontend Checkを実行し、`Frontend generated tree is fresh in resources/js/blackops.`を確認した。Framework UpdateはApplication所有Config／SourceをVendorから上書きしていない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Console/FrontendGenerateCommandTest.php \
  tests/Internal/Console/FrontendCheckCommandTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php
Result: OK (57 tests, 416 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1332 tests, 5100 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2273 / Warnings 0 / Errors 0。

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
Result: Frozen Install、Build、5-file Generate、Fresh Check、Strict ESM、Narrowing、Injected Fetch Runtime、2 Operation Module Shapeが全成功。

bash tests/Consumer/framework-update-generators.sh
Result: Framework Update Generator Smoke成功。Frontend Config／Source保持と更新後Check到達を確認。

Management Comment ID、Generated Sensitive／Forbidden Runtime Surface、Generated Artifact Tracking、bash syntax、git diff --check Guard
Result: 成功。

mise exec -- pnpm --dir tests/Frontend run clean
Result: Generated Tree、Build Artifact、Runtime Emitを削除。Empty Build Directoryだけ再準備。
```

初回Frontend Type CheckはTask Contractにない`exactOptionalPropertyTypes`／`noUncheckedIndexedAccess`まで有効にし、Generated RuntimeのOptional Property表現を拒否した。Fixtureを確定Contractの`strict`へ補正し成功した。Runtime CommonJS CompileはTypeScript 6のNode10 Resolution非推奨診断を`ignoreDeprecations: 6.0`で限定抑制した。初回MagoはCheckerのClosure Union型を解析できなかったため、明示Filesystem Interface／Native Adapterへ分離し、lint／analyzeを成功させた。

## Acceptance Criteria

- [x] `frontend:check`がCanonical PrefixなしCommandとしてLazy登録される
- [x] Fresh 0、Missing／Drift 1、Invalid 2を厳密に返す
- [x] stdout／stderrとSafe Messageが固定Contractに一致する
- [x] Manifest Missing／Stale／Invalid時にBuild／Generateを暗黙実行しない
- [x] Expected Path／Bytes／余剰Fileを比較し、Nested Symlinkを追跡しない
- [x] Drift CheckがExisting TreeとApplication Source／Configを変更しない
- [x] DOMなしStrict TypeScript ESM CompileとResult Narrowingが成功する
- [x] Node／SSR Injected Fetch Runtime Testが成功する
- [x] Operation ModuleのNamed ESM／Sibling非Import／No Import-time Side Effectを確認する
- [x] GitHub ActionsがBuild／Generate／Check／TypeScript／Runtime Chainを実行する
- [x] Generated Tree、Temporary Tree、Build Artifact、Runtime EmitをCommitしない
- [x] Framework UpdateがApplication-owned Frontend Config／Sourceを保持する
- [x] Quickstart／Skeleton／Guide／Website、Public PHP API、Migration、Database Schemaを変更しない
- [x] Required PHP／Frontend／Consumer Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

P15-005を妨げるBlockerはない。

Quickstart／SkeletonへのFrontend Config／Package Script、Guide／Websiteの利用者Journey、Generated Objectから実HTTP Serverへ接続するConsumer E2EはP15-006で扱う。Deferred Status／Outcome取得とPollingはPhase 16まで追加しない。Documentation WebsiteのPublication／Deployは実行していない。

## Suggested Next Action

P15-005をTask単位でCommit／Pushし、P15-006 Consumer Experience and CloseoutのTask Packetへ進む。

## Orchestrator Review

Commandの解決順、Exit／stdout／stderr Contract、Read-only比較、Nested Symlink非追跡、File／DirectoryのInspection中変更検出を実装とTestで確認した。Canonical CLI Chain、DOMなしStrict TypeScript、Injected Fetch Runtime、Framework Update SmokeをOrchestrator側で独立実行し、Target 57 tests／416 assertions、Full 1332 tests／5100 assertions、Composer／Mago／Deptrac／Guardの成功を確認した。CIはFrontend Fixture CleanupとDocker Cleanupを別々の`if: always()` Stepにし、一方の失敗がもう一方を妨げない。仕様矛盾、Scope外変更、BlockerはないためAcceptedとした。

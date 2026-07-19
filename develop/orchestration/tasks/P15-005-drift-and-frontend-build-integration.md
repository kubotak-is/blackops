# P15-005: Drift and Frontend Build Integration

Status: Ready

## Goal

P15-004までに実装したFrontend ContractとGenerated TypeScript Runtimeへ、非破壊の`frontend:check`、常設のTypeScript Compile／Node Runtime Fixture、GitHub ActionsのFrontend Gateを追加する。

Canonical Build順序を`build:compile -> frontend:generate -> frontend:check -> TypeScript compile/test`として実行し、Generated TreeのMissing／Drift、Invalid Config／Artifact／Contractを安定したExit Codeと安全なstdout／stderrで識別する。Framework UpdateはApplication所有のFrontend Config／Sourceを変更しない。

## In Scope

- `frontend:check` Project CLI CommandとLazy Registration
- Expected Generated Treeと既存OutputのPath／Bytes／余剰File比較
- Fresh／Missing／Drift／InvalidのExit Contract
- Drift Checkの完全なRead-only動作
- Symlink、Malformed Tree、Inspection Failureの安全な扱い
- DOM非依存Strict TypeScript ESM Type Check
- Node／SSR Injected Fetch Runtime Test
- Discriminated Union Narrowing Type Test
- 独立したFrontend Test Application／Toolchain Fixture
- GitHub ActionsのFrontend Build Job
- Generated Frontend Tree／Build Artifact／Runtime EmitのGit IgnoreとTracking Guard
- Framework Update時のApplication-owned Frontend Config／Source保持回帰
- Internal Architecture Documentation、Report、STATE同期

## Out of Scope

- Quickstart／SkeletonへのFrontend Config、Package Script、Frontend Source追加
- Guide／Websiteの利用者向けFrontend Journey
- 実HTTP Serverへ接続するFrontend Consumer E2E
- `frontend:generate`または`build:compile`の暗黙実行
- Watch Mode、Vite Plugin、Bundler Plugin、NPM Package Publication
- React／Vue／Svelte／Inertia Adapter、Form Helper
- Retry、Backoff、Polling、Deferred Status／Outcome取得、Cache、Offline Queue
- Typed Collection、Nested DTO、Enum、Date／Time、Upload／Stream
- Public PHP API、Attribute、Migration、Database Schema
- Documentation WebsiteのPublication／Deploy

P15-005でCanonical CLI Chainを成立させるための独立Fixtureは追加するが、Install直後のQuickstart／Skeleton体験はP15-006で扱う。

## Relevant Specifications and Decisions

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/orchestration/reports/P15-004-typed-fetch-runtime-results.md`

## Files Allowed to Change

### Production

- New `src/Internal/Console/FrontendCheckCommand.php`
- New `src/Internal/Frontend/Generation/*Check*.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- P15-005のRead-only Inspection Invariantに必要な既存`src/Internal/Frontend/Generation/*.php`だけ

### Tests and Fixtures

- New `tests/Internal/Console/FrontendCheckCommandTest.php`
- New `tests/Internal/Frontend/Generation/*Check*Test.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/FrontendGenerateCommandTest.php`（Generate／Check回帰に必要な場合だけ）
- New `tests/Frontend/**`
- `tests/Consumer/framework-update-generators.sh`
- `.gitignore`

### CI, Documentation, and Orchestration

- `.github/workflows/ci.yml`
- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P15-005-drift-and-frontend-build-integration.md`

変更可能Fileの追加が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## `frontend:check` Input Contract

`frontend:check`は`frontend:generate`と同じ順で次を解決する。

1. Application Build ConfigurationとExpected Application Build ID
2. Frontend Contract Manifest Artifact
3. ArtifactのBuild ID一致
4. Application Frontend Output Configuration
5. `FrontendTypeScriptGenerator`によるExpected Tree
6. Existing OutputのRead-only比較

Frontend Contract ManifestをMissing、Stale、Build ID不一致、Unsupported Schema、Malformed Contractとして検出した場合、`build:compile`または`frontend:generate`を暗黙実行しない。Expected Tree生成でContract Invariant違反を検出した場合も既存Outputを変更しない。

## Tree Comparison Contract

- Expected TreeのRelative File Path SetとExisting OutputのRegular File Path Setを比較する
- Path Set一致後、各FileのBytesを厳密比較する
- Expected Fileの不足、Bytes差分、余剰FileはDriftとする
- Output Directory自体が存在しない場合はMissingとする
- Directory Entryの列挙順、mtime、permission、inodeへ依存しない
- Expected Treeに必要なDirectory以外の空Directoryは結果へ影響させない
- Nested Symlinkを追跡しない。Expected File位置または余剰EntryがSymlinkならDriftとする
- Config解決時のOutput／Ancestor Symlink、OutputがFile、Application外PathはInvalid Configurationとする
- Directory列挙／File読取のI/O Failure、循環または安全に分類できないEntryはInvalid Inspectionとする
- Markerだけを特別扱いせず、Expected `manifest.json`のBytesとして比較する
- Check中にFile／Directoryの作成、書込、Rename、Delete、Cleanupを一切行わない

比較結果はInternalなFresh／Missing／Drift Valueとして返し、CLI以外へPublic APIを追加しない。

## Exit, stdout, and stderr Contract

Canonical Command:

```text
php blackops frontend:check
```

Exit Codeと出力を次に固定する。`<relative-output>`はApplication RootからのSlash区切りRelative Pathであり、Absolute Pathを表示しない。

| State | Exit | stdout | stderr |
| --- | ---: | --- | --- |
| Fresh | 0 | `Frontend generated tree is fresh in <relative-output>.` | empty |
| Missing | 1 | `Frontend generated tree is missing in <relative-output>.` | empty |
| Drift | 1 | `Frontend generated tree has drift in <relative-output>.` | empty |
| Invalid Configuration | 2 | empty | `Frontend check failed: configuration is invalid.` |
| Invalid Artifact／Build ID | 2 | empty | `Frontend check failed: contract artifact is invalid.` |
| Invalid Generated Contract | 2 | empty | `Frontend check failed: generated contract is invalid.` |
| Invalid Inspection | 2 | empty | `Frontend check failed: generated tree could not be inspected.` |

各Messageは末尾Newlineを持つ。Invalid時はCaught ExceptionのClass、Message、Stack、Absolute Path、Contract Payload、Runtime Value、Credentialを出さない。Symfony `ConsoleOutputInterface`ではError Outputへ書き、Unit Testではstdout／stderrを分離して検証する。

`frontend:check`内部で予期しないThrowableを握り潰してFresh／Driftにしない。Inspection境界のThrowableだけを安全なInvalid Inspectionへ変換し、Programming ErrorまたはFramework Invariant違反はReportへ記録する。

## Independent Frontend Fixture

`tests/Frontend/`へ、Documentation Websiteから独立した最小ToolchainとApplication Fixtureを置く。

- Node `24.18.0`／pnpm `11.12.0`はRepository `mise.toml`を使用する
- TypeScriptは`6.0.3`へ固定し、独立`package.json`／`pnpm-lock.yaml`を持つ
- `tests/Frontend/fixture/`はProject Root `blackops`、Bootstrap、Config、HTTP Operationを持つ最小Applicationとする
- Fixture Operationは少なくともInline OutcomeとDeferredを含み、Value／Outcome Scalar、Path／Query／Header／Body、Validation Field、Sensitive Input名だけを生成Contractで確認できるようにする
- Build ArtifactとGenerated TreeはFixture内のIgnore対象へ出力し、Repositoryへ固定しない
- ESM Type Checkは`strict`、ES2022、Bundler Resolution、DOM Libなしで実行する
- Narrowing Type TestはOperation固有Resultを`ok`／`kind`／`status`で分岐し、成功Modeに存在しないBranchを型へ混ぜない
- Node Runtime TestはGenerated Operation ObjectをLoadし、Injected Structural FetchでURL／Method／Header／Body、Inline／Deferred成功、代表的Failure／Transport、Object Freezeを検証する
- Runtime用CommonJS Emitを一時的に使ってよいが、Source ESM Type Checkを別に必須とする
- Runtime Emit、Build Artifact、Generated TreeはTest終了時に限定CleanupできるPackage Scriptを用意する
- Frontend Fixtureから`docs/website/node_modules`を参照しない

Tree-shaking可能性は、Operation ModuleがNamed ESM Exportであり、Sibling Operation ModuleをImportせず、共通`client.ts`／`types.ts`だけへ依存し、Import時にHTTP送信またはGlobal Mutationを行わないことをTestする。BundlerやBundle Size Gateは追加しない。

## CI Contract

`.github/workflows/ci.yml`へ独立したFrontend Jobを追加する。

1. Checkout（Credential非保持）
2. Host UID／GID設定
3. Pinned Node／pnpm Toolchain InstallとVersion確認
4. Development Image BuildとRoot Composer Install
5. `tests/Frontend`をFrozen LockfileでInstall
6. Fixtureで`php blackops build:compile`
7. Fixtureで`php blackops frontend:generate`
8. Fixtureで`php blackops frontend:check`
9. Strict TypeScript／Narrowing／Node Runtime Test
10. Generated Tree、Runtime Emit、Build ArtifactがTrackedでないことをGuard
11. Always CleanupでFrontend Fixture生成物とDocker Resourceを削除

Frontend JobはWebsite Jobへ依存させず、Documentation WebsiteのToolchain／Generated Contentを再利用しない。既存PHP Quality JobとWebsite JobのGateは弱めない。

## Framework Update Preservation

既存Framework Update Consumer SmokeへApplication所有の`config/frontend.php`とFrontend Source Fileを追加し、Framework Package更新前後のBytesが一致することを確認する。Framework UpdateはProject Root `blackops`から新しい`frontend:check`実装へ到達するが、Application Config／SourceをVendorから上書きしない。

このTaskではQuickstartのCanonical Frontend ConfigやGenerated Sourceを追加しない。Consumer Smoke専用のTemporary Application Fileとして検証し、P15-006のQuickstart／Skeleton同期と分離する。

## Acceptance Criteria

- [ ] `frontend:check`がCanonical PrefixなしCommandとしてLazy登録される
- [ ] Fresh 0、Missing／Drift 1、Invalid 2を厳密に返す
- [ ] stdout／stderrとSafe Messageが固定Contractに一致する
- [ ] Manifest Missing／Stale／Invalid時にBuild／Generateを暗黙実行しない
- [ ] Expected Path／Bytes／余剰Fileを比較し、Nested Symlinkを追跡しない
- [ ] Drift CheckがExisting TreeとApplication Source／Configを変更しない
- [ ] DOMなしStrict TypeScript ESM CompileとResult Narrowingが成功する
- [ ] Node／SSR Injected Fetch Runtime Testが成功する
- [ ] Operation ModuleのNamed ESM／Sibling非Import／No Import-time Side Effectを確認する
- [ ] GitHub ActionsがBuild／Generate／Check／TypeScript／Runtime Chainを実行する
- [ ] Generated Tree、Temporary Tree、Build Artifact、Runtime EmitをCommitしない
- [ ] Framework UpdateがApplication-owned Frontend Config／Sourceを保持する
- [ ] Quickstart／Skeleton／Guide／Website、Public PHP API、Migration、Database Schemaを変更しない
- [ ] Required PHP／Frontend／Consumer Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Console/FrontendGenerateCommandTest.php \
  tests/Internal/Console/FrontendCheckCommandTest.php \
  tests/Internal/Application/ApplicationConsoleKernelTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
bash tests/Consumer/framework-update-generators.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'credential-secret|sensitive-value|default-must-not-appear|raw-body' \
  tests/Frontend/fixture/resources/js/blackops
! rg -n 'react|vue|svelte|inertia|vite|retry|backoff|poll' \
  tests/Frontend/fixture/resources/js/blackops --glob '*.{ts,json}'
! git ls-files tests/Frontend/fixture/var/build tests/Frontend/fixture/resources/js/blackops tests/Frontend/.build | rg .
mise exec -- pnpm --dir tests/Frontend run clean
git diff --check
```

Generated TreeをCleanupする前にGenerated Content Guardを実行する。Consumer SmokeまたはFrontend Gateを環境理由で実行できなかった場合、未実行理由をReportへ明記し、成功したと推測しない。

## Expected Report

`develop/orchestration/reports/P15-005-drift-and-frontend-build-integration.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Check State／Exit／Output Matrix
- Read-only／Symlink／I/O Failure Evidence
- Frontend Fixture and Type Narrowing Matrix
- Node Runtime Cases
- CI Build Chain
- Framework Update Preservation Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

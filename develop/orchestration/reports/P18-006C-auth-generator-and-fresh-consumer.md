# P18-006C Auth Generator and Fresh Consumer Report

## Summary

Built-in `php blackops make:auth`を追加し、Install直後のApplicationへBearer Session Authentication Starterを27 Fileで生成できるようにした。Generatorは全Targetと全StubをPreflightし、初回Atomic Create、Current Version No-op、Partial Zero-write、Older Version限定`--force`、Application-owned File／MigrationのNo-overwriteを保証する。

生成CodeはUser／Password／Registration Policy／Identity判断を`app/Domain/Identity`へ、Doctrine DBALとSession Identity接続を`app/Infrastructure/Identity`へ、Route付きRegister／Login／Logout Adapterを`app/Feature/Identity`へ分離した。Fresh ConsumerはWorking Tree Packageと実PostgreSQLからGenerate、Migration、Build、Frontend、HTTP Authentication Lifecycleを完走し、Raw Password／Tokenが永続Surfaceへ残らないことを確認した。

## Changed Files

- Console／Application: `MakeAuthCommand`、Built-in予約／Lazy登録／Factory、Optional `auth.php`読込、`app.services`後の`auth.services` Merge
- Generator: `AuthGenerator`、`AuthGenerationResult`、`ProjectFileWriter`のAtomic Replace／Fingerprint Race Guard／Rollback
- Stubs: `resources/stubs/auth-*.php.stub` 27 File
- Tests: Generator／Command／Configuration／Console Unit、Fresh Consumer Fixture／E2E、Framework Update、Package Export Required Path
- Documentation: Guide Landing／Security、Internal Session Authentication／Auth Generator
- Development: Application Ergonomics／Phase 18 Spec、TODO、STATE、本Report

変更禁止の`src/Auth/Session/**`、`src/Internal/Auth/Session/**`、`src/Core/EphemeralOutcome.php`、`examples/community-board/**`は変更していない。

## Final Generated Tree and Ownership

```text
app/
├── AuthServiceProvider.php                                      framework-owned generated adapter
├── Domain/Identity/                                             application-owned
│   ├── User.php
│   ├── UserRepository.php
│   ├── PasswordHasher.php
│   ├── RegistrationPolicy.php
│   ├── EnabledRegistrationPolicy.php
│   ├── DisabledRegistrationPolicy.php
│   ├── IdentityIdentifier.php
│   ├── IdentityService.php
│   └── Exception/{DuplicateEmail,InvalidCredentials,RegistrationDisabled}.php
├── Infrastructure/Identity/
│   ├── DoctrineUserRepository.php                               application-owned
│   ├── RandomIdentityIdentifier.php                             application-owned
│   └── ApplicationSessionIdentityProvider.php                   framework-owned generated adapter
└── Feature/Identity/                                            application-owned
    ├── Register/{Register,RegisterValue,RegistrationCompleted}.php
    ├── Login/{Login,LoginValue,LoginCompleted}.php
    └── Logout/{Logout,LogoutValue,LogoutCompleted}.php
config/auth.php                                                   framework-owned generated configuration
migrations/Version20260722000000.php                              immutable application user snapshot
migrations/Version20260722000100.php                              immutable framework session snapshot
```

`--force`が置換するのは`AuthServiceProvider.php`、`ApplicationSessionIdentityProvider.php`、`config/auth.php`の3 Fileだけである。Domain、Repository、Identifier、Operation、Outcome、User／Session Migrationは置換しない。

## First, No-op, Conflict, Force, Failure Matrix

| State | Default | `--force` | Write保証 |
| --- | --- | --- | --- |
| Target 0件 | 27 File Create | 27 File Create | 全Stub準備後だけPublish |
| 全Target＋Version 1 | No-op Success | Framework-owned 3 File Replace | 通常実行は内容比較なし |
| 全Target＋Older Version | `--force`案内Error | Framework-owned 3 File Replace | Application-owned内容保持 |
| Partial Target | Relative PathだけのError | 同じError | Zero write |
| Missing／Invalid Stub | Fixed Safe Error | Fixed Safe Error | Zero write、Stub Path非露出 |
| Unknown／Future Marker | Safe Error | Safe Error | Zero write |
| Directory Target／Symlink／Root外Ancestor | Safe Error | Safe Error | Zero write、Complete扱いしない |
| Prepare／Publish Failure | Rollback | BackupからRollback | 実行所有File／Directoryだけ削除 |
| Publish Race | 競合File保持 | rename前後のFingerprint不一致で停止 | 競合内容をTargetへ復元する |

Command Outputは`Created:`／`Updated:`とApplication-relative Path、または固定No-op文言だけを出す。Absolute Path、Environment、Credential、Stub Sourceを出さない。

## Domain, Infrastructure, and Operation Responsibilities

- Domain: Email canonicalization、Password hash／verify／rehash、Registration可否、Duplicate／Invalid Credential判断を`IdentityService`とDomain Portへ集約する。`PasswordHasher`はProcess起動時にDummy Hashを作り、User存在／不存在に関係なく`verifyCredential(password, ?knownHash)`から`password_verify()`を必ず1回実行する。BlackOps、Doctrine、SymfonyへのImportは0件である。
- Infrastructure: `DoctrineUserRepository`は`public.users`だけを所有する。Session Token、Hash、TTL、Rotation、Revocation、CleanupはFramework Session Coreへ委譲する。
- Operation: Register／Login／LogoutはValidation済みValueをServiceへ渡し、Domain FailureをStable Rejection Codeへ写像する。3 OperationはRoute付き明示Inline、`#[Transactional]`、`EphemeralOutcome`である。
- Transaction: RegisterのUser保存＋Session発行、Loginの任意Password rehash＋Session発行、LogoutのRevocationはそれぞれ同じApplication ConnectionのOperation Transactionへ参加する。
- Dependency: Generated DBAL Repository／MigrationがDoctrineを直接Importするため、Fresh Consumerは`doctrine/dbal`と`doctrine/migrations`を直接Requirementとして宣言する。GeneratorはComposer Manifest／Lockを変更しない。

## Configuration and DI Evidence

`ApplicationConfigurationLoader`はOptional `auth.php`を既存Environment Snapshotで一度だけ評価する。`ApplicationConfigurationRegistrations`は`app.services`、`auth.services`の順でMergeし、Auth File欠落時は既存Service集合を変えない。

Generated `config/auth.php`は次を型付きで読む。

- `AUTH_REGISTRATION_ENABLED`: `bool`、default `true`
- `AUTH_SESSION_TTL_SECONDS`: positive int、default `28800`
- `AUTH_SESSION_TOUCH_INTERVAL_SECONDS`: positive int、default `300`

`AuthServiceProvider`はApplication PortだけをBindingし、`SessionServiceProvider::bearer()`がFramework Session Capabilityを明示Opt-inする。User TableとSession TableにForeign Keyを張らず、RequestごとのIdentity解決で不存在Userを`null`へする。

## Authentication Lifecycle Evidence

Fresh Consumerで次を実PostgreSQL／HTTPから確認した。

- 生成前: Auth Config／Domain／Feature／Migration／Table／Routeなし、既存Build成功
- 生成: 27 File、Relative Output、Composer Autoload、2 Migration、Build／Frontend Generate／Fresh Check成功
- Register: 200、User保存、43文字Token一回返却、Duplicateは409 `auth.email_unavailable`
- Login: 200、別Token発行、User不存在／Password不一致は同じ照合APIを通り、同じ401 `auth.invalid_credentials`
- Authentication: Bearer Tokenから現在Userを`ActorRef`へ解決
- Rotation: 旧Token 401、Successor Tokenだけ有効
- Expiry: Expired Token 401
- Logout: 同じTokenを2回Revokeして双方200 `{}`、以後401
- Cleanup: Retention Cutoffより古いRevoked Sessionを削除
- Ephemeral: Register／Login／Logout／RotationのOutcome Row 0件
- Frontend: Register／Login／Logoutは`.fetch()`を持ち、`.status()`／`.wait()`を持たない

## Raw Secret Non-persistence Evidence

Fresh ConsumerはRandom Password、Register Token、Login Token、Rotated Tokenを次のSurfaceからFixed-string Searchし、0件を確認した。

- PostgreSQL data-only dump
- `var/build`、`var/log`
- Generated Frontend Tree
- Container Log
- Generator Created／No-op／Force Output

Register／Login／LogoutはCanonical Receivedを空Data、Completedを空Outcomeとして記録し、Ephemeral実値をOutcome Storeへ保存しない。Invalid Credential ResponseはUser不存在とPassword不一致でOperation ID以外が同一であり、Credential判別情報を返さない。

## Decisions and Assumptions

- D111／D112どおりSession CoreとEphemeral Public Contractは変更せず、Application-owned Starterだけを生成した。
- Ray.Aop 2.20.0の既知Tokenizer gapにより、`#[Transactional]` Operationの明示Inline Metadataは既存D108方式のliteral class-stringを使う。Proxy生成対象Operationは非final readonlyとした。
- Documentation Website IAはTask Scope外のため変更せず、新しい利用手順を既存公開`Security` Pageへ統合した。
- Community Boardの起動中Runtime／ignored dependencyはUser所有として維持し、Source差分だけを0で確認した。
- Orchestrator Reviewで、未知EmailのTiming Enumeration、rename直後のTOCTOU、Directory Target／Symlink AncestorのComplete誤判定を検出した。Dummy Hash照合、rename-invariant Fingerprintのpost-check、全Targetのregular-file／lexical-realpath Preflightで修正した。
- Replaceの事前Fingerprintはctime込みを維持する。rename自体がctimeを変更するため、post-renameだけはdev／inode／size／mtime／content hashを比較し、競合書換えと正常renameを分離する。

## Commands and Results

- Focused PHPUnit: success、31 tests／151 assertions。Orchestrator Review修正後のGenerator全体は46 tests／195 assertions
- Full PHPUnit: success、1654 tests／6623 assertions
- `docker compose run --rm app mago format --check src tests`: success、all files formatted
- `docker compose run --rm app mago lint`: success、no issues
- `docker compose run --rm app mago analyze`: success、no issues
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress`: success、0 violations／0 skipped／0 uncovered／0 warnings／0 errors、2792 allowed
- Root／Quickstart `composer validate --strict`: valid
- `bash tests/Consumer/auth-generator-fresh.sh`: success、Orchestrator Review修正後の最終再実行を含む。最初のReview再実行はpost-checkへctimeを含めたため正常renameを競合扱いして`--force`で停止し、rename-invariant Fingerprintへ修正後に完走
- `bash tests/Consumer/quickstart-setup.sh`: success
- `bash tests/Consumer/quickstart-e2e.sh`: success
- `bash tests/Consumer/skeleton-create-project.sh`: success
- `bash tests/Consumer/framework-update-generators.sh`: success。Local 1.0.0 → 1.1.0後にAuth Stub／Command更新、27 File生成、No-op、Build Discoveryを確認
- Permanent Frontend Build／Generate／Fresh Check: success、7 generated files
- Permanent Frontend TypeScript／Runtime／Bound／Status-Wait／Module Shape: success、3 operations
- Website `pnpm test`: success、42／42
- Website `pnpm check`: success、Astro 0 errors／0 warnings／0 hints
- Website `pnpm build`: success、31 routes／30 public pages、Artifact／Navigation／Accessibility／Search Check成功。既存Vite chunk-size warningだけを確認
- Management ID Guard、Community Board差分0、Quickstart差分0、`git diff --check`: success
- Generated Frontend／Website Content／Build／Dependency Artifact: cleanup済み

`tests/Consumer/framework-package-export.sh`にはAuth Stub Required Pathを追加してSyntax Check済みである。Git側Archiveは`HEAD`を正本とするため、Worker Commit禁止かつ新規Fileが未Commitの段階では完全実行できない。Composer path package inclusion、Framework UpdateのTemporary Commit／Package、Fresh Consumerで配布対象Source／Stubの存在を検証済みであり、Orchestrator Commit後の独立Package Export再実行をSuggested Next Actionとする。

Localの`mago format --check src tests examples`は、Userが起動中のCommunity Board用ignored `vendor/blackops/framework -> repository root` Symlinkを辿って既存Vendor Fixtureを対象化するため実行不能だった。AGENTS.md必須の`src tests`は成功し、Community Board Source差分は0である。Clean CheckoutのCIには同ignored Artifactが存在しない。

## Acceptance Criteria

- [x] `php blackops make:auth`がInstall直後から追加登録なしで利用できる
- [x] Generated LayoutがDomain／Infrastructure／Featureを分離し、Operationが薄い
- [x] Auth Configurationが既存Application Registrationと共存し、未導入Applicationを変えない
- [x] All-or-nothing、No-op、No-overwrite、限定`--force`、Immutable Migrationを満たす
- [x] Register／Login／LogoutがEphemeral HTTP Contractで動き、Raw Secretを永続化しない
- [x] User保存／Password／RegistrationはApplication、Session LifecycleはFrameworkが所有する
- [x] Fresh ConsumerがGenerateからExpiry／Rotation／Revocationまで完走する
- [x] Generated Frontend Clientが直接Fetchだけを公開し、Status／Waitを公開しない
- [x] Quickstart／Skeleton／Framework Update／Full Quality Gateが回帰する
- [x] Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Remaining Issues

Active Implementation Blockerはない。Community BoardをGenerated Auth Starter／Typed Configuration／Bound Clientへ移行して手動配線削減を計測する作業はP18-007 Scopeである。Git／Composer Package ExportのCommit後GateはOrchestratorが再実行する。

## Suggested Next Action

OrchestratorがGenerator State Machine、Atomic Replace Rollback、3 Fileだけの`--force`所有境界、Domain Vendor非依存、Transaction／Ephemeral Secret境界、Fresh Consumerを独立Reviewする。Commit後に`bash tests/Consumer/framework-package-export.sh`を再実行し、Accept後はP18-007 Community Board Migration and Phase Closeoutへ進む。

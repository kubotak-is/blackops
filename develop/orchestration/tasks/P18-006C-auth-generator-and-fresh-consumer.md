# P18-006C: Auth Generator and Fresh Consumer

Status: Ready

## Goal

Install直後のBlackOps Applicationで`php blackops make:auth`を一度実行すると、Bearer Session Authenticationを使うRegister／Login／Logout API Starterが生成され、Migration、Build、HTTP、Frontend Contractまで追加の手動配線なしで動く状態にする。

生成CodeはApplication-ownedなUser／Password／Registration Policy／Identity Domainを`app/Domain/Identity`、DBALとFramework Sessionの接続を`app/Infrastructure/Identity`、薄いOperation Adapterを`app/Feature/Identity`へ分離する。Password検証やRegistration Policy等のDomain知識をOperationへ埋め込まない。

GeneratorはAll-or-nothing Preflight、同一Generator VersionのNo-op Success、Application-owned FileとMigrationのNo-overwriteを保証する。Community Boardは変更せず、Fresh Consumerで生成後の実利用を証明する。

## In Scope

- Built-in `make:auth` Command、Framework Command予約、Lazy Console登録
- Versioned Auth Generator、Stub、Atomic Preflight／Publish／Rollback
- `config/auth.php`のTyped `Environment` ClosureとAuth Service Registration読込
- `app/Domain/Identity`のUser、Repository Port、Password Service、Registration Policy、Domain Service、Safe Domain Failure
- `app/Infrastructure/Identity`のDoctrine DBAL Repository、Clock／Identifier等の必要なAdapter、`SessionIdentityProvider`
- `app/Feature/Identity/Register`、`Login`、`Logout`のRoute付き明示Inline Ephemeral Operation
- Application Service Provider、User Migration、Framework-owned Session Migration Snapshot
- Unit／Integration／Fresh Consumer／Framework Update Generator Test
- Guide／Internal Documentation、Specification／TODO／Report／STATE

## Out of Scope

- Community Board Source／Configuration／Migration／Frontend変更（P18-007）
- HTML／SvelteKit UI、Cookie発行、Cookie Attribute、CSRF
- JWT／OAuth／MFA／Password Reset／Email Verification
- User Role／Permission、Application固有Account State
- Framework Session Core／Ephemeral Outcome Public Contractの再設計
- Composer Dependencyの自動Install／Update、`composer.lock`書換え
- Session Authentication用の外部Repository／Packagist／Tag／Release
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Decisions and Specifications

- `develop/decisions/080-project-generator-command-contract.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/111-session-auth-package-contract.md`
- `develop/decisions/112-authentication-credential-response-boundary.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/17-core-api.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Command and Generator Contract

- Canonical Commandは`php blackops make:auth`とし、引数を持たない。`--force`だけをOptional Flagとして持つ。
- Install直後から追加Command登録なしでGlobal List／Helpへ現れ、Framework Command名として衝突検証する。
- Generator VersionをFramework内で明示し、Framework-owned Marker／Configurationへ保存する。Runtime ArtifactやSecret値は保存しない。
- First Runは全Stub読込、全Target Path、既存File、Symlink、Ancestor、Composer Autoload前提をPreflightしてから一括生成する。
- 一つでもTargetが存在するPartial／Unknown Stateでは、Target相対Pathだけを含むSafe Errorで何も書かない。
- Current Versionの全Targetが存在するComplete Stateは通常実行でNo-op Successとし、Application-owned Fileの内容を比較／上書きしない。
- `--force`はCurrent／Older Complete Stateに対してFramework-owned Configuration／Adapterだけを更新できる。User、Repository、Password、Registration Policy、Domain Service、Operation、User Migration、Session Migrationは置換しない。
- Migrationは生成時点のImmutable Snapshotであり、Framework Updateと再実行で書き換えない。
- Prepare／Publish途中のFilesystem Failure／Raceは、その実行が作成／置換したFileとDirectoryだけをRollbackし、競合Fileを保持する。
- Command Outputは`Created:`／`Updated:`／`Authentication starter is already current.`の安全な相対Path／固定文言だけとし、Absolute Path、Environment、Credential、Stub Sourceを出さない。

## Generated Application Layout

少なくとも次の責務と配置を生成する。Class分割は責務を保つ範囲で追加できるが、OperationへDomain Logicを戻さない。

```text
app/
├── Domain/Identity/
│   ├── User.php
│   ├── UserRepository.php
│   ├── PasswordHasher.php
│   ├── RegistrationPolicy.php
│   └── IdentityService.php
├── Infrastructure/Identity/
│   ├── DoctrineUserRepository.php
│   └── ApplicationSessionIdentityProvider.php
├── Feature/Identity/
│   ├── Register/{Register.php,RegisterValue.php,RegistrationCompleted.php}
│   ├── Login/{Login.php,LoginValue.php,LoginCompleted.php}
│   └── Logout/{Logout.php,LogoutValue.php,LogoutCompleted.php}
└── AuthServiceProvider.php
config/auth.php
migrations/Version*CreateUsers.php
migrations/Version*CreateBlackOpsSessions.php
```

- DomainはBlackOps、Doctrine、Symfonyへ依存しない。Email canonicalization、Password Hash／Verify／Rehash、Registration可否、Duplicate／Invalid CredentialのDomain判断をDomain Serviceへ集約する。
- Infrastructure RepositoryはDoctrine DBALを利用し、User Tableだけを所有する。Session Table／Token Hash／TTL／Rotation／Revocationを再実装しない。
- `ApplicationSessionIdentityProvider`はOpaque Identity IDを現在のApplication Userへ解決し、無効／不存在なら`null`を返す。
- `AuthServiceProvider`はApplication-owned Port／Implementationを登録する。Framework Sessionは`SessionServiceProvider::bearer()`でOpt-in登録する。
- `config/auth.php`は`Environment` ClosureでRegistration Enabled、Session TTL、Touch Intervalを型付き読込し、Auth Provider群を既存`app.services`と決定的にMergeする。Auth FileがないApplicationの登録結果を変えない。
- User MigrationはApplication-owned `public.users`相当のTableを作成する。Session Migrationは`resources/stubs/auth-session-migration.php.stub`のFramework-owned SnapshotをPublishし、User Foreign Keyを張らない。
- Generated SourceがVendor PackageのClass／Interface／Attributeを直接Importする場合、Fresh Consumerの`composer.json`はそのPackageをDirect Requirementとして宣言する。Generator自身はComposer Manifest／Lockを変更しない。

## Operation and HTTP Contract

- Register: `POST /auth/register`、Stable Type `auth.register`、明示`Inline`、Email／Display Name／Passwordを受け取る。
- Login: `POST /auth/login`、Stable Type `auth.login`、Email／Passwordを受け取る。
- Logout: `POST /auth/logout`、Stable Type `auth.logout`、Current Raw Tokenを明示Inputとして受け取る。
- Password／Raw Tokenは`#[Sensitive]`で、Email等はBlackOps Validation Attributeを使う。Validation Backendを重複実装しない。
- 3 OperationはすべてRoute付き明示Inlineで、Register／LoginはRaw Token、Issued At、Expires Atを持つ`EphemeralOutcome`を返す。Credential Propertyは`#[Sensitive]`を持つ。LogoutはPropertyなし`EphemeralOutcome`を返す。
- Register／Login／LogoutのOperationはInputをDomain／Application Serviceへ渡し、Domain FailureをStable Rejection Codeへ写像する薄いAdapterにする。Password Policy、Email canonicalization、Repository Query、HashingをOperationへ書かない。
- RegisterのUser保存とSession発行は同じApplication Connection／Operation TransactionでAtomicにする。Loginの必要なRehashとSession発行も同じOperation Transactionへ参加する。
- Invalid CredentialはUser不存在／Password不一致を区別せず同じUnauthorized Codeにする。Duplicate EmailはConflict、Registration DisabledはForbiddenまたはBusiness Ruleの一つへ固定する。
- Raw Password／TokenはJournal、Outcome Store、Status、Log、Exception、Generator Output、Artifact、Reportへ残さない。HTTP ResponseでTokenを返すのはRegister／Loginの一度だけとする。
- Frontend Generate後、Register／Login／LogoutはTyped `.fetch()`を持ち、`.status()`／`.wait()`を持たない。

## Fresh Consumer Contract

Repository内のCommitted ExampleをAuth Starterで汚さず、TemporaryなFresh Consumerを`tests/Consumer`から構築する。

1. Framework Packageを現在のWorking TreeからInstallする
2. Auth生成前はAuth Service／Route／Migration／Session Tableが存在せず、既存Buildが成功する
3. `php blackops make:auth`を実行し、期待LayoutとSafe Outputを確認する
4. 同一Version再実行がNo-op、Partial ConflictがZero Write、`--force`がApplication-owned File／Migrationを保持することを確認する
5. Composer Autoload、Database Migration、`build:compile`、`frontend:generate`を実行する
6. Register成功、Duplicate拒否、Login成功、Invalid Credential同一拒否、Authenticated Requestを完走する
7. Logoutを2回実行してIdempotent Revocation、旧Token認証不可を確認する
8. Rotationは旧Token無効／Successor有効、Expiryは認証不可、CleanupはRetention境界を満たす
9. Journal／Outcome／Session／User Table、Generated Tree、Command Output、HTTP Error、Container LogへRaw Password／Tokenがないことを確認する
10. Consumer終了時にDependency／Generated／Runtime／Database ArtifactをCleanupする

## Allowed Files

- `src/Internal/Console/**`、`src/Internal/Application/**`のBuilt-in Command登録とAuth Configuration Mergeに必要な最小差分
- `src/Internal/Generator/**`のAuth Generator／Atomic Writerに必要な最小差分
- `resources/stubs/auth-*.php.stub`と既存Session Migration Stub
- 対応する`tests/Internal/**`、`tests/Consumer/auth-generator-fresh.sh`、専用Fixture
- Generator Update／Skeleton／Quickstart回帰に必要な既存`tests/Consumer/**`の最小差分
- `docs/guide/**`、`docs/internal/**`
- `develop/spec/74-application-ergonomics.md`、`develop/spec/75-phase-18-delivery-plan.md`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-006C-auth-generator-and-fresh-consumer.md`

`src/Auth/Session/**`、`src/Internal/Auth/Session/**`、`src/Core/EphemeralOutcome.php`、`examples/community-board/**`は変更禁止とする。Public Auth／Ephemeral API、Session Schema、Community Board変更が必要なら実装を広げずBlockerとしてOrchestratorへ返す。

## Required Verification

1. Command List／Help／Reserved Name／Lazy Factory Unit Test
2. Generator First／No-op／Partial Conflict／Unknown Marker／Old Version／`--force`／Filesystem Failure／Race／Symlink Test
3. Stub Missing／Invalid Placeholder／Absolute Path・Secret非露出 Test
4. Configuration Loader／Registration MergeとAuth FileなしRegression Test
5. Generated Source Syntax／Autoload／Build Discovery／Container Compile Test
6. Register／Login／Logout Unit／HTTP／実PostgreSQL Integration Test
7. Fresh ConsumerのGenerate→Migrate→Build→Frontend→HTTP→Auth Lifecycle E2E
8. Existing Full PHPUnit、Quickstart Setup／E2E、Skeleton Create-project、Framework Update Generator
9. Root／Fresh Consumer／Quickstart `composer validate --strict`
10. Permanent Frontend TypeScript／Runtime、Website Reader Test／Build（公開利用方法を変更する場合）
11. Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`
12. Community Board差分0、Generated／Dependency／Runtime Artifact Cleanup

## Acceptance Criteria

- [ ] `php blackops make:auth`がInstall直後から追加登録なしで利用できる
- [ ] Generated LayoutがDomain／Infrastructure／Featureを分離し、Operationが薄い
- [ ] Auth Configurationが既存Application Registrationと共存し、未導入Applicationを変えない
- [ ] All-or-nothing、No-op、No-overwrite、限定`--force`、Immutable Migrationを満たす
- [ ] Register／Login／LogoutがEphemeral HTTP Contractで動き、Raw Secretを永続化しない
- [ ] User保存／Password／RegistrationはApplication、Session LifecycleはFrameworkが所有する
- [ ] Fresh ConsumerがGenerateからExpiry／Rotation／Revocationまで完走する
- [ ] Generated Frontend Clientが直接Fetchだけを公開し、Status／Waitを公開しない
- [ ] Quickstart／Skeleton／Framework Update／Full Quality Gateが回帰する
- [ ] Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Completion Report

`develop/orchestration/reports/P18-006C-auth-generator-and-fresh-consumer.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Generated TreeとOwnership分類
- First／No-op／Conflict／Force／Failure Matrix
- Domain／Infrastructure／Operation責任分界
- Configuration／DI MergeとOpt-in Evidence
- Register／Login／Logout／Expiry／Rotation／Revocation結果
- Raw Secret Non-persistence Evidence
- Consumer／Website／Full Quality Gate結果
- Allowed Scope外の必要性が発生した場合のBlocker

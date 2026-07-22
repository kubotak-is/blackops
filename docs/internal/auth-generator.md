# Authentication Generator

Built-in `make:auth`はFramework Package内のVersioned StubをApplicationへPublishする。Generator Version 1のMarkerは`config/auth.php`の`generator_version`であり、Runtime SecretやBuild Artifactを保持しない。

## State Machine

Generatorは全Stub読込と全Target Preflightの後だけFilesystemを変更する。

| State | Default | `--force` |
| --- | --- | --- |
| Target 0件 | 全27 FileをAtomic Create | 全27 FileをAtomic Create |
| 全Target＋Current Marker | No-op Success | Framework-owned 3 FileだけAtomic Replace |
| 全Target＋Older Marker | Safe Error | Framework-owned 3 FileだけAtomic Replace |
| Partial Target | Zero-write Safe Error | Zero-write Safe Error |
| Unknown／Future Marker | Safe Error | Safe Error |
| Symlink／Root外Ancestor | Safe Error | Safe Error |

CreateはTemporary Fileを全てPrepareしてHard LinkでPublishする。Replaceは各既存FileのFingerprintをPublish直前に再確認し、BackupからRollbackする。Raceで作られた競合Fileは削除しない。Errorへ出すFilesystem情報はTargetのApplication-relative Pathだけに制限する。

## Ownership

Framework-owned Generated Fileは次の3点だけである。

- `config/auth.php`
- `app/AuthServiceProvider.php`
- `app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php`

Domain、Repository、Password Hasher、Registration Policy、Operation、User Migration、Session MigrationはApplication-ownedまたはImmutable Snapshotであり、`--force`でも変更しない。

## Configuration Merge

`ApplicationConfigurationLoader`はOptional `auth.php`を他のConfigurationと同じEnvironment Snapshotで一度評価する。`ApplicationConfigurationRegistrations`は`app.services`の後に`auth.services`をMergeする。File欠落時は空Listなので既存ApplicationのContainer Graphを変えない。

Generated `AuthServiceProvider`はApplication Portだけを登録する。`SessionServiceProvider::bearer()`がSession Store、Manager、AuthenticatorとApplication Session Identity Adapterを登録する。User TableとSession TableにはForeign Keyを張らず、毎RequestのIdentity解決でCurrent Account Stateを反映する。

## Security Boundary

Register／Login／LogoutはRoute付き明示Inline Ephemeral Operationである。Ray.AopのTokenizer gapを避けるため、`ExecuteWith`は既存のmetadata literal方式でInline Class-stringを記述する。`#[Transactional]`対象OperationはProxy生成可能な非final readonly Classにする。

Fresh ConsumerはWorking Tree PackageからGeneratorを実行し、DomainのVendor非依存、No-op／Conflict／Force、Migration、Container Compile、Frontend Direct Fetch、実PostgreSQLのIssue／Authenticate／Rotate／Expire／Revoke／Cleanup、Raw Secret非永続化を検証する。Consumer ProbeはTemporary ConsumerだけへCopyし、Committed Exampleを変更しない。

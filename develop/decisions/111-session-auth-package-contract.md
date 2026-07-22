# D111: Session Authentication Contract

Status: Decided

## Context

D110は、任意のSession Authentication CapabilityがOpaque Token、Hash、TTL、Rotation、Revocation、Doctrine DBAL Store、Migration、HTTP Credential抽出を所有し、ApplicationがUser、Password、Registration、Authorization、Cookie／UIを所有すると決めた。D110では別Packageを前提としたが、D111でFramework同梱のOpt-in Capabilityへ見直す。P18-006では、この責任分界をPublic API、Database Schema、HTTP Adapter、`php blackops make:auth` Generatorへ落とし込む。

Community Boardの現行Identity実装は、32-byte Random Token、SHA-256 Hash、8時間のAbsolute TTL、Bearer Credential、Logout RevocationをApplication内に実装している。一方、`last_used_at`、明示Rotation、Expired Cleanup、Concurrent Rotation／Revocation、Cookie Adapter、Session Migrationは未実装である。

Session Tokenの保存形式、並行制御、Migration History、PackageとFrameworkの依存方向は、後続Taskで暗黙に決めない。Public／Security Contractとして先に固定する。

## Inherited Decisions

- Session Authenticationは`blackops/framework`に同梱するが、Configuration／Service Binding／MigrationのないApplicationでは有効化しない。
- Raw TokenをDatabase、Journal、Outcome、Log、Command Output、Reportへ保存／出力しない。
- User、Password Hash Policy、Registration、Account State、Role／Permission、Cookie Attribute、CSRF、Route／UIはApplicationが所有する。
- Frameworkの`HttpAuthenticator`と`AuthenticationResult`をHTTP Authenticationの中立Contractとする。
- Durable Actorは`ActorRef(id, type)`だけを保持し、Credential、Role、Permission、Claimを保持しない。
- Applicationが直接利用するPackageはApplicationの`composer.json`へ明示する。
- Session Authentication用の別Package／GitHub Repository／Packagist Publication／Version Tagを作成しない。

## Question 1: Framework同梱か独立Packageか

### User Comment

> A
>
> ところでsession-authとしてるのはなぜ？
> blackops/authではだめなの？また、パッケージを分ける明確な理由はなに？Laravelは同梱してなかったっけ？

### Review

LaravelはBrowser向けAuthenticationとSessionを`laravel/framework`に同梱し、Sanctum／PassportのようなAPI Token／OAuth Capabilityを別Packageにしている。

BlackOpsはすでにDoctrine DBAL、Doctrine Migrations、PSR-7、Actor、HTTP Authentication、DI、Project CLIをFrameworkの必須Capabilityとして持つ。Session Authenticationを別PackageにしてもFrameworkの依存を減らせず、Compatibility Matrix、Release、Publication、Install Commandを増やす。現時点で別Packageにする明確な技術的利点はない。

`blackops/auth`はPassword、Session、JWT、OAuth、MFA、Authorizationまで所有するように見える広い名前である。今回のCapabilityはSession Token Lifecycleだけなので、別Packageであれば`session-auth`が正確だが、Framework同梱に変更する場合はPackage名自体が不要になる。Public Namespaceを`BlackOps\Auth\Session`とすることで、Auth配下のSession Capabilityであることを表現できる。

### Revised Options

- A: Session Authenticationを`blackops/framework`へ同梱する。Public APIは`BlackOps\Auth\Session`配下に置き、Configuration／Service Binding／Migrationを追加したApplicationだけが有効化する。`blackops/auth`／`blackops/session-auth`は作らない
- B: D110どおり`blackops/session-auth`を別Packageにする
- C: Authentication全般を所有する`blackops/auth`を別Packageとして作り、将来のJWT／OAuth／MFAも同じPackageへ追加する

### Revised Recommendation

Aを推奨する。

Laravelに近いFramework同梱のOpt-in Capabilityとし、別Package化はFrameworkと独立したVersioning／Dependency／Release Cycleが必要になった時点で再検討する。

[ANSWER]

A

[/ANSWER]

## Question 2: Sessionが保存するIdentityとApplication Provider

### Options

- A: Session RowはApplicationが発行時に渡すOpaqueな`identity_id`だけを保持する。FrameworkはPublic `SessionIdentityProvider::resolve(string $identityId): ?ActorRef`を呼び、削除／停止済みUserをApplicationが無効化できるようにする。User TableへのForeign Keyは張らない
- B: Session Rowへ`actor_id`と`actor_type`を保存し、Providerなしで直接`ActorRef`を復元する
- C: FrameworkがUser Table／User Entity／Account Stateまで所有する

### Recommendation

Aを推奨する。

Session StoreをApplicationのUser Schemaから独立させつつ、毎Requestで現在のAccount Stateを反映できる。Providerが返す`ActorRef`は既存のAuthorization／Deferred Contractへそのまま接続できる。

[ANSWER]
A
[/ANSWER]

## Question 3: Token形式とHash

### Options

- A: CSPRNGの32 byteをpaddingなしBase64url 43文字にし、DatabaseへはSHA-256 Hashだけを保存する。Random Source／ClockはTest用Portから注入し、TokenとHashの`__toString()`／JSON変換は提供しない
- B: 64 byte TokenとArgon2id Hashを保存する
- C: UUID v7をRaw Tokenとしてそのまま保存する

### Recommendation

Aを推奨する。

256 bitの予測不可能なTokenはOffline Guessに十分強く、決定的HashによるIndexed Lookupが可能である。Passwordと異なりLow-entropy Inputではないため、Argon2idの計算Costは実用上の利点よりRequest Costを増やす。

[ANSWER]
A
[/ANSWER]

## Question 4: TTL、Last Used、Rotation、Revocation

### Options

- A: Default 8時間のAbsolute TTLを起動時Configurationで変更可能にする。認証時の`last_used_at`はDefault 5分間のTouch Intervalを超えた場合だけConditional Updateする。Rotationは明示APIで旧SessionをRevokeしてSuccessorを発行する単一Transactionとし、同時Rotationの先行者だけが成功する。Logout RevocationはIdempotent、CleanupはExpired／RevokedのRetention Cutoff以前だけを削除する
- B: 認証のたびにExpiryを延長するSliding TTLとし、`last_used_at`を毎Request Updateする
- C: TTLとLogoutだけを実装し、Last Used／Rotation／Cleanupは後続Phaseへ送る

### Recommendation

Aを推奨する。

Absolute TTLは失効時刻が安定し、Touch Intervalは`last_used_at`の監査価値を保ちながら毎Request Writeを避ける。RotationとRevocationをTransaction／Conditional Updateで固定することで、旧Tokenの再利用と並行成功を防げる。

[ANSWER]
A
[/ANSWER]

## Question 5: BearerとCookieのHTTP Adapter

### Options

- A: Frameworkは`BearerSessionAuthenticator`と`CookieSessionAuthenticator`を別々提供する。Applicationは一つを`HttpAuthenticator`へBindingし、Cookie AdapterのCookie名だけを起動時に渡す。Cookieの発行、`Secure`／`HttpOnly`／`SameSite`、Domain／Path、CSRFはApplicationが所有する
- B: 一つのAdapterがBearerとCookieを同時に読み、Bearerを優先する
- C: BearerだけをPackageで提供し、CookieはApplicationが実装する

### Recommendation

Aを推奨する。

Credential SourceをBinding時に一つに固定し、二つのCredentialが同時に来た場合の暗黙優先順位を作らない。Frameworkは抽出と検証を所有するが、Browser Security PolicyはApplicationに残す。

[ANSWER]
A
[/ANSWER]

## Question 6: Migrationの配布とTable所有

### Options

- A: FrameworkはPostgreSQL用のImmutable Forward Migration Templateを所有し、`make:auth`がApplicationの`migrations/`へ一度だけPublishする。Table名は`blackops_sessions`、User TableへのForeign Keyはなし、ApplicationのMigration Historyに取り込んだFileはFramework Updateで書き換えない
- B: Framework Migration RunnerがFramework内のSession MigrationをApplication Migrationと別に直接実行する
- C: Frameworkが起動時にSchemaを自動作成／更新する

### Recommendation

Aを推奨する。

Applicationが実行済みMigrationの履歴を所有でき、Framework Updateで過去のMigrationが変化しない。Migrationは通常のGenerator Stubと異なり、Publish時点のImmutable Snapshotにすることが意図に合う。Runtime Schema Mutationは行わない。

[ANSWER]
A
[/ANSWER]

## Question 7: `make:auth`のCommand接続

### Options

- A: `blackops/framework`がBuilt-in `make:auth`とBuilt-in Generator／Stubを所有し、Install直後から追加登録なしで実行できる
- B: Session Authenticationのために汎用Console Extension Manifestを新設する
- C: Applicationが`MakeAuthCommand`を`config/app.php`へ明示登録する

### Recommendation

Aを推奨する。

`php blackops make:auth`をInstall直後から予測可能にし、生成LogicをFramework Updateで更新する。P18-006のためだけに汎用Plugin Discoveryを設計するより変更面を狭くできる。Question 1でFramework同梱を選んだためAで確定する。

[ANSWER]

A

[/ANSWER]

## Question 8: Generatorが作るApplication Code

### Options

- A: `make:auth`はFramework-neutralなAPI Authentication Starterを生成する。Application-owned User Model／DBAL Repository／Password Verifier／Registration Policy／Identity Provider、Register／Login／Logout Operation、Service Provider、Configuration、User Migration／Framework Session Migrationを含め、Build後にHTTP APIが動く状態にする。HTML／SvelteKit UI、Cookie発行、CSRFは生成しない
- B: Identity Provider InterfaceとService Providerだけを生成し、User／Password／Operation／Migrationは利用者がすべて実装する
- C: SvelteKitのLogin／Registration／Logout Page、Cookie、CSRFまで生成する

### Recommendation

Aを推奨する。

認証の定型実装を実際に減らし、生成後のCodeをApplication固有Policyとして編集可能にする。同時にHeadless境界を守り、SvelteKitとBrowser Security PolicyまでFrameworkへ固定しない。

[ANSWER]

A

[/ANSWER]

## Question 9: GeneratorのConflictと再実行

### Options

- A: DefaultはAll-or-nothing Preflightで一つでもConflictしたら何も書かない。`--force`はFramework-owned Generated Configuration／Adapterだけを更新可能にし、User、Repository、Password、Registration Policy、Operation、Migrationは上書きしない。完全生成済みの同一VersionではNo-op成功する
- B: `--force`でMigrationを除く全生成Fileを上書きする
- C: Conflict FileだけをSkipし、その他を部分生成する

### Recommendation

Aを推奨する。

部分生成による半端なAuthentication Surfaceを作らず、Applicationが編集するDomain／Security PolicyをFramework Updateが破壊しない。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Session Authenticationは`blackops/framework`へ同梱し、Public APIを`BlackOps\Auth\Session`配下に置く。ApplicationがConfiguration、Service Binding、Migrationを追加したときだけ有効になるOpt-in Capabilityとする。
2. Session RowはOpaqueな`identity_id`を保持し、Public `SessionIdentityProvider::resolve(string $identityId): ?ActorRef`でApplicationの現在のAccount Stateを解決する。User TableへForeign Keyを張らない。
3. TokenはCSPRNG 32 byteのpaddingなしBase64urlとし、DatabaseへSHA-256 Hashだけを保存する。Raw TokenのString／JSON自動変換を提供しない。
4. Default 8時間のAbsolute TTL、Default 5分のLast-used Touch Interval、単一TransactionのRotation、Idempotent Revocation、Retention Cutoff付きCleanupを提供する。Concurrent Rotationは先行者だけが成功する。
5. BearerとCookieは別々の`HttpAuthenticator` Adapterとして提供する。Cookie名はApplication Configuration、Cookie発行／Attribute／CSRFはApplication責務とする。
6. `make:auth`はFramework所有のImmutable PostgreSQL Session MigrationをApplicationのMigration HistoryへPublishする。Table名は`blackops_sessions`とし、User TableへForeign Keyを張らない。
7. `make:auth`はBuilt-in Command／GeneratorとしてInstall直後から追加登録なしで使える。
8. GeneratorはFramework-neutralなAPI Authentication Starterを生成し、User、DBAL Repository、Password Verifier、Registration Policy、Identity Provider、Register／Login／Logout Operation、Service Provider、Configuration、User／Session Migrationを含める。HTML／SvelteKit／Cookie発行／CSRFは生成しない。
9. GeneratorはAll-or-nothing Preflight、同一VersionのNo-op Success、Application-owned File／MigrationのNo-overwriteを保証する。`--force`はFramework-owned Generated Configuration／Adapterだけを更新できる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- D110の別Package前提を置き換え、Session AuthenticationはFrameworkと同じVersion／Releaseで管理する。
- Authentication不要のApplicationにSession Table、Service Binding、Runtime Writeは追加されない。
- User／Password／Registration／Role／Cookie／CSRFは引き続きApplication所有である。
- 将来JWT／OAuth／MFAに独立Versioning／Dependencyが必要になった場合だけ、別Package化を新しいDecisionで検討する。

[/CONSEQUENCES]

## Invariants

- Raw Tokenは発行直後の呼出し側に一度だけ返し、Database／Log／Exception／Journal／Outcome／Reportへ永続化しない
- Token Hash、Session ID、Identity IDをCredentialとして受理しない
- Expired／Revoked／Rotated SessionはAuthenticationとRotationに使えない
- Concurrent Rotationで有効なSuccessorを二つ発行しない
- Invalid Credentialは存在有無、Expiry、Revocation、Account Stateの差をHTTP Errorへ反映しない
- Framework Session CapabilityはUser Password、Role／Permission、Cookie Attribute、CSRF、UIを保持しない
- Session Configuration／Binding／MigrationのないApplicationでCapabilityを有効化しない
- Application MigrationとApplication-owned FileをFramework Update／`--force`で上書きしない
- RuntimeでApplication Source Scan／Attribute Reflection Fallbackを行わない
- Session Authentication用のExternal Repository／Packagist／Tag／Releaseを作成しない

## Traceability

- Application Ergonomics: [D110](110-application-ergonomics.md)
- Middleware and Authentication: [D095](095-phase-12-middleware-and-authorization-runtime.md)
- Public Core API: [Spec 17](../spec/17-core-api.md)
- Application Ergonomics: [Spec 74](../spec/74-application-ergonomics.md)
- Phase 18 Delivery: [Spec 75](../spec/75-phase-18-delivery-plan.md)

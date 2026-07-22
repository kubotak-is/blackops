# Session Authentication Core

`BlackOps\Auth\Session`はFramework同梱のOpt-in Capabilityである。`SessionServiceProvider`が登録されたCompiled ContainerだけにSession Serviceと選択済みHTTP Authenticatorを定義する。未登録ApplicationのBuild Artifact／Service／Migrationは変化しない。

## Runtime composition

`SessionServiceProvider`Public SurfaceはIdentity Provider Class、TTL／Touch Configuration、Bearer／Cookie選択とCookie名だけを受ける。Internal Registrationは次のServiceをCompiled Containerへ結ぶ。

- `DefaultSessionManager`
- `PostgreSqlSessionStore`
- `CryptographicSessionTokenGenerator`
- `SymfonySessionIdentifierGenerator`
- `SystemSessionClock`
- Application-owned `SessionIdentityProvider`
- 選択した`BearerSessionAuthenticator`または`CookieSessionAuthenticator`

`SessionConfiguration`と`SessionCookieName`はSecretを含まないScalar Value Objectである。Internal DI Adapterはこの2型だけをConstructor Definitionへ変換し、Raw object serializeによるCredential／SecretのCompiled Artifact化を行わない。

## Token and storage

Token Generatorは`random_bytes(32)`をpaddingなしBase64url 43文字へ変換する。ParserはAlphabet、Length、32-byte Decode、Canonical Re-encodeを検証し、Store LookupにはSHA-256 Lowercase Hexだけを渡す。Row Fetch後も`hash_equals()`でHashを確認する。

`PostgreSqlSessionStore`はDefault `public.blackops_sessions`を使い、Testだけが検証済み別Schema Tableを注入できる。Issue／Authenticate／Rotate／RevokeはDoctrine DBALの同じ`DatabaseManager`Connectionを使う。AuthenticationはActive／UnexpiredかつTouch Threshold対象のRowを`UPDATE ... RETURNING`で先に更新する。更新対象がない場合はThresholdより新しいActive Rowを通常`SELECT`するため、同時AuthenticationでShare Lock Upgradeを行わない。両経路でHashを再確認し、Expiryは延長しない。

RotationはOld Rowを`FOR UPDATE`でLockし、Successor InsertとOld Rowの`revoked_at`／`rotated_to_id`更新を一Transactionで行う。更新件数が1でない場合はTransactionをRollbackし、Orphan Successorを残さない。RevokeもRow Lock後のConditional UpdateでIdempotentにする。

## HTTP and failure boundary

`SessionManager::authenticate()`がStoreのOpaque IdentityをApplication ProviderのCurrent `ActorRef`へ解決する。Bearer／Cookie AdapterはSessionManagerだけに依存し、Internal Typeの`instanceof`やRuntime Fallbackを行わない。

Application-owned User／Password／Operationの生成と更新境界は[Authentication Generator](auth-generator.md)を参照する。

Malformed／Unknown／Expired／Revoked／Rotated／Identity Missingは`authentication.invalid_session`で同じ外部Surfaceにする。DBAL／Clock／Random／Identity Provider ThrowableはInvalid Credentialへ丸めず、Application HTTP RuntimeのSafe Internal Failure Boundaryへ伝播する。Raw Token／Hash／Identity IDをException Message、Journal、Outcome、Log、Reportへ出さない。

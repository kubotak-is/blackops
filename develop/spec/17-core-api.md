# Core API

## Marker Interface

Operation Definition、OperationValue、Outcomeは共通Methodを持たないMarker Interfaceとする。

```php
#[PublicApi]
interface Operation
{
}

#[PublicApi]
interface OperationValue
{
}

#[PublicApi]
interface Outcome
{
}

#[PublicApi]
interface EphemeralOutcome extends Outcome
{
}
```

業務Classは対応するInterfaceを実装する。標準Typed Self-handled OperationのValueとOutcomeはNative `handle()` SignatureからBuild時に推論する。

`EphemeralOutcome`はHTTP Clientへ一度だけ返し、Canonical Journal／Outcome Store／Statusへ保存しないSecret-bearing Outcomeを表す。通常OutcomeのSubtypeとしてAuthoring Modelを維持するが、Route付きの明示Inline Operationだけに限定し、Deferred／Console／Status／Waitでは利用できない。

## Operation Handler

標準Self-handled Operationは、`Operation` を実装し、具象 `OperationValue` を第一引数、具象 `Outcome` または `void` をReturn Typeに持つPublic `handle()` Methodを定義する。必要な場合は第二引数で `ExecutionContext` を受け取る。Build CompilerがNative SignatureからValue／Outcomeを推論・検証するため、`#[Accepts]`、`#[Returns]`、Generic DocBlock、Value Narrowing Guard、`OperationResult::completed()`は不要である。

`OperationHandler` はSeparate HandlerとLegacy Self-handled Operationの後方互換Contractとして維持する。Legacy Handlerは `OperationHandler` を実装し、単一の `handle()` Methodを持つ。

```php
/**
 * @template TValue of OperationValue
 * @template TOutcome of Outcome
 */
#[PublicApi]
interface OperationHandler
{
    /**
     * @param OperationEnvelope<TValue> $operation
 * @return OperationResult<TOutcome>
 */
    public function handle(OperationEnvelope $operation): OperationResult;
}
```

標準Typed Self-handled Operationは成功Outcomeを直接返し、予期された業務拒否では `OperationRejectedException` をthrowする。Frameworkが内部 `OperationResult` へ正規化する。Legacy Handlerは成功または業務拒否を `OperationResult` で返し、PHPDoc GenericでValue型を表現する。

Console公開はOperation Classへ付けるPublic `#[ConsoleCommand]` Attributeで明示する。Console入口のOrigin／Authorization ActorはOptional Public `BlackOps\Console\ConsoleActorProvider`から取得し、CredentialやContainerをPublic Contractへ露出しない。両型は`#[PublicApi]`の互換性Contractとする。

Session Authenticationは`BlackOps\Auth\Session`配下の`#[PublicApi]`型で提供する。`SessionManager`はIssue／Authenticate／Rotate／Revoke／Cleanupを所有し、AuthenticateはRaw Tokenを受けてCurrent `ActorRef` または`null`を返す。Identity ID、Token Hash、Stored SessionはPublic APIへ返さない。`SessionIdentityProvider`のThrowableはInvalid Credentialへ丸めず上位Failure Boundaryへ伝播する。

`RawSessionToken`はPublic Constructor／Property、`__toString()`、JSON変換を持たず、明示的な`reveal()`だけでRaw Valueを取得する。`IssuedSession`はToken／Issued At／Expires Atだけを露出し、Session ID／Identity ID／Token Hashを露出しない。

## PHP Public API

Framework利用者による直接利用を公式に想定し、SemVer上の後方互換性を管理する型には、BlackOps固有の `#[PublicApi]` Attributeを付ける。

`#[PublicApi]` は実行時の振る舞いを追加するものではない。

- `#[PublicApi]` が付いた型は後方互換性の対象とする
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない
- `#[PublicApi]` がない型は、後方互換性を保証しない
- HTTP APIと区別が必要な文脈では「PHP Public API」と表記する

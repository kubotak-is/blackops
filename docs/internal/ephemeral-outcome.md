# Ephemeral Outcome Boundary

`EphemeralOutcome`はHTTPへ一度だけ返す実Outcomeと、永続化するLifecycle Dataを分離する。Operationは通常のInline State Machineを通り、Operation ID、Actor相関、Attempt、Rejected／Failedの安全な情報を維持する。

## Build Contract

CompilerはDeclared OutcomeからEphemeral Flagを導出し、Operation、HTTP、Frontendの各Manifestへ同じFlagを保存する。Routeが一つであること、`#[ExecuteWith(Inline::class)]`が明示されていること、Console Commandがないことを検証する。Manifest DecoderとApplication ComposerもFlag、Declared Type、Strategy、HTTP Metadataを相互検証し、Artifact改ざん時にReflection Fallbackしない。

Ephemeral Outcomeはfinal readonlyな具象Classとpublic constructor-promoted Propertyだけを使う。Credential名のRoot Propertyには`#[Sensitive]`が必要である。Nested DTO内のSensitive Propertyは拒否し、Credential境界をRootで明示する。

## Runtime Flow

```text
bind and validate value
  -> received(EmptyJournalData)
  -> attempt.started
  -> invoke handler
  -> validate exact ephemeral type and JSON shape
  -> attempt.succeeded
  -> completed(OperationCompletedData(EmptyOutcome))
  -> commit
  -> return actual outcome to the inline caller
  -> HTTP JSON 200
```

Runtime Shape検証はTransaction内でCommitより前に行う。Encoding不能な値やWrong Typeは安全な実行失敗となり、業務更新と成功TerminalをRollbackする。ExceptionへRaw Object、Property値、Encoding Error Detailを連結しない。

Canonical WriterとObserverは実Value／実Outcomeを一度も受け取らない。PostgreSQL Journal CodecとOutcome Codecも実Ephemeral Objectを防御的に拒否する。Status SourceはSubject取得とAuthorizationを先に行い、許可後にUnavailableを返す。Console RuntimeとDeferred Acceptorは改ざんMetadataを実行前に拒否する。

## Frontend Surface

Generated Operation Objectは`.fetch()`、`.toRequest()`、`.url()`と直接200 Responseの型だけを公開する。Unbound／Boundの`.status()`／`.wait()`、Status Result Alias、Runtime Methodは生成しない。Decoder ErrorやTransport ErrorへRaw Response BodyとCredentialを残さない。

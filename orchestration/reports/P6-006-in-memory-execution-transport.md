# P6-006: InMemory Execution Transport Report

Status: Completed

## Summary

- Public `ExecutionTransport`を実装するDatabase非依存の`InMemoryExecutionTransport`を追加した。
- Operation ID単位でMessageを保持し、同じIDの再enqueueをMessage内容やSettlement状態にかかわらず拒否するようにした。
- `availableAt <= claimedAt`と失効LeaseをEligibility条件とし、`availableAt`の瞬間、Operation ID順で決定的に一件だけClaimするようにした。
- Operation単位のClaim Sequenceを保持し、Claim／再Claimごとに単調増加するFencing Tokenを発行するようにした。TokenはOperation IDとSequenceだけで、Payload／Contextを含まない。
- PSR Clockと正のLease秒数をConstructorで要求し、Heartbeat、Acknowledge、Releaseの操作時刻をClockから取得するようにした。
- Heartbeatで現在時刻からLeaseを延長し、AcknowledgeでInMemory Queue EntryをTerminal Settlement、Releaseで指定時刻へ再投入するようにした。
- Unknown、Stale、Expired、Released、Settled Claim操作を`DeferredTransportException`で拒否し、検証完了前にStateを変更しないようにした。
- Lease期限直前／ちょうど、Heartbeat延長後、Release再投入時刻直前／ちょうど、異なるUTC Offsetの同一瞬間をUnit Testで検証した。
- Stale Fencing TokenによるHeartbeat／Acknowledge／Release後も現在Leaseが変わらず、自然失効時に次Tokenで再Claimできることを検証した。
- InMemory Adapterが非Durable、単一Process／Object限定であり、PostgreSQLのTransaction、Lifecycle Store、Attempt Recoveryを代替しないことをDocumentationへ記録した。

## Changed Files

- `src/Transport/InMemory/InMemoryExecutionTransport.php`
- `src/Transport/InMemory/InMemoryOperationRecord.php`
- `src/Transport/InMemory/InMemoryOperationState.php`
- `tests/Transport/InMemory/InMemoryExecutionTransportTest.php`
- `docs/internals/in-memory-execution-transport.md`
- `docs/internals/deferred-transport-contract.md`
- `docs/internals/README.md`
- `TODO.md`
- `orchestration/tasks/P6-006-in-memory-execution-transport.md`
- `orchestration/reports/P6-006-in-memory-execution-transport.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Messageは`availableAt <= claimedAt`で利用可能、Claimed Messageは`leaseExpiresAt <= claimedAt`で再Claim可能とした。`DateTimeImmutable`比較はTimezone表記ではなく瞬間比較として扱う。
- Heartbeat、Acknowledge、Releaseが成功できるActive Leaseは`clock.now() < leaseExpiresAt`とした。期限ちょうどではClaimが失効しているため操作を拒否し、新規Claimは同じ瞬間に成功できる。
- Claim開始時刻とEligibilityはPublic `ClaimRequest::claimedAt()`を正本とし、Enqueue受付時刻およびClaim後の操作時刻は注入Clockを正本とした。
- Claim TokenはPostgreSQL Adapterと同様の`operation-id:sequence`形状とし、比較は`hash_equals()`を使用した。TokenにはPayload、Context、Operation Type等を含めない。
- Stale Token拒否時はState、Message、Lease、Sequenceのいずれも変更しない。Testでは失敗後も現在Claimが元の期限まで占有し、期限ちょうどに次Sequenceで再Claimできることを確認した。
- AcknowledgeはFramework Lifecycle Stateではなく、InMemory Adapter内のQueue EntryだけをSettledにする。別Lifecycle Storeを持たないUnit Test Adapterのため、以後の再Claimを禁止する最小Settlementとした。
- Releaseは現在のMessage Metadataを保持し、`availableAt`だけを指定値へ置き換えた新しいMessageとして再投入する。古いClaimは即時にStaleになる。
- InMemory AdapterはAttempt開始を表現しないため、現在かつ未失効のClaimをReleaseできる。PostgreSQLのAttempt開始後Release拒否は変更しない。
- PostgreSQL Adapter、Worker Runtime、Public Portは変更していない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter InMemoryExecutionTransport
Result: OK (13 tests, 66 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (504 tests, 1537 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1134 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

初回Mago Lintは内部`fencingToken` Property名を秘密情報として扱い、Literal初期値と通常比較を拒否した。非秘密の内部単調Counterを`claimSequence`へ改名し、Claim Token比較を`hash_equals()`へ強化した後、最終Lintは成功した。

## Acceptance Criteria

- [x] Adapterが`ExecutionTransport`を実装する
- [x] enqueueが同じOperation IDの重複を拒否する
- [x] 未来の`availableAt`を持つMessageは期限前にClaimされない
- [x] eligible Messageを`availableAt`、Operation ID順で一件Claimする
- [x] ClaimごとにFencing Tokenが単調増加する
- [x] Lease期限切れMessageを新しいTokenで再Claimできる
- [x] heartbeatが現在ClaimのLeaseを延長する
- [x] acknowledge済みMessageは再Claimされない
- [x] releaseしたMessageは指定時刻以後に再Claimできる
- [x] Stale / Unknown / Settled Claim操作が専用Exceptionで拒否される
- [x] Unit Test向け非Durable AdapterであることがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- MVP残作業の次Task Packetへ進む。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更されていることを確認した。
- Claimが`availableAt`の瞬間とOperation IDで決定的に一件を選ぶことを確認した。
- Lease期限ちょうどで旧Claim操作を拒否し、新しいFencing Tokenで再Claimできることを確認した。
- Stale TokenによるHeartbeat、Acknowledge、Releaseが現在Stateを変更しないことをTestで確認した。
- AcknowledgeとReleaseのQueue Semantics、およびPostgreSQL Lifecycle Storeとの差分がDocumentationへ明記されていることを確認した。
- Public PortとPostgreSQL Adapterに差分がないことを確認した。
- Targeted PHPUnitを再実行し、`OK (13 tests, 66 assertions)`を確認した。
- Mago LintとDeptracを再実行し、問題がないことを確認した。
- Review指摘およびBlockerはない。

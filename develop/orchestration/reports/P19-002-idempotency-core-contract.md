# P19-002 Idempotency Core Contract

Status: Accepted

## Summary

Implemented the Framework core idempotency contract without exposing HTTP, dispatcher lifecycle, PostgreSQL, retention, outbox, or consumer behavior. Caller keys are shape-validated and immediately reduced to opaque versioned SHA-256 values. Execution contexts carry only that hash across root, attempt, and deferred codec boundaries; child contexts intentionally clear it. Versioned scope and operation-value fingerprints stream field boundaries directly into SHA-256. Processing／terminal records, typed claim results, and an unregistered in-memory atomic store fixture cover same, conflict, concurrent, stale, and terminal transitions.

## Changed Files

- `src/Idempotency/IdempotencyKey.php`
- `src/Idempotency/IdempotencyKeyHash.php`
- `src/Internal/Idempotency/IdempotencyScopeHash.php`
- `src/Internal/Idempotency/OperationFingerprint.php`
- `src/Internal/Idempotency/IdempotencyRecordState.php`
- `src/Internal/Idempotency/IdempotencyClaimStatus.php`
- `src/Internal/Idempotency/ProcessingRecord.php`
- `src/Internal/Idempotency/TerminalRecord.php`
- `src/Internal/Idempotency/IdempotencyClaimResult.php`
- `src/Internal/Idempotency/IdempotencyStore.php`
- `src/Internal/Idempotency/IdempotencyScopeHasher.php`
- `src/Internal/Idempotency/OperationValueFingerprinter.php`
- `src/Internal/Idempotency/InMemoryIdempotencyStore.php`
- `src/Core/ExecutionContext.php`
- `src/Internal/ExecutionContext/ExecutionContextFactory.php`
- `src/Internal/Codec/ExecutionContextHydrator.php`
- `src/Internal/Codec/ExecutionContextNormalizer.php`
- `tests/Idempotency/IdempotencyKeyTest.php`
- `tests/Internal/Idempotency/OperationValueFingerprinterTest.php`
- `tests/Internal/Idempotency/IdempotencyStoreTest.php`
- `tests/Internal/Codec/ExecutionContextJsonCodecTest.php`
- `tests/Internal/Codec/ReflectionJsonOperationCodecTest.php`
- `tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php`
- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`

## Decisions and Assumptions

- Key validation accepts exactly 1–255 bytes in printable ASCII `!` through `~`; whitespace, controls, and non-ASCII values are rejected with a generic error.
- Key hashing uses SHA-256 Version 1 with a domain separator, network-order byte length, and raw key bytes. Raw key data is not returned, stringified, JSON encoded, or included in errors/debug information.
- Context codec field name is `idempotency_key_hash`, containing only `{version,digest}`. Missing field remains `null` for legacy payloads; unknown version, digest shape, and nested fields fail closed.
- Scope hashing requires an authenticated authorization `ActorRef`; operation type, actor type/id, and key digest are independently length-delimited. Scope, fingerprint, claim, record, and store types are Internal-only in this packet.
- Fingerprinting follows public declaration order and incrementally encodes operation type, value type, property names, declared property type boundaries, scalar type/value boundaries, string byte length, list/map distinction, and canonical map key order. Finite floats use deterministic `%.17g` encoding; non-finite floats and unsupported values fail with the existing codec failure type.
- The in-memory store is an internal fixture and is not registered by `ProductionRuntimeComposer`.

## Public Value Matrix

| Value | Exposed | Opaque boundary |
| --- | --- | --- |
| `IdempotencyKey` | `hash()` only | no raw getter, `__toString()`, JSON serialization, or raw debug output |
| `IdempotencyKeyHash` | version, algorithm, digest, constant-time `equals()` | 64-char lowercase digest only |
| `IdempotencyScopeHash` (Internal) | version, digest, constant-time `equals()` | no actor/key input retained |
| `OperationFingerprint` (Internal) | codec version, digest, constant-time `equals()` | no canonical payload retained |

## ExecutionContext Propagation Matrix

| Transition | Hash behavior |
| --- | --- |
| Root `receive(..., IdempotencyKey)` | key is hashed once and stored |
| Attempt `startAttempt()` | exact hash instance/value is retained |
| Deferred JSON encode/decode | version and digest round-trip; raw key never appears |
| Child `createChild()` | hash is cleared for the new operation identity |
| Legacy context without field | decodes with `idempotencyKeyHash() === null` |

## Scope／Fingerprint Matrix

| Contract | Inputs | Storage result |
| --- | --- | --- |
| Scope | operation type, authorization actor type/id, key digest | Version 1 scope digest |
| Fingerprint | operation/value type, declaration order, field/type/value boundaries, string byte lengths, sorted maps | Version 1 fingerprint digest |
| Sensitive field | participates directly in stream | raw value and whole canonical buffer are never created or returned |
| Unsupported shape | object/resource/closure/cyclic unsupported value | `OperationCodecException` without value details |

## Storage Claim／Terminal Matrix

| Existing state | Fingerprint | Typed result |
| --- | --- | --- |
| none | any | `claimed` with one processing record |
| processing or terminal | equal | `existing_same_fingerprint` |
| processing or terminal | different | `existing_conflict` |
| processing | matching operation/key/fingerprint and expected `processing` state | terminalize succeeds once |
| terminal / wrong operation / stale state | any | terminalize returns false; record is unchanged |

The fixture retains records past `expiresAt`; a retained record always wins the scope claim, including same-fingerprint retries. Purge/replay expiry semantics are deferred.

## Sensitive Evidence

- Tests assert invalid-key errors do not include supplied values.
- Codec tests assert encoded contexts contain only hash version/digest and never the raw key.
- Fingerprint failures use generic codec messages and do not include unsupported values.
- Store records carry only scope/key/fingerprint hashes, operation identity, strategy, state, and lifecycle timestamps; HTTP response status/headers/body/snapshot fields are intentionally deferred.

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit tests/Idempotency tests/Internal/Idempotency tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php tests/Internal/Codec/ExecutionContextJsonCodecTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php tests/Architecture/PublicApiArchitectureTest.php` — PASS, 59 tests / 149 assertions after review corrections.
- `docker compose run --rm app vendor/bin/phpunit` — Orchestrator PASS, 1,744 tests / 6,936 assertions.
- `docker compose run --rm app mago format --check src tests` — PASS, all files formatted.
- `docker compose run --rm app mago lint <complete P19-002 changed source and tests>` — Orchestrator PASS, no issues found.
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — Orchestrator PASS, 0 violations / 2,884 allowed.
- `docker compose run --rm app mago lint src tests` — Executed; existing repository-wide baseline failed with 136 errors / 1,277 warnings / 5 notes. The complete P19-002 changed scope is clean.
- `docker compose run --rm app mago analyze src tests` — Executed; existing repository-wide baseline failed with 361 errors / 3 warnings / 1 note / 594 help messages. Focused／Full PHPUnit、Public API Architecture、changed-scope Lint、Deptrac found no P19-002 failure.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS.
- `git diff --check` — PASS.
- `git status --short` — only files listed in this report are modified or untracked.

## Orchestrator Review

- Public APIを`IdempotencyKey`／`IdempotencyKeyHash`だけへ限定し、Scope／Fingerprint／Store／RecordをInternalへ戻した
- HTTP Response SnapshotをP19-003まで延期し、P19-002 Terminal RecordからStatus／Header／Bodyを除去した
- Retention期限を過ぎてもPurgeまではSame FingerprintとしてRecordを保持し、Conflictや暗黙再実行へ変換しないことを確認した
- Fingerprintへ宣言型境界を追加し、既存Canonical Codecに合わせて有限Floatを決定的に扱い、非有限Floatだけを拒否した
- Context Root／Attempt／Deferred Codec／Child非伝播とLegacy Field欠落Decodeを独立Reviewした
- Focused／Full PHPUnit、変更Scope Lint、Full Format、Deptrac、Management ID、diff Guardを独立再実行した

## Acceptance Criteria

- [x] Valid／invalid key shape is fixed by public value tests.
- [x] Raw key is excluded from persistence-shaped values, codec, error, and debug surfaces.
- [x] Root／attempt／deferred／child hash propagation is covered by tests.
- [x] Scope and fingerprint are deterministic and field/type-boundary based.
- [x] Same／conflict／concurrent claim and one-shot terminalization are covered by the in-memory fixture.
- [x] Existing context payloads and keyless runtime decode unchanged.
- [x] Public API architecture guard and Deptrac pass.
- [x] No HTTP／PostgreSQL／retention／outbox／consumer files were changed.

## Remaining Issues

- Repository-wide `mago lint src tests`／`mago analyze src tests`には既存Baseline Failureがある。P19-002変更ScopeはLint cleanであり、範囲外の既存Fileは変更していない。
- PostgreSQL adapter, HTTP/Dispatcher lifecycle, retention, and outbox integration remain intentionally deferred to subsequent packets.

## Suggested Next Action

Create P19-003 for HTTP／PHP duplicate lifecycle, PostgreSQL idempotency persistence, and independent retention without changing the accepted P19-002 Core Contract.

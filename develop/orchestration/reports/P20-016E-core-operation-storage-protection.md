# P20-016E Core Operation Storage Protection

## Summary

Implemented mandatory BOPD envelope use for canonical journal records, deferred payload/context, outcomes, and bounded observer replay. PostgreSQL protected writes bind binary bytea directly; duplicate deferred acceptance compares decrypted existing envelopes with fresh plaintext. Runtime composers resolve the compiled storage-protection codec and reject bootstrap without a provider.

## Changed Files

- PostgreSQL journal, deferred, outcome, replay adapters and schemas.
- Application runtime composition/provider resolver and status/diagnostics wiring.
- `Version20260808000000` protected-storage migration and migration/package fixtures.

## Decisions and Assumptions

- Journal AAD uses a new clear `operation_schema_version` column because journal `schema_version` is the record schema.
- The protection migration rejects every non-empty legacy journal/operations/outcomes table before alteration; no plaintext conversion or deterministic nonce is attempted.
- Outbox relay continues to use the protected deferred transport sender; outbox table payload protection remains out of scope.

## Commands and Results

- `mago format` on changed production files: PASS.
- `docker compose run --rm app mago format --check src tests`: PASS (all files already formatted after the final fixture sync).
- Focused `mago lint`: PASS.
- Focused `mago analyze`: no errors; existing mixed-value warnings remain.
- Protected PostgreSQL writes bind arbitrary envelope bytes with `ParameterType::BINARY`.
- Storage-protection composition PHPUnit: PASS (6 tests, 37 assertions).
- Migration runner/command fixtures: PASS (24 tests, 111 assertions) after the 8-to-9 framework migration update. Application console, HTTP runtime, and seeder fixtures register explicit test-owned providers.
- Replay fixtures and bounded selection: PASS (`PostgreSqlObserverReplayStoreTest` and `ObserverReplayRuntimeFailureTest`, 17 tests, 43 assertions).
- Deferred sender/outcome protection fixtures: PASS (26 tests, 111 assertions).
- Operation-data readers/injector and tampered-outcome classification: PASS (5 tests, 12 assertions).
- Deferred context tenant/origin fail-closed validation: PASS (6 tests, 72 assertions including worker runtime).
- Fresh PostgreSQL schemas now include BOPD prefix checks; deferred tombstone rows remain the only nullable envelope case.
- Focused Mago lint still reports pre-existing test complexity/style findings; changed production readers are explicitly annotated for their increased cyclomatic complexity.
- Quickstart now uses an env-backed strict-base64 sample provider; `.env.example` keeps `BLACKOPS_STORAGE_KEY=` empty and the consumer script injects a per-run 32-byte key only into its temporary `.env` with xtrace disabled. Independent e2e diagnosis confirmed the deferred operation reached `completed` after worker claims `0` then `1`; the former `origin_actor_id` assertion incorrectly expected an execution actor although clear journal metadata intentionally stores only the origin actor. The corrected journey verifies masked diagnostics projection, excludes actor IDs and credentials from artifacts, and passes end to end.
- Constructor and direct-row fixture synchronization is complete for the scoped Transport, HTTP, Integration, Internal Execution/Replay/Application, Console replay, and Outbox relay tests. Protected raw-row fixtures now bind binary BOPD envelopes and include `operation_schema_version` where required.
- `docker compose run --rm app vendor/bin/phpunit tests/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteServiceTest.php tests/Transport/PostgreSql/PostgreSqlOutcomeRetentionDeleteServiceTest.php tests/Transport/PostgreSql/PostgreSqlRetentionHoldStoreTest.php tests/Transport/PostgreSql/PostgreSqlRetentionPlannerTest.php tests/Transport/PostgreSql/PostgreSqlTenantIsolationTest.php tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php tests/Transport/PostgreSql/PostgreSqlEphemeralOutcomeIntegrationTest.php tests/Http/DeferredOperationRequestHandlerTest.php tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`: PASS (63 tests, 430 assertions).
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationStorageProtectionResolverTest.php tests/Transport/PostgreSql/PostgreSqlTenantIsolationTest.php --filter 'VersionMigration|Resolver|PairConstraints'`: PASS (4 tests, 33 assertions). The migration matrix verifies empty upgrade success and non-empty journal/operations/outcomes failure before alteration, with ciphertext/row snapshots unchanged; resolver coverage verifies missing-provider rejection without key material.
- Acceptance matrix test mapping: `PostgreSqlTenantIsolationTest::testWrongTenantRowsAreFilteredBeforeBlobDecodeAndOutboxCarriesTenant`, `::testObserverReplayTimeSelectionCarriesTenantAndRejectsTamperedClearSubject`, and `::testSameIdDifferentTenantSenderFailsSafely` cover tenant isolation and clear-subject tamper; `PostgreSqlCanonicalJournalStoreTest::testPurposeRowFieldAndTagTamperingFailClosed` and `::testUnknownEnvelopeKeyIsRejectedWithoutPlaintextFallback` cover purpose, row, operation-field, tag, and strict unknown-key failures; `PostgreSqlDeferredOperationReceiverTest::testClaimRejectsPayloadContextCiphertextSwap` covers payload/context field substitution. These ran in the passing task-scoped suite.
- Raw-wire absence assertions are explicit in `PostgreSqlDeferredOperationSenderTest::testEnqueueStoresMessageAndReturnsAcknowledgement` (payload/context), `PostgreSqlDeferredOperationReceiverTest::testClaimMarksEligibleOperationRunningAndReturnsClaim` (payload/context), `PostgreSqlCanonicalJournalStoreTest::testAppendsAndReadsValidationViolationsWithoutRawValues` (journal), and `PostgreSqlOutcomeStoreTest::testVersionTwoRoundTripsStructuredOutcomeListsNullableDtoAndFloat` (outcome): each checks `BOPD` and excludes known JSON fragments.
- Retention does not decode protected bytes: `PostgreSqlTransportPayloadTombstoneServiceTest::testTombstoneDoesNotDecodeTamperedEligibleEnvelope`, `PostgreSqlOutcomeRetentionDeleteServiceTest::testDeleteDoesNotDecodeTamperedEligibleOutcomeEnvelope`, and `PostgreSqlJournalRetentionDeleteServiceTest::testDeleteDoesNotDecodeTamperedJournalEnvelope` tamper eligible envelope tags and still complete tombstone/delete. Replay safe projection and convergence are covered by `PostgreSqlObserverReplayStoreTest::testCorruptCanonicalRowFailsSafelyWithoutMutation`, `::testCanonicalIdsAndEncodedBytesRemainUnchangedAcrossSelection`, and `ObserverReplayRuntimeFailureTest::testFlushFailureRedeliversSameRecordIdAndIdempotentTargetConverges`.
- Expanded task-scoped run after these additions: PASS (303 tests, 1,898 assertions). The final run includes payload/context ciphertext-swap rejection, tampered Journal/Outcome/Transport retention non-decode tests, and the three synchronized application fixture providers.
- `docker compose run --rm app composer validate --strict`: PASS.
- Application fixture regression run: PASS (10 tests, 133 assertions).
- `docker compose run --rm app vendor/bin/phpunit`: PASS (2,074 tests, 8,300 assertions; one existing deprecation).
- `docker compose run --rm app mago format --check src tests`: PASS.
- `docker compose run --rm app mago analyze`: PASS with 29 warnings and 0 errors.
- `docker compose run --rm app mago lint`: known baseline only (82 findings: 9 errors, 27 warnings, 29 notes, 17 help messages); no task regression was identified and focused changed-source lint passes.
- `docker compose run --rm app vendor/bin/deptrac`: cannot execute under PHP 8.5 because the vendored parser fails at `vendor/deptrac/deptrac/src/DefaultBehavior/Ast/Parser/Helpers/NikicFileReferenceVisitor.php:106`; this is the recorded baseline tooling incompatibility. Full-suite architecture guards pass.
- `bash tests/Consumer/quickstart-e2e.sh`: PASS (`Quickstart consumer E2E passed.`), including fresh install, 11 migrations, build, seed, HTTP, retry/completion, status, diagnostics, retention, and frontend journeys.
- `bash tests/Consumer/framework-package-export.sh`: PASS after commit `190d42a`; Git and Composer archives both contain `Version20260808000000.php`, satisfy the root inventory/exclusion contract, pass strict Composer validation, and generate the production autoloader.
- Shell syntax, management-ID comment guard, and `git diff --check`: PASS.

## Acceptance Criteria

Accepted. Mandatory envelope wire shape, no-plaintext storage, runtime bootstrap, tamper and tenant failures, migration rollback, authorization-before-decode, retention non-decode, replay projection, full suite, consumer journey, and exact Git/Composer package export evidence all pass.

## Remaining Issues

- Broad Mago lint and Deptrac retain the documented baseline findings/tooling incompatibility above; neither is introduced by P20-016E.

## Suggested Next Action

Start P20-016F for Outbox, Dead Letter, Idempotency, and Result protection.

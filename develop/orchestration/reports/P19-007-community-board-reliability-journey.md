# P19-007 Community Board Reliability Journey Report

Status: Accepted.

## Summary

Connected the Community Board digest form to a cryptographically secure server-generated idempotency key and replay-safe generated client request. Added application-owned notifications, a deferred `NotifyPostOwner` child operation, transactional outbox registration from comment creation, recipient-scoped latest-first listing, server-only notification BFF, UI route/navigation, and additive notification migration.

## Changed Files

- Community Board Notification domain, operation, repository, provider, migration, and focused tests.
- Board comment result/service and AddComment transactional outbox registration.
- Digest server BFF, form action/page/key preservation, and focused tests.
- Notification server BFF, page, navigation, and focused tests.
- Consumer migration expectation, digest key/replay journey, and product relay/worker/duplicate notification journey.
- `develop/STATE.md` checkpoint.

## Decisions and Assumptions

- `AddComment` injects `NotificationService` and registers a canonical `new NotifyPostOwner($notifications)` instance. This avoids persisting a DI/AOP proxy as the outbox operation definition while retaining the same operation type and child identity.
- Notification rows persist only IDs, delivery identity, and timestamp; fixed safe text is generated at the UI boundary.
- Source post/comment IDs intentionally have no foreign keys so delivery remains possible after source deletion.
- Digest keys are 24 cryptographically random bytes rendered as printable lowercase hexadecimal; raw values are only carried by the server-rendered form and request header.
- `startWeeklyDigest` rejects tampered keys before transport when they are not printable ASCII or outside the 1..255 byte boundary.

## Digest Idempotency Matrix

| Case | Evidence |
| --- | --- |
| Server-generated key | `GET /digests` emits a 48-character hidden field from Web Crypto. |
| Same-key replay | Consumer submits the same hidden key through the generated `idempotencyKey` option and compares operation identity. |
| Fresh key | Consumer refreshes the form before the second digest and asserts a distinct key, operation, and digest. |
| Same key / different week | Alice submits K1 with another valid week; BFF returns generic 503, preserves K1, and does not disclose O1 or its fingerprint. |
| Different actor replay | Bob submits Alice's K1/week and receives a distinct actor-scoped operation, never O1; the Bob operation is completed before the remaining journey. |
| Response contract | Digest consumer asserts HTTP 200 action envelopes with `status: 303`; native Playwright forms assert HTTP 303 and exact `Location`; browser controller executes two attempts for each operation. |
| Validation preservation | Invalid week submit includes and returns the same form key. |
| Transport unavailable | Backend is stopped during an Alice K1 submit; BFF returns generic 503 with K1 preserved, then backend is restarted and health boundary verified. |
| Raw key secrecy | Server-only BFF and client-build guards avoid key persistence or browser bundle inclusion. |

## Comment / Outbox Transaction Matrix

| Case | Evidence |
| --- | --- |
| Bob comments on Alice post | AddComment registers `NotifyPostOwner` through `TransactionalOutbox` in the framework transaction. |
| Self-comment | Owner/author equality skips registration. |
| Relay stopped | Product journey observes pending outbox and zero notifications before relay. |
| Comment persistence failure | PostgreSQL BEFORE INSERT trigger returns safe action failure; comments and outbox remain unchanged; trigger/function are dropped. |
| Outbox registration failure | PostgreSQL outbox trigger returns safe action failure; comment rolls back and outbox remains empty; trigger/function are dropped. |
| Comment/outbox atomicity | Existing framework transaction boundary plus product PostgreSQL journey exercise the committed row pair. |

## Relay / Worker / Duplicate Delivery Matrix

Product journey passes one-shot `outbox:relay:run`, `worker:run`, then resets the same outbox row to pending and repeats relay/worker. Notification count remains one by the delivery-operation unique constraint and application `ON CONFLICT` guard. A second pending child is delivered after its source post/comment are deleted, proving source-ID-only persistence.

## Notification Authorization / Sensitive Matrix

- `ListNotifications` derives recipient from authenticated actor, never request data.
- Alice sees her notification; Bob receives an empty safe result.
- BFF strips delivery operation identity and maps transport/auth failures to safe messages.
- No comment/post body, author snapshot, token, credential, or raw digest key is stored in the notification table.
- Focused ListNotifications test proves recipient selection comes from the authorization actor; request limit cannot switch actors.

## Migration / Seed Evidence

- Additive `Version20260724000100` creates the notification table, unique delivery identity, and `(recipient_user_id, created_at DESC, id DESC)` index.
- Down removes only the notification table/index.
- Product and digest clean installs report 11 migrations; existing seed remains 3 users / 3 posts / 4 comments and zero notifications.

## Frontend / Browser Evidence

- Frontend check, Vitest, and production build pass (46 tests).
- Product journey passes notification navigation and sensitive/browser bundle guards.
- Browser Playwright passes 2 tests, including independent Alice/Bob contexts, relay/worker/replay synchronization, and reload polling around server-rendered notifications.

## Commands and Results

- `docker compose run --rm app php examples/community-board/blackops build:compile` — PASS.
- `docker compose run --rm app vendor/bin/phpunit` (Community Board) — PASS, 55 tests / 583 assertions.
- Focused Notification migration/repository/operation/build-artifact tests — PASS, including BoardBuildArtifactTest (117 assertions) and Notification operation idempotency.
- `pnpm --dir examples/community-board/frontend run check` — PASS.
- `pnpm --dir examples/community-board/frontend run test` — PASS, 46 tests.
- `pnpm --dir examples/community-board/frontend run build` — PASS.
- `bash tests/Consumer/community-board-product-journey.sh` — PASS, including self-comment, PostgreSQL comment/outbox failure triggers with cleanup, relay/worker duplicate delivery, and source-delete-before-delivery.
- `bash tests/Consumer/community-board-digest.sh` — PASS, including K1/O1 replay, fresh K2/O2, fresh K3/O3 after source deletion, response contracts, and raw-key guards.
- `bash tests/Consumer/community-board-browser.sh` — PASS, 2 Playwright tests / 27.8s.
- Full Framework PHPUnit — PASS, 1,875 tests / 7,576 assertions (1 accepted deprecation); Mago analyze PASS; Mago lint exit 0 with existing note/help baseline; Deptrac 0 violations.
- Scoped Mago format checks and `git diff --check` — PASS; broad example scan remains subject to the repository's existing local vendor symlink-loop limitation.
- Focused migration/notification/build-artifact/comment-contract PHPUnit — PASS, 10 tests / 73 assertions (migration up/down SQL parity, authorization actor separation, and exact canonical outbox definition).
- Canonical outbox fix: `AddComment` injects `NotificationService` and registers `new NotifyPostOwner($notifications)`, avoiding the DI/AOP proxy definition in the outbox payload. Focused correction run passes 10 tests / 73 assertions; full Community Board PHPUnit passes 55 tests / 583 assertions.
- Digest consumer refreshes a third key (`K3`) after source deletion, so the third digest is a new operation rather than replaying completed `O2`.
- Digest consumer also covers same-key/different-week safe conflict, transport-unavailable K1 preservation with backend stop/restart, and different-actor K1 replay isolation (Bob receives a distinct completed operation, never O1).
- Form failure assertions account for SvelteKit devalue serialization: each action requires the `idempotencyKey` field and exactly one occurrence of the raw K1 value, without assuming a literal JSON key/value pair.
- Browser Playwright asserts native-form HTTP 303 responses and `Location` headers; Bob comment completion is asserted before signaling the controller, which waits for notification count 1 after each relay/worker pass.
- `bash tests/Consumer/framework-package-export.sh` — PASS from implementation commit `fc39c15`.
- `bash tests/Consumer/community-board-clean-install.sh` — PASS from implementation commit `fc39c15`, including dependency installation, 11 migrations, generated-client freshness, 46 frontend tests, production build, database snapshot, and live HTTP startup.
- GitHub Actions CI Run `30087475600` — PASS, all five jobs including the complete Community Board browser/foundation/identity/post-comment/product/digest chain.
- Documentation Delivery Run `30087475591` — PASS for the verified website artifact and credential boundary; production deployment was skipped by the existing credential gate.
- Review correction: canonical outbox test now asserts exact `NotifyPostOwner::class` (not merely `assertInstanceOf`), preventing proxy subclasses from satisfying the contract. Focused correction run passes 10 tests / 73 assertions.

## Acceptance Criteria

- [x] Digest server-generated key and generated-client idempotency option.
- [x] Comment transactional outbox registration and fixed child operation.
- [x] Application Notification store/operation/BFF/UI and recipient authorization.
- [x] Additive migration and 11-migration consumer synchronization.
- [x] PostgreSQL failure trigger matrix and source-deletion delivery rerun.
- [x] Full Framework PHPUnit/Mago/Deptrac and browser journey rerun.

## Remaining Issues

- No P19-007 blockers remain.

## Suggested Next Action

Proceed to P19-008 Consumer, Documentation, and Phase Closeout.

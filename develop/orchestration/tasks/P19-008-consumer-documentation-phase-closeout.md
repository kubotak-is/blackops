# P19-008: Consumer, Documentation, and Phase Closeout

Status: Completed

## Goal

P19-007までに実装したIdempotency、Transactional Outbox、Relay、Dead Letter再開、Operation Replay、Canonical Observer Replayを、配布可能なQuickstart／Skeleton、Community Board、BlackOps CLI、利用者向けGuide、内部Referenceへ同期する。Retention、Sensitive Data、Worker Reuse、Artifact、Migration Current Schemaを含む全回帰Gateを完走し、Phase 19のSpecification、Roadmap、TODO、Report、STATEをCompleteへCloseする。

既存の公開Entrypointは`php blackops`、正式呼称はBlackOps CLIとする。互換性のため`docs/guide/project-cli.md`のFilename／公開Slugは維持するが、読者向け本文、Link Label、Status、ReportでProject CLIを正式名称として使わない。

## In Scope

- QuickstartまたはPermanent FixtureによるIdempotency／Outbox／Relay／Replayの一続きのConsumer Evidence
- Skeleton／QuickstartのConfig、Migration、BlackOps CLI、Upgrade、Guide、Internal Reference同期
- Community Board Clean Install、Seed、Product、Digest、Notification、Browser Journey回帰
- Framework Full PHPUnit、Mago、Deptrac、Frontend、Package Export、Skeleton Publication Dry-run／Workflow、Documentation Website回帰
- Retention、Sensitive Data、Worker Reuse、Generated／Package Artifact、Migration Current Schema回帰
- Phase 19 Report、TODO、STATE、Reliability Specification、Delivery Plan、Roadmap、CHANGELOG／UPGRADE同期
- 古い「Outbox／Relay／Replayは未実装・後続」説明とPhase 19 Planned／In Progress表記の除去

## Out of Scope

- Framework Production `src/**`の新機能またはPublic API変更
- 新しいExecution Model、Message Bus、Exactly Once保証
- NotificationのEmail／Push／WebSocket配送、Read State、一般Inbox
- Stable Release、Version変更、Tag、Packagist、Skeleton Remote更新
- Documentation Website／Community BoardのExternal Publication／Deploy
- GitHub Actions Workflowの外部Credential／Cloudflare設定変更
- 互換性のため維持する`project-cli.md` Filename／公開Slug、Project Root `blackops` Entrypoint、Canonical Command名の変更

既存Capabilityの不足または仕様矛盾が判明した場合は`src/**`へ実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Relevant Specifications and Decisions

- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/orchestration/reports/P19-003-http-php-duplicate-lifecycle-retention.md`
- `develop/orchestration/reports/P19-004-transactional-outbox-persistence.md`
- `develop/orchestration/reports/P19-005-relay-runtime-and-blackops-cli.md`
- `develop/orchestration/reports/P19-006-canonical-observer-replay.md`
- `develop/orchestration/reports/P19-007-community-board-reliability-journey.md`

## Consumer and Documentation Contract

- Optional Idempotency Key付きInline／Deferred Requestは同じActor／InputへReplayし、KeyなしPathを変更しない。
- Raw Key、Credential、Sensitive Input、Canonical Fingerprint Input、SQL、Throwable DetailをStorage、Journal、Log、CLI、Browser Bundle、Generated Artifact、Reportへ残さない。
- Transactional OutboxはApplication Mutationと同一Named Connection／Transactionへ参加し、Relay停止中のPending、One-shot／Daemon、Retry／Dead Letter／Fencing、固定Child Identityを維持する。
- Operation Replay、Dead Letter再開、Canonical Observer Replayは異なるCommand／Identity／Audit境界として説明し、Exactly Onceと表現しない。
- Observer ReplayはCanonical Journalを変更せず、現在のSensitive Projectionを再適用し、at-least-once配送とCheckpoint／Resumeを説明する。
- RetentionはIdempotency、Outbox／Relay、Operation／Journalの各Policyを混同せず、Hold、Plan、Dry-run、Confirm、Auditを維持する。
- BlackOps CLIの`list`／`help`は重いRuntimeをEager構成せず、各Commandだけが必要Dependencyを解決する。
- Skeleton／QuickstartはCurrent Framework PackageからConfig／Migration／Stub／BlackOps CLIを利用し、Framework UpdateでApplication-owned Sourceを上書きしない。
- Documentation Websiteは`docs/guide/`だけを公開Sourceとし、`docs/internal/`、`develop/`、Task／Decision管理番号をArtifactへ含めない。

## Files Allowed to Change

- `examples/quickstart/**`
- `tests/Consumer/quickstart-*.sh`
- `tests/Consumer/skeleton-*.sh`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/community-board-*.sh`
- `tests/Fixture/**`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/**`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `develop/DOCS.md`
- `develop/TODO.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/orchestration/tasks/P19-008-consumer-documentation-phase-closeout.md`
- `develop/orchestration/reports/P19-008-consumer-documentation-phase-closeout.md`
- `develop/STATE.md`

`.github/workflows/**`、`src/**`、Framework Migration、Public API、Community Board Production Sourceは変更禁止とする。検証期待値に実装との不一致がある場合、許可範囲のConsumer／Documentation Fixtureだけを根拠付きで同期する。許可外Fileが必要な場合は変更せずBlockerを返す。

## Required Verification

1. Quickstart Setup／E2EでKeyなしInline／Deferred、Retention、Sensitive、Worker再起動を検証し、Permanent Framework FixtureでIdempotency／Outbox／Relay／Replayを検証する
2. Skeleton Create-project、Publication Dry-run／Workflow、Framework Update、Framework Package Exportを検証する
3. Community Board Foundation／Identity／Post Comment／Product／Digest／Browser／Clean Installを検証する
4. Community Board PHP、Frontend Check／Vitest／Build、Generated Client Freshnessを検証する
5. Full Framework PHPUnit、Mago format／lint／analyze、Deptracを検証する
6. Migration Status／Current Schema、additive up/down、Fresh Install、Seed idempotencyを検証する
7. Documentation Website Test／Check／Build、Navigation／Search／Artifact Guardを検証する
8. Raw Key／Credential／Sensitive／Management ID／Generated Artifact／Runtime Artifact Guardを検証する
9. すべての一時Directory、Process、Container、Volume、Generated Tree、Website Artifactを成功／失敗にかかわらずCleanupする

## Acceptance Criteria

- [x] Quickstart／Permanent FixtureでIdempotency、Outbox、Relay、Replayが一続きで完走する
- [x] KeyなしPath、Direct Deferred Transport、Authentication／Authorization、Worker Reuseが回帰しない
- [x] Retention、Hold、Crash／Lease／Fencing、Duplicate、Sensitive Failure Matrixが回帰しない
- [x] Skeleton／Config／Migration／Upgrade／Guide／Internal ReferenceがCurrent Capabilityへ同期する
- [x] 読者向け正式名称がBlackOps CLIへ統一され、互換Filename／Slugだけが維持される
- [x] Community Board Clean Install／Seed／Product／Digest／Notification／Browserが成功する
- [x] Framework Full PHPUnit、Mago、Deptrac、Frontend、Package／Publication Dry-run、Websiteが成功する
- [x] Generated、Package、Migration Current Schema、Sensitive、External Publication境界が固定される
- [x] Phase 19 Specification、Delivery Plan、Roadmap、TODO、Report、STATEがCompleteへ同期する
- [x] External Publication／Deploy、Stable Release、Tag、Remote Skeleton更新を行わない
- [x] `src/**`、Public API、Framework Migration、GitHub Actions Workflowへ差分がない
- [x] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/community-board-clean-install.sh
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/community-board-browser.sh
pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
pnpm --dir docs/website run test
pnpm --dir docs/website run check
pnpm --dir docs/website run build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart/app examples/community-board/app examples/community-board/tests --glob '*.php'
git diff --check
git status --short
```

実行時間と重複を抑えるため、個別Consumerと同じ境界をGitHub Actionsまたは既存のより広いScriptが完全に包含する場合は、Reportへ包含関係と結果を明記できる。未実行Commandは理由を明記し、Phase Acceptanceを推測でCheckしない。

## Expected Report

`develop/orchestration/reports/P19-008-consumer-documentation-phase-closeout.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Consumer／Failure Matrix
- BlackOps CLI／Skeleton／Upgrade同期
- Retention／Sensitive／Worker Reuse Evidence
- Migration／Artifact／Package Evidence
- Community Board Evidence
- Framework／Frontend／Website Full Gate
- Phase 19 Acceptance Criteria
- External Publication／Deploy Non-action
- Remaining Roadmap

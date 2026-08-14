# P20-016A Tenant Context and Propagation — Completion Report

## Summary

Public immutable `TenantRef(type, id)`とOptional Tenant Contextを追加し、HTTP、ConsoleCommand、Scheduled Operation、Public Root DispatcherからChild、Transactional Outbox、Deferred Worker、Retry、Lease Recoveryまで同一Tenantを不変伝播した。

Canonical JournalはTenant Identityを保持し、Observed Journal、JSONL、Default LogはRaw Tenant IDを出力しない。Provider FailureはTenantなしへFallbackせず、安全なFramework Errorへ縮約する。

## Changed Files

- Public API: `src/Core/TenantRef.php`、`src/Core/ExecutionContext.php`、`src/Execution/Dispatcher.php`
- Entry Ports: `src/Console/ConsoleTenantProvider.php`、`src/Scheduling/ScheduledTenantProvider.php`、HTTP Authentication／Request Handler／Deferred Acceptor
- Runtime Propagation: Execution Context Factory／Codec、Console／Scheduled Runtime、Inline／Deferred、Worker Lease Recovery、Transactional Outbox経由のChild Context
- Journal Boundary: `JournalOperation`、Journal Record Builder、Observed Projector、PostgreSQL Journal Codec
- Regression Tests: Core、HTTP、Console、Scheduled、Context Codec、Worker／Retry／Lease Recovery、Outbox、Journal／JSONL／PostgreSQL Codec
- Orchestration: Task Packet、Report、`develop/STATE.md`、`develop/TODO.md`

## Decisions and Assumptions

- `TenantRef`はTrim済みのOpaqueな`type`／`id`だけを保持し、Credential、Role、Permission、Plan、Membershipを持たない。
- `ExecutionContext`は末尾Optional Constructor ParameterとGetterだけを追加し、MutatorやChild Tenant Overrideを提供しない。
- `AuthenticationResult`はBuilt-in HTTP Tenant Sourceである。Application-owned Resolverを使う場合も、検証済み`TenantRef`をAuthentication後のRequestへ設定する責任はApplication側にある。
- Authentication Middlewareは上流で事前設定されたTenant Attributeを除去し、認証結果が返した検証済みTenantだけを再設定する。
- Console／Scheduled Tenant ProviderはActor Providerから独立し、未登録時だけTenantなしを許可する。Provider Errorや不正Runtime TypeはTenantなしへFallbackしない。
- Current SourceにはObserver Replayはあるが、Terminal Operation ReplayのPublic API／Runtime Call Siteは存在しない。推測したReplay APIは追加せず、将来はSpecification 99どおりAuthorization後に元Tenantを新Rootへ渡す。

## Commands and Results

- `docker compose run --rm app composer validate --strict`
  - PASS: `composer.json is valid`
- `docker compose run --rm app vendor/bin/phpunit`
  - PASS: 2019 tests、7981 assertions
  - Existing PHP 8.5 deprecation: 1
- `bash tests/Consumer/scheduled-operation.sh`
  - PASS: Tenant Provider未登録のScheduled Inline／Deferred Worker、Recovery、Concurrency Journey
- `docker compose run --rm app mago format --check src tests`
  - PASS
- `docker compose run --rm app mago lint`
  - Repository baselineでFAIL: 78 issues（9 errors、26 warnings、29 notes、14 help）
  - P20-016A changed sourceを対象にしたLintはPASS
  - `InlineDispatcher::dispatchEnvelope()`の既存Halstead warningだけは変更前から残る
- `docker compose run --rm app mago analyze`
  - PASS with 1 existing warning in unchanged `JsonlJournalRecordEncoder`
  - P20-016Aで一度検出したConsole Tenant Providerの`mixed-assignment`はtyped guardへ修正済み
- `docker compose run --rm app vendor/bin/deptrac`
  - BLOCKED before graph analysis: installed Deptrac vendor parserがPHP 8.5で`unexpected token '('`
- Management-ID Guard
  - PASS
- `git diff --check`
  - PASS

## Acceptance Criteria

- [x] TenantRefのPositive／empty／whitespace Matrix
- [x] Tenantあり／なしExecutionContextの不変性
- [x] Authenticated HTTPの検証済みTenant伝播とAnonymous／Invalid境界
- [x] Console／Scheduled Tenant ProviderのActor Providerからの独立
- [x] Public Root Dispatcherの末尾Optional Tenant
- [x] Child／Transactional Outboxの親Tenant継承とOverride禁止
- [x] Deferred Context round-tripとWorker／Retry／Lease RecoveryのTenant維持
- [x] Current SourceのTerminal Operation Replay不在監査と、推測APIを追加しない境界
- [x] Provider Failureのno-fallbackとSafe Error
- [x] Observed Journal／JSONL／Default LogからのRaw Tenant ID除外
- [x] Existing TenantなしConsumer JourneyとFull Suite
- [x] Report／STATE／TODO同期、Worker Commitなし

## Remaining Issues

- PostgreSQL Tenant Column／Query／MigrationはP20-016Cで扱う。
- Status／Journal／Outcome Read AuthorizationはP20-016Dで扱う。
- Encryption、Storage Key Provider、Protected Adapter、Rotation、Public GuideはP20-016BおよびP20-016E〜Hで扱う。
- Terminal Operation Replay Runtimeを将来追加するTaskは、Specification 99のTenant継承とAuthorization順序をAcceptanceへ含める。
- Broad Mago LintとDeptrac ParserはRepository既存品質課題であり、P20-016A差分のBlockerではない。

## Suggested Next Action

P20-016B Storage Protection Coreへ進み、XChaCha20-Poly1305 Envelope、Storage Purpose、Associated Data、Application-owned Storage Key Provider、Fail-closed Protection Errorを実装する。

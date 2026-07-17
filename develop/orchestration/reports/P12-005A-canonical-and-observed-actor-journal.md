# P12-005A: Canonical and Observed Actor Journal Report

Status: Accepted

## Summary

Public `JournalOperation`へOptional ActorContextを追加し、Journal Builderの共通経路から全Canonical Lifecycle Recordへorigin／authorization／execution ActorのIDとTypeだけを記録するようにした。PostgreSQL Canonical CodecはActorContextをRaw ID／TypeのままRound-tripし、Actor Field欠落の旧Payloadを維持する。Observed ProjectionはCanonical Operationを再利用せず、全Actor IDを`[masked]`へ置換し、JSONLへMask済みActorとType／null関係を出力する。

## Changed Files

- `src/Journal/JournalOperation.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- `tests/Journal/JournalRecordTest.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Internal/Projection/ObservedJournalRecordProjectorTest.php`
- `tests/Logging/JsonlJournalObserverTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/core-api.md`
- `docs/guide/security.md`
- `docs/internal/journal-record.md`
- `docs/internal/sensitive-projection.md`
- `docs/internal/postgresql-journal-store.md`
- `develop/orchestration/reports/P12-005A-canonical-and-observed-actor-journal.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `JournalOperation` Constructor末尾へ`?ActorContext $actorContext = null`を追加し、既存6引数構築をActorなしとして維持した。
- JournalRecordBuilderの`buildFromContext()`一箇所でActorContextをコピーすることで、Received、Accepted、Attempt、Rejected、Completed等の全Factory経路を同じCanonical規則へ揃えた。
- Canonical Codecは新規Encodeで`actors`を必ず出し、Actorなしは`null`とする。Decodeは`actors` Field欠落と`null`をActorContextなしとして扱う。
- Actor Contextはorigin／authorization／execution、Actorはid／typeの完全なField集合だけを許可する。欠落、余分なSecurity Field、不正型、空文字、前後空白を固定MessageのRuntime Errorで拒否し、DecodeによるActor値の変更を防いだ。
- Canonical CodecはActor IDをMaskしない。Observed Projectorだけが非null Actorを新しい`ActorRef('[masked]', $type)`へ置換し、Typeとorigin／authorizationのnullを維持する。
- JSONL EncoderはObserved Operationの`actors`を出力し、Actorなしでも`actors: null`を出す。Encoder自身はCanonical Dataを受け取らず、Framework Projection境界を前提とする。
- PostgreSQL Journal Record Codec内のActor Encode／Decodeは局所Callableへ集約し、Method数閾値を維持した。完全Field検証でClass Complexity閾値を超えるため、このCodec Classへだけ局所的なMago期待注釈を付けた。
- Record Schema Versionは後方互換なOptional Field追加として1のまま維持した。

## Commands and Results

```text
docker compose run --rm app mago format src tests
Result: Success。初回3 filesを整形し、最終実行では全FileがFormat済み。

docker compose run --rm app vendor/bin/phpunit tests/Journal/JournalRecordTest.php tests/Internal/Journal/JournalRecordFactoryTest.php tests/Internal/Projection/ObservedJournalRecordProjectorTest.php tests/Logging/JsonlJournalObserverTest.php tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php tests/Integration/MvpSampleEndToEndTest.php
Result: OK (41 tests, 213 assertions)。

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全FileがFormat済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (989 tests, 3192 assertions, Deprecations 0)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1840 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

初回対象TestはPostgreSQLからDecodeしたActorRefを入力Objectと同一Instanceとして比較したIntegration Testだけが失敗した。Canonical Round-tripの契約はID／Typeの値維持であるため値比較へ修正し、最終対象Testは成功した。初回LintはCodecの責務追加によるComplexity／Method閾値、初回Analyzeはnullable execution Actor推論等を検出した。局所Callableへの集約、Class単位のComplexity期待注釈、型明示で解消し、最終Required Commandsはすべて成功した。

## Acceptance Criteria

- [x] ActorContext付きEnvelopeから全Canonical Lifecycle RecordへActor ID／Typeが入る
- [x] ActorなしRecordと既存6引数JournalOperation構築が従来どおり動く
- [x] PostgreSQL Canonical JournalがActorContextをRound-tripする
- [x] Actor Field欠落の旧Canonical PayloadをDecodeできる
- [x] 余分なField、不正型、空Actor値をSafeにDecode拒否する
- [x] Observed ProjectionがActor IDをすべて`[masked]`にし、Actor Typeとnull関係を維持する
- [x] Observed ProjectionにRaw Actor ID、Credential、Permission Snapshotが含まれない
- [x] JSONL Operation Actor出力がMask済みである
- [x] Actor以外のCanonical／Observed Journal既存契約を維持する
- [x] Guide／Internal DocsがCanonical正本とObserved Maskの責任分界を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- Deferred WorkerのSystem execution Actor置換と再認可は後続TaskのScopeである。
- Retry／Backoff／Dead LetterのActor Context検証は後続TaskのScopeである。
- Canonical Journalの暗号化、Access Control、Retention変更はApplication／運用責務のままである。
- Blockerはない。

## Orchestrator Review

- Journal Builder共通経路からのCanonical Actor伝播、PostgreSQL Raw ID／Type Round-trip、旧actors欠落／null互換を確認した。
- Actor Context／Actor Objectの完全Field検証と、Observed Operation再構築による全Actor ID `[masked]`、Type／null維持を確認した。
- JSONLがMask済みActorだけを出力し、MVP End-to-endでもRaw Actor IDがObserved Surfaceへ出ないことを確認した。
- 対象PHPUnit 41 tests／213 assertions、Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`をOrchestratorが独立再実行し、すべて成功した。
- Acceptance Criteriaを満たすため、本TaskをAcceptedとする。

## Suggested Next Action

P12-005B Deferred Worker Reauthorization and System Actorへ進む。

# P16-002 Public Status Query Contract Report

Status: Accepted

## Summary

Database、HTTP、Frontendに依存しないPublic Status Query Contractを実装した。7 Stateの不変Status Aggregate、Found／Unavailable／Expired Result、Typed Outcome、Safe Terminal Error、専用Query Authorizer、既定Deny、Safe Query Exceptionを`BlackOps\Status`へ追加した。

Internal側は認可前の最小Subjectと認可後のDetailを別DTOにし、`findSubject -> authorize -> readDetail`の順序を`DefaultOperationStatusQuery`へ固定した。Unknown／Denyは同じUnavailable、Allow済みExpiredだけExpired、Allow済みAvailableだけDetailを読む。Source／AuthorizerのThrowableは元Detailを露出せず安定したPublic Codeへ正規化する。

## Changed Files

### Production

- `src/Status/OperationStatusState.php`
- `src/Status/OperationStatus.php`
- `src/Status/OperationStatusError.php`
- `src/Status/OperationStatusResult.php`
- `src/Status/OperationStatusFound.php`
- `src/Status/OperationStatusUnavailable.php`
- `src/Status/OperationStatusExpired.php`
- `src/Status/OperationStatusQuery.php`
- `src/Status/OperationStatusAuthorizer.php`
- `src/Status/OperationStatusAuthorizationRequest.php`
- `src/Status/OperationStatusAuthorizationDecision.php`
- `src/Status/DenyOperationStatusAuthorizer.php`
- `src/Status/Exception/OperationStatusQueryException.php`
- `src/Internal/Status/OperationStatusSubject.php`
- `src/Internal/Status/OperationStatusDetail.php`
- `src/Internal/Status/OperationStatusSource.php`
- `src/Internal/Status/OperationStatusSourceFailure.php`
- `src/Internal/Status/OperationStatusSourceException.php`
- `src/Internal/Status/DefaultOperationStatusQuery.php`
- `deptrac.yaml`

### Tests

- `tests/Status/OperationStatusStateTest.php`
- `tests/Status/OperationStatusTest.php`
- `tests/Status/OperationStatusAuthorizationTest.php`
- `tests/Status/OperationStatusResultAndExceptionTest.php`
- `tests/Internal/Status/DefaultOperationStatusQueryTest.php`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `docs/internal/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-002-public-status-query-contract.md`

## Decisions and Assumptions

- `OperationStatusQuery`はPublic Port、`DefaultOperationStatusQuery`はInternal実装とした。Public SignatureはInternal Source／DTOを露出しない。
- `OperationStatus`はPrivate ConstructorとState別Named ConstructorでField組合せを強制する。
- Operation Typeは既存`#[OperationType]`と同じ小文字Dot区切り形式を検証する。
- Rejected Category／Codeは既存Stable Codeと同じ安全なIdentifier形式を検証する。Failed／Dead Letteredは固定Code以外を生成できない。
- 認可前SubjectはOperation ID、Operation Type、Origin Actorまたはnull、Expired Evidenceの有無だけを持つ。Outcome、Terminal Error、Payload、Context、Journalは保持しない。
- SubjectのOperation ID不一致はAuthorizerへ渡す前にIntegrity Failureとする。DetailのOperation ID／Type不一致もIntegrity Failureとする。
- 分類されていないSource ThrowableはStorage FailureへFail-closedで正規化する。Authorizer ThrowableはDenyへ偽装せずAuthorization Failureにする。
- PostgreSQL Source Authority、Retention Evidenceの構成、Internal `supervising`のPublic `running`投影は後続Taskの責務として追加していない。

## Public API Shape

```text
OperationStatusQuery
  find(OperationId, ActorRef|null): OperationStatusResult

OperationStatusResult
  OperationStatusFound(status)
  OperationStatusUnavailable
  OperationStatusExpired

OperationStatusAuthorizer
  decide(OperationStatusAuthorizationRequest): OperationStatusAuthorizationDecision

OperationStatusAuthorizationRequest
  operationId
  operationType
  currentActor|null
  originActor|null

OperationStatusAuthorizationDecision
  allow
  deny
```

全Public型へ`#[PublicApi]`を付けた。既定`DenyOperationStatusAuthorizer`はCurrent Actorの有無に関係なくDenyする。

## State Invariant Matrix

| State | Attempt | Retry At | Outcome | Error |
| --- | --- | --- | --- | --- |
| `accepted` | - | - | - | - |
| `running` | 1以上 | - | - | - |
| `retry_scheduled` | 1以上 | UTC | - | - |
| `completed` | - | - | `Outcome` | - |
| `rejected` | - | - | - | Safe Category／Code |
| `failed` | - | - | - | `operation_failed` |
| `dead_lettered` | - | - | - | `operation_dead_lettered` |

Completedの既定Outcomeは`EmptyOutcome`である。`retryAt`は生成時にUTCへ正規化する。

## Authorization／Source Call Order Matrix

| 条件 | Call Order | Result |
| --- | --- | --- |
| Subjectなし | Subject | Unavailable |
| Subjectあり、Deny | Subject -> Authorizer | Unavailable |
| Expired Subject、Allow | Subject -> Authorizer | Expired |
| Available Subject、Allow | Subject -> Authorizer -> Detail | Found |
| Subject ID不一致 | Subject | Integrity Failure |
| Detail ID／Type不一致 | Subject -> Authorizer -> Detail | Integrity Failure |

## Safe Failure Matrix

| Failure | Public Code |
| --- | --- |
| Authorizer Throwable | `status_query.authorization_failed` |
| Source Storage Failure | `status_query.storage_failed` |
| Source Decode Failure | `status_query.decode_failed` |
| Source Integrity Failure | `status_query.integrity_failed` |
| 分類不能なSource Throwable | `status_query.storage_failed` |

Public Exception MessageはCodeだけを含む。Source／AuthorizerのMessage、SQL、Credential、Actor、Payloadは連鎖またはMessageへ残さない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Status \
  tests/Internal/Status \
  tests/Architecture/PublicApiArchitectureTest.php
Result: OK (42 tests, 171 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1370 tests, 5260 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2344 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: 該当なし。

git diff --check
Result: 成功。
```

実装途中の最初のTarget PHPUnitはTest内でException Objectを配列Keyにしたため1 Errorとなった。Pair Listへ修正後、TargetとFull Suiteを再実行して上記のとおり成功した。Magoの初回Format Checkは6 Fileを検出し、`mago format src tests`で整形後に再検査して成功した。

## Acceptance Criteria

- [x] 7 Stateを安定したString-backed Enumで表現した
- [x] Status AggregateがState別Fieldの組合せを強制する
- [x] Typed OutcomeとSafe Terminal Errorを表現できる
- [x] Found／Unavailable／Expiredを排他的なResultで表現する
- [x] 専用Query Authorizerと既定DenyをPublic Contractにした
- [x] Subjectなし／DenyではDetail Sourceを呼ばない
- [x] Allow後だけStatus DetailまたはExpiredを返す
- [x] Authorizer／Storage／Decode／Integrity FailureをSafe Codeへ正規化する
- [x] Actor、Payload、Context、Raw Error、Internal型をPublic Resultへ露出しない
- [x] PostgreSQL、HTTP、Frontend、Migrationを変更していない
- [x] Required PHP Quality Gateが成功した
- [x] WorkerはCommitしていない

## Remaining Issues

- PostgreSQLのOperations Row、Journal、Outcome Store、Dead Letter、Purge AuditからSubject／Detailを構成するAdapterは未実装である。
- Internal `supervising`からPublic `running`への投影は未実装である。
- HTTP Resource、Deferred 202 Header、Generated `.status()`／`.wait()`は後続Taskで扱う。

仕様矛盾とBlockerはない。

## Suggested Next Action

P16-003 Task Packetを作成し、PostgreSQL Status ProjectionとRetention Evidenceを実装する。

## Orchestrator Review

Public API Shape、State別Invariant、Subject -> Authorizer -> Detailの呼出順、Unknown／Denyの同一Unavailable、Allow後だけのExpired／Detail、Safe Failure変換を独立Reviewした。Public SignatureからInternal型、Payload、Context、Journal、Raw Throwableが露出しないことを確認した。Production差分はTask Packetの許可範囲内である。

次をOrchestratorが再実行し、すべて成功した。

```text
Composer Root／Quickstart: valid
Mago format／lint／analyze: 成功
Target PHPUnit: OK (42 tests, 171 assertions)
Full PHPUnit: OK (1370 tests, 5260 assertions)
Deptrac: Violations 0 / Warnings 0 / Errors 0
Management ID Guard: 該当なし
git diff --check: 成功
```

修正要求、仕様矛盾、Blockerはなく、P16-002をAcceptedとした。

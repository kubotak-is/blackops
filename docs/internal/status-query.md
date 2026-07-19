# Status Query Contract

Public Status Queryは、Operation IDから現在のLifecycle StateとTerminal Resultを安全に参照するためのPHP Contractである。Database、HTTP、Frontendから独立しており、Adapterは`BlackOps\Internal\Status\OperationStatusSource`を実装する。

## Public API

`BlackOps\Status\OperationStatusQuery`は次のResultのいずれかを返す。

| Result | 意味 |
| --- | --- |
| `OperationStatusFound` | 認可済みでStatusを取得できた |
| `OperationStatusUnavailable` | Unknown、Subject完全Purge、またはDeny |
| `OperationStatusExpired` | 認可済みでRetentionによる期限切れを証明できた |

`OperationStatus`はNamed Constructorでのみ生成できる。許可されるFieldはStateごとに固定される。

| State | `attempt` | `retryAt` | `outcome` | `error` |
| --- | --- | --- | --- | --- |
| `accepted` | - | - | - | - |
| `running` | Required | - | - | - |
| `retry_scheduled` | Required | Required | - | - |
| `completed` | - | - | Required | - |
| `rejected` | - | - | - | Safe category／code |
| `failed` | - | - | - | `operation_failed` |
| `dead_lettered` | - | - | - | `operation_dead_lettered` |

Attemptは1始まりで、`retryAt`はObject生成時にUTCへ正規化する。値のないCompletedは`EmptyOutcome`を使う。

## Authorization Boundary

Status参照はOperation実行時のAuthorizationとは別である。Applicationは`OperationStatusAuthorizer`を実装し、`OperationStatusAuthorizationRequest`のOperation ID、Operation Type、Current Actor、Origin ActorだけでAllow／Denyを判断する。

Frameworkの`DenyOperationStatusAuthorizer`は常にDenyする。Applicationが明示的に置き換えない限り、Status Detailは読まれない。

```text
Operation ID
  -> findSubject()
    -> not found: Unavailable
    -> Subject
      -> Authorizer
        -> Deny: Unavailable
        -> Allow
          -> readDetail()
            -> retained detail: Found
            -> proven retention: Expired
```

Subject DTOは認可に必要な最小情報だけを持ち、Outcome、Terminal Error、Payload、Context、Journalを持てない。Detail SourceはAllow後にだけ呼ばれる。Journalが存在するDetailでは、SubjectとJournalのOperation ID、Operation Type、Origin Actorのnull性・ID・Typeを厳密に照合し、不一致をIntegrity Failureにする。

Origin Actorを復元できない場合は`null`を渡す。Current Actorで補完してはならない。

## Safe Failure

Source Adapterは失敗を`OperationStatusSourceException`へ分類する。Default QueryはPublic `OperationStatusQueryException`へ次のように正規化する。

| Failure | Public code |
| --- | --- |
| Authorizer Throwable | `status_query.authorization_failed` |
| Storage failure、分類不能なSource Throwable | `status_query.storage_failed` |
| Decode failure | `status_query.decode_failed` |
| Subject／Detail不整合、Source integrity failure | `status_query.integrity_failed` |

Public ExceptionのMessageは安定Codeだけであり、元Exception、SQL、Credential、Actor ID、Payloadを含めない。Unknown、Deny、ExpiredはExceptionではなくResultとして返す。

## PostgreSQL Adapter

`PostgreSqlOperationStatusSource`は認可前のSubject読取と認可後のDetail読取を分離する。Subject QueryがPHPへ返す列は次だけである。

| Source | Projected fields |
| --- | --- |
| Operations Row | Operation ID、Operation Type |
| Canonical Journalの先頭Record | Operation ID、Operation Type、Origin Actor ID／Type |

Journal QueryはPostgreSQLのJSON Pathで必要な値だけを抽出する。`encoded_record`全体、Journal Data、Transport Payload、Encoded Context、State、Outcome、Purge AuditはSubject Resultへ含めない。Operations Rowだけが残る場合、Origin Actorは`null`である。Operations RowとJournalのType不一致やOrigin Actorの片側欠落はIntegrity Failureになる。

認可後にJournalのOrigin Actorが変化しても、認可時のSubjectとDetail Journalの照合で拒否する。Journal Retention済みでSubjectがOperations Rowだけから構成される場合は、SubjectとDetailの双方でActor不明のままとし、別Actorを推測しない。

DetailはSubjectが認可された後、同じDBAL Connection上の`REPEATABLE READ, READ ONLY`トランザクションで読む。AdapterはIsolation LevelとRead Onlyを検証し、既存の制御外Transactionには相乗りしない。成功時はCommit、失敗時はRollbackし、ConnectionへTransactionを残さない。これにより、Operations Row、Journal、Outcome、Dead Letter、Retention Auditの途中更新を混ぜたStatusを返さない。

## Source Authority

| Operation | State authority | Terminal detail authority |
| --- | --- | --- |
| Inline | Canonical Journal | Completed Outcome、Rejected Safe Reason、Failed Event |
| Deferred | Operations Row | CompletedはOutcome Store、RejectedはJournal、Failedは固定Code、Dead LetteredはRow／Purge Evidence |
| Deferred受付前Terminal | Canonical Journal | Rejected Safe ReasonまたはFailed Event |

DeferredではOperations RowのType、Schema Version、State、Next Sequence、Attempt、Retry時刻をJournalと照合する。Internal `supervising`はPublic `running`へ投影する。Public Stateは次のようになる。

| Internal state | Public state | Attempt／retryAt |
| --- | --- | --- |
| `accepted` | `accepted` | Attempt 0を内部検証、Public Fieldなし |
| `running` | `running` | Attempt 1以上 |
| `supervising` | `running` | Attempt 1以上 |
| `retry_scheduled` | `retry_scheduled` | Attempt 1以上、Operations Rowの`available_at`をUTCで返す |
| `completed` | `completed` | Registryの期待型と一致するTyped Outcome |
| `rejected` | `rejected` | Safe Category／Codeだけ |
| `failed` | `failed` | 固定`operation_failed` |
| `dead_lettered` | `dead_lettered` | 固定`operation_dead_lettered` |

Canonical JournalはSequence 1始まりの連番、Lifecycle遷移、Operation Identity、Attempt ID／Number／Started At、Retry Dataを検証する。検証できない欠落や矛盾を推測でPublic Stateへ丸めない。

## Retention Boundary

| Situation | Result |
| --- | --- |
| Allow後、Completed OutcomeがなくOutcome Purge Auditあり | Expired |
| Allow後、Rejected JournalがなくJournal Purge Auditあり | Expired |
| Failed JournalがPurge済み | Found、固定Failed Code |
| Dead Letter Journal／RowがPurge済みでAudit整合 | Found、固定Dead Letter Code |
| Transport PayloadだけPurge済み | Statusを継続して投影 |
| Evidenceなしの欠落、RowとPurge Auditの併存 | Integrity Failure |
| Subject Identityも完全にPurge済み | Unavailable |

Unknown／DenyではPurge Auditを読まないため、存在やRetention状態を推測できない。Dead LetterのReason Message、Raw Payload、Attempt ID、`purged_by`もPublic StatusまたはSafe Exceptionへ出さない。

## Adapter Rules

- `findSubject()`は認可前に呼ばれるため、最小Subject以外をSELECT／Decodeしない
- `readDetail()`は渡されたSubjectに対応するStatusだけを返す
- Retention Evidenceがない欠落をExpiredとして推測しない
- Database固有のExceptionやDTOをPublic Signatureへ露出しない
- Internal Diagnostics AggregateをPublic Statusへ直接返さない

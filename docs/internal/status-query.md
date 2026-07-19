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
        -> Allow + expired: Expired
        -> Allow + available
          -> readDetail()
            -> Found
```

Subject DTOは認可に必要な最小情報だけを持ち、Outcome、Terminal Error、Payload、Context、Journalを持てない。Detail SourceはAllow後にだけ呼ばれる。SubjectとDetailのOperation IDまたはOperation Typeが一致しない場合はIntegrity Failureになる。

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

## Adapter Rules

- `findSubject()`は認可前に呼ばれるため、最小Subject以外をSELECT／Decodeしない
- `readDetail()`は渡されたSubjectに対応するStatusだけを返す
- Retention Evidenceがない欠落をExpiredとして推測しない
- Database固有のExceptionやDTOをPublic Signatureへ露出しない
- Internal Diagnostics AggregateをPublic Statusへ直接返さない

PostgreSQL Source Authority、Retention Evidence、Internal `supervising`からPublic `running`への投影は後続Adapter Taskで実装する。

# D062: Retention Audit Log Delivery

Status: Decided

## Context

Retention PurgeはPayloadを含まないAudit RecordをDatabaseとSystem Logの両方へ残す。Database Audit Storeは実装済みだが、System Log配送と失敗時の処理が未確定だった。

System Logが失敗したままPurgeをCommitすると、データは消えているのに必要な外部監査証跡が欠落する。

## Decision

Database Audit Storeをprimaryとし、PSR-3 LoggerへPayloadなしの構造化Contextを配送する内部 `RetentionPurgeAuditPort` Decoratorを提供する。

System Log配送はfail-closedとする。Loggerが例外を投げた場合は成功扱いにせず、Purge Serviceへ失敗を伝播させる。Purge ServiceのDatabase Transaction内でDecoratorを使うことで、削除またはTombstone化とDatabase AuditをRollbackする。

System Log ContextはAudit ID、Operation ID、Target、Affected Count、Policy Reference、Purge Time、Actorだけを含む。Operation Payload、Journal本文、Outcome、Error本文、Credentialは含めない。

## Consequences

- System LogなしでRetention削除だけが成功する状態を防げる。
- Monolog JSONL Backendなど、Applicationが構成したPSR-3 Loggerを使える。
- System Log障害中はRetention実行も失敗するため、Scheduler／CLIは障害を可視化し、復旧後に再実行する必要がある。
- Database TransactionとSystem Logは分散Transactionではない。Log書き込み後のDatabase Commit失敗による過剰Logは得るが、ログなし削除は許容しない。

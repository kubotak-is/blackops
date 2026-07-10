# Retention Hold

Retention Holdは、特定OperationのRetention削除を明示的に停止するためのContractである。

Holdは通常のOperation Lifecycleから自動発生しない。権限を持つ管理者、Framework Maintenance Tool、または外部Compliance Systemが、調査や監査などの理由で明示的に設定する。

## Identifier

`RetentionHoldId` はRetention Hold専用のUUIDv7 Identifierである。

既存のFramework IDと同じく、小文字RFC 4122形式へ正規化し、UUID Version 7だけを受け入れる。Symfony UIDなどの具体Library型は公開APIへ露出しない。

## Actor Reference

`RetentionActorRef` はHold設定者または解除者を表す非空文字列Referenceである。

将来のActor Modelとは直接結合しない。現時点では、Actor ID、System Actor名、外部Compliance System名など、Credentialを含まない安定した参照だけを保持する。

## Hold Record

`RetentionHold` は次を保持する。

```text
hold_id
operation_id
category
reason
placed_at
placed_by
released_at nullable
released_by nullable
```

CategoryはLegal、Security、Audit、Support、Otherを扱う。

Hold解除は同一Recordの`released_at` / `released_by`を埋めた状態として表す。公開Objectはimmutableなので、`release()`は解除済みの新しいRecordを返す。

## Port

`RetentionHoldPort` はHoldの保存実装を抽象化する。

```text
place
release
activeFor
```

PostgreSQL Store、CLI、Purge Serviceとの接続は後続Taskで扱う。

## PostgreSQL Schema

`retention_holds` TableはOperationごとのHold設定と解除履歴を保持する。

```text
hold_id
operation_id
category
reason
placed_at
placed_by
released_at nullable
released_by nullable
created_at
```

`operation_id` はOperations Tableへ `ON DELETE RESTRICT` で参照する。Cascade Deleteは使わない。

Active Holdは `released_at IS NULL` で判定できる。解除済みHoldも履歴として残す。

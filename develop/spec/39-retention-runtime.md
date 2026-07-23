# Retention Runtime

## MVP範囲

MVPは次を実装する。

- Retention Schema
- Policy評価
- Dry Run
- 手動Purge CLI
- Framework Maintenance Scheduler Worker
- Retention Hold
- Purge Audit
- Idempotency Record独立Retention

## CLI

```text
blackops retention:plan
blackops retention:purge --dry-run
blackops retention:purge --confirm
blackops scheduler:run
```

Retention TaskはScheduler Workerへ既定登録する。

Scheduler WorkerはScheduled Operation Strategyとは別のFramework保守Runtimeである。

## Policy

Retention期間に暗黙の既定値を設けない。

ProductionではTransport Payload、Canonical Journal、Outcome、Dead LetterのPolicyを明示設定する。Idempotency Record期間はOptionalで、未指定時は既存4期間の最長値を使う。未設定時は起動時またはManifest Compile時に警告し、Purgeを実行しない。

## Retention Hold

`retention_holds` Tableで設定と解除の履歴を保持する。

```text
retention_holds
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

権限を持つActorまたは外部Compliance Systemによる明示設定だけを許可する。Failed／Dead Letteredを理由とする自動Holdは行わない。

## Purge Audit

Purge結果は削除対象自身のJournalへ記録しない。

対象Operation ID、対象種別、件数、Policy、実行時刻、実行Actorを、Payloadを含まないRetention Audit Recordとして別TableとSystem Logへ記録する。

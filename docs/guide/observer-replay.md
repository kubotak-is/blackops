# Observer Replay

`journal:observer:replay` は、Canonical Journal の Record を指定した Observer へ現在の Projection で再配送する BlackOps CLI です。通常の Journal Observation や Outbox Retry とは別の、明示的な運用操作として実行します。

## Selector と実行モード

Selector は次のいずれか一つだけを指定します。

- `--operation-id=<uuid>`: Operation の `sequence, record_id` 順
- `--record-id=<uuid>`: 一つの Canonical Record
- `--from=<RFC3339> --to=<RFC3339>`: UTC へ正規化した `[from,to)` の範囲

Observer は `--observer=<stable-name>` を一つ以上指定します。新規実行では `--checkpoint=<id>`、`--actor=<operator>`、`--reason=<reason>` も必須です。Checkpoint ID は小文字英数字のセグメントを `.`、`_`、`-` でつないだ 1〜128 バイトの値に限られます。Batch は `--batch-size` で指定でき、範囲は 1〜1000 です。

```bash
php blackops journal:observer:replay \
  --operation-id=019f32ab-2be0-7b38-a0a7-1ab2f9687697 \
  --observer=application-jsonl --batch-size=100 --dry-run

php blackops journal:observer:replay \
  --from=2026-07-01T00:00:00Z --to=2026-07-02T00:00:00Z \
  --observer=application-jsonl --checkpoint=journal-replay-20260701 \
  --actor=operator --reason="restore projection" --confirm
```

`--dry-run` と `--confirm` は必ずどちらか一つだけを選びます。Dry-run は Selector と Target を検証し、安全な件数、先頭／末尾 Record ID、`has-more` だけを表示します。Observer、Audit、Checkpoint、Canonical Journal への Write は行いません。

実行途中で停止した場合は、Selector と Observer を再指定せず、保存済み Binding を使って新しい Actor／Reason で再開します。

```bash
php blackops journal:observer:replay \
  --resume=journal-replay-20260701 \
  --actor=operator --reason="resume after maintenance" --confirm
```

## Identity と安全な監査

Replay は Canonical `recordId`、Operation ID、Sequence、Occurred At を維持します。JSONL Envelope にも同じ `recordId` が含まれるため、Target は Record ID を冪等性キーとして扱えます。配送保証は at-least-once です。Observer が受け付けた直後に Process が落ちた場合、Resume で同じ Record ID が再配送されることがあります。

Audit には安全な Selector 境界（Operation／Record ID または時刻範囲）、Target 名、Operator Actor／Reason、Invocation ごとの件数、時刻、Version 付き Failure Fingerprint だけを保存します。Canonical Payload、Projection Data、Canonical Actor ID、Credential、SQL、Throwable の Message／Trace は保存せず、CLI 出力や例外にも漏らしません。

Replay Source は Canonical Journal を SELECT するだけです。Canonical Row の Append／Update／Delete や Lifecycle Record の追加は行いません。

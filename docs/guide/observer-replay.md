# Observer Replay

`journal:observer:replay` は、Canonical Journal の Record を指定した Observer へ現在の Projection で再配送する BlackOps CLI です。通常の Journal Observation や Outbox Retry とは別の、明示的な運用操作として実行します。

これは完了済みOperationをHandlerへ再実行するOperation Replayではありません。Journalの構造とCanonical／Observedの境界は[Journal](journal.md)を参照してください。

## 実行場所と準備

ApplicationのProject Rootで、Application-owned `config/journal.php`とObserver設定を確認します。Hostでは`php blackops`を使い、Containerでは同じCommandを`docker compose run --rm app`へ渡します。対象Database、Actor、ReasonをApplicationの運用Policyで決め、CredentialやProtected PayloadをShellへ書きません。

## Selector と実行モード

Selector は次のいずれか一つだけを指定します。

- `--operation-id=<uuid>`: Operation の `sequence, record_id` 順
- `--record-id=<uuid>`: 一つの Canonical Record
- `--from=<RFC3339> --to=<RFC3339>`: UTC へ正規化した `[from,to)` の範囲

Observer は `--observer=<stable-name>` を一つ以上指定します。Checkpoint ID は小文字英数字のセグメントを `.`、`_`、`-` でつないだ 1〜128 バイトの値に限られます。Batch は `--batch-size` で指定でき、範囲は 1〜1000 です。

新規実行の `--dry-run` は Selector と Observer だけを指定します。Checkpoint、Actor、Reasonは不要です。新規実行を適用する `--confirm` では、これら三つをすべて指定します。

```bash
php blackops journal:observer:replay \
  --operation-id=019f32ab-2be0-7b38-a0a7-1ab2f9687697 \
  --observer=application-jsonl --batch-size=100 --dry-run

php blackops journal:observer:replay \
  --from=2026-07-01T00:00:00Z --to=2026-07-02T00:00:00Z \
  --observer=application-jsonl --checkpoint=journal-replay-20260701 \
  --actor=operator --reason="restore projection" --confirm
```

`--dry-run` と `--confirm` は必ずどちらか一つだけを選びます。Dry-runの出力は `selected`、`delivered`、`failed`、`has-more`、`complete` と、候補がある場合の `first-record-id`／`last-record-id` です。出力にCheckpoint、Actor、Payloadは含みません。また、Observerへの配送、Audit、Checkpoint、Canonical JournalへのWriteは行いません。Exit Codeは0です。

Confirmの出力は同じ件数に加えて `checkpoint` を含み、配送後の `delivered`、`failed`、`has-more`、`complete` と先頭／末尾Record IDを返します。Checkpoint保存とObserver配送を行い、Canonical Journalは変更しません。

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

配送が失敗した場合はFailure FingerprintとCheckpointだけを確認し、SelectorやRecordを直接変更せず、原因を修正して`--resume`で再実行します。

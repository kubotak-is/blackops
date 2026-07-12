# D070: Quickstart Journal Observer

Status: Proposed

## Context

P7-006のLocal Runtime and Consumer E2Eは、Inline／Deferred／Worker／Retry／Outcomeに加えてSensitive Projectionを検証する。

Canonical JournalはPostgreSQLへ保存されるが、Sensitive Mask済みのObserved Journalを出力する `JournalObservationPipeline` は現在のPublic `Application::http()` Compositionへ接続されていない。`examples/quickstart/config/journal.php` も空Placeholderである。

既存のPublic `JsonlJournalObserver` はMask済み `ObservedJournalRecord` をJSON LinesとしてResourceへ書ける。Internal PipelineはObserverごとにBest EffortまたはRequiredのDelivery Policyを扱える。

QuickstartからInternal Pipelineを生成せず、FrameworkがAccepted ConfigからLocal JSONL Observerを構成するContractを決定する。

## Question 1: Quickstart Observed Journal Backend

QuickstartのSensitive Projectionをどこへ出力するか。

### Options

- A: `config/journal.php` でJSONL Fileを有効化し、既定Pathを `var/log/journal.jsonl` とする
- B: QuickstartはObserved Journal Backendを持たず、Sensitive Projectionの検証をFramework内部Testだけへ残す
- C: Application独自Service ProviderがJournal Observerを生成し、Frameworkへ任意Objectを渡すPublic Extension APIを追加する

### Recommendation

Aを推奨する。

Install直後のLocal RuntimeでMask結果を直接確認でき、QuickstartにInternal Compositionを持ち込まない。P7-006ではFramework所有のConfig Validator／ComposerがPathとPolicyを読み、JSONL ObserverとSensitive Projection Pipelineを内部構成する。

[ANSWER]



[/ANSWER]

## Question 2: Delivery Failure Policy

Local JSONL Observerの書込失敗がOperationを失敗させるか。

### Options

- A: Best Effortを既定とし、JSONL出力失敗でCanonical Operationを失敗させない
- B: Requiredを既定とし、JSONL出力失敗時はOperationも失敗させる
- C: Configで必須選択にし、Framework Defaultを持たない

### Recommendation

Aを推奨する。

Canonical JournalはPostgreSQLが正本であり、Quickstart JSONLはLocal観測用Projectionである。Local Log Fileの一時的な失敗で業務Operationを止めない。Best EffortでもMask前のCanonical ValueをJSONLへ出さず、Observerへ渡す前にSensitive Projectionを適用する。

Configは将来Requiredを選べる形にできるが、P7-006のQuickstart DefaultはBest Effortとする。

[ANSWER]



[/ANSWER]

## Proposed Configuration

両方Aの場合は次を正本とする。

```php
return [
    'jsonl' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/var/log/journal.jsonl',
        'delivery' => 'best_effort',
    ],
];
```

Pathは絶対Path、`enabled` はBoolean、Deliveryは `best_effort` または `required` とする。FrameworkはDirectoryを暗黙作成せず、Deployment／Post-create／手動Setupが事前準備する。

HTTP RuntimeはFileをAppend Modeで開き、Inline Journal Observationへ同じObserverを接続する。Canonical JournalとObserved JSONLの責務は分離し、JSONLへPassword、API Token、Sensitive HeaderのRaw値を含めない。

Worker側Observed ProjectionのProcess LifecycleとFlushは、必要な場合に後続Taskで拡張する。P7-006のSensitive Projection E2EはInline WelcomeのHeader Maskを対象とする。

## Decision

[DECISION]

回答待ち。

[/DECISION]

## Consequences

[CONSEQUENCES]

回答後に確定する。

[/CONSEQUENCES]

## References

- [Installed Application Boundary](../spec/42-installed-application-boundary.md)
- [Phase 7 Delivery Plan](../spec/45-phase-7-delivery-plan.md)
- [JSONL Journal Observer](../../docs/internals/jsonl-journal-observer.md)
- [Log Delivery and Retention](015-log-delivery-and-retention.md)
- [Journal Ports](032-journal-ports.md)

# D045: Retention MVP Scope

Status: Decided

## Context

Retention Policy、Tombstone、Restrict外部キー、Legal Holdの概念を決定した。次に、MVPでどこまで実装するか、既定Policy、Legal Holdの保存形式、削除操作の監査方法を決める。

## Question 1: MVP実装範囲

### Options

- A: SchemaとPortだけ用意し、実際のPurge処理はMVP後にする
- B: Schema、Policy評価、Dry Run、手動Purge CLIまでMVPへ含める
- C: Schedulerを含む完全自動削除までMVPへ含める

### Recommendation

Bを推奨する。

```text
blackops retention:plan
blackops retention:purge --dry-run
blackops retention:purge --confirm
```

自動Schedulerを増やさず、Retention設計が実際に安全に動くことまで検証できる。

[ANSWER]

C
ユーザーに提供するスケジュールワーカーにデフォルトでこのコマンドが入ってる状態

[/ANSWER]

## Question 2: 既定Policy

### Options

- A: 自動削除の既定値を持たず、Productionでは明示設定を要求する
- B: Frameworkが全用途共通の日数を決める
- C: 無期限保持を暗黙の既定値とする

### Recommendation

Aを推奨する。

法令、監査、個人情報、業務要件によって適切な期間が異なる。Production Modeでは各Policyが未設定なら起動時またはManifest Compile時に警告し、Purgeは実行しない。

[ANSWER]

A

[/ANSWER]

## Question 3: Legal Holdの保存

### Options

- A: `legal_holds` Tableで設定・解除履歴を保持する
- B: Operations TableのBoolean一つだけで表す
- C: Config Fileだけで管理する

### Recommendation

Aを推奨する。

```text
legal_holds
  hold_id
  operation_id
  reason
  placed_at
  placed_by
  released_at nullable
  released_by nullable
```

誰が、なぜ、いつHoldまたは解除したかを追跡できる。

[ANSWER]

A
legal_holdsってどんなタイミングで発生するもの？

[/ANSWER]

### Follow-up 3-1: Holdが発生するタイミング

Holdは通常のOperation Lifecycleから自動発生するものではない。保持期限が来ても特定Operationの削除を止める必要が生じたとき、権限を持つ管理者や外部Compliance Systemが明示的に設定する。

例：

- 訴訟、法的照会、捜査対応のため証拠を保全する
- Security Incidentや不正利用を調査する
- 会計・規制監査の対象になった
- Customer Support上の重大な紛争を調査する
- Dead Letterの原因調査中にPayloadやJournalの消去を防ぐ

Hold中は期限を過ぎてもRetention Schedulerが対象Dataを削除しない。調査終了後は、権限を持つActorが明示的に解除する。

Frameworkが `operation.failed` や `operation.dead_lettered` を理由に自動でHoldすると、解除されないDataが蓄積し続けるため、既定では自動設定しない。Applicationは必要であればJournal Eventを契機に管理者へ通知し、その後にCLIまたはPort経由で設定する。

法律上の保全だけでなく調査上の一時保全にも使うため、Table名を一般化する案がある。

```text
retention_holds
  hold_id
  operation_id
  category          legal / security / audit / support / other
  reason
  placed_at
  placed_by
  released_at nullable
  released_by nullable
```

### Question

Holdの名称と発生方法をどうするか。

### Options

- A: `retention_holds` とし、権限を持つActorによる明示設定だけを許可する
- B: `legal_holds` のまま法律上の保全だけに限定する
- C: Failed／Dead Lettered時にFrameworkが自動でHoldする

### Recommendation

Aを推奨する。法律、Security、監査、Supportの用途を一つの安全な削除停止機構で扱え、意図しない無期限保持も避けられる。

[ANSWER]

A

[/ANSWER]

## Question 4: Purgeの監査

### Options

- A: Payloadを含まないRetention Audit Recordを別TableとSystem Logへ残す
- B: 削除対象のJournal内へPurge Eventを追加してから削除する
- C: Purge履歴を残さない

### Recommendation

Aを推奨する。

削除対象自身へ記録すると、そのRecordも同時に消え得る。対象Operation ID、対象種別、件数、Policy、実行時刻、実行Actorだけを別のRetention Auditへ残す。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

MVPにはSchema、Policy評価、Dry Run、手動Purge CLIに加え、Retentionを定期実行するFramework Maintenance Scheduler Workerを含める。

```text
blackops retention:plan
blackops retention:purge --dry-run
blackops retention:purge --confirm
blackops scheduler:run
```

Retention TaskはScheduler Workerへ既定登録する。ただしRetention期間に暗黙の既定値を設けず、Policy未設定時は警告してPurgeを実行しない。Productionでは明示設定を要求する。

Holdは `retention_holds` Tableで設定・解除履歴を保持する。CategoryはLegal、Security、Audit、Support、Otherを扱い、権限を持つActorまたは外部Compliance Systemによる明示設定だけを許可する。Failed／Dead Letteredを理由とする自動Holdは行わない。

Purge結果は削除対象自身のJournalではなく、Payloadを含まないRetention Audit Recordとして別TableとSystem Logへ記録する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Retentionを設計だけでなく定期実行までMVPで検証できる。
- Scheduler WorkerはScheduled Operation Strategyとは別のFramework保守Runtimeとなる。
- Policy未設定による意図しない自動削除を防げる。
- 法務以外のSecurity、監査、Support調査でも削除停止を利用できる。
- Holdの設定と解除をActor、理由、時刻付きで監査できる。
- 削除対象Dataが消えた後もPurge操作の証跡を残せる。
- MVP Scopeが拡大するため、Retention実装はCore Vertical Slice完成後の独立Phaseとして扱う。

[/CONSEQUENCES]

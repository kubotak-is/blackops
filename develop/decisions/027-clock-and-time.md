# D027: Clock and Time

Status: Decided

## Context

Operation受付時刻、Attempt開始時刻、Journal発生時刻、Deadline等で時刻を扱う。直接 `new DateTimeImmutable()` を呼ぶとTestの再現性が下がり、Timezoneや文字列精度が場所ごとにずれる。

時刻の取得Contract、内部表現、永続化形式を統一する。

## Question 1: Clock Contract

### Options

- A: PSR-20 `Psr\Clock\ClockInterface` を採用する
- B: BlackOps独自の `Clock` Interfaceを定義する
- C: 必要な場所で `DateTimeImmutable` を直接生成する

### Recommendation

Aを推奨する。

標準Contractを利用でき、Test用Clockも差し替えやすい。BlackOpsの公開APIへ独自の薄い抽象を増やさずに済む。

[ANSWER]

A

[/ANSWER]

## Question 2: 内部表現とTimezone

### Options

- A: `DateTimeImmutable` を使い、生成時に必ずUTCへ正規化する
- B: Unix Timestampの整数で保持する
- C: Timezoneを保持した `DateTimeImmutable` をそのまま使う

### Recommendation

Aを推奨する。

PHP内では型の意味を保ち、Process・Region間ではUTCに統一する。利用者の表示Timezoneへの変換はFramework Coreの責務にしない。

[ANSWER]

A

[/ANSWER]

## Question 3: Journal等の文字列表現

### Options

- A: UTCのRFC 3339文字列、マイクロ秒6桁、末尾 `Z` に統一する
- B: UTCのRFC 3339文字列、ミリ秒3桁に統一する
- C: Unix Timestampと小数部で記録する

### Recommendation

Aを推奨する。

```text
2026-07-02T12:34:56.123456Z
```

JSON Lines、OTel Adapter、Database間で可読性と精度を保てる。Timestampは観測時刻であり、一意性や厳密な並び順の保証には使用しない。

[ANSWER]

A

[/ANSWER]

## Question 4: 順序の扱い

同じ時刻や時計の巻き戻りがあり得るため、TimestampだけではJournal Recordの厳密な順序を保証できない。

### Options

- A: Timestampは観測用とし、Lifecycleの順序は別の順序情報または状態遷移規則で扱う
- B: UUIDv7の辞書順を厳密な順序として扱う
- C: Timestampだけを順序の正本とする

### Recommendation

Aを推奨する。UUIDv7も分散Process全体の厳密な順序を保証しない。必要な順序情報の具体形はJournal Schema設計で決める。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

時刻取得にはPSR-20 `Psr\Clock\ClockInterface` を採用し、FrameworkおよびAdapterへDIする。Framework内部で現在時刻を直接生成しない。

PHP内部の時刻表現には `DateTimeImmutable` を使用し、生成時にUTCへ正規化する。

Journal、Log、Transport等の文字列表現は、UTCのRFC 3339形式、マイクロ秒6桁、末尾 `Z` に統一する。

```text
2026-07-02T12:34:56.123456Z
```

Timestampは観測時刻であり、一意性または厳密な順序の正本として使用しない。Lifecycleの順序は別の順序情報または状態遷移規則で扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- TestでClockを固定し、時刻依存処理を再現できる。
- Process、Host、Region間の時刻表現をUTCへ統一できる。
- 表示用Timezoneへの変換はFramework Coreの責務に含めない。
- JSON Lines、Database、Transport、Observerで同じ時刻Codecを共有する。
- Clockの巻き戻り、同一Timestamp、分散Processを考慮し、TimestampやUUIDv7の辞書順だけにLifecycle順序を依存しない。
- Journal Schemaで明示的な順序情報または順序復元規則を定義する。

[/CONSEQUENCES]

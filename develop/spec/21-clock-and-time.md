# Clock and Time

## Clock

時刻取得にはPSR-20 `Psr\Clock\ClockInterface` を採用し、FrameworkおよびAdapterへDIする。

Framework内部で現在時刻を直接生成しない。TestではClockを固定または制御可能な実装へ差し替える。

## PHP内部表現

時刻は `DateTimeImmutable` で保持し、生成時にUTCへ正規化する。

利用者向け表示Timezoneへの変換はFramework Coreの責務に含めない。

## 文字列表現

Journal、Log、Transport等では、UTCのRFC 3339形式、マイクロ秒6桁、末尾 `Z` に統一する。

```text
2026-07-02T12:34:56.123456Z
```

共通の時刻Codecを使用し、Adapterごとの形式差を作らない。

## 順序との分離

Timestampは観測時刻であり、一意性または厳密な順序の正本として使用しない。

同一Timestamp、Clockの巻き戻り、分散Processを考慮し、Lifecycle順序は別の順序情報または状態遷移規則で扱う。UUIDv7の辞書順も厳密な分散順序として扱わない。

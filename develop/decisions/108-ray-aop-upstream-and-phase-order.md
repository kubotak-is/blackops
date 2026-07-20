# D108: Ray.Aop Upstream and Phase Order

Status: Proposed

## Context

P17-007Aで、Transactional Operationのclass-level Attributeへ複数のtyped `::class`引数を置くとRay.AopのBuild-time Proxy生成が壊れることを確認した。

```php
#[ExecuteWith(Deferred::class)]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class GenerateWeeklyDigest implements Operation
```

Ray.Aop 2.19.1と2.20.0の両方で再現する。Tokenizerが`Deferred::class`の`T_CLASS`を実class declarationと誤認し、次のAttribute名を次のように壊す。

```php
#[Authorize999 extends Authorize (FixtureAuthorizationPolicy::class)]
```

P17-007は、Security Policyをtypedに維持し、metadata-onlyのStrategyだけをliteral class-stringにする回避で動作している。

```php
#[ExecuteWith('BlackOps\\Core\\Execution\\Deferred')]
#[Authorize(AuthenticatedUserPolicy::class)]
```

Released Dependencyだけでは解決できない。BlackOps内で吸収するにはSource加工、Vendor Compiler複製／Fork、またはProxy Reflection Semantics変更が必要であり、現在のSafety Boundaryでは採用しない。

## Question 1: Phase 17の進行

### Options

- A: P17-007のliteral Strategy回避を一時維持し、P17-008 Visual Designへ進む。P17-007AはRay.Aop Stable Release修正待ちとして追跡する
- B: Phase 17を一時停止し、Ray.Aop upstreamでの解決または新しいStable Releaseを待つ
- C: BlackOps内のSource加工／Vendor Fork相当の修正を許可し、typed表記をただちに優先する

### Recommendation

Aを推奨する。

回避はBuild-timeに成功し、Operation Metadata、Authorization、Deferred Runtime、Transaction、E2Eの挙動を変えない。Visual DesignとAccessibilityはこのTokenizer修正に依存しないため、Phase 17を停める必要はない。CはDependency境界を壊し、将来のUpdate Costを高める。

[ANSWER]


[/ANSWER]

## Question 2: Upstreamへの外部Action

### Options

- A: Ray.Aop公式Repositoryへ、Credential／BlackOps固有情報を含まない最小再現とRoot CauseをIssueとして報告する。PRはMaintainerの反応後に別途判断する
- B: Issueに加え、Ray.Aop側のTokenizer修正とRegression TestをPRとしてすぐ提案する
- C: Repository内Report／TODOだけで追跡し、外部Issue／PRは作成しない

### Recommendation

Aを推奨する。

原因と最小再現は特定済みであり、upstreamで修正するのが最も安全である。ただしIssue作成は外部への書き込みになるため、明示的な許可後に実行する。PRはMaintainerの期待する修正形式を確認してから切り出す。

[ANSWER]


[/ANSWER]

## Proposed Consequences

- Question 1=Aなら、P17-008以降を先行し、literal StrategyはKnown Dependency Workaroundとして限定的に維持する
- Question 1=Bなら、Phase 17はP17-007 Acceptedのまま停止し、P17-007Aの再開を待つ
- Question 1=Cなら、Dependency Fork／Source加工の保守責任とUpdate Policyを追加Decisionで先に定義する
- Question 2=A／BはGitHubへの外部書き込みを含み、本Decisionの回答をその実行許可とする
- Stable Releaseで修正された後、Root Regression、Community Board compile／Digest E2Eを実行してliteral workaroundを削除する

## Traceability

- Decision: [D096 Phase 13 Database and Transaction Runtime](096-phase-13-database-and-transaction-runtime.md)
- Accepted Feature: [P17-007 Report](../orchestration/reports/P17-007-deferred-digest-and-progress.md)
- Blocked Investigation: [P17-007A Report](../orchestration/reports/P17-007A-aop-class-constant-attributes.md)
- Tracking: [TODO](../TODO.md)

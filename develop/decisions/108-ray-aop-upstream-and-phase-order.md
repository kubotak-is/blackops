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

Released Dependencyだけでは解決できない。P17-007Aの限定ScopeでBlackOps内に吸収するにはSource加工、Vendor Compiler複製／Fork、またはProxy Reflection Semantics変更が必要であり、現在のSafety Boundaryでは採用しない。

## Alternative Audit

Ray.Aopを外しても、Operation自身の`#[Transactional]`は維持できる。OperationはFrameworkが所有するLifecycle Entry Pointを通るため、既存のTransaction RuntimeでHandlerと成功Terminal Journal／Outcomeを同じTransactionへ含められる。

一方、任意のDI管理ServiceへAttributeを付けるだけでPublic MethodをInterceptする現在の使用感は、PHPとSymfony DIだけでは自動的に得られない。次の選択肢がある。

### Framework-owned Build-time Proxy

BlackOpsが`#[Transactional]`と`#[AfterCommit]`だけに用途を限定したSubclass ProxyをBuild時に生成し、Symfony DIへ登録する。Runtime Source Scanは不要で、現在のTransaction RuntimeとAttributeを再利用できる。

ただし、Method Signature、継承、`readonly`、参照、Variadic、Union／Intersection Type、Default Value、Attribute、`final`制約、生成物の更新と削除をBlackOpsが継続して正しく扱う必要がある。これは小さなTokenizer回避ではなく、独立した設計・実装Phaseとして扱うべき変更である。汎用AOP Engineは作らず、Database Transactionの二つのAttributeだけを対象にする。

### Explicit Transaction API

任意ServiceのAOPを廃止し、Operationだけは現在のAttributeとLifecycle保証を維持する。一般Serviceは`TransactionManager::transactional()`とAfter Commit登録APIを明示的に呼ぶ。

Proxy生成が不要になり最も単純で堅牢だが、D096で選んだ「Application Service／Command ServiceへAttributeを付けるだけ」というDeveloper Experienceを変更する。既存利用者向けのMigrationとPublic API Decisionが必要になる。

### Interface Decorator

Symfony DIのService DecorationでInterface単位のWrapperを登録する。Proxy Code Generationは不要だが、対象ServiceごとのInterfaceとBindingが必須になり、任意のConcrete ClassをAttributeだけでInterceptする現在のContractは維持できない。

### Another AOP／Proxy Dependency

別のAOP／Proxy Libraryへ置き換える方法もあるが、Build-time-only、Symfony DI、Attribute Semantics、`readonly`、現在のPHP Versionを同時に満たすかを新たに検証する必要がある。Ray.AopのTokenizer問題を別Dependency Riskへ置き換えるだけになる可能性があるため、即時回避とはしない。

## Question 1: Phase 17の直近進行

### Options

- A: P17-007のliteral Strategy回避を一時維持し、P17-008 Visual Designへ進む。AOPの長期方針はQuestion 2として独立して決める
- B: Phase 17を一時停止し、Ray.Aop upstreamでの解決または新しいStable Releaseを待つ
- C: Phase 17を一時停止し、Framework-owned Build-time Proxyの設計を先に始める

### Recommendation

Aを推奨する。

回避はBuild-timeに成功し、Operation Metadata、Authorization、Deferred Runtime、Transaction、E2Eの挙動を変えない。Visual DesignとAccessibilityはAOP Engineの選択に依存しないため、長期方針を検討しながらPhase 17を進められる。

[ANSWER]

上記Alternative Auditへ反映した。Ray.Aopを外すことは可能。ただし、現在のAttributeだけで任意ServiceをInterceptする使用感を維持する場合は、BlackOps所有のBuild-time Proxy生成が必要になる。

[/ANSWER]

## Question 2: AOPの長期方針

### Options

- A: 当面Ray.Aopと限定的なliteral workaroundを維持する。Stable修正を追跡し、Framework-owned Proxyは作らない
- B: Ray.Aopを将来削除し、`#[Transactional]`／`#[AfterCommit]`専用のFramework-owned Build-time Proxyを独立Phaseで設計・実装する
- C: Ray.Aopと任意Service AOPを将来削除する。OperationはAttributeとLifecycle保証を維持し、一般Serviceは明示的なTransaction／After Commit APIへ移行する
- D: Interface Bindingを必須にし、Symfony DIのService Decoratorへ移行する

### Recommendation

Bを推奨する。

現在のDeveloper Experienceを維持しながらDependency固有Tokenizerから離れられる。ただし汎用AOP Frameworkは作らず、BlackOpsのTransaction Semanticsに用途を限定する。P17-008とは独立して設計し、実装前に対応するPHP Signature Matrix、Generated Artifact Contract、Migration、Ray.Aop削除条件をDecisionで確定する。

[ANSWER]


[/ANSWER]

## Question 3: Upstreamへの外部Action

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
- Question 1=Cなら、P17-008より先にFramework-owned Build-time Proxyの設計Taskを作成する
- Question 2=Bなら、Ray.Aopを即時削除せず、互換Testを維持した置換PhaseとRemoval GateをRoadmapへ追加する
- Question 2=C／Dなら、D096のPublic Contract変更、Migration、Documentation更新を置換Phaseより先に確定する
- Question 3=A／BはGitHubへの外部書き込みを含み、本Decisionの回答をその実行許可とする
- Stable Releaseで修正された後、Root Regression、Community Board compile／Digest E2Eを実行してliteral workaroundを削除する

## Traceability

- Decision: [D096 Phase 13 Database and Transaction Runtime](096-phase-13-database-and-transaction-runtime.md)
- Accepted Feature: [P17-007 Report](../orchestration/reports/P17-007-deferred-digest-and-progress.md)
- Blocked Investigation: [P17-007A Report](../orchestration/reports/P17-007A-aop-class-constant-attributes.md)
- Tracking: [TODO](../TODO.md)

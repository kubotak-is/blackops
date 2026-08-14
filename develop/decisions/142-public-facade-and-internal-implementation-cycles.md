# D142 Public Facade and Internal Implementation Cycles

Status: Decided

## Context

D022は初期8 NamespaceのArchitecture Contractとして、責務の逆流と循環依存をDeptracで防ぐと決定した。その後、D064／D069／D111／D114はInstalled ApplicationへInternal型を露出しないため、Public `Application`／`Auth`／`Http` facadeがFramework-owned Internal implementationを構成する境界を追加した。

P22-003BはPHP 8.5で初めて完走した全857-file graphを明示Layerへ同期し、152 violations／59 uncoveredを0へした。しかしDocumentation Reviewで、0 violationsのRuleset自体が次の追加strongly connected componentを許可し、Sourceも使用していることが判明した。

- `Application / Auth / Http / Internal`
- direct mutual edges: `Auth -> Internal -> Auth`、`Http -> Internal -> Http`
- `Application -> Internal -> Http -> Application`もSource上に存在する

これは`Application`／`Auth`／`Http`からcatch-all `Internal`を許可した大粒度Rulesetが、後発のPublic facade／Internal implementation境界と任意のInternal依存を区別できていないためである。D141で除去した`Internal -> Application` edgeとは別の既存graph issueであり、Deptrac zeroだけでD022の循環防止を満たしたとは判定できない。

## Evidence Inventory

Public facadeからInternalへ向く実依存は次へ限定されている。

- `Application` -> `Internal\Application`
- `Auth\Session\SessionServiceProvider` -> `Internal\Auth\Session`
- HTTP request／response -> `Internal\Http`、`Internal\Idempotency`
- `Http\SapiRuntime` -> `Internal\Runtime\FrankenPhp`

逆方向では、Internal Application runtimeがPublic HTTP responder／manifestを、Internal Auth implementationがPublic Auth contract／valueを、Internal HTTP／Idempotency implementationがPublic HTTP contractを利用する。FrankenPHP SAPI adapterからPublic Application／Auth／Httpへの逆依存はない。

完全なSource cycle除去には、`SapiRuntime::run(Application)`、Session DTO／manager contract、HTTP responder／manifest境界等の再設計が必要となる。これは20超のProduction fileとApplication／Auth／HTTP／SAPI／Session回帰へ波及し、D064／D069／D111／D114で確定したPublic facadeをRelease直前に変更する。

一方、次のnarrow collectorをcatch-all Internalから除外すれば、Public facadeが利用できるInternal implementationを固定して監査できる。

- `InternalApplication`
- `InternalAuth`
- `InternalHttp`
- `InternalIdempotency`
- `InternalSapiRuntime` (`BlackOps\Internal\Runtime\FrankenPhp` only)

この案でもPublic contractとそのInternal implementation間のbounded cycleは残るが、generic `Application/Auth/Http -> Internal`は削除できる。新しいInternal subnamespaceをPublic facadeから利用する場合は、Specification／collector／Ruleset／reviewを同時に要求できる。

現行RulesetをSCC解析した非自明なSCCは次の2つだけである。

- `Core / Idempotency / Telemetry`
- `Application / Auth / Http / Internal / InternalApplication / InternalAuth / InternalHttp / InternalIdempotency`

後者はD064／D069／D111／D114で確定したfacade compositionとimplementation間依存から生じる、D142が明示的に受入れる唯一のfacade／internal SCCである。`InternalSapiRuntime`はpublic facadeから実装への一方向依存だけを持ち、SCC外にある。

## Inherited Constraints

- Installed Application／Skeleton／Bootstrapへ`BlackOps\Internal`型を露出しない。
- Public SignatureへInternal型を露出しない。
- Generic `Transport -> Internal`、Deptrac skip／baseline／uncovered ignoreを導入しない。
- Runtime behavior、safe failure、Session security、SAPI compatibilityを変更しない。
- P22-003BはこのDecisionと再Reviewが完了するまでAccepted／Commitしない。
- Push、Tag、Release、Packagist、Skeleton publication、Documentation Deployを承認しない。

## Question 1: Release Architecture Boundary

### Options

- A: `1.2.0`前にPublic facade／Internal implementationのSource cycleを完全に除去する。Application／Auth／Http contractとRuntime compositionを再設計し、Ruleset graphの追加SCCをなくす。
- B: 後発Decisionで確定したPublic facadeを維持し、5つのInternal implementationをnarrow collectorへ分離する。`Application/Auth/Http -> Internal`のgeneric permissionを削除し、列挙したbounded facade／implementation cycleだけを明示例外として許可・監査する。
- C: 現行のcatch-all `Application/Auth/Http -> Internal` permissionを維持し、Specificationの循環禁止を緩和する。

### Recommendation

Bを推奨する。AはRelease直前の公開SAPI／Session／HTTP contract再設計となり、Architecture correctionを超えるSemVer／Security riskを持つ。CはPublic facadeから任意のInternal実装への依存を検出できず、D022の目的を失う。BはRuntime behaviorと公開Signatureを変えず、現在実際に使うfacade／implementation pairだけをDeptracで固定できる。

[ANSWER]
B
[/ANSWER]

## Decision

[DECISION]

Option Bを採用する。

後発Decisionで確定したPublic `Application`／`Auth`／`Http` facadeとRuntime behavior／Public Signatureを維持する。次の5つをcatch-all Internalと重複しないnarrow collectorへ分離する。

- `InternalApplication`: `BlackOps\Internal\Application`
- `InternalAuth`: `BlackOps\Internal\Auth`
- `InternalHttp`: `BlackOps\Internal\Http`
- `InternalIdempotency`: `BlackOps\Internal\Idempotency`
- `InternalSapiRuntime`: `BlackOps\Internal\Runtime\FrankenPhp`

Public Layerからcatch-all `Internal`へのgeneric permissionを削除し、次だけを許可する。

- `Application -> InternalApplication`
- `Auth -> InternalAuth`
- `Http -> InternalHttp, InternalIdempotency, InternalSapiRuntime`

Internal implementationからPublic contractおよび必要なFramework implementationへの依存はcollector単位で明示する。Public facade／Internal implementationのbounded cycleは、D064／D069／D111／D114で決定済みの「Installed ApplicationへInternal型を露出せず、Frameworkが実装を所有する」契約を実現する例外として許可する。上記8-layer SCC以外の非自明なcycleは許可しない。boundedの意味は、Public facadeからcatch-all `Internal`へのdirect permissionを禁止し、列挙した5つのnarrow collectorだけを入口にすることである。

新しいPublic -> Internal implementation edgeを追加する場合は、catch-all `Internal`を許可せず、Specification 16、narrow collector、Ruleset、Architecture evidenceを同じ変更で更新する。Deptrac skip／baseline／uncovered ignoreは使用しない。

D022の一般的な逆流／循環防止は維持し、本Decisionの列挙済みbounded facade cycleだけが後発の明示例外となる。D141のTransport／Telemetry／Storage Protection／Deferred Integrity boundaryと`Internal -> Application`禁止も維持する。

[/DECISION]

## Consequences

- Public API、SAPI、Session、HTTP behaviorをRelease直前に再設計しない。
- `Application`／`Auth`／`Http`から任意のInternal subnamespaceへ依存する変更はDeptracで失敗する。
- 許可するbounded cycleはcollector名とRulesetで可視化され、Public facadeからcatch-all `Internal`へのdirect permissionへ拡張されない。
- P22-003Bはcollector非重複、857-file zero graph、config guard、回帰Test、独立Documentation Reviewを再実行してからCommitできる。

## Traceability

- [D022 Namespace Dependencies](022-namespace-dependencies.md)
- [D064 Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)
- [D069 Skeleton HTTP Entrypoint Adapters](069-skeleton-http-entrypoint-adapters.md)
- [D111 Session Authentication Contract](111-session-auth-package-contract.md)
- [D114 Application Runtime and Bootstrap Dependency Boundary](114-application-runtime-and-bootstrap-dependency-boundary.md)
- [D141 Release Architecture and Export Boundary](141-release-architecture-and-export-boundary.md)
- [Namespace Dependencies](../spec/16-namespace-dependencies.md)
- [P22-003B Architecture and Export Closure](../orchestration/tasks/P22-003B-release-architecture-and-export-closure.md)

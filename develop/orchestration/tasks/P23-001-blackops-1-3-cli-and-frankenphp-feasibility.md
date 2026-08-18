# P23-001: BlackOps 1.3 CLI and FrankenPHP Feasibility

Status: Ready (Discovery Only)

## Goal

User提示のBlackOps `1.3`候補を、Command UX、Compiled Metadata、safe operational output、FrankenPHP lifecycle、Failure isolation、Migrationまで検証し、実装可能なDelivery PlanとTask分割へ確定する。

## User Proposal

- `list`／`ls`: 利用可能なBlackOps Command一覧
- `route:list`／`route:ls`: 登録HTTP Route一覧
- `schedule:list`／`schedule:ls`: 登録Schedule一覧
- `worker:list`／`worker:ls`: Job一覧
- その他の有用なCommand候補
- FrankenPHP Worker Modeへの完全対応
- Deferred Workerを別Processで起動しない構成の可否
- Classic Mode廃止

## Discovery Questions

1. Symfony Console既定`list`をそのままPublic Contract化するか、BlackOps-owned output schemaを追加するか。`ls` aliasはHuman／JSON／raw formatのどこまで同じか。
2. `route:list`はCompiled HTTP Manifestだけを読み、Application Container、Database、Handlerを解決せずに成立するか。
3. `schedule:list`はCompiled Schedule Metadataだけを読み、現在時刻評価やProvider解決を行わずに成立するか。
4. `worker:list`のJobはqueued Operation row、Worker definition、Transport／queue statsのどれを意味するか。Payload、Actor、Tenant、Credentialを表示せず、Operation ID、Type、State、Available At、Attempt等のsafe metadataだけで有用か。
5. `about`、`diagnostics:check`、`queue:status`、`outbox:status`、`dead-letter:list`を既存`operation:list`／`operation:inspect`／`database:status`等と重複せず提供できるか。
6. 公式FrankenPHP HTTP Worker ModeはHTTP request threadを常駐させるが、BlackOpsのDeferred Worker loopを自動実行しない。この二つを同一OS Processへ統合するにはExtension Workers／custom Caddy module／custom binaryが必要か。
7. 現行PCNTL Signal、Heartbeat、Lease Recovery、graceful shutdownをFrankenPHP thread lifecycleへどう写像するか。
8. Deferred Worker startup failure、連続crash、memory leak、DB outageがHTTP serving processを停止させないFailure isolationを作れるか。
9. Classic Modeを削除した場合、Worker bootstrap前FailureのSafe 500、upgrade consumer、rollback pathをどう置き換えるか。

## Candidate Delivery Order

1. P23-001: Discovery／Decision／Specification／Delivery Plan
2. P23-002: `list` Public Contractと`ls` alias、`route:list`／`route:ls`
3. P23-003: `schedule:list`／`schedule:ls`とsafe compiled metadata
4. P23-004: Deferred job／queue visibility contractと`worker:list`命名決定
5. P23-005: `about`／`diagnostics:check`／queue／outbox／dead-letter候補から採用分を実装
6. P23-006: FrankenPHP Extension Worker feasibility spike
7. P23-007: 採用時のopt-in same-process Deferred runtime、Failure／resource isolation Consumer
8. P23-008: Worker Mode default、Classic removal migration、Skeleton／Guide／full gate
9. P23-009: Stable `1.3.0` release gate and publication

P23-006がFailure isolation、Signal、Heartbeat、thread safety、restartを満たさない場合、same-process案は採用せず、HTTP Worker Modeと外部Deferred WorkerのProcess分離を維持する。Classic Mode removalはsame-process Deferred採否から分離して判断する。

## In Scope

- Official FrankenPHP Worker／Extension Worker仕様の調査
- 現行CLI／Manifest／Queue／Worker RuntimeのSource audit
- Command名、alias、Human／JSON、Exit Code、safe output契約
- Same-process／separate-process optionsとFailure Matrix
- Classic removal migration／rollback contract
- Decision、Specification、Delivery Plan、Task Packet

## Out of Scope

- Discovery Task内のProduction Runtime実装
- `1.3.0`を公開済みとしてDocumentationへ表示すること
- Classic Modeの即時削除
- FrankenPHP custom binary／Caddy moduleの配布
- Tag、Release、Packagist、Deploy

## Acceptance Criteria

- [ ] User Proposalの各Commandへ意味、Data Source、Output、Exit、alias契約がある
- [ ] 既存Commandとの重複と追加候補の優先順位が決まる
- [ ] Same-process Deferred Workerの実現方式、必要Dependency、Failure MatrixがEvidence付きで比較される
- [ ] Classic Mode廃止をsame-process案から独立して判断できる
- [ ] 1.3 Delivery Plan、Task順序、Release／Documentation境界が確定する
- [ ] 未公開RoadmapがStable `1.2.0` current documentationへ混入しない

## Required Evidence

- Official FrankenPHP Worker Mode／Extension Workers／failure／state persistence documentation
- Current `ApplicationConsoleKernel`、HTTP Manifest、Schedule Registry、Deferred Worker Loop、PCNTL／Heartbeat implementation
- Focused prototypeまたは明確なNot Feasible evidence
- Documentation ReviewerによるRoadmap／Stable lane分離確認

## Expected Report

`develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`へSummary、Options、Command Matrix、Runtime Failure Matrix、Decision Needed、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

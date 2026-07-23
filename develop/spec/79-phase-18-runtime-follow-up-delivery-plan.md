# Phase 18 Runtime Follow-up Delivery Plan

## Goal

Environment File Bootstrap、Classic／FrankenPHP SAPI Runtime、Public UUIDv7 GeneratorをFirst-party Capabilityとして追加し、Quickstart／Skeleton／Community Boardから不要なVendor Runtime配線とDirect Dependencyを削除する。Application BootstrapとEntrypointを薄くし、Full Consumer Gateで境界を再確認してからPhase 19へ進む。

## Delivery Order

```text
P18-009A Environment File Bootstrap
  -> P18-009B Framework-owned SAPI Runtime
    -> P18-009C Public UUIDv7 Generator and Consumer Adoption
      -> P18-009D Distribution, Documentation, Dependency Audit, and Closeout
        -> P18-009D1 SAPI Location Status Correction
          -> P18-009D Resume and Closeout
            -> Phase 19 Reliability and Delivery
```

Taskを並行実装しない。各TaskはWorker Report、Orchestrator Review、独立Verification、Commitを完了してから次へ進む。

## P18-009A: Environment File Bootstrap

Status: Accepted.

- Public `ApplicationBuilder::withEnvironmentFile()`を追加する
- Process Environment優先、Optional `.env`、string-only Snapshot、Safe Failureを実装する
- 既存`withEnvironment(array)`／Process-only／External Loader互換を維持する
- Bootstrap時一回だけ読込み、Environment値をArtifact／Logへ保存しない
- Quickstart BootstrapをFramework Capabilityへ移行してEnvironment Consumerを回帰する

HTTP Entrypoint、Worker Loop、UUID Generator、Community Board、Skeletonは変更しない。

## P18-009B: Framework-owned SAPI Runtime

Status: Accepted.

- Public `BlackOps\Http\SapiRuntime`のClassic／Worker実行境界を追加する
- Request Factory、Response Emit、Safe 500、Environment Restore、GCをFrameworkへ移す
- `Application::http()`のPSR-15 Escape Hatchを維持する
- Quickstart／Community BoardのClassic／Worker Entrypointを薄くする
- Multi-request、Failure Recovery、Cleanup、Memory、Classic Fallbackを回帰する

Environment Contract、UUID Generator、Auth Generator、Dependency削除は変更しない。

## P18-009C: Public UUIDv7 Generator and Consumer Adoption

Status: Accepted.

- Public `BlackOps\Identifier\Uuidv7Generator`とFramework Default Service Bindingを追加する
- Canonical UUIDv7、Override、Container DI、Architectureを検証する
- Auth GeneratorとCommunity BoardのInfrastructure Identifier AdapterへConstructor Injectionし、DomainのVendor／Framework非依存を確認する
- `make:auth` StubをPublic Generator Injectionへ移行する
- Community Board Identity／Board Identifier Adapterを移行する
- Domain層のVendor／BlackOps非依存とExisting／Clean Consumerを回帰する

SAPI Runtime Contract、Distribution Dependency削除、公開Documentation Closeoutは変更しない。

## P18-009D: Distribution, Documentation, Dependency Audit, and Closeout

Status: Accepted.

- Skeleton／Create-project／Framework Update／Package Exportへ新BootstrapとEntrypointを同期する
- Quickstart／Community Boardから未使用のDotenv、Nyholm、Laminas、Symfony UID Direct Dependencyを削除する
- DBAL／Migrations Direct Dependency維持とSource Import対応を確認する
- README、Guide、Internal Docs、Website、Upgrade／Changelogを同期する
- Full PHP／Consumer／Frontend／Browser／Website Quality Gateを実行する
- Runtime Follow-upを閉じ、Phase 19へSTATEを進める

External Publication／DeployとPhase 19 Production Codeは変更しない。

## P18-009D1: SAPI Location Status Correction

Status: Accepted.

- Quickstart E2Eで検出した`Location`付き202 Responseの302上書きを補正する
- Headerを検証後にEmitし、明示StatusをBodyより前かつHeaderより後に確定する
- Public API、Application Response Contract、Worker Loopを変更しない
- Focused／Full PHPUnitとQuickstart実HTTP E2Eで回帰を固定する
- Accepted後にP18-009Dの残りConsumer Gateへ戻る

Application／Distribution／Dependency／Documentation差分は変更しない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立Verification、Commitする
- Task間で後続Production Fileを先取り変更しない
- ApplicationがVendor APIを直接ImportするPackageだけをApplication Direct Dependencyへ残す
- DBAL／Migrationsを隠すWrapperを追加しない
- Generated／Dependency／Runtime Artifactは各Task完了前にCleanupする

## Follow-up Acceptance Criteria

- [x] D114がA／A／A／AでDecidedである
- [x] Environment FileをFrameworkが一度だけ安全にSnapshotできる
- [x] Default Classic／Worker EntrypointがVendor Runtime Classを直接Importしない
- [x] Worker Failure後にEnvironment／Execution／Connection境界が復元される
- [x] Public UUIDv7 GeneratorをApplication InfrastructureへConstructor Injectionできる
- [x] Auth Generator／Community Board DomainがSymfony UID／BlackOpsへ直接依存しない
- [x] Quickstart／Skeleton／Community Boardから未使用Direct Dependencyを削除できる
- [x] DBAL／Migrations Direct DependencyとVendor API対応が維持される
- [x] Full PHP／Consumer／Frontend／Website Quality Gateが成功する
- [x] External Publication／Deployなし、Worker Commitなし

## Traceability

- Decision: [D114 Application Runtime and Bootstrap Dependency Boundary](../decisions/114-application-runtime-and-bootstrap-dependency-boundary.md)
- Contract: [Application Runtime and Bootstrap](78-application-runtime-and-bootstrap.md)
- Application Ergonomics: [Application Ergonomics](74-application-ergonomics.md)
- Seeder Follow-up: [Phase 18 Follow-up Delivery Plan](77-phase-18-follow-up-delivery-plan.md)
- Next Phase: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)

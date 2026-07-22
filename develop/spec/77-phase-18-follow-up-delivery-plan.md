# Phase 18 Follow-up Delivery Plan

## Goal

Database SeederをFramework-owned Console Capabilityとして追加し、ApplicationがSeed Logicだけを所有する境界を完成する。Public Contract／Build、Console／Generator、Consumer Adoptionを独立Taskで検証し、Community BoardからSeeder用Symfony Commandと不要な直接Dependencyを削除してからPhase 19へ進む。

## Delivery Order

```text
P18-008A Seeder Core and Build Discovery
  -> P18-008B Seeder Console and Generator
    -> P18-008C Seeder Consumer Adoption and Follow-up Closeout
      -> Phase 19 Reliability and Delivery
```

Taskを並行実装しない。各TaskはWorker Report、Orchestrator Review、独立Verification、Commitを完了してから次へ進む。

## P18-008A: Seeder Core and Build Discovery

Status: Accepted.

- Public `Seeder`／`SeederRunner` Interfaceを追加する
- Standard／Explicit Seeding Configurationを検証する
- SeederをBuild時だけDiscoveryし、Compiled Container Private Service／Locatorへ登録する
- Root Convention、Explicit Override、Constructor DIを実装する
- Ordered／Nested／Empty実行、Unknown Class、Cycle、Child Failureを実装する
- Missing DefaultをExisting Application互換として維持する
- Runtime Source Scan／Reflection Fallbackなしを固定する

Console Command、Generator、Example Sourceは変更しない。

## P18-008B: Seeder Console and Generator

Status: Accepted.

- Built-in `database:seed`をFramework Console Kernelへ追加する
- Safe Output、Exit Code、Verbosity、Lazy Dependency境界を実装する
- `make:seeder <Name>`、Nested Name、Stub、Atomic／No-overwrite Safetyを実装する
- Command名予約とApplication／Operation Command衝突を回帰する
- Framework Update後にProject Entrypoint不変で新Command／Stubを利用できることを検証する

Quickstart／Skeleton／Community Board Sourceと公開Guideは変更しない。

## P18-008C: Seeder Consumer Adoption and Follow-up Closeout

Status: Accepted.

- Quickstart／Skeletonへ標準`DatabaseSeeder`を追加する
- Community BoardへRoot `DatabaseSeeder`とRunner Compositionを追加する
- `CommunityBoardSeedCommand`、`app:seed`、Seeder用Symfony Console直接Importを削除する
- Application Service Providerの手動Seeder登録を、Build-time Seeder Discoveryへ移す
- `symfony/console`をCommunity Boardが直接利用しないことを確認してDirect Dependencyから削除する
- Existing Volume／Clean InstallでMigration、Build、Seed、HTTP、Deferred、Browser Journeyを完走する
- CLI、Generator、Database、Directory Structure、Community Board、Troubleshooting、Security DocumentationとWebsiteを同期する
- Package Export、Skeleton Publication Dry-run、Framework Update、Full Quality Gateを実行する
- Community Boardの残Direct Dependencyを再監査し、他Packageは別Decisionへ送る

Seeder以外のDotenv、HTTP Runtime、UUID、DBAL／Migrations Wrapperは変更しない。Documentation WebsiteとCommunity Boardを外部公開しない。

## Dependency and Ownership Rules

- Production CodeはTask Packet単位でGPT-5.6 Luna High workerが実装する
- WorkerはCommitしない
- OrchestratorはTaskごとにReview、独立Verification、Commitする
- Public APIや仕様矛盾はTask Scopeを広げずReportのBlockerとして返す
- Task間で後続Production Fileを先取り変更しない
- Generated／Dependency／Runtime Artifactは各Task完了前にCleanupする

## Follow-up Acceptance Criteria

- [x] D113とDatabase Seeding仕様がDecidedである
- [x] Public Seeder API、Build-time Discovery、Compiled Locator、Cycle Guardが実装される
- [x] `database:seed`がFramework-owned Safe Commandとして動く
- [x] `make:seeder`が安全にRoot／Nested Seederを生成する
- [x] Quickstart／SkeletonがInstall直後と同じSeeder Layoutを持つ
- [x] Community BoardがSeeder用Application Commandを持たず、Seed Journeyを回帰する
- [x] Community Boardの`symfony/console` Direct Dependencyを削除できる
- [x] Existing Application Command／Operation Console／Migration／Buildが回帰しない
- [x] Full PHP／Consumer／Website Quality Gateが成功する
- [x] External Publication／Deployなし、Worker Commitなし

## Traceability

- Decision: [D113 Database Seeder Contract](../decisions/113-database-seeder-contract.md)
- Contract: [Database Seeding](76-database-seeding.md)
- Phase 18: [Phase 18 Delivery Plan](75-phase-18-delivery-plan.md)
- Next Phase: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)

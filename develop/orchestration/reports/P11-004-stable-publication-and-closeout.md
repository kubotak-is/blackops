# P11-004: Stable Publication and Closeout Report

Status: Accepted

## Summary

Experimental Stable `1.1.0`のFramework／Skeleton公開とLive Verificationを完了し、Phase 11をCloseした。

FrameworkはFixed Sourceへannotated tagをPushした。Tag-trigger Skeleton Publication WorkflowはFull Quality、Consumer／Installation、Local Publication、Workflow Regressionをすべて通過した後、固定SplitをDistribution `main`とannotated tagへ公開し、CredentialとTemporary StateをCleanupした。Manual Recoveryは不要だった。

Packagist両Package、GitHub Release、公開Packageだけを使う通常／`--no-scripts` Create-project、Project Root CLI、Documented QuickstartをLive検証した。Documentation Websiteは公開していない。

## Fixed Inputs

- Framework Source: `e3df5576c7216cfe8bd9e10e12ee6795f7674088`
- Skeleton Split: `293f880940636669f28ded756a888a8d6ba65f1b`
- Release Version: `1.1.0`
- P11-003 Tracking Commit: `518ebcae1f9059d995392aebdcb007b37e0bd598`

## Fixed Source, Split, and Preflight Evidence

- Checked At: `2026-07-17T00:48:00+09:00`
- Working Tree: clean
- Local／Remote `main`: P11-004 Checkpoint `d5914eaadf659957cb900c5e601facdf6063cd92`で一致
- Fixed SourceはCommit Objectで、Remote `main` Historyのancestorである
- Framework／Skeleton Remote `1.1.0` Direct Ref／Peeled Ref: 不存在
- GitHub Release `1.1.0`: 不存在
- Actions Secret: `SKELETON_DEPLOY_KEY`の名前だけを確認し、値は取得していない

## Framework and Skeleton Publication Evidence

| Surface | Direct Ref | Peeled／Branch Commit | Result |
| --- | --- | --- | --- |
| Framework `1.1.0` | Tag Object `11f38bf208198cc1ad89690e02fc9bd1eed719d2` | `e3df5576c7216cfe8bd9e10e12ee6795f7674088` | annotated tag、Message `BlackOps Framework 1.1.0` |
| Skeleton `1.1.0` | Tag Object `472809679a1c14d2cb647c9ae8a1c3c7fc78e5c3` | `293f880940636669f28ded756a888a8d6ba65f1b` | annotated tag、Message `BlackOps Skeleton 1.1.0` |
| Skeleton `main` | n/a | `293f880940636669f28ded756a888a8d6ba65f1b` | Fixed Splitと一致 |

Skeleton Publication Workflow:

- Run: `29512808726`
- Event／Branch／Head: `push` / `1.1.0` / `e3df5576c7216cfe8bd9e10e12ee6795f7674088`
- Result: Success
- Duration: `3m15s`
- Job: `87670251770`
- Framework Quality、Consumer／Installation、Generator Ownership、Local Publication、Workflow Regression、Deploy Key展開、Split／annotated Tag Push、Credential Cleanupの全Stepが成功
- `actions/checkout@v4`のNode.js 20 Deprecation Warningが出たが、JobとPublicationは成功した

Manual Dispatch Recoveryは実行していない。

## Packagist and GitHub Release Evidence

Packagist p2 Metadataを`2026-07-17T00:52:00+09:00`以降に確認した。

| Package | Version | Source Reference | Contract |
| --- | --- | --- | --- |
| `blackops/framework` | `1.1.0` | `e3df5576c7216cfe8bd9e10e12ee6795f7674088` | PHP `>=8.5`、Symfony Validator `^7.4`を含む |
| `blackops/skeleton` | `1.1.0` | `293f880940636669f28ded756a888a8d6ba65f1b` | Framework `^1.1`、Type `project` |

GitHub Release:

- Name／Tag: `BlackOps 1.1.0` / `1.1.0`
- URL: `https://github.com/kubotak-is/blackops/releases/tag/1.1.0`
- Published At: `2026-07-16T15:53:03Z`
- Draft／Prerelease: `false` / `false`
- Experimentalと1.x Minor間Backward Compatibility未保証を先頭に明記
- Validation、Generator、Worker Mode、400／422、Root CLIのHighlightsを記載
- Entrypoint／Command Prefix／Skeleton ConstraintのBreaking Changeを記載
- Authentication／Authorization、Sensitive Data、Deferred Status API、AdapterのKnown Limitationsを記載
- `CHANGELOG.md`と`UPGRADE.md`へ誘導

## Remote Normal, No-scripts, and Quickstart Evidence

空のComposer HomeとRepository外Temporary Directoryを使い、Local Path Repository、Local Framework Mount、既存Composer Cacheを使わず、Packagistから次を実行した。

```text
composer create-project blackops/skeleton /smoke/normal 1.1.0 --no-interaction --prefer-dist
composer create-project blackops/skeleton /smoke/no-scripts 1.1.0 --no-interaction --prefer-dist --no-scripts
```

- 通常／`--no-scripts`ともSkeleton `1.1.0`、Framework `1.1.0`、41 PackageをInstall
- Root `blackops`がExecutableで、旧`bin/blackops`は不在
- Composer Rootに`repositories`／`version`の混入なし
- Framework／Welcome Application ClassのAutoload成功
- 通常Installは`.env`と`var/build`／`var/log`をPost-createで準備
- `--no-scripts`はInstall直後`.env`不在、Manual `php bin/setup`で準備し、再実行で`.env`のHash不変
- `php blackops operation:list`と`php blackops build:compile`が成功

通常Projectをそのまま使い、記載済みQuickstartを実行した。

```text
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:migrate
docker compose up -d
```

- Database Migration: `migrations: 2`
- Inline Response: `{"message":"Welcome to BlackOps"}`
- Deferred Response: `{"status":"accepted","operationId":"019f6ba5-e96b-7348-907b-d4b0056e85aa","acceptedAt":"2026-07-16T15:57:43.147706Z"}`
- Worker 1: `Processed claims: 0`
- Worker 2: `Processed claims: 1`
- Journal: `[masked]`が存在し、Raw `apiToken` Valueは不在
- Final Result: `Remote BlackOps 1.1.0 smoke passed and cleaned up.`

最初のHarnessはDeferred Response確認でHost `php`を呼び出し、HostにPHPがないためExit 1となった。二回目はWorker完了後のJournal検証がJSONの形まで過剰に固定してExit 1となった。どちらもApplication実行の失敗ではなく、TrapがContainer／Network／Volume／Temporary DirectoryをCleanupした。Host PHP依存を除き、既存E2Eと同じ「`[masked]`が存在しRaw値が不在」という公開契約に修正した三回目が最初から最後まで成功した。

## Immutable Tag, Credential, and Documentation Boundary

- Framework `1.0.0`: Direct Tag Object `344ce0f7fd51bebce5c8f10fbca9115bef0b8062`、Peeled `279716f904f17be9341f3fdaae30156ab17d8a62`で不変
- Skeleton `1.0.0`: lightweight Direct Commit `da573f3190e5e855a9c09e275980c6ddc5cce028`で不変
- `1.1.0`のFramework／Skeleton Tagを移動、削除、再割当していない
- Secretは名前だけを確認し、値をLog／Repository／Reportへ記録していない
- Documentation Delivery Run `29512733907`はArtifact Build成功、Credential Check成功、Cloudflare Deploy StepはSkip
- Documentation Websiteは引き続き非公開

## Changed Files

- `develop/TODO.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-004-stable-publication-and-closeout.md`
- `develop/orchestration/reports/P11-004-stable-publication-and-closeout.md`
- `develop/STATE.md`

## Decisions and Assumptions

- D094のPublication Approval Bにより、P11-003 Accepted後は追加User確認なしでannotated Tag PushからRemote Smokeまで継続する。
- Documentation Websiteは別の明示Publication Taskまで公開しない。
- Packagist反映はGitHub Tag連携に委ね、WorkflowからAPI Mutationを行わない。
- GitHub ReleaseはPackagist公開とTag／Split整合確認後に作成した。

## Commands and Results

| Command | Result |
| --- | --- |
| P11-004 Checkpoint Commit／Push | `d5914eaadf659957cb900c5e601facdf6063cd92`、Local／Remote一致 |
| Framework annotated tag作成／Local検証／Push | Object Type `tag`、Message、Peeled Fixed Source一致、Push成功 |
| `gh run watch 29512808726 --exit-status` | Success、Publish Job `3m15s` |
| Framework／Skeleton `git ls-remote` | Direct Tag ObjectとPeeled CommitがFixed Source／Splitと一致 |
| Packagist p2 Framework／Skeleton | 両方`1.1.0`、Source Referenceと`^1.1`整合 |
| `gh release create 1.1.0 --verify-tag` | `BlackOps 1.1.0`公開成功 |
| Remote normal／`--no-scripts`／Quickstart Harness | 最終実行成功、全Temporary Resource Cleanup |
| Existing `1.0.0` Remote Ref | Framework／Skeletonとも不変 |
| Latest CI／Documentation Delivery | `29512734019`／`29512733907`、Success、Cloudflare Deploy Skip |
| `git diff --check` | Success |

## Acceptance Criteria

- [x] Framework annotated tagとFixed Sourceが一致する
- [x] Skeleton Publication Workflowの全GateとCredential Cleanupが成功した
- [x] Skeleton annotated tag／`main`とFixed Splitが一致する
- [x] Packagist両Packageが`1.1.0`を公開し、SkeletonがFramework `^1.1`を要求する
- [x] GitHub ReleaseがExperimental Policy、Highlights、Breaking Change、Known Limitationsを記載する
- [x] Remote通常／`--no-scripts` Create-projectが成功した
- [x] Root CLI、Compile、Migration、Inline／Deferred／Worker／Journal Maskが成功した
- [x] Existing Tag、Credential、Documentation Websiteの不変境界を確認した
- [x] Phase 11 Acceptance CriteriaとTrackingをCloseした

## Remaining Issues

Phase 11 Blockerはない。GitHub Actionsの`actions/checkout@v4` Node.js 20 Deprecation WarningはPublicationを妨げないが、将来のWorkflow Maintenanceで更新する。

## Suggested Next Action

P11-004 CloseoutをCommit／Pushし、Phase 12 Middleware and Authorization Runtimeの既存SpecificationとFramework／Application所有境界を監査して、未決Public API／Security／Deferred再認可をDecisionで確定する。

## Orchestrator Review

Accepted。Framework／SkeletonのDirect Tag ObjectとPeeled Commit、Skeleton `main`、Workflow Runの全Step、Packagist Metadata、GitHub Release、既存`1.0.0`不変をExternal read-onlyで再照合した。

Remote SmokeはHarness由来の2回の失敗を隠さず、Temporary Resource Cleanupを毎回確認した。公開契約に合わせた最終Harnessは空Composer HomeからInstall、Runtime起動、Deferred完了、Sensitive Mask、Cleanupまで完走した。Production Code／Test／Workflowの変更は不要と判定した。

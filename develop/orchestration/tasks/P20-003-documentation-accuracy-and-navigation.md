# P20-003: Documentation Accuracy and Navigation

Status: Accepted

## Goal

Documentation Reviewで確認された事実誤りと孤立Pageを修正し、全Public GuideをSidebarとAnchor Validationで発見可能かつ再発防止された状態にする。

## Source of Truth

- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `docs/documentation-review.md`

矛盾時はD117／Specification 84がNavigationとBannerを置き換える。Landing指定本文はSpecification 83を維持する。

## In Scope

- Review A-6の正確性5件
- Broken Fragment Anchor検出とRegression Test
- 全Public SlugのSidebar一意配置とMissing Guard
- D117のNavigation順序、Label、H1同期
- `Whats BlackOps`から`What's BlackOps`への文法補正
- 日本語一行Version BannerとReleases Link
- `docs/guide/README.md`のGitHub上で壊れない相対Link化
- D117／Specification 84／TODO／Report／STATE同期

## Out of Scope

- Stable Quickstartの新設またはFramework Stable Release変更
- Guide全37 Pageの文章編集
- Testing／Deployment等の大幅増強
- Callout／Copy Button／Prev Next／Edit Link
- LandingへのCode Demo、Hero／三要素本文変更
- Public Slug／Redirect変更
- Framework `src/**`、Public API、Migration変更
- External Publication／Deploy

## Accuracy Corrections

1. `attributes.md`: `#[Sensitive]`の付与対象を`OperationValue`／`Outcome` Propertyへ一致させ、Sensitive ModeをOperation Attributesの文脈へ移す。
2. `application-bootstrap.md`: 現在認識するConfigを実装と`configuration.md`へ一致させ、古い7 File断言を削除する。
3. `validation.md`: `operations.md#rejection`と`application-bootstrap.md#operationとservice`を実在Anchorへ補正する。
4. `configuration.md`: Database例を`static fn (Environment $env): array => [...]`で包む。
5. `execution.md`: Transactional child Operation例を標準形へ揃え、`#[OperationType]`とDeferred child定義を正しく示す。

## Navigation

D117の全項目を`site-navigation.mjs`と`blume.config.ts`へ同じ順序で反映する。次の6 Pageを必ず追加する。

- `concepts/core-concepts`
- `getting-started/first-operation`
- `operations/authoring`
- `operations/generators`
- `execution/context`
- `database/retention`

`validateNavigation()`はMapped Public SlugのMissing／Duplicate／Unknown／Reorderを拒否する。全Sidebar LabelとSource H1をTestする。

## Files Allowed to Change

- `docs/guide/README.md`
- `docs/guide/why-blackops.md`
- `docs/guide/core-concepts.md`
- `docs/guide/first-operation.md`
- `docs/guide/operations.md`
- `docs/guide/project-generators.md`
- `docs/guide/execution-context.md`
- `docs/guide/retention.md`
- `docs/guide/attributes.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/validation.md`
- `docs/guide/configuration.md`
- `docs/guide/execution.md`
- `docs/website/blume.config.ts`
- `docs/website/content-map.mjs`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/validate-content.mjs`
- `docs/website/tests/**`
- `docs/website/README.md`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-003-documentation-accuracy-and-navigation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。`docs/documentation-review.md`はUser Review Sourceとして読み取り専用にする。

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [ ] Review A-6の5件がSourceと一致して修正される
- [ ] Broken Fragment AnchorをPermanent Testが拒否する
- [ ] 全Public SlugがSidebarへ一度だけ配置される
- [ ] Missing／Duplicate／Unknown／Section ReorderをNavigation Testが拒否する
- [ ] D117のSidebar Label／順序と全対象H1が一致する
- [ ] `What's BlackOps`がNavigation、H1、Landing CTAで一致する
- [ ] Bannerが日本語一行でExperimental PolicyとReleases Linkを示す
- [ ] Landingの指定本文とGridが変わらない
- [ ] Public Slug、Redirect、Search、Artifact Boundaryを維持する
- [ ] Website full gateが成功する
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-003-documentation-accuracy-and-navigation.md`へSummary、Changed Files、Accuracy Corrections、Navigation、Anchor Validation、Version Banner、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

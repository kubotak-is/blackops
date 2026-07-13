# P10-005D: Reader Journey Corrections

Status: Blocked by P10-005C

## Goal

Website Reviewで判明したLanding、Quickstart、Tutorial、Diagram、Validation説明の問題を修正し、初見の中級PHP開発者が価値理解から実行・実装まで迷わず進める導線を作る。

## In Scope

- Landing Hero／CTAと4 Feature Link Block
- Install込みQuickstart
- `make:operation`を起点にしたTutorialへの改稿と改名
- 全公開Commandの`php blackops`統一
- 「図のテキスト代替」見出し削除と自然な隣接説明
- HTTP／Deferred Sequence DiagramのDesktop可読性改善
- Validation GuideとNavigation
- Binding／Value／Business Validationの現行能力と未実装Gap
- Current Status／Stable vs mainの正直な更新
- Content／Navigation／Diagram／Browser Test
- Report／STATE更新

## Out of Scope

- Declarative Value Validation Attributeの実装
- HTTP Configuration Lifecycle変更
- Stable `1.0.0`再発行
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/083-project-root-blackops-entrypoint.md`
- `develop/decisions/084-documentation-reader-journey-corrections.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/59-documentation-reader-experience.md`

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/**`
- `develop/orchestration/reports/P10-005D-reader-journey-corrections.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Feature Blockは装飾だけでなくKeyboard操作可能なLinkにする
- Quickstartは別のInstallation Page完了を前提にしない
- TutorialはGeneratorで作るFileと利用者が編集する内容を区別する
- `main` GeneratorとStable `1.0.0`の差を隠さない
- Text Alternative本文とMermaid `accTitle`／`accDescr`は維持する
- DiagramはPage Level Overflowを作らず、必要なScrollをDiagram内へ閉じ込める
- 実装されていないValidation AttributeをExampleへ書かない
- Validation Exampleは現在のPublic APIで動く完全なものにする

## Acceptance Criteria

- [ ] Landingが4 Feature Link BlockとQuickstart CTAを持つ
- [ ] QuickstartがComposer InstallからInline／Deferred／Workerまで一Pageで実行できる
- [ ] Tutorialの表示名が「チュートリアル: Operationを作る」で`make:operation`から始まる
- [ ] 公開Guideに`php bin/blackops`が残らない
- [ ] 「図のテキスト代替」という表示見出しがなく、同等本文は残る
- [ ] HTTP／Deferred DiagramがDesktop／Mobileで読めるSizeと局所Scrollを持つ
- [ ] Validation GuideがBinding、手動Value Validation、Business Validation、Rejected HTTP／Journalを説明する
- [ ] Declarative Value Validation Attributeが未実装であることをCurrent StatusとGuideが明示する
- [ ] Stable／main Bannerと既知制約が維持される

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago analyze examples/quickstart/app
! rg -n 'php bin/blackops|図のテキスト代替' docs/guide docs/website/src
! rg -n 'docs/internal|develop/|BlackOps\\Internal' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005D-reader-journey-corrections.md`へSummary、Landing Evidence、Quickstart／Tutorial Evidence、Diagram Browser Evidence、Validation Capability Matrix、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。


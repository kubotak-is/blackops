# P10-001 Documentation Website Contract Report

## Summary

Phase 10のDocumentation Website Contractを確定した。公開WebsiteはFramework利用者向けに限定し、`docs/guide/`だけをMarkdown Source of Truthとする。`docs/internal/`はRepository内に維持するが、Website、Navigation、Search Index、Static Artifactへ含めない。

User回答に基づき、miseで固定するNode.js 24 LTS／pnpm 11、Main DocumentとStable Version表示、GitHub ActionsからのCloudflare Pages Direct Uploadを採用した。確定仕様2件とP10-002からP10-006のTask Packetへ分割した。Production Code／Website Codeは変更していない。

## Existing Documentation Findings

- `docs/guide/`には10 Markdownがあり、先頭H1を持つ通常のRepository Markdownである。
- `docs/internals/`にはFramework実装者向けのArchitecture、Bootstrap、Runtime、Adapter文書があり、AGENTS、README、Specification、Task／Reportから多数参照される。
- `docs/guide/installed-application-status.md`はPhase Acceptance Evidence中心で、利用者向けWebsiteよりInternal Documentationに適する。
- Starlight標準ContentはFrontmatter TitleとContent Directoryを前提とするため、既存H1を維持した決定的な生成層が必要である。
- Laravel DocumentationはGetting Started、Architecture Concepts、Basics、Database等を利用者の作業別に構成し、Symfony DocumentationもGetting Startedと機能／Referenceを利用者向けに整理している。
- 2026-07-13時点でNode.js 24はLTS、pnpmの現行CI Documentationは11.xとNode.js 24の組合せを例示している。

## Decisions and Assumptions

- D081 Question 1、4、5、6はAを採用した。
- Question 2はRecommendationを変更し、公開Websiteを利用者向けだけに限定した。Source Directory名`guide`はURLへ出さず、Getting Started、Operations、Execution、Database、Referenceで構成する。
- Question 3はUser指定によりmiseとpnpmを採用した。Node.js 24 LTS／pnpm 11のPatch VersionはP10-003実装時点でExact Pinする。
- D063のDirectory集約と`docs/internal/`へのRenameは維持する。両DirectoryをWebsiteへ公開する部分だけをD081が置き換える。
- Cloudflare Project名は`blackops-docs`、初期Hostは`blackops-docs.pages.dev`、Custom DomainはScope外とする。

## Commands and Results

```text
AGENTS、STATE、P10-001、D063、D077、Spec 41、現行Guide／Internal Documentation確認
Result: 既決定境界、Source形式、公開Audienceの不一致、Repository参照範囲を確認した。

D081 User Answer確認
Result: Q1 A、Q2 利用者向け限定、Q3 mise＋pnpm、Q4 A、Q5 A、Q6 A。

Laravel 12、Symfony current、Astro／Starlight、Node.js Release、mise、pnpm、Cloudflare Pages公式Documentation確認
Result: 利用者Task別Navigation、Node.js 24 LTS、pnpm 11 CI、Starlight Content、Direct Upload境界を確認した。

Worker起動Interface確認
Result: 利用可能なInterfaceにModel／Profile指定Parameterがなく、GPT-5.6 Luna Highを明示できない。

Required traceability grep
Result: D081、Spec 57／58、P10-002からP10-006、STATEの対応を確認した。

git diff --check
Result: No output.
```

## Changed Files

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/README.md`
- `develop/orchestration/tasks/P10-001-documentation-website-contract.md`
- `develop/orchestration/tasks/P10-002-documentation-directory-migration.md`
- `develop/orchestration/tasks/P10-003-starlight-single-source-foundation.md`
- `develop/orchestration/tasks/P10-004-user-documentation-information-architecture.md`
- `develop/orchestration/tasks/P10-005-cloudflare-pages-delivery.md`
- `develop/orchestration/tasks/P10-006-phase-10-closeout.md`
- `develop/orchestration/reports/P10-001-documentation-website-contract.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Acceptance Criteria

- 現行Documentation／Markdown確認: Satisfied
- Starlight／Cloudflare現行Contract確認: Satisfied
- D081へのOption／Recommendation記録: Satisfied
- User回答とDecided化: Satisfied
- Phase 10 Specification／Task Packet: Satisfied
- Worker Model／Profile指定可否確認: Checked; unavailable before Production implementation

## Remaining Issues

D081に未決事項はない。

P10-002はProduction Codeを変更しないDocumentation RenameとしてOrchestratorが実行可能である。P10-003以降はGPT-5.6 Luna High workerが必要だが、現在のWorker起動InterfaceにはModel／Profile指定Parameterがない。別Modelへ黙ってFallbackできないため、P10-003開始前にUser承認または実行環境の更新が必要である。

Cloudflare ProjectとGitHub SecretsはP10-005まで不要であり、現時点では作成しない。

## Suggested Next Action

P10-001をReview／Commit後、P10-002でDocumentation Directory RenameとRepository参照同期を行う。その後、GPT-5.6 Luna Highを明示選択できるWorker環境を用意するか、Phase 10に限る代替WorkerをUserが明示承認してからP10-003を開始する。

# P10-005B: Guided Tutorial, Security, and Reference

Status: Accepted

## Goal

最初のOperationを実行結果まで完走できるTutorialと、運用時に必要なTroubleshooting、Security責任分界、Core API／Attribute Referenceを提供し、全公開Guideを中級PHP開発者向けの能動的なToneへ統一する。

## In Scope

- First OperationのWrite／Compile／curl／Response／Journal／Outcome一気通貫化
- Input／Output PairとSensitive Mask済み実出力
- Troubleshooting／FAQ Page
- Security & Sensitive Data Pageと責任分界表
- Core API Types Page
- Attributes Page
- 全公開Guideの初出用語注釈、Glossary Link、Tone／表記統一
- Stable／main差とCurrent Status制約の維持
- Navigation／Content／Code／JSON／Artifact Test
- Report／STATE更新

## Out of Scope

- Framework Public API変更
- Stable Release再発行
- Cloudflare External Configuration
- Version別Documentation

## Relevant Specifications and Decisions

- `develop/decisions/082-documentation-reader-experience.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/53-typed-self-handled-operation-invocation.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/**`
- `develop/orchestration/reports/P10-005B-guides-security-and-reference.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- 原則としてGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答「Y」により、Phase 10に限り、Model／Profileを明示できない現在利用可能なWorkerで進めることを承認済みである
- Code Exampleは現在のQuickstart／Public APIと照合し、抜粋でCompile不能な例を標準手順にしない
- Journal／Response／Outcome Exampleは実Commandから取得し、Dynamic Fieldを明示する
- Secret Input LiteralをJournal Example、Artifact、Test Logへ残さない
- `#[Sensitive]`を暗号化／認証認可／Access Control／Retentionの代替として説明しない
- Internal Namespaceを利用者向けAPIとして掲載しない
- Stable `1.0.0`にないmain機能をStable Tutorialの必須手順にしない

## Acceptance Criteria

- [x] First OperationがSourceからOutcome取得まで一Pageで完走する
- [x] 全Commandに対応するInput／Outputがあり、HTTP StatusとJSONがParse可能である
- [x] `journal.jsonl`実例がSensitive Maskを含みRaw Secretを含まない
- [x] TroubleshootingがSignature／Discovery／Artifact／202 without Worker／DB／Journal／Outcomeを扱う
- [x] Security PageがFrameworkとApplication／運用の責任を表で分離する
- [x] Core API Types Tableが現在のPublic APIと一致する
- [x] Attribute Tableが用途、対象、例、Typed標準形での必要性を示す
- [x] Glossary Termの初出注釈とLinkが主要Pageにある
- [x] 全公開Guideが日本語主体の能動態と統一表記を使用する
- [x] Stable／main Banner、Current Status、既知制約が維持される

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago analyze examples/quickstart/app
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'docs/internal|develop/|BlackOps\\Internal' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005B-guides-security-and-reference.md`へSummary、Tutorial Evidence、Troubleshooting Coverage、Security Boundary、API／Attribute Source Audit、Tone／Terminology Audit、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

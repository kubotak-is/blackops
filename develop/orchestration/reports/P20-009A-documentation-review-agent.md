# P20-009A Documentation Review Agent Report

## Summary

これまでのDocumentation Website Reviewで繰り返し発見されたAccuracy、Runnable User Journey、Navigation、Visual、Mermaid可読性の失敗パターンを、Repository管理の専用Read-only Reviewerへ固定した。

`documentation-reviewer`は`gpt-5.6-sol`／`high`を使用し、DocumentationやProduction Codeを修正しない。Implementation、Stable Tag、Specificationへ主張を照合し、実Browserを用いた読者視点のReview結果だけをEvidence付きでOrchestratorへ返す。

## Changed Files

- `.codex/agents/documentation-reviewer.toml`
  - Documentation Reviewer Profile、Model、Read-only Promptを定義した。
- `AGENTS.md`
  - Profile登録とOrchestrator／Worker／Reviewerの役割分離を追加した。
- `develop/decisions/125-documentation-review-agent.md`
  - 専用Reviewerを導入する理由と責任境界を記録した。
- `develop/spec/92-documentation-review-agent.md`
  - Review Contract、Evidence Hierarchy、Review Lane、Browser Contract、Severity、Finding Formatを確定した。
- `develop/spec/README.md`
  - Specification 92とD125の索引を追加した。
- `develop/orchestration/tasks/P20-009A-documentation-review-agent.md`
  - Scope、変更可能File、Required Commands、Acceptance Criteriaを記録しAcceptedへ更新した。
- `develop/TODO.md`
  - P20-009Aを完了へ更新した。
- `develop/STATE.md`
  - StartとAcceptance Checkpointを記録した。
- `develop/orchestration/reports/P20-009A-documentation-review-agent.md`
  - 本Reportを追加した。

Documentation本文、Website Source／Runtime、Framework `src/**`、Test、Generated Artifactは変更していない。

## Review Contract

- DefaultはRead-onlyであり、修正、Rewrite、Commit、Push、Deploy、Finding解決を行わない。
- `main`の主張はImplementation／Test／Generator Stub／Exampleへ、Stableの主張はGit Tag `1.1.0`へ照合する。
- Accuracy、Runnable User Journey、Information Architecture、Editorial、Visual、Accessibility、Artifact Boundaryを分離して確認する。
- Visual PassにはDesktop 1440px Light／DarkとMobile 390pxの実Browserが必要である。
- MermaidはSVGの存在だけでなく、表示寸法、文字の判読性、Mobile Host内横Scroll、Page Overflowを確認する。
- FindingはUser Impact基準のP1／P2／P3とし、Location、Evidence、Required Correction、Verification、Confidenceを含める。
- Browserや外部環境を確認できない項目をPASSにせず、Not Verifiedへ分離する。
- Reportは既定でChatへ返し、明示Output Pathがある場合だけ新規Fileへ書く。既存Reviewを暗黙に上書きしない。

## Past Review Coverage

次の既知のFailure PatternをReview Laneへ反映した。

- Stable `1.1.0`とRepository `main`のCapability／Syntax混同
- 実装と異なるRoute、Command、Status、Header、Error Code、Response
- CopyしてもCompile／実行できない不完全なSample
- Prerequisite、Working Directory、Host／Container、Expected Result不足
- Landing CTA、Internal Link、Target H1、Sidebar Active Stateの退行
- Userが指定していないCopy、Section、導線の追加
- Landing Featureの不均等、Code clipping、Mobile／Page Overflow
- Mermaidが描画済みでも縮小され、実際には読めない状態

Regression HistoryはD117〜D124、Specification 84〜91、関連Task Reportを使う。`docs/documentation-review.md`は現在のWorking Treeに存在しないため、存在する場合だけ読むOptional Contextとした。

## Commands and Results

```text
python3 -c "import pathlib,tomllib; tomllib.loads(pathlib.Path('.codex/agents/documentation-reviewer.toml').read_text())"
PASS

codex exec --strict-config --help
PASS
PATH aliasを更新できない旨のWarningは出たが、Config ParseとCommand HelpはExit 0。

test -f develop/spec/92-documentation-review-agent.md
PASS

Profile／AGENTS／Specification／Decision／TaskのReference Guard
PASS

Read-only、Browser必須、P1／P2／P3、既存File非上書きのRole Guard
PASS

docker compose run --rm app mago format --check src tests
PASS（All files are already formatted）

Production Code／TestのManagement ID Comment Guard
PASS（該当なし）

git diff --check
PASS
```

最初に候補とした`codex --strict-config features list`は、このCodex CLIの`features` Subcommandが`--strict-config`を受け付けずExit 1となった。Task PacketのRequired Commandを、実際にStrict ConfigをParseする`codex exec --strict-config --help`へ訂正して再実行し、PASSした。

## Trial Review

現SessionのAgent Profile一覧は起動時に固定され、新規`documentation-reviewer` ProfileをHot Reloadできなかった。そのため独立Agentへ新ProfileとSpecification 92を明示的に読ませ、`docs/guide/execution.md`のInline／Deferred Outcome保存境界を狭いScopeでRead-only Reviewさせた。

Trial結果:

- File編集、Test、Build、Commit、Pushを行わずRoleを維持した。
- `src/Internal/Execution/InlineDispatcher.php`、`src/Internal/Execution/DeferredWorkerRuntime.php`、関連Test、Specification 90へ照合した。
- InlineはOutcomeを永続化せず、Deferred WorkerはOutcomeを永続化するというGuide説明を正しいと判定した。
- P1／P2／P3 Findingはなしとし、Positive Findingと未確認範囲を分離した。
- Historical Review Fileは直接確認できないLimitationsとして扱い、現行Evidenceによる判定へ影響しないことを明示した。

Repositoryから新Profileを名前指定して起動するSmokeは、Profile一覧が再読込される次のCodex Sessionで確認する。

## Acceptance Criteria

- [x] `documentation-reviewer` Profileを`gpt-5.6-sol`／`high`で定義した
- [x] Read-only責務とOrchestrator／Workerの役割分離を固定した
- [x] Implementation／Stable Tagを優先するEvidence Hierarchyを固定した
- [x] Runnable Journey、IA、Editorial、Visual、Accessibility、Artifact Laneを固定した
- [x] Desktop Light／DarkとMobile実Browserを必須にした
- [x] Mermaid実寸と局所Scrollの確認を必須にした
- [x] P1／P2／P3とEvidence付きFinding Formatを固定した
- [x] Not Verifiedと既存Review非上書きを固定した
- [x] Management Documentを同期した
- [x] Required CommandsとRead-only Trialを完了した
- [x] Commitしていない

## Remaining Issues

- 現SessionはRepositoryへ追加したAgent ProfileをHot Reloadできない。次のCodex Sessionで`documentation-reviewer`を名前指定し、Profile DiscoveryだけをSmoke確認する。
- `docs/documentation-review.md`は現在のWorking Treeに存在しない。過去Reviewの確定内容はDecision、Specification、Task Reportへ保存済みであり、ReviewerはそれらをRegression Historyとして使う。

## Suggested Next Action

次のP20-010開始前に、新しいCodex Sessionから`documentation-reviewer`へ`changed-pages` Reviewを依頼する。P20-010 Workerへ渡す前のBaseline Findingと、実装後のAcceptance Reviewを同じContractで分離して実施する。

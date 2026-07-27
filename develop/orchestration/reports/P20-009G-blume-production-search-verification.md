# P20-009G Blume Production Search Verification Report

## Summary

Blume ProductionのSearch Live Verification契約を、旧Starlight／Pagefind AssetからBlume Oramaの`/blume-search.json`へ同期した。D129、D130、Specification 57、現行運用手順、TODO、Task、STATEをReview Pendingの状態へ揃えた。Website Runtime／Artifact、Cloudflare外部設定、Framework Production Code、Historical Checkpointは変更していない。

## Changed Files

- `docs/internal/documentation-website.md`
- `develop/decisions/129-documentation-website-publication.md`（OrchestratorのD130同期差分を保持）
- `develop/spec/57-documentation-website-delivery-contract.md`（OrchestratorのBlume Search gate差分を保持）
- `develop/spec/README.md`（D130 index差分を保持）
- `develop/TODO.md`
- `develop/orchestration/tasks/P20-009G-blume-production-search-verification.md`
- `develop/orchestration/reports/P20-009G-blume-production-search-verification.md`
- `develop/STATE.md`

`develop/decisions/130-blume-production-search-verification.md`はOrchestrator作成済みの正本として変更せず保持した。既存Phase 20のWorking Tree差分と履歴Task／Report／STATE内のPagefind記録は変更していない。Secret値、Account ID、Token、生成Artifact、Framework `src/**`は扱っていない。

## Decisions and Assumptions

- D130に従い、安定した公開Search Artifactは`https://blackops-php.pages.dev/blume-search.json`とする。
- `/pagefind/pagefind.js`のHTTP 404はBlume移行後の想定結果であり、Active Production ContractのFailure Gateには含めない。Historical Checkpoint内の記録は履歴として保持する。
- Browser上のSearch操作はD130／Specification 57の要件としてOrchestrator Gateで実測し、HTTP到達性だけで成功扱いにしない。

## External Evidence

- PR #1 merge commit `42cf27807919078f9d0e82e4b1f6a2e2b9debb8d`を起点とするDocumentation Production run `30212941358`はBuildとCloudflare Pages DeployがSUCCESS。
- `https://blackops-php.pages.dev/` — HTTP 200。
- `https://blackops-php.pages.dev/getting-started/installation/` — HTTP 200。
- `https://blackops-php.pages.dev/blume-search.json` — HTTP 200。
- `https://blackops-php.pages.dev/pagefind/pagefind.js` — HTTP 404（旧Asset。Deploy Failureとは扱わない）。
- Worker時点ではBrowser Search操作を未実施としていた。Orchestrator ReviewでPlaywright 1.61.1 Chromiumを使用し、Desktop 1280px相当では`Control+K`、Mobile 390×844では検索Buttonから`Journal`を入力し、両方でJournal Pageを先頭に含む検索結果を確認した（2 tests PASS）。

## Commands and Results

- `rg -n 'blume-search\.json|Orama|Browser.*Search' docs/internal/documentation-website.md develop/decisions/129-documentation-website-publication.md develop/spec/57-documentation-website-delivery-contract.md` — PASS。現行手順／D129／Specification 57にBlume Search契約を確認。
- `! rg -n 'pagefind/pagefind\.js|Pagefind Asset' docs/internal/documentation-website.md develop/decisions/129-documentation-website-publication.md develop/spec/57-documentation-website-delivery-contract.md` — PASS。Active contractに旧Pagefind確認なし。
- `git diff --check` — PASS。
- `docker compose run --rm app mago format --check src tests` — PASS（All files are already formatted）。
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS（該当なし）。

## Acceptance Criteria

- [x] D129がBlume Orama Search IndexをLive Verification対象とする
- [x] 現行運用手順が`/blume-search.json`をHTTP確認する
- [x] Specification 57がBlume Search live gateを明示する
- [x] Active contractに`/pagefind/pagefind.js`確認が残らない
- [x] Production成功／HTTP 200／404のEvidenceを推測せず記録する
- [x] TODO／Task／Report／STATEがReview Pendingへ同期する
- [x] `git diff --check`がPASSする
- [x] Worker Commitがない

## Remaining Issues

Documentation Website初回Publicationに関するRemaining Issueはない。Custom Domain、Version別Deploy、Cloudflare Git IntegrationはD129のOut of Scopeとして維持する。

## Suggested Next Action

Orchestratorが本CorrectionをCommitし、main保護Rulesetに従ってPull Request経由で運用契約を反映する。

## Orchestrator Review

OrchestratorはD129／D130／Specification 57／現行運用手順、Production run、HTTP Evidenceを独立Reviewした。`/blume-search.json`はHTTP 200で、Playwright ChromiumによるDesktop Keyboard SearchとMobile Button Searchは2 tests PASSし、`Journal`検索結果にJournal Pageが表示された。Required text guardと`git diff --check`もPASSしているため、P20-009GをAcceptedとする。

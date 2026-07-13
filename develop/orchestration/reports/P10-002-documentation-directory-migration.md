# P10-002 Documentation Directory Migration Report

## Summary

Framework実装者向けDocumentationを`docs/internals/`から`docs/internal/`へAtomicにRenameした。現行のAGENTS、README、Guide、Specification、Task Packet、Completion Report内のPath／Markdown Linkを新Directoryへ同期した。

公開WebsiteのAudience境界に合わせ、Acceptance Evidence中心の`installed-application-status.md`を`docs/guide/`から`docs/internal/`へ移した。Guide Indexから公開導線を外し、Internal Index、Root README、Development Documentationから新Pathへ到達できるようにした。Production CodeとTestは変更していない。

## Rename and Reference Evidence

- `docs/internal/`にはIndexを含む41 Markdownが存在する。
- 旧`docs/internals/` Directoryは存在しない。
- `installed-application-status.md`は`docs/internal/`にあり、Guide Indexには含まれない。
- AGENTSのFramework実装者向けDirectoryとArchitecture／Adapter更新先は`docs/internal/`である。
- Root READMEのDevelopment Setup、Internals Index、Installed Application Statusは新Pathを指す。
- Develop Documentation、Specification、過去Task／Reportの有効なPath参照を新Directoryへ同期した。
- Markdown Relative Link Checkで欠落Targetは0件だった。
- Rename前後を説明するD063、D081、Spec 41／57／58、P10-001／002だけが旧Path文字列を履歴として保持する。

## Changed Files

- `docs/internals/**` -> `docs/internal/**`（40 Markdown Rename）
- `docs/guide/installed-application-status.md` -> `docs/internal/installed-application-status.md`
- `AGENTS.md`
- `README.md`
- `docs/guide/README.md`
- `docs/guide/mvp-status.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/DOCS.md`
- `develop/TODO.md`
- `develop/decisions/**`の現行Documentation Path参照
- `develop/spec/**`の現行Documentation Path／Link
- `develop/orchestration/tasks/**`の変更可能Path参照
- `develop/orchestration/reports/**`のChanged Files／Evidence Path参照
- `develop/orchestration/tasks/P10-002-documentation-directory-migration.md`
- `develop/orchestration/reports/P10-002-documentation-directory-migration.md`
- `develop/STATE.md`

## Decisions and Assumptions

Directory Rename後も過去Task／Reportの対象Fileへ到達できることを優先し、Path参照を機械的に新Directoryへ同期した。Taskの結論や実行結果は変更していない。

D063等で旧Pathから新Pathへの移行そのものを説明する表記は判断履歴なので保持した。これらは壊れたLinkではなくInline Codeである。

Installed Application StatusはPhase 7から9のAcceptance EvidenceとCommit／Package境界を扱うためInternalへ移した。利用者向けの実装済み機能と制約は`docs/guide/mvp-status.md`を引き続き公開Sourceとする。

## Commands and Results

```text
Directory and audience migration
Result: docs/internal exists with 41 Markdown; docs/internals does not exist; Installed Application Status moved to Internal.

Repository path synchronization
Result: AGENTS、README、Guide、Develop、Specification、Task／Reportの現行参照をdocs/internalへ更新した。

Python Markdown relative-link scan
Result: Missing target 0.

Old path allowlist guard
Result: Rename履歴を説明するD063、D081、Spec 41／57／58、P10-001／002以外に旧Path参照なし。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

PHP management ID guard
Result: No matches; negated command exited 0.

git diff --check
Result: No output.
```

## Acceptance Criteria

- Internal Documentation Rename: Satisfied
- Old Directory Removal: Satisfied
- AGENTS／README Sync: Satisfied
- Repository Path／Link Sync: Satisfied
- Installed Application Status Audience Move: Satisfied
- Guide／Internal Index Boundary: Satisfied
- Old Path Allowlist: Satisfied

## Remaining Issues

P10-002に残作業はない。

P10-003以降のWebsite実装はGPT-5.6 Luna High workerが必要だが、現在のWorker起動InterfaceではModel／Profileを明示できない。Phase 10に限る代替Worker承認または実行環境更新が必要である。

## Suggested Next Action

P10-002をCommitする。その後、Worker Model／ProfileのBlockerをUserへ確認し、解消後にP10-003を開始する。

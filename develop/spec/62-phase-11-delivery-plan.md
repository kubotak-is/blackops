# Phase 11 Delivery Plan

## Goal

現在の`main`をFeature Freezeし、Experimental Release Contractに従ってFramework／Skeleton `1.1.0`を検証、公開する。

## P11-001: Release Surface Reset

- Project CLIの旧`blackops:*` Aliasと予約を削除する
- PrefixなしCanonical CommandとProject Root `blackops`だけを公式Surfaceとして検証する
- `1.0.0`からのPublic API／Entrypoint／Command／Database／Configuration差分を監査する
- Breaking SurfaceをReportへ固定し、後続のCHANGELOG／UPGRADE入力にする

## P11-002: Release Documentation and Metadata

- Skeleton Framework Constraintを`^1.1`へ更新する
- `CHANGELOG.md`と`UPGRADE.md`を作成する
- README、Guide、Current Status、Website Version Noticeを`1.1.0`へ同期する
- Experimental／Backward Compatibility未保証を明示する

## P11-003: Release Candidate Gate

- Full PHP／Website Quality Suite
- 全Consumer E2E
- `1.1.0` Publication Dry RunとSplit Create-project Smoke
- Release Candidate CommitとGitHub Actions Evidence
- Release Source SHA、Known Limitations、Publication Checklist固定

Source of Truth監査でSkeleton Publication Automationがannotated tag契約を満たさないことを検出したため、Fixed Candidate GateはBlockerとして停止し、P11-003A完了後に新Candidateで最初から再実行する。

## P11-003A: Annotated Skeleton Release Tag

- Local Publication Testでannotated Tag Object、Tag Message、Peeled Split Commitを検証する
- Publication Workflowがannotated Tag RefをPushし、Remote Direct RefとPeeled Refを分離監査する
- 新規Releaseのlightweight tagを拒否し、既存annotated tagはPeeled Commit一致時だけ冪等成功とする
- 公開済みSkeleton `1.0.0` lightweight tagは同一Split CommitのManual Recoveryだけを許可し、Tagを変更しない
- Temporary Bare Repositoryを使うWorkflow Regressionで外部状態を変更せずRecovery境界を検証する

## P11-004: Stable Publication and Closeout

- annotated tag `1.1.0` Push
- Skeleton Publication Workflow監視とRecovery
- Framework／Skeleton Git Tag、Packagist、GitHub Release検証
- Remote通常／`--no-scripts` Create-projectとQuickstart Smoke
- Phase Report、TODO、STATE Closeout

## Dependency Order

```text
P11-001 Release Surface Reset
  -> P11-002 Release Documentation and Metadata
    -> P11-003 Release Candidate Gate
      -> P11-003A Annotated Skeleton Release Tag
        -> P11-003 Release Candidate Gate (new fixed candidate)
          -> P11-004 Stable Publication and Closeout
```

## Phase Acceptance Criteria

- [x] Project CLIの公式SurfaceがProject Root `blackops`とPrefixなしCanonical Commandだけである
- [x] `1.0.0`からのBreaking SurfaceとMigrationがDocumentedである
- [x] Skeleton `1.1.0`がFramework `^1.1`を要求する
- [x] CHANGELOG、UPGRADE、README、GuideがLatest StableとExperimental Policyに一致する
- [x] Skeleton Publicationがannotated Tag ObjectとPeeled Split Commitを検証する
- [x] Full Quality／Consumer／Publication GateがRelease Candidate Commitで成功する
- [ ] Framework／Skeleton `1.1.0` TagとPackagist Metadataが一致する
- [ ] 公開Packageから通常／`--no-scripts` Create-projectとQuickstartが成功する
- [ ] GitHub Release `1.1.0`が確定Release Noteを持つ

## Traceability

- Decision: [D094 Stable 1.1 Release Contract](../decisions/094-stable-1-1-release-contract.md)
- Contract: [Experimental Release Contract](61-experimental-release-contract.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)

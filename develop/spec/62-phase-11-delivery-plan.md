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
      -> P11-004 Stable Publication and Closeout
```

## Phase Acceptance Criteria

- [ ] Project CLIの公式SurfaceがProject Root `blackops`とPrefixなしCanonical Commandだけである
- [ ] `1.0.0`からのBreaking SurfaceとMigrationがDocumentedである
- [ ] Skeleton `1.1.0`がFramework `^1.1`を要求する
- [ ] CHANGELOG、UPGRADE、README、GuideがLatest StableとExperimental Policyに一致する
- [ ] Full Quality／Consumer／Publication GateがRelease Candidate Commitで成功する
- [ ] Framework／Skeleton `1.1.0` TagとPackagist Metadataが一致する
- [ ] 公開Packageから通常／`--no-scripts` Create-projectとQuickstartが成功する
- [ ] GitHub Release `1.1.0`が確定Release Noteを持つ

## Traceability

- Decision: [D094 Stable 1.1 Release Contract](../decisions/094-stable-1-1-release-contract.md)
- Contract: [Experimental Release Contract](61-experimental-release-contract.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)

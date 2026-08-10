# P22-002: Stable 1.2 Release Documentation

Status: Accepted

## Goal

公開済みStable `1.1.0`からRepository `main`の未公開`1.2.0` candidateまでのRelease Surfaceを実装、Git履歴、Migration、Package、Skeleton、Consumerから監査し、完全なCHANGELOG／実行可能なUPGRADE／利用者向けDocumentationへ同期する。実際の`1.1.0` TagからLocal `1.2.0` Packageへ更新するConsumer Journeyを固定し、後続Release Candidate Full Gateの入力を完成させる。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/decisions/139-stable-1-2-version-baseline.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/reports/P22-001-stable-1-2-version-baseline.md`
- Git tag `1.1.0` and committed Repository `main`

## Release Delivery Boundary

- P22-002: `1.1.0...main` Release Surface Audit、complete CHANGELOG／UPGRADE、actual Stable-to-candidate Framework Update Consumer、Documentation Review
- P22-003: fixed Release Candidate SHA、Full PHP／Consumer／Website／CI Gate、Publication Checklist
- P22-004: separately authorized Tag／Push／Skeleton publication／Packagist／GitHub Release／Remote Smoke

P22-002は`1.2.0`をLatest Stableまたは公開済みと表示しない。P22-002の作業中にTag、Push、GitHub Release、Packagist、Skeleton Distribution、Documentation Deployを変更しない。

## In Scope

- annotated tag `1.1.0`とcurrent committed candidateのCommit／File／Public API／Command／Configuration／Package Dependency／Database Migration／Skeleton Surface監査
- Phase 12〜21およびP20-018G／P22-001を利用者影響へ再分類した完全な`CHANGELOG.md` Unreleased `1.2.0` entry
- Stable `1.1.0` Applicationが`1.2.0`へ移行するための実行可能な`UPGRADE.md`
- Breaking／Added／Changed／Removed／Fixed／Known Limitationsの明示
- Application-owned Entrypoint／Bootstrap／Config／Migration／Generated SourceをFramework Updateが自動更新しない境界
- Actual Git tag `1.1.0` Package／SkeletonからLocal `1.2.0` candidateへ更新するFramework Update Consumer
- README、Releases、Quickstart、Security、Runtime、Database、Frontend、Observability等のStable-vs-candidate Release表示と正本Linkの同期
- Website content map／tests／artifact guardsの必要な更新
- Specification 103／Roadmap／TODO／Report／STATE同期
- Documentation ReviewerによるStable `1.1.0`とmain `1.2.0`両Channelの最終Review

## Out of Scope

- 新Feature、Public API追加、Production Runtimeの仕様変更
- Release Surface Auditで発見した実装／仕様矛盾の無断修正
- Full PHPUnit、全Consumer、Deptrac、broad Mago、GitHub ActionsによるFixed Candidate Gate
- `1.2.0` Release date確定、Unreleased sectionの公開済みVersion化
- annotated tag作成／Push、Skeleton Distribution更新、Packagist／GitHub Release作成
- Documentation Website Deploy

## Files Allowed to Change

- `CHANGELOG.md`
- `UPGRADE.md`
- `README.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/README.md`
- `docs/website/content-map.mjs`
- `docs/website/scripts/**`
- `docs/website/tests/**`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P22-002-stable-1-2-release-documentation.md`
- `develop/orchestration/reports/P22-002-stable-1-2-release-documentation.md`

Auditで範囲外のProduction／Test／Workflow修正が必要と判明した場合は実装を広げず、ReportへBlockerとして返す。

## Constraints

- Release Metadata、利用者向けDocumentation、Consumer TestはRepository設定のGPT-5.6 Luna High workerが変更し、Review前にCommitしない
- Audit Baseは既存annotated tag `1.1.0`とし、歴史的Tag内容を書き換えない
- CHANGELOGはMarketing Feature列挙ではなく利用者が認識できる契約変更、Migration、制約を記録する
- UPGRADEは順序、Working Directory、Application-owned manual merge、Database backup／migration、Credential／Key準備、Rollback境界を実行可能に説明する
- Credential、Storage Key、Session Token、Telemetry Payload、Sensitive Fixture値をReport／Logへ保存しない
- Third-party Version、HTTP `1.1`、historical Task／Decision／ReportをRelease Versionとして機械置換しない
- Website Generated Content／`dist`を直接編集しない
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Required Release Surface Inventory

Reportへ少なくとも次を記録する。

- Audit base／candidate commit and commit count
- Public `#[PublicApi]` type additions、removals、signature changes
- Composer runtime dependency additions／removals
- Framework／Application database migration inventory and required ordering
- Framework-owned command additions／removals／renames
- Application bootstrap、environment、configuration、runtime dependency ownership changes
- Authentication／Authorization／Actor／Tenant／Storage Protection and key rotation boundary
- Transaction／AfterCommit／Framework proxy and Ray removal boundary
- Diagnostics／Status／Frontend／Ephemeral Outcome／Scheduling／Seeder／Idempotency／Outbox／Replay／Observability surface
- Stable `1.1.0` unchanged lane and `1.2.0` known limitations

## Acceptance Criteria

- [x] Release Surface Inventoryがtag `1.1.0`とcurrent candidateの実装差分をEvidence付きで分類する
- [x] CHANGELOG Unreleasedが`1.2.0`のAdded／Changed／Removed／Fixed／Known LimitationsをPhase 12〜21まで完全に記録する
- [x] UPGRADEが`1.1.0`から`1.2.0`へのComposer、Application Source、Configuration、Database、Security／Storage、Runtime／Generated Artifact移行を実行可能に説明する
- [x] Actual Stable `1.1.0`からLocal candidate `1.2.0`へのFramework Update ConsumerがApplication-owned Source不変と明示Migration境界を実証する（latest exact rerun exit 0、cleanup／source-state invariant／generator smoke PASS）
- [x] Stable install CTAは`1.1.0`を維持し、main `1.2.0`をLatest Stable／公開済みと誤表示しない
- [x] README／Guide／Internal docs／WebsiteのRelease説明がCHANGELOG／UPGRADE正本へ接続する
- [x] Version guardがcomplete Release Note／Upgrade、actual `1.1.0`→`1.2.0` Update fixture、false publication claimを検査する
- [x] Composer strict、focused Consumer、Website test／check／build、management-ID／artifact／diff guardがPASSする（broad Magoはignored third-party traversalを除外したclean clone equivalentで確認）
- [x] Documentation Reviewer最終FindingがP1=0／P2=0である（P1=0／P2=0／P3=0、Acceptance permitted）
- [x] Report／STATE／TODO／Specification 103がP22-003 Full Gateへの入力として同期する

P22-003 fixed-SHA input contract: run the shared Database migration/setup and DDL guard evidence first, then Provider-present HTTP／Worker Positive and Provider-missing HTTP／Worker safe Negative lanes from the same candidate SHA. The Compatibility-first Consumer remains limited to Composer/source/hash, `frontend_manifest`, `build:compile`, and `operation:list` evidence.

## Required Commands

```bash
git cat-file -t refs/tags/1.1.0
git rev-list --count 1.1.0..HEAD
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
bash -n tests/Consumer/framework-update-generators.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-002-stable-1-2-release-documentation.md`へ次を記録する。

- Summary
- Audit Base and Candidate
- Release Surface Inventory
- Breaking and Migration Matrix
- CHANGELOG／UPGRADE Coverage
- Actual Framework Update Consumer Evidence
- Documentation and Website Coverage
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- P22-003 Inputs and Suggested Next Action

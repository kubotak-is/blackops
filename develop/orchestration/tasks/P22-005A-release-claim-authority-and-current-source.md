# P22-005A: Release Claim Authority and Current Source

Status: Accepted

## Goal

Stable `1.2.0`のcurrent-facing stale claimを修正し、Release Authority、historical allowlist、Source／Artifact共有classifier、CI wiringによって同じ退行をfail closedで拒否する。

## In Scope

- 機械可読Release Authority
- `docs/guide`全体と`content-map.mjs`のcurrent Stable claim修正
- 正当な`1.1.0` historical referenceのexact allowlist
- Source／Artifact共通release claim checkerとunit／negative fixture
- Search、raw Markdown、HTML metadata、`llms.txt`、`llms-full.txt` guard
- Website scripts、tests、package scripts、CI／Documentation Workflow wiring
- `version-baseline.sh`の共有checker wiring guard
- Task／Report／STATE／TODO同期

## Out of Scope

- Sidebar Section、Public Slug、Landing visual compositionの変更
- Guide本文の全面的な文章再構成
- Framework／Skeleton Production Code、Public API、Tag、Release、Packagist変更
- Website Production deploy
- `1.3.0`のcurrent documentation追加

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`

## Files Allowed to Change

- `docs/guide/*.md`
- `docs/website/content-map.mjs`
- `docs/website/package.json`
- `docs/website/scripts/*.mjs`
- `docs/website/tests/*.test.mjs`
- `.github/workflows/ci.yml`
- `.github/workflows/docs.yml`
- `tests/Consumer/version-baseline.sh`
- `AGENTS.md`
- `develop/orchestration/tasks/TEMPLATE.md`
- `develop/orchestration/reports/TEMPLATE.md`
- `develop/spec/release-authority.json`
- `develop/spec/README.md`
- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/orchestration/tasks/P22-005A-release-claim-authority-and-current-source.md`
- `develop/orchestration/reports/P22-005A-release-claim-authority-and-current-source.md`

## Allowed Checkpoint References

The following parent decision/specification files are explicit P22-005A allowed checkpoint scope for release-history and documentation-lifecycle contract: `develop/decisions/143-documentation-release-truth-and-information-architecture.md`, `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`. They are included in the P22-005A candidate even though this worker did not edit them. The separately proposed `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md` is unrelated untracked scope and is excluded from the P22-005A candidate.

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- `docs/guide`23件の`1.1.0` referenceは17件のstale current claimと6件のhistorical候補を区別し、一括置換しない
- Allowlistはpath、heading、normalized exact sentence、category、reasonを必須とし、unexpected／unused entryを拒否する
- CapabilityはFramework Package、Skeleton、Repository Example、Documentation-only Surfaceを区別する
- SourceとArtifactの自然言語判定は一つの依存なしNode moduleへ集約する
- Bashは判定を再実装せず、共有checker、fixture、Workflow wiringをguardする
- Generated `dist/`を直接編集しない
- `1.3.0`案はRoadmapであり、Stable提供済みとして公開しない

## Acceptance Criteria

- [x] Release AuthorityがStable `1.2.0`、固定Framework／Skeleton refs、Capability Surface、historical allowlistを保持する
- [x] 全current-facing stale `1.1.0`／未公開`1.2.0` candidate claimがSourceから除去される
- [x] 正当なhistorical referenceだけがexact allowlistで保持される
- [x] Source checkerが全GuideとContent Mapを検査する
- [x] Authorityのmapped page path／Capability ID／lane shapeと、mapped Source pageのcurrent-version／lane claimをfull-source checkerで検証する
- [x] Artifact checkerがHTML／metadata、raw Markdown、Search、LLM artifactを検査する
- [x] Roadmap version/state、Japanese unpublished candidate、HTML metadata/OG/Twitter/JSON-LD、artifact historical exact-matchをfail closedで検証する
- [x] current Stable downgrade、candidate化、Stable Capability main-only化、unexpected／moved／unused history、artifact-only stale injection、Authority-only version bumpのnegative fixtureが失敗する
- [x] `ci.yml`と`docs.yml`がBuild前Source guard／Build後Artifact guardを実行する
- [x] Website test／check／build、version baseline、management-ID、diff checkが成功する
- [x] Orchestrator独立ReviewとDocumentation ReviewがP1=0／P2=0を確認する

## Required Commands

```bash
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-005A-release-claim-authority-and-current-source.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Version occurrence before／after classification
- Source／Search／LLM artifact evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

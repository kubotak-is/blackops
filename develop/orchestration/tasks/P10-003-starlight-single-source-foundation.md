# P10-003: Starlight Single-source Foundation

Status: In Progress

## Goal

mise／pnpmで再現可能なAstro Starlight Projectを構築し、`docs/guide/`だけから公開Contentを決定的に生成・検証・Buildできるようにする。

## In Scope

- Repository Root `mise.toml`のNode.js 24 LTS／pnpm 11 Exact Pin
- `docs/website/` Astro Starlight ProjectとLockfile
- `docs/guide/`から未追跡Starlight Content／Manifestを生成するScript
- Title／Slug／内部Link／決定性／公開対象外Path Guard
- Astro Type Check、Static Build、Node Unit Test
- `.gitignore`とWebsite Development README
- 通常CIへのWebsite Check／Build Job追加
- Report／STATE更新

## Out of Scope

- Guide本文の全面改稿
- 最終Landing／Navigation Design
- Cloudflare Pages Deploy
- Custom Domain
- PHP Production Code変更

## Relevant Specifications and Decisions

- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`

## Files Allowed to Change

- `mise.toml`
- `.gitignore`
- `.github/workflows/ci.yml`
- `docs/website/**`
- `develop/orchestration/reports/P10-003-starlight-single-source-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- 原則としてGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答「Y」により、Phase 10に限り、Model／Profileを明示できない現在利用可能なWorkerで進めることを承認した
- この承認はPhase 10以外へ自動継続しない
- Node.js 24 LTSとpnpm 11は実装時点のPatch Versionを`mise.toml`へExact Pinする
- `packageManager`とCIのpnpm Versionをmiseと一致させる
- `pnpm-lock.yaml`をCommitし、CIはFrozen Lockfileを使う
- Generated Contentと`dist/`をCommitしない
- `docs/internal/`、`develop/`、Repository Absolute PathをContentへ取り込まない
- Source Markdownを生成処理から変更しない
- Website用本文を手動複製しない

## Acceptance Criteria

- [ ] `mise install`で固定Node／pnpmを導入できる
- [ ] Frozen Lockfile Installが成功する
- [ ] `content:generate`が`docs/guide/`だけからContentとManifestを生成する
- [ ] 同一入力からbyte-for-byte同じManifestを生成する
- [ ] Title不在、重複Slug、壊れた内部Link、Source外参照を拒否する
- [ ] Generated Contentと`dist/`がGit管理外である
- [ ] Astro Type CheckとStatic Buildが成功する
- [ ] Node Unit Testが正常系とGuard失敗系を検証する
- [ ] 通常CIがWebsite Check／BuildをCredentialなしで実行する

## Required Commands

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run content:generate
git diff --exit-code -- docs/website/src/content/docs docs/website/.generated
! git ls-files docs/website/src/content/docs docs/website/.generated docs/website/dist | grep .
! rg -n 'docs/internal|develop/' docs/website/dist docs/website/.generated
python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/ci.yml").read_text())'
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

存在しないGenerated Pathを扱うGuardは、Shellが成功／失敗を誤判定しない形へ実装時に調整し、Reportへ実Commandを記録する。

## Expected Report

`develop/orchestration/reports/P10-003-starlight-single-source-foundation.md` に次を記録する。

- Summary
- Toolchain and Lock Evidence
- Content Generation Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

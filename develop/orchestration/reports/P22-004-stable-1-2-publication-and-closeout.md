# P22-004 Stable 1.2 Publication and Closeout Report

Status: Accepted

## Summary

User authorized exact candidate `3332fd1dd0738fc7e79750facd93d49a59054ecf` CI qualification and Green-gated `1.2.0` publication. Same-SHA CI and Documentation delivery passed, corrected final Documentation Review returned P1=0／P2=0／P3=0, and PR #3 merged as `547149109419b62ab769af9d3aad1ed80dbba905`. Post-fetch ancestry and tree equality proved the fixed source is unchanged in remote `main` history.

P22-004 integrated its reviewed tracking checkpoint and completed immutable Framework／Skeleton publication. Framework direct tag object `00e8c587` peels to fixed source `3332fd1`; Skeleton direct tag object `fedcfda5` peels to fixed split `fa5e8247`; Packagist and GitHub Release `1.2.0` are live. Successful Manual Recovery `31889808876`／job `95024306339` completed the public-package normal／`--no-scripts` Remote smoke. P22-004G now synchronizes public／internal／website-source／baseline documentation; Website production deployment remains out of scope.

## Fixed Inputs

- Framework Source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Framework Merge Commit: `547149109419b62ab769af9d3aad1ed80dbba905`
- Skeleton Split: `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`
- Release Version: `1.2.0`
- Same-SHA CI: `31771509163` SUCCESS
- Documentation Delivery: `31771509167` SUCCESS
- P22-003: Accepted

## Preflight Evidence

- PASS: `3332fd1` is an ancestor of `origin/main` merge commit `5471491`; candidate and merge trees are identical.
- PASS: PR #3 is merged with exact candidate as second parent.
- PASS: independent read-only Documentation Review returned P1=0／P2=0／P3=0 and permitted an eight-management-document-only checkpoint Commit plus dedicated PR integration.
- PASS: tracking checkpoint Commit `cd0b025` merged through PR #4 as `55bfe123f9706c3ee5c7124ef4240060ae617f43`; local／remote main and clean Working Tree were verified.
- PASS before tag push: Framework／Skeleton `1.2.0` refs, GitHub Release, and both Packagist versions were absent; Actions secret name `SKELETON_DEPLOY_KEY` was present without reading its value.
- PASS: deterministic preflight regenerated exact Skeleton split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce` from exact Framework source `3332fd1`.

## Framework and Skeleton Publication Evidence

- Framework local annotated tag message: `BlackOps Framework 1.2.0`.
- Live Framework direct tag object: `00e8c5875047a3c47acbebfe57f75b0e581d18b9`.
- Live Framework peeled commit: `3332fd1dd0738fc7e79750facd93d49a59054ecf` — exact fixed source.
- Tag-triggered Skeleton publication run: `31809007808` — FAILURE.
- Passed before failure: checkout／tag validation, container-user configuration, image build, dependency install, Framework quality gates.
- Exact failure: `tests/Consumer/quickstart-e2e.sh: line 63: mise: command not found`; Consumer step exit `127`.
- Credential configuration and Skeleton publication steps were not reached. Always-run credential／temporary-state cleanup passed.
- Historical pre-recovery checkpoint: live Skeleton remote was `main=293f880940636669f28ded756a888a8d6ba65f1b`; direct／peeled `1.2.0` refs were absent at that checkpoint.

## Packagist and GitHub Release Evidence

- Packagist Framework `1.2.0`: PRESENT.
- Packagist Skeleton `1.2.0`: PRESENT and resolves the published annotated Skeleton tag.
- GitHub Release `1.2.0`: PRESENT; published `2026-08-15T16:21:09Z`.

## Remote Normal, No-scripts, and Quickstart Evidence

Remote normal／`--no-scripts` create-project resolved Skeleton／Framework `1.2.0` without Local Path repository or existing Composer cache. Project Root CLI、compile、12 migrations、HTTP welcome、Worker retry→Completed、and sensitive-value redaction passed; temporary resources were removed. After HTTP wrote root-owned `var/log/journal.jsonl`, non-root `operation:inspect` returned `diagnostics.storage_failed`; root comparison returned masked data. This confirmed bind-mount ownership limitation is recorded separately and is not an overall smoke failure.

## Immutable Tag, Credential, and Documentation Boundary

- Framework `1.2.0` is immutable and will not be moved, deleted, or recreated.
- Skeleton `1.2.0` tag and GitHub Release are immutable live publication inputs.
- No credential value has been read or recorded.
- Documentation Website production deployment is out of scope.

## Changed Files

- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003D-runtime-consumer-executable-mode.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/tasks/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/reports/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/tasks/P22-004C-quickstart-consumer-output-drain.md`
- `develop/orchestration/reports/P22-004C-quickstart-consumer-output-drain.md`
- `develop/orchestration/tasks/P22-004E-generator-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/reports/P22-004E-generator-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/tasks/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/reports/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/tasks/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-update-runtime.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/STATE.md`

## Decisions and Assumptions

- Release Source remains exact `3332fd1`; the merge／tracking commits are not retagged as Framework Source.
- Skeleton Split remains exact `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- User authorization covers the Green-gated fixed publication sequence without additional confirmation.
- Any Source correction returns to a new candidate/full gate rather than mutating the accepted inputs.

## Commands and Results

- PASS: `git fetch origin main`; remote `main` advanced to merge commit `5471491`.
- PASS: `git merge-base --is-ancestor 3332fd1 origin/main`.
- PASS: `git diff --quiet 3332fd1 origin/main`; candidate／merge trees match.
- PASS: GitHub PR metadata confirms PR #3 merged, head exact `3332fd1`, merge commit `5471491`.
- PASS: `git diff --check` before Task initialization.
- PASS: independent Documentation Review confirmed P22-003 Accepted evidence, fixed Framework Source／Skeleton Split, publication-unexecuted state, and eight-document-only scope.
- PASS: PR #4 CI `31792379283` and Documentation delivery `31792379255`; PR merged as `55bfe12`, then local main fast-forward and clean status passed.
- PASS: Framework annotated tag object/message/peeled commit verification before push and live direct／peeled refs after push.
- FAIL: Skeleton publication run `31809007808`, Consumer gates, missing `mise`, exit 127. Full failed-step log confirms failure occurred before credential configuration and distribution push; cleanup passed.
- PASS: User authorized Composer-installable release recovery; P22-004A worker implemented the two-Source-file bounded correction and Orchestrator independently reviewed and reran all focused guards.
- PASS: P22-004A commit `aa74ef5`, PR #5, Documentation delivery `31823195126`, and five of six CI jobs in run `31823195147`.
- FAIL: unchanged Runtime Consumer assumes `1.2.0` is absent and exits 128 with `fatal: tag '1.2.0' already exists` after the real immutable release tag became visible in full-history checkout.
- PASS diagnostic: release runtime paths are byte-identical between fixed source `3332fd1` and PR head `aa74ef5`.
- PASS: P22-004B correction committed as `13bd326`, new CI `31826566683` and Documentation delivery `31826566626` passed all required checks, and PR #5 merged as `f61dc037533f3dea54ba33df9e203c7727d06443`.
- FAIL: Manual Recovery run `31827240918` passed tag／toolchain／Framework quality gates but `quickstart-e2e.sh` exited 1 at `retention:plan | grep -q 'Total:'` with `write /dev/stdout: broken pipe`; credential and publication steps were skipped and cleanup passed.
- PASS historical diagnostic from the pre-publication recovery checkpoint: Skeleton remote was `main=293f880940636669f28ded756a888a8d6ba65f1b` with no direct／peeled `1.2.0` tag refs.
- PASS: P22-004C worker and Orchestrator independently passed full Quickstart Consumer without broken pipe plus version baseline, deterministic publication regression split `fa5e8247`, Mago format, management-ID, diff, scope, and cleanup checks.
- PASS: P22-004C／D commit `e80b0ac` passed PR #6 CI run `31878390676` and Documentation delivery `31878390735`; PR #6 merged as `8c8e975b62dcdb31b5cdf0474cdc5c313c458467`, and local `main` is clean.
- FAIL: Manual Recovery run `31878757317` passed Framework quality, full Quickstart, and Skeleton create-project, then Generator Consumer exited 128 with `fatal: tag '1.2.0' already exists`. Credential／publication steps were skipped and cleanup passed.
- PASS historical diagnostic from the pre-publication recovery checkpoint: live Skeleton remote was `main=293f880940636669f28ded756a888a8d6ba65f1b`; direct／peeled `1.2.0` refs were absent then.
- PASS: P22-004E worker implemented the bounded Generator post-release tag lifecycle and Manual-Recovery-only reviewed Quickstart／Generator harness overlay. The current published-tag Generator Consumer passed; static guards, Workflow regression, Mago format, management-ID, diff, and cleanup checks passed. No external mutation occurred.
- PASS: Orchestrator independently reviewed and reran both dynamic Generator lifecycle lanes: existing published annotated `1.2.0` resolved exact `3332fd1`, while a disposable clone with only its local `1.2.0` removed created and resolved the pre-release candidate at current `8c8e975`. Deterministic split, static／format／scope／cleanup guards also pass.
- PASS: Corrected independent P22-004E Documentation Review returned P1=0／P2=0／P3=0 and approved the exact ten-path Working Tree for one Commit／dedicated PR after the dual-array-member and trap／restore／hash／all-Consumer guard corrections.
- PASS: P22-004E commit `920d2f3`, PR #7 CI `31880456812`, and Documentation delivery `31880456849` passed all required checks; PR #7 merged as `f454e34d317e37b51085b1b87432561c9dd1ad44` and local／remote main are clean.
- FAIL: Manual Recovery run `31887488249` passed all Framework quality and Consumer gates, then `Verify generator resource ownership` compared 32 actual tracked root stubs with an obsolete four-file literal and exited 1. Credential／publication steps were skipped and cleanup passed.
- PASS diagnostic: filesystem and Git tracked root inventories are exact-equal at 32 files in both immutable tag and current main; no Quickstart nested stubs exist.
- PASS: `bash -n tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh`; `bash tests/Consumer/version-baseline.sh`; deterministic Skeleton workflow regression split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`; Mago format; management-ID; and `git diff --check`.
- PASS: P22-004F worker replaced the obsolete four-file ownership list with non-empty sorted root-relative filesystem／Git inventory equality, invalid-entry rejection, and retained Quickstart nested-stub prohibition. Static guards and required focused checks pass; no external mutation occurred.
- PASS: Orchestrator independently confirmed the exact 32-file inventory, all fail-closed ownership paths, deterministic split `fa5e8247`, format, management-ID, release-runtime Source equality, diff, nine-path scope, and cleanup.
- PASS: Corrected independent P22-004F Documentation Review returned P1=0／P2=0／P3=0 and approved the exact nine-path Working Tree for one Commit／dedicated PR.

## Acceptance Criteria

- [x] Tracking checkpoint is reviewed and integrated through the PR-required remote path.
- [x] Framework and Skeleton annotated tags match fixed inputs.
- [x] Skeleton publication workflow succeeds and cleans credentials.
- [x] Packagist and GitHub Release expose exact `1.2.0` metadata.
- [x] Published-package normal／no-scripts／runtime smoke succeeds and cleans temporary state; root-owned journal bind-mount limitation is separately recorded.
- [x] Existing tags, credential values, and documentation production state remain unchanged.
- [x] Phase 22 tracking is closed with evidence (P22-004G final Documentation Review P1=0／P2=0／P3=0).

P22-004C preserves immutable release Source `3332fd1`, drains complete Docker Compose output before assertions, and permits the reviewed dispatch-SHA Quickstart harness after fail-closed release-runtime equality; it is integrated through all-Green PR #6. P22-004E added the Generator post-release tag lifecycle and Manual-Recovery-only Generator harness overlay and is integrated through all-Green PR #7. P22-004F corrected the obsolete Generator resource inventory and was integrated before the successful publication recovery. P22-004G historically owned the public documentation closeout and recorded the separate `operation:inspect` ownership follow-up without implementing it; P22-004H now owns the current bounded PR #9 correction.

P22-004G changed only the allowed public／internal／website-source／baseline／management documentation and Website assertion files. The closeout records Framework／Skeleton direct and peeled tag objects, Manual Recovery `31889808876`／job `95024306339`, GitHub Release publication time, public normal／`--no-scripts` smoke successes, and the confirmed non-root `operation:inspect` `diagnostics.storage_failed` limitation caused by root-owned `var/log/journal.jsonl` on a bind mount. No Production Code、Consumer runtime behavior、Workflow、Tag、Release、Packagist, or Website production state changed.

P22-004H is the current post-PR #9 correction and remains uncommitted after Orchestrator Review and Documentation Review P1=0／P2=0. Its local evidence covers fail-closed Manual Recovery restoration, README-only release-runtime separation with all other paths checked, the full Runtime Consumer, and exact Blume `1.3.0` local Ubuntu font variants with artifact rejection of remote providers and license/reference checks. It does not alter the immutable public release, rerun CI, or change the separately recorded root-owned journal limitation.

## Remaining Issues

- P22-004F Commit／PR／CI／merge and the successful Manual Recovery are complete; the historical one-shot Dispatch restriction is closed.
- P22-004G documentation implementation, independent Orchestrator review, final Documentation Review, and Phase 22 acceptance are historical closeout evidence. P22-004H Orchestrator／Documentation Review is complete; the exact reviewed change requires one Commit, PR #9 push, same-SHA CI／Documentation delivery Green, and merge／fetch. No production Website deploy is authorized.
- The root-owned journal bind-mount behavior remains a separate follow-up; no Source correction or `1.2.1` publication is included here.

## Suggested Next Action

Create the exact P22-004H reviewed Commit, push PR #9, require same-SHA CI／Documentation delivery Green, and only then decide merge／fetch of a clean `main`; re-verify immutable public refs／metadata. Do not mutate tags, Release, Packagist, or deploy the Website.

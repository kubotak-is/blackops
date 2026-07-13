# P9-004: Framework Update Generator Smoke and Phase 9 Closeout

Status: In Progress

## Goal

既存ProjectがEntrypointと生成済みSourceを保持したままFramework Update後のGenerator Command／Stubを利用できることをConsumer境界で検証し、Phase 9をCloseする。

## In Scope

- Framework Update前後を再現するLocal Consumer Smoke
- Project `bin/blackops` Hash不変検証
- Update前生成Operation／Migration Hash不変検証
- Update後のFramework所有Command／Stub利用検証
- Generatorを含むLocal Create-project／Quickstart Consumer E2E
- Publication Source AllowlistへのFramework Stub包含確認
- Guide／Internals／Quickstart／MVP Status同期
- Full Quality Suite
- TODO、Phase Plan、Report、STATE Closeout

## Out of Scope

- Packagistへの新Release公開
- Distribution Repositoryへの新Tag Push
- 既存Application Sourceの自動Upgrade
- Documentation Website
- Generator追加Option

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/080-project-generator-command-contract.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`

## Files Allowed to Change

- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `.github/workflows/publish-skeleton.yml`
- `docs/guide/README.md`
- `docs/guide/project-generators.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/installed-application-status.md`
- `docs/guide/mvp-status.md`
- `docs/internals/README.md`
- `docs/internals/project-generators.md`
- `docs/internals/skeleton-publication.md`
- `examples/quickstart/README.md`
- `develop/TODO.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/orchestration/tasks/P9-004-framework-update-generator-smoke.md`
- `develop/orchestration/reports/P9-004-framework-update-generator-smoke.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- SmokeはRepository外の一時Directoryを使い、終了時にCleanupする
- External Package Publication／Remote Mutationを行わない
- Credential、Token、Composer Authenticationを保存しない
- Update前Project Entrypointと生成済みSourceをTest都合で書き換えない
- Framework UpdateとSkeleton Updateを混同しない

## Acceptance Criteria

- [ ] 既存ProjectのFramework DependencyだけをLocal旧版相当からCurrentへ更新できる
- [ ] Update前後で`bin/blackops`がbyte-for-byte不変である
- [ ] Update前生成Operation／Migrationがbyte-for-byte不変である
- [ ] Update後にCurrent Frameworkの`make:operation`／`make:migration`とStubを利用できる
- [ ] Framework StubがFramework Packageへ含まれ、Skeletonへ複製されていない
- [ ] Local Create-project／Quickstart Consumer E2EがGenerator込みで成功する
- [ ] Full Quality Suiteが成功する
- [ ] TODO／Phase Plan／Report／STATEがPhase 9 Completeへ同期する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! find examples/quickstart -type f -path '*/stubs/*' -print -quit | grep .
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P9-004-framework-update-generator-smoke.md` に次を記録する。

- Summary
- Framework Update Evidence
- Stub Ownership Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

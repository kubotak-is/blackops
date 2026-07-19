# P16-007: Consumer Experience and Phase Closeout

Status: Ready

## Goal

P16-006までに実装したPublic Status ResourceとGenerated `.status()`／`.wait()`を、Install直後のQuickstart／Composer Skeleton、Real HTTP Consumer E2E、利用者向けGuide、Documentation Website Sourceへ統合する。

QuickstartのGenerated `GenerateReport`から実HTTPへ202受付、一回Status取得、Worker Retry、Terminal Completed、Typed Outcome取得までを一続きで検証する。Application所有`OperationStatusAuthorizer`の安全な例、Unknown／Unauthorized／Sensitive境界、Skeleton／Publication／Framework Update保持を固定し、Full PHP／Frontend／Consumer／Website Gateを完走してPhase 16をCloseする。

Documentation WebsiteはLocal／CIでTest／Check／Buildするが、外部公開、Deploy、Cloudflare変更は行わない。

## In Scope

- Quickstart Application所有`OperationStatusAuthorizer`実装とService Binding
- Current ActorとPersisted Origin Actorを使う最小Same-origin Policy例
- Quickstart Frontend TypecheckとReal HTTP `.status()`／`.wait()` Journey
- Deferred 202 `Location`／`Retry-After`、accepted／retry_scheduled／completed、Typed Outcomeの実Response確認
- Status QueryのAuthentication、Unavailable、Safe Error、No-store境界
- Worker未起動中Non-terminalとWorker起動後Terminalの対
- WaitのRequired Signal／Deadline、有限Timeout、Error即時停止
- Sensitive Input／Credential／Actor／Raw Error非露出
- Skeleton通常／`--no-scripts`、Publication Dry-run／Workflow、Framework UpdateのApplication-owned File保持
- Guide／Website SourceのStatus／Outcome／Polling／Authorization／Retention／Troubleshooting同期
- Current StatusのStable 1.1.0と`main`差の正直な更新
- Full Quality GateとPhase 16 Acceptance Criteria／TODO／STATE同期

## Out of Scope

- Documentation Website Publication／Deploy／Cloudflare設定
- React／Vue／Svelte／Inertia Adapter、Hook、Form Helper
- Global Generated Client、Credential Store、Cache、Offline Queue
- 自動Polling付き`.fetch()`、無限Wait、任意Retry／Backoff／Jitter
- Production認証方式、Tenant Model、Role／Permission Repository
- Public PHP／HTTP／Generated Runtime Contract、Manifest Schema、Database Schema／Migration変更
- Stable 1.1.0再公開、Framework／Skeleton Release、Tag作成、Packagist操作
- Retention PolicyやStatus AuthorizationのProduction既定値決定

Quickstart／Guide統合で既存Public Contractの変更が必要になった場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/decisions/090-documentation-information-architecture.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`
- `develop/orchestration/reports/P16-006-generated-wait-and-frontend-ci.md`

## Files Allowed to Change

### Installed Quickstart and Skeleton Source

- `examples/quickstart/app/ApplicationServiceProvider.php`
- New `examples/quickstart/app/Security/SampleOperationStatusAuthorizer.php`
- `examples/quickstart/resources/js/application/operations.ts`
- `examples/quickstart/tests/Frontend/typecheck.ts`
- `examples/quickstart/tests/Frontend/real-http.ts`
- `examples/quickstart/README.md`
- `examples/quickstart/bin/setup`（表示手順同期が必要な場合だけ）

既存Operation／Value／Outcome、Config、Migration、Composer／Frontend Dependencyは変更しない。Generated `resources/js/blackops`と`.build`はCommitしない。

### Consumer, Skeleton, Publication, CI Regression

- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/quickstart-setup.sh`（新しい表示／File Guardに必要な場合だけ）
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Consumer/framework-update-generators.sh`
- `.github/workflows/ci.yml`（既存Jobが新Evidenceを実行しない場合だけ）

Consumer Scriptは成功／失敗にかかわらずTemporary Directory、Process、Container、Volume、Generated ArtifactをCleanupする。

### Guide and Website Source

- `docs/guide/README.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/first-operation.md`
- `docs/guide/execution.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/configuration.md`
- `docs/guide/directory-structure.md`
- `docs/guide/testing.md`
- `docs/guide/deployment.md`
- `docs/guide/core-api.md`
- `docs/guide/mvp-status.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`（新Pageが必要な場合だけ。IA順序は変更しない）
- `docs/website/tests/*.test.mjs`
- `docs/website/scripts/*.mjs`（Content Contract同期が必要な場合だけ）

新しいGuide Pageは原則追加せず、Status／Wait Journeyを既存Quickstart、Execution、Outcome、Security、TroubleshootingへDiátaxisに従って配置する。Generated `docs/website/src/content/docs/**`、`.generated`、`dist`はCommitしない。

### Internal Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/status-query.md`
- `develop/spec/60-post-phase-10-roadmap.md`（Phase完了Check同期だけ）
- `develop/spec/69-deferred-status-and-outcome-api.md`（Acceptance Check同期だけ）
- `develop/spec/70-phase-16-delivery-plan.md`（Acceptance Check同期だけ）
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`

上記以外の変更が必要な場合は実装を広げずReportへ記録する。

## Quickstart Status Authorization Contract

QuickstartはApplication所有`SampleOperationStatusAuthorizer`を実装し、`ApplicationServiceProvider`から`OperationStatusAuthorizer::class`へBindingする。

- Current ActorとOrigin Actorが両方存在する場合だけ検討する
- 両Actorの`id`と`type`が完全一致し、`type === 'user'`の場合だけAllowする
- Actor欠落、Type不一致、ID不一致はDenyする
- Operation IDまたはOperation Typeだけを知っていてもAllowしない
- Credential、Role、Token、Payload、Outcome、Journal DetailをAuthorizerへ要求しない
- SampleはTenant／Production PolicyではないことをREADME／Securityへ明記する
- Application Bindingを外した場合はFramework既定Denyで同じSafe 404になる既存境界を維持する

Unit Test専用のApplication Production Test Fileは追加せず、Build、Skeleton File Guard、Real HTTP E2Eで実装とBindingを確認する。PHPUnit回帰が必要なら既存Quickstart／Consumer責務Testへ限定する。

## Quickstart Frontend Contract

Application-owned TypeScriptはGenerated Fileを手書きせず、Operation ObjectのCapabilityを利用する。

```ts
const accepted = await GenerateReport.fetch(value, callOptions);
if (!accepted.ok) return accepted;

const current = await GenerateReport.status(accepted.data.operationId, callOptions);
const terminal = await GenerateReport.wait(accepted.data.operationId, {
  ...callOptions,
  signal,
  maxWaitMilliseconds: 15_000,
});
```

- `.status()`の7 StateとErrorをTypecheckでNarrowingする
- `.wait()`はTerminal 4 State／FailureだけをNarrowingし、Non-terminalを返り型へ持たない
- Typed `ReportGenerated` Outcomeの既存Public Property `reportName`／`location`をCompletedから読む
- `.fetch()`は202後に自動Status／WaitしないことをFetch回数／URLで確認する
- DOMなしQuickstart TypeScriptを維持する
- Node実行では購読可能な小さいStructural SignalをApplication-owned Test Helperとして使ってよい
- Default Clock／TimerとInjected FetchをReal HTTPで利用し、Global Mutable Clientを作らない
- OutputはInput／Resultの対で示し、Credential／Sensitive値を出力しない

## Real HTTP Consumer E2E

`tests/Consumer/quickstart-e2e.sh`とQuickstart `real-http.ts`は少なくとも次を一続きで検証する。

1. Generated `GenerateReport.fetch()`が実HTTP 202とOperation IDを返す
2. `.fetch()`直後の`.status()`が同じCredentialで`accepted` 200、正整数Retry Hintを返す
3. Status GETがOperation Route Body／Header Bindingを流用せず、`private, no-store`を維持する
4. Node `.wait()`を開始したままShell側でWorker Attemptを進める
5. First AttemptはRetry Scheduled、Delay後のSecond AttemptはCompletedになる
6. `.wait()`が同じOperation IDのCompletedとTyped `ReportGenerated` Outcomeを返す
7. Worker未起動の別Operationまたは短いDeadlineが有限`poll_timeout`になり、後続Worker処理を壊さない
8. Missing／Invalid TokenはAuthentication 401、UnknownまたはDenyはSafe 404になりDetailを露出しない
9. Terminalへ余分なRetry-Afterがなく、Malformed／5xxを自動Retryしない既存Generated Evidenceを弱めない

E2EのProcess協調は固定Sleepだけに依存せず、Database Stateまたは安全な出力Fileを有限回PollしてNode Wait開始を確認してからWorkerを進める。Background ProcessはTrapで停止・回収する。

## Sensitive and Security Evidence

- `recipientEmail`、Sample Token、Actor ID、Worker ID、Raw Error、Canonical PayloadをPublic Status／Wait Resultへ含めない
- Completed OutcomeはPublic Outcome Propertyだけを返す
- Unknown／Denyを区別せず404にする
- Authorized Retention Expiredだけ410であることをGuideへ明記する。E2EでRetention Dataを破壊して無理に410を作らない
- `private, no-store`とNon-terminal `Retry-After`を実HTTPで確認する
- Browser／Frontendの型安全性はAuthentication／Authorizationを代替しないと説明する
- Operation IDはSecretではなく相関Keyだが、知っているだけで参照権限を得ない
- ApplicationがAuthentication、Tenant Policy、Encryption、Access Control、Retentionを所有する責任分界を維持する

## Skeleton, Publication, and Framework Update

- 通常`composer create-project`と`--no-scripts`が新Status AuthorizerとFrontend Source／Testをbytes単位で持つ
- `ApplicationServiceProvider` BindingがInstall Artifactに含まれる
- `--no-scripts`は引き続き`php bin/setup`を明示実行する
- `skeleton-publication.sh --dry-run`とPublication Workflow Regressionが新FileをDistribution／Split Commitへ含める
- Workerは未Commit差分のため`bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`を成功扱いしない。Working-tree `--dry-run`を実行し、Commit後にOrchestratorがHEAD Gateを実行する
- Framework UpdateはApplication-owned Authorizer、Provider Binding、Frontend Source／Test／READMEを書き換えない
- Update後のProject Root `blackops`からCurrent Build／Generate／Check／Typecheckへ到達する
- Generated Tree、Build Artifact、Node Modules、Frontend EmitをDistributionへ混入させない

## Guide and Website Contract

読者向け文書はspec調ではなく、入力、Command、出力、次の判断が分かる能動態で書く。

- Quickstart: Installから202、Operation ID、`.status()`、Worker、`.wait()` CompletedまでInput／Output対で示す
- First Operation: Canonical DBを直接読む主JourneyをPublic Status Resource／Generated Clientへ置き換え、低Level `OutcomeReader`はPHP Adapter向けReferenceへ分離する
- Execution: `.fetch()`は受付だけ、`.status()`は一回、`.wait()`は明示的有限待機と説明する
- Outcome: Public Status Query／Generated ClientでPending／Terminal／Expiredを区別し、`OutcomeReader`の用途も残す
- Security: Status Authorizer、Unknown／Deny 404、Origin Actor、Retention 410の責任分界を追加する
- Troubleshooting: 202だがWorker未起動、404、410、`poll_timeout`、`unexpected_response`をSymptom／Cause／Verify／Fixで説明する
- Configuration／Directory／Testing／Deployment: Service Binding、Generated Capability、Worker監督、Test境界を同期する
- Core API: `BlackOps\Status`のPublic Query／Status／Authorizer／Decision／Exceptionを用途別に追加し、Internal Source DTOを載せない
- Current Status: Stable 1.1.0はNot available、`main`はDeferred Status Resource／Generated `.status()`／`.wait()` Available（Experimental）へ更新し、古い「未提供」記述を削除する
- WebsiteのStable／main Banner、Current Statusの正直さ、既存IAを維持する

Website Generated ContentはCommitせず、Content Generate／Check、Test、Astro Check、Build後にCleanupする。外部URLへPublish／Deployしない。

## Acceptance Criteria

- [ ] QuickstartがApplication所有Status Authorizerと明示Bindingを持つ
- [ ] Same-origin Actor一致だけAllowし、欠落／不一致をDenyする
- [ ] Install直後のSkeleton通常／`--no-scripts`へAuthorizerとFrontend Journeyが含まれる
- [ ] Generated `.status()`が実HTTP acceptedを一回取得する
- [ ] Generated `.wait()`がWorker Retry後のCompleted Typed Outcomeを返す
- [ ] Worker未起動が有限Timeoutとなり、`.fetch()`は自動Pollingしない
- [ ] 401／404／Safe Header／No-store／Retry-Afterを実HTTPで確認する
- [ ] Sensitive Input／Credential／Actor／Raw Error／Canonical DetailがPublic Result／Artifact／Logへ露出しない
- [ ] Skeleton Publication Dry-run／WorkflowとFramework UpdateがApplication-owned Fileを保持する
- [ ] Guide／Website SourceがStatus／Wait／Authorization／Retention／Troubleshootingを同期する
- [ ] Stable 1.1.0と`main` Experimental Surfaceを正しく区別する
- [ ] Full PHP／Frontend／Consumer／Skeleton／Publication／Website Gateが成功する
- [ ] Generated／Build／Node Modules／Website Content／Dist ArtifactをCommitしない
- [ ] Documentation Websiteを外部公開しない
- [ ] Phase 16 Delivery Plan／Roadmap／TODO／STATEをClosedへ同期する
- [ ] WorkerはCommitしていない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
mise exec -- pnpm --dir tests/Frontend run clean

bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/framework-update-generators.sh

mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run content:generate
mise exec -- pnpm --dir docs/website run content:check
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests examples --glob '*.php'
! rg -n 'credential-secret|sensitive-value|default-must-not-appear|raw-body' \
  examples/quickstart/resources/js/blackops docs/website/dist
! git ls-files \
  examples/quickstart/resources/js/blackops \
  examples/quickstart/node_modules \
  examples/quickstart/.build \
  tests/Frontend/fixture/var/build \
  tests/Frontend/fixture/resources/js/blackops \
  tests/Frontend/.build \
  docs/website/src/content/docs \
  docs/website/.generated \
  docs/website/dist | rg .
git diff --check
```

Task完了時にQuickstart／Frontend／WebsiteのGenerated ArtifactをCleanupする。Worker未Commit時はPublication `HEAD` Gateを未実行としてReportへ記録し、Orchestratorが実装Commit直後に実行する。

## Expected Report

`develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Quickstart Status Authorization Matrix
- Frontend `.status()`／`.wait()` Type Matrix
- Real HTTP 202-to-Terminal Journey
- Authentication／Unavailable／Expired／Header Matrix
- Sensitive／Credential／Actor／Raw Error Evidence
- Skeleton／Publication／Framework Update Evidence
- Guide／Website／Current Status Evidence
- Commands and Results
- Phase 16 Acceptance Criteria
- Remaining Issues
- Suggested Next Action

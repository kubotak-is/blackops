# P10-005G: FrankenPHP Worker Mode Default Promotion Report

Status: Accepted

## Summary

Install直後のQuickstart Default `http`をFrankenPHP Worker Modeへ昇格した。Default Service名`http`とPort 8080を維持し、`public/worker.php`、Process単位Application／Configuration再利用、`FRANKENPHP_MAX_REQUESTS`を標準構成にした。

従来のClassic Modeは`Caddyfile.classic`と`http-classic` Serviceへ移し、`classic-mode` Profileから明示的に起動するFallbackとして維持した。P10-005Fで構築したState Isolation、Journal Flush、Database Reconnect、Memory Bound、`max_requests` Restart EvidenceはDefault `http`に対して継続検証した。

## Default Service Evidence

- `Caddyfile`は`/app/public/worker.php`、`num 1`、`match *`を指定するWorker Mode構成である
- `FRANKENPHP_MAX_REQUESTS`はDefault `http`へ渡され、未指定時は1000である
- Default Compose ConfigのService Setは`http`と`postgres`だけである
- Default HTTPはService名`http`、公開Port 8080を維持する
- Deferred Worker、Scheduler、Migration、Retention、Classic HTTPはDefault Service Setへ含まれない

## Classic Fallback Evidence

- `Caddyfile.classic`はWorker Blockを持たない`php_server`構成である
- `http-classic`は`classic-mode` Profileに限定され、Default Service Setへ含まれない
- Classic HTTPの既定Portは8081であり、`CLASSIC_HTTP_PORT`から変更できる
- Consumer E2Eの最後に`http-classic`を起動し、必須`X-Sample-Token` Header付きWelcome Requestが成功した
- Quickstart READMEのDefault／Classic curlはいずれも必須Token Headerを含み、Architecture TestとPublication Guardで固定した

## State, Memory, and Reconnect Evidence

- 同一Default `http` WorkerでSuccess、Validation Rejected、Database Throwable、Reconnect後Success、32連続Requestを処理した
- Process Bootstrap、Request終了時Journal Flush、Rejected後の継続、Operation ID分離、Secret非露出を確認した
- PostgreSQL停止中は500 `internal_error`となり、同じHTTP Workerを停止せずDatabase再起動後に200へ復旧した
- `FRANKENPHP_MAX_REQUESTS=8`でWorker Restartを確認し、各Bootが8 Requestを超えないことを検査した
- BootごとのMemory増分を16 MiB以下として検証し、EvidenceにSensitive値が残らないことを確認した

## Changed Files

- Quickstart Runtime: `examples/quickstart/Caddyfile`、`Caddyfile.classic`、`compose.yaml`、`.env.example`、`README.md`。旧`Caddyfile.worker`はDefault `Caddyfile`への昇格に伴い削除
- Tests: `tests/Architecture/QuickstartApplicationArchitectureTest.php`、`tests/Consumer/frankenphp-worker-mode.sh`、`skeleton-create-project.sh`、`skeleton-publication.sh`
- Website Tests: `docs/website/tests/guide-code.test.mjs`、`reader-experience.test.mjs`
- Guide: `docs/guide/README.md`、`mvp-sample.md`、`mvp-status.md`、`runtime-bootstrap.md`
- Internal Documentation: `docs/internal/frankenphp-runtime.md`、`installed-application-status.md`
- Specifications and Decision: `develop/spec/44-public-application-bootstrap-api.md`、`49-feature-first-quickstart-application.md`、`51-local-runtime-and-consumer-e2e.md`、`58-phase-10-delivery-plan.md`、`develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- Orchestration: 本Report、`develop/STATE.md`

## Decisions and Assumptions

- D085の確定方針とP10-005F Acceptance Evidenceに従い、追加のRuntime API変更なしでDefaultを昇格した
- Default Service名とPortは互換性のため`http`／8080のままとし、Classic Fallbackだけを`http-classic`／8081へ分離した
- Worker E2E用Boot／Memory Evidence Environmentは未指定時に空であり、通常利用ではArtifactを生成しない
- FrameworkがRequest境界でcleanupする対象はOperation Scope、Observer Buffer、Connection、`$_ENV`である。Application固有Service Stateは汎用resetせず、Application ServiceがRequest固有Stateをproperty／staticへ保持しない責務境界とした
- Stable `1.0.0`は変更せず、Current Skeletonとの差を維持した
- 実行環境からModel／Profile Metadataを確認できないため、Phase 10対象TaskでUserが回答`Y`により承認した現在利用可能なWorker利用の例外に従った

## Commands and Results

```text
docker compose --project-directory examples/quickstart -f examples/quickstart/compose.yaml config
Result: Success. Default Service Setはhttp、postgres。httpはWorker Caddyfile、Port 8080、max_requests 1000。

docker compose --project-directory examples/quickstart -f examples/quickstart/compose.yaml --profile classic-mode config
Result: Success. Default Setへhttp-classicだけが追加され、Classic CaddyfileとPort 8081を使用。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (8 tests, 142 assertions).

bash -n tests/Consumer/*.sh
Result: Success.

bash tests/Consumer/frankenphp-worker-mode.sh
Result: FrankenPHP worker mode consumer E2E passed. Default WorkerのBootstrap、Flush、Rejected、DB 500／Reconnect、32 Request、Memory、max_requests RestartとClassic Fallbackを検証。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed. 通常／--no-scripts両方のCurrent Layoutを検証。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Skeleton publication dry run passed. version=1.0.1、split=working-tree。Caddyfile.classicとREADME HeaderをDistributionで検証。

mise exec -- pnpm --dir docs/website run test
Result: Reviewer修正後の最終Runも33 tests / 33 passed / 0 failed. Stable／main Status、Default Worker／Classic Fallback、Application ServiceのRequest State責務を説明するRuntime Guideを検証。

Stale Worker Layout Guard、PHP Management ID Guard、git diff --check
Result: すべて成功。
```

Docker SocketへSandbox内から接続した最初のComposer／Format／Architecture実行はPermission Deniedになった。承認済みDocker実行へ切り替え、上記の最終結果はすべて成功した。Website testの最初の追加Runは旧Opt-in期待2件を検出したため、Task PacketへTest範囲を追加してDefault Worker／Classic Fallback期待へ同期し、最終Runで全件成功した。

## Acceptance Criteria

- [x] Default `http`が`public/worker.php`を使いApplication／ConfigurationをProcess単位で再利用する
- [x] Default Compose Service SetがPostgreSQLとWorker Mode HTTPだけである
- [x] Default HTTP Service名`http`とPort 8080を維持する
- [x] Classic Modeを明示Profile／Serviceで起動できる
- [x] Default WorkerでState Isolation、Flush、Reconnect、Memory、`max_requests`をConsumer E2E検証する
- [x] Classic FallbackのWelcome Requestが成功する
- [x] Quickstart Consumer、Create-project、Publication Guardが新Layoutで成功する
- [x] Guide／Current StatusがWorker Mode DefaultとClassic Fallbackを説明する
- [x] Stable `1.0.0`との差を維持する

## Remaining Issues

- Repository内実装のRemaining Issueはない
- Application固有Stateful Singletonの自動Reset Frameworkは本Taskの範囲外であり、Request固有StateをService／staticへ保持しないContractを維持する
- Cloudflare Project／Credential／GitHub Environment設定はRemote DeploymentだけのExternal Blockerとして継続する

## Suggested Next Action

P10-006でPhase 10 Closeoutを進め、Repository内Acceptanceを確定する。Cloudflare External Configurationは取得可能なEvidenceと未設定Blockerを分離する。

## Orchestrator Review

2026-07-14T03:55:32+09:00にDefault／Classic Compose境界、Caddyfile命名、Distribution Layout、D085／確定仕様、Guide、Architecture／Consumer GuardをReviewした。Application固有Service StateをFrameworkが汎用Resetするよう読める仕様表現を修正へ戻し、Framework CleanupとApplication責務を分離した。Compose Config、Website 33 tests、Architecture 8 tests／142 assertions、Shell／Stale Layout／Management ID／diff Guardを独立再検証し、Default `http`に対するWorker Consumer E2EでBootstrap、Flush、Rejected後継続、DB 500→Reconnect、32 Request分離、Memory、`max_requests` Restart、Classic Fallbackを完走したためAcceptedとする。

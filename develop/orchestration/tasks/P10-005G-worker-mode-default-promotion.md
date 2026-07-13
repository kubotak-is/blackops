# P10-005G: FrankenPHP Worker Mode Default Promotion

Status: Accepted

## Goal

D085で確定した最終到達点とP10-005Fの安全性Evidenceに従い、Install直後のDefault HTTPをFrankenPHP Worker Modeへ昇格し、Classic Modeを明示Fallbackとして維持する。

## In Scope

- Quickstart Default `http` ServiceのWorker Mode化
- Default `docker compose up -d`でPostgreSQLとWorker Mode HTTPだけを起動する構成
- Classic HTTPの明示Profile／Service Fallback
- CaddyfileのDefault／Fallback命名整理
- Port、`FRANKENPHP_MAX_REQUESTS`、E2E Evidence Environment
- Default Worker ModeとClassic FallbackのConsumer E2E
- Quickstart／Skeleton Publication Guard
- Guide／Internal Document、確定仕様、Report、STATE同期

## Out of Scope

- Deferred Operation Worker、Scheduler、Migration、PurgeのDefault常駐化
- Application固有Stateful Singletonの自動Reset Framework
- PHP-FPM／RoadRunner／Swoole対応
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/058-frankenphp-runtime-premise.md`
- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/58-phase-10-delivery-plan.md`

## Files Allowed to Change

- `examples/quickstart/Caddyfile`
- `examples/quickstart/Caddyfile.classic`
- `examples/quickstart/Caddyfile.worker`
- `examples/quickstart/compose.yaml`
- `examples/quickstart/.env.example`
- `examples/quickstart/README.md`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/**`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/tests/**`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/orchestration/reports/P10-005G-worker-mode-default-promotion.md`
- `develop/STATE.md`

## Constraints

- 原則Codex GPT-5.4-mini workerが実装し、Review前にCommitしない
- UserはPhase 10対象TaskでModel／Profile Metadataを確認できない現在利用可能なWorkerを使う例外を回答`Y`で承認済み
- Default HTTP Service名とDefault Port `http`／8080は維持する
- `Caddyfile`をDefault Worker Mode、`Caddyfile.classic`をFallbackとして明確にする
- Classic FallbackはDefault Service Setへ含めない
- Deferred Worker、Scheduler、Migration、RetentionをDefault起動しない
- Secretを含むConfiguration Artifactを生成しない
- Request State、Exception、Connection、Journal Flush、Memory、RestartのP10-005F Evidenceを弱めない

## Acceptance Criteria

- [ ] Default `http`が`public/worker.php`を使いApplication／ConfigurationをProcess単位で再利用する
- [ ] `docker compose up -d`がPostgreSQLとDefault Worker Mode HTTPだけを起動する
- [ ] Default HTTP Portが8080のままである
- [ ] Classic Modeが明示Profile／Serviceで起動できる
- [ ] Default WorkerでState Isolation、Flush、Reconnect、Memory、`max_requests`をConsumer E2E検証する
- [ ] Classic FallbackのWelcome Requestが成功する
- [ ] Quickstart Consumer、Create-project、Publication Guardが新Layoutで成功する
- [ ] Guide／Current StatusがWorker Mode DefaultとClassic Fallbackを正直に説明する
- [ ] Stable `1.0.0`との差を維持する

## Required Commands

```bash
docker compose --project-directory examples/quickstart -f examples/quickstart/compose.yaml config
docker compose --project-directory examples/quickstart -f examples/quickstart/compose.yaml --profile classic-mode config
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
bash -n tests/Consumer/*.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
! rg -n 'Caddyfile\.worker|profile worker-mode|--profile worker-mode|http-worker' examples/quickstart tests/Consumer docs/guide docs/internal
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005G-worker-mode-default-promotion.md`へSummary、Default Service Evidence、Classic Fallback Evidence、State／Memory／Reconnect Evidence、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

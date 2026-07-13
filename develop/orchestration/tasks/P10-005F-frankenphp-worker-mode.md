# P10-005F: FrankenPHP Worker Mode

Status: Accepted

## Goal

Application、Environment、Configuration、Compiled RuntimeをProcess起動時に一度だけ構成するFrankenPHP Worker Modeを明示Opt-inで導入し、Request間のState Safetyを実証する。

## In Scope

- FrankenPHP Worker Entrypoint／Caddy Configuration
- Process単位Application Bootstrap
- Request LoopとPublic PSR境界
- Operation Scope終了
- Journal Observer Flush
- DB Connection Health／Reconnect
- Exception後Cleanup
- `max_requests`／Restart境界
- Classic Mode Fallback
- 複数Request Consumer E2E、Memory／State Leak Guard
- Internal／Guide Source、Report、STATE

## Out of Scope

- Secretを含むCompiled Configuration Artifact
- Worker Modeの即時Default化
- PHP-FPM／RoadRunner／Swoole対応
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/058-frankenphp-runtime-premise.md`
- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/49-feature-first-quickstart-application.md`

## Files Allowed to Change

- `src/Application/**`
- `src/Internal/Application/**`
- `src/Internal/Runtime/**`
- `src/Http/**`
- `src/Journal/**`
- `src/Transport/PostgreSql/**`
- `examples/quickstart/Caddyfile`
- `examples/quickstart/Caddyfile.worker`
- `examples/quickstart/public/**`
- `examples/quickstart/bootstrap/**`
- `examples/quickstart/compose.yaml`
- `examples/quickstart/Dockerfile.frankenphp`
- `examples/quickstart/README.md`
- `tests/Application/**`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Internal/Application/**`
- `tests/Internal/Runtime/**`
- `tests/Consumer/**`
- `docs/internal/**`
- `docs/guide/**`
- `develop/orchestration/reports/P10-005F-frankenphp-worker-mode.md`
- `develop/STATE.md`

## Constraints

- 原則GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答`Y`により、本TaskでModel／Profile Metadataを確認できない現在利用可能なWorkerを使う例外を承認済み
- 最初は明示Opt-inでClassic ModeをFallbackとして維持する
- Configuration SnapshotをFileへDumpしない
- Request固有StateをStatic／Singleton／Serviceへ残さない
- Throwable後も次Requestを安全に処理する
- Secret、Request Body、Actor／Tenant StateをRequest間で共有しない

## Acceptance Criteria

- [ ] Bootstrap／Config評価がWorker Process起動時の一度だけである
- [ ] 複数RequestでOperation ID、Context、Observer Bufferが混線しない
- [ ] Success／Rejected／Throwable後の次Requestが正常に動く
- [ ] JSONL Journalが各Request終了時にFlushされる
- [ ] DB切断後に安全に失敗またはReconnectし、Stale Connectionを成功扱いしない
- [ ] `max_requests`到達時にWorkerがGraceful Restartする
- [ ] Classic Modeが明示Fallbackとして動く
- [ ] Consumer E2EがMemory GrowthとSecret／State Leakを検査する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/frankenphp-worker-mode.sh
docker compose config
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005F-frankenphp-worker-mode.md`へSummary、Bootstrap Count Evidence、Request Isolation、Flush／Reconnect／Restart Evidence、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。

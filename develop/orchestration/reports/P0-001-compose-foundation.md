# P0-001: Docker Compose Foundation - Implementation Report

Status: Accepted

## Summary

Phase 0 の開発基盤を Docker Compose で構築した。HostへPHPやComposerを導入せず、Compose経由でPHP 8.5、Composer、Mago、PHPUnit、Deptrac、PostgreSQLをすべて実行できることを検証した。`blackops/framework` Composer Packageの最小構成、`BlackOps\Core` Namespaceの起点クラス、PHPUnitの最小Test、PostgreSQL接続Smoke Testを追加し、Spec 13/14/15/16/34/40のPhase 0範囲を満たすことを確認した。

Codex Review指摘4点をRevision 2で反映した: (1) Application ContainerをHost UID/GIDで実行し生成Repository Fileの所有権をHost editable化、既存root所有Fileの所有権をContainer経由で修復、(2) `.gitignore` へ `vendor/`, `.phpunit.cache/`, `.deptrac.cache` を追記、(3) `composer validate --strict` でのgit dubious ownership警告を解消、(4) PostgreSQLのHost Port公開を削除し内部Network接続のみ維持。

## Changed Files

- `Dockerfile` (new/rev2): PHP 8.5-cli、PDO_PGSQL、zip、Composer 2.10、`vendor/bin` をPATHへ追加。build args `UID`/`GID` で `app` UserをHost UID/GID一致生成、`/app` chown、`git config --global --add safe.directory /app` 設定、`USER app` で非root実行。`COMPOSER_HOME`/`HOME` を `/home/app` 配下へ設定、`COMPOSER_ROOT_VERSION=1.0.0@dev` でroot version警告抑制。
- `.dockerignore` (new): docs/orchestration/spec等の不要コピーを除外
- `.env.example` (new/rev2): PostgreSQL接続情報の既定値、`HOST_UID`/`HOST_GID` 既定値1000
- `.env` (new/local, ignored): Host実UID/GID 1000を設定。`.gitignore` でCommit除外済み（`vendor/` `*.log` `.phpunit.cache/` `.deptrac.cache` と併記）。`git status` でuntracked一覧に出ないことを検証済み。
- `compose.yaml` (new/rev2): `app` Service（build args `UID`/`GID`、`user: ${HOST_UID}:${HOST_GID}`、Repository Rootを`/app`へBind Mount）と`postgres:18` Service（`pg_isready` healthcheck）。PostgreSQLのHost Port公開を削除し、Compose内部Network経由接続のみ。
- `composer.json` (new): `blackops/framework`、PSR-4 `BlackOps\\` / `BlackOps\\Tests\\`、dev依存にMago/PHPUnit/Deptrac
- `composer.lock` (new): 依存Versionを固定。所有権をHost UID 1000へ修復済。
- `mago.toml` (new): `src` をSource pathに指定、Lint/Format/Analyzeを有効化
- `deptrac.yaml` (new): Spec 16の依存方向をLayerとRulesetで定義
- `phpunit.xml` (new): `tests` Directoryを`unit` Suiteへ
- `src/Core/Framework.php` (new): `BlackOps\Core\Framework` 起点Class
- `tests/Core/FrameworkTest.php` (new): PHPUnit最小Test
- `tests/Database/DatabaseConnectionTest.php` (new): PostgreSQL接続Test
- `docker/db-smoke-test.php` (new): 接続結果を標準出力へ報告
- `.gitignore` (rev2/rev3): 既存行(`decisions`, `spec`, `develop/TODO.md`, `develop/DOCS.md`, `vendor`, `*.log`)を保持したまま `.phpunit.cache/`, `.deptrac.cache`, `.env` を追記
- `docs/internals/development-setup.md` (edit): Phase 0の実行可能Commandを追記

## Dependency Versions

| Package | Version | 備考 |
| --- | --- | --- |
| php | 8.5.7 | `php:8.5-cli` Official Image |
| composer | 2.10.1 | Composer公式ImageからCopy |
| carthage-software/mago | 1.42.0 | PHP 8.5対応、初回実行時にBinary Download |
| phpunit/phpunit | 13.2.2 | PHP >=8.4.1 要求 |
| deptrac/deptrac | 4.6.2 | PHP >=8.2、Symfony 6.4/7.4/8.0対応 |
| postgres | 18 | `postgres:18` Official Image（server_version 18.4） |

## Decisions and Assumptions

- PHP Runtime ImageはSpec 13/09に従い`php:8.5-cli`を採用。`8.5-cli` Tagは2026-06にDocker HubでGA化済み。
- Composerは`composer:2.10` Official ImageのBinaryをCopyし、Host/Container個別導入を避けた。
- Mago Binaryは`carthage-software/mago` Packageが初回実行時にDownloadするRust Binaryを使用。`vendor/bin/mago` をContainer PATHへ追加し、Task Packetの`mago lint` / `mago analyze` Formをそのまま実行可能にした。
- PostgreSQL 18はMVP範囲（Spec 34）のReference Transport要件を満たす最新安定版。
- Deptrac LayerはSpec 16の`Core, Journal, Execution, Transport, Http, Logging, Console, Internal` 8層を定義。Rulesetも同Specの依存方向をそのまま反映。Phase 0では`Core`層に1 Classのみ存在するが、Spec違反0を検証済み。
- `composer.lock` は `composer install` で生成されたものをそのまま使用し、Content Hash整合を保った。
- Review指摘1 (Host UID/GID): `Dockerfile` で `ARG UID/GID` を受けて `app` UserをHost一致UID/GIDで生成し、`compose.yaml` で `user:` と build args を `HOST_UID/HOST_GID` から供給。`.env.example` と `.env` でHost実UID/GIDを設定可能。これにより `vendor/`, `composer.lock`, `.phpunit.cache/`, `.deptrac.cache` はすべてHost User所有で生成される。
- Review指摘1 (所有権修復): 既存root所有の `vendor/`, `composer.lock` は `docker compose run --rm --user 0 app chown -R 1000:1000 /app` で安全に修復済。修復後 `ls -la` で `kubotak kubotak` 所有を確認。
- Review指摘2 (`.gitignore`): 既存行を保持し、末尾へ `.phpunit.cache/` と `.deptrac.cache` を追記。`vendor` 行は既存のため重複追記せず。
- Review指摘3 (dubious ownership): `Dockerfile` で `USER app` 切替後に `git config --global --add safe.directory /app` を実行し、Container内gitが `/app` をsafe扱いするよう恒久設定。`COMPOSER_ROOT_VERSION=1.0.0@dev` でroot version既定値警告も抑制。再検証で `./composer.json is valid` のみ出力され警告消失。
- Review指摘4 (PostgreSQL Port): `compose.yaml` から `ports:` Mappingを削除し、`app` ServiceからはCompose内部Network経由(`postgres:5432`)で接続。`docker compose ps` で `PORTS` 列が `5432/tcp`（Container内部のみ）となりHost公開なし。DB Smoke Test依然成功。
- PostgreSQL Credentialは`.env.example` で既定値。`.env` はHost UID/GIDが含まれCommit対象外。`

## Commands and Results (Revision 2)

| Command | Result |
| --- | --- |
| `docker compose config` | Success。build args UID/GID=1000、`user: 1000:1000`、postgres に `ports` なし |
| `docker compose build app` | Success。`blackops/framework:dev` Build、`app` User生成・`safe.directory` 設定まで完了 |
| `docker compose run --rm --user 0 --no-deps app chown -R 1000:1000 /app` | Success。既存root所有File の所有権をHost UID/GIDへ修復 |
| `docker compose up -d postgres` | Success。`postgres:18` Container起動 |
| `docker compose ps` | `blackops-postgres-1` が `Up (healthy)`、`PORTS` は `5432/tcp` のみ（Host公開なし）|
| `docker compose run --rm app php --version` | `PHP 8.5.7 (cli)`（`app` User実行）|
| `docker compose run --rm app composer --version` | `Composer version 2.10.1`（`app` User実行）|
| `docker compose run --rm app composer validate --strict` | `./composer.json is valid`。dubious ownership 警告 なし、root version 警告 なし |
| `docker compose run --rm app mago lint` | `No issues found.` |
| `docker compose run --rm app mago analyze` | `No issues found.` |
| `docker compose run --rm app vendor/bin/phpunit` | `OK (2 tests, 2 assertions)`。`.phpunit.cache/` はHost User所有 |
| `docker compose run --rm app vendor/bin/deptrac` | Violations 0 / Warnings 0 / Errors 0。`.deptrac.cache` はHost User所有 |
| `docker compose run --rm app php docker/db-smoke-test.php` | `DB_CONNECTION_OK server_version=18.4 (Debian 18.4-1.pgdg13+1)`（内部Network接続）|
| `docker compose down` | Success |
| `ls -la vendor composer.lock .phpunit.cache .deptrac.cache` | すべて `kubotak kubotak` 所有、Host編集可能 |

## Acceptance Criteria

- [x] `docker compose config` が成功する
- [x] Application ImageをBuildできる
- [x] Compose経由でPHP 8.5以上を確認できる（8.5.7）
- [x] Compose経由でComposerを実行できる（2.10.1）
- [x] PostgreSQL ServiceがHealthyになる
- [x] Application ContainerからPostgreSQLへ接続できる（`DB_CONNECTION_OK`、内部Network経由）
- [x] `composer validate --strict` が成功する（警告なし）
- [x] MagoのLintとStatic Analysisが成功する
- [x] PHPUnitの最小Testが成功する
- [x] DeptracのArchitecture検査が成功する
- [x] HostへのPHP／Composer導入なしで全Commandを再実行できる
- [x] 実行Commandが内部向けSetup文書へ記載される（`docs/internals/development-setup.md`）

## Remaining Issues

- `Mago Binary Download` 進捗が初回実行時に大量出力される。CI logで抑止したい場合は `MAGO_PROGRESS=0` 等の環境変数調整をPhase 1で検討（Out of Scope）。
- `.env` はHost UID/GIDを含むため`.gitignore` でCommit除外。`vendor/`, `.phpunit.cache/`, `.deptrac.cache` もGit追跡除外。`git status` untracked一覧に `.env` が出ないことを検証済み。
- `composer.lock` は開発環境とCIの依存再現性を保つため追跡対象とする。

## Suggested Next Action

- Codex Reviewに合格し、Review指摘とAcceptance Criteriaをすべて満たした。
- `composer.lock` は追跡対象として確定した。
- 次TaskはPhase 1のInline Vertical Slice（Operation、Value、Envelope、Handler、Result、Dispatcher、Lifecycle、`GET /welcome`）準備。D047 Frontend Integrationは引き続きDiscussingのため、HTTP Response Contractを実装する前にCodex確定を待つ。

# P0-002: Runtime Dependency Baseline - Implementation Report

Status: Accepted

## Summary

既決定のMVP技術StackをComposer依存として固定し、PHP 8.5上で競合なくInstall・品質検査できるRuntime Dependency Baselineを作成した。PSR-11 Container、Symfony DependencyInjection 7.4 LTSとConfig Component、PSR-7／PSR-15／PSR-17 Contract、Nyholm PSR-7実装、FastRoute、Symfony UID、Symfony Console、Monolog 3とPSR-3、PSR-20 Clockをすべて `composer.json` の直接依存へ追加し、`composer.lock` を更新した。各PackageのVersion ConstraintはTask実行時点のPackagist公式MetadataでPHP 8.5互換性を確認して決定した。採用理由と用途は `docs/internals/runtime-dependencies.md` へ記録した。

## Changed Files

- `composer.json` (edit): `require` へRuntime依存14件を追加。Version ConstraintはSemantic VersioningのCaret (`^`) で表現し、Lock Fileで厳密Versionを固定。
- `composer.lock` (regenerate): `composer update` で再生成。新規Lock 10件、Symfony Componentを8.1系から7.4 LTS系へDowngrade、`symfony/polyfill-php85` 削除、`phpstan/phpstan` 2.2.4 → 2.2.5（Dev、Transitive）。
- `docs/internals/runtime-dependencies.md` (new): 各DependencyのConstraint、Lock Version、PHP Requirement、採用理由と用途を記録。
- `docs/internals/README.md` (edit): `Runtime Dependencies` へのLinkをPlanned Topicsへ追加。
- `orchestration/STATE.md` (edit): Task StatusをIn Progressへ更新、Timestamp更新。
- `orchestration/reports/P0-002-runtime-dependency-baseline.md` (new): 本Report。

## Dependency Versions and Constraints

| Package | Constraint | Lock Version | PHP Requirement (公式Metadata) | Capability |
| --- | --- | --- | --- | --- |
| `psr/container` | `^2.0` | 2.0.2 | `>=7.4.0` | PSR-11 Container Contract |
| `psr/http-message` | `^2.0` | 2.0 | `^7.2 \|\| ^8.0` | PSR-7 HTTP Message |
| `psr/http-server-handler` | `^1.0` | 1.0.2 | `>=7.0` | PSR-15 Server Request Handler |
| `psr/http-server-middleware` | `^1.0` | 1.0.2 | `>=7.0` | PSR-15 Middleware |
| `psr/http-factory` | `^1.0` | 1.1.0 | `>=7.1` | PSR-17 HTTP Factory |
| `psr/log` | `^3.0` | 3.0.2 | `>=8.0.0` | PSR-3 Log |
| `psr/clock` | `^1.0` | 1.0.0 | `^7.0 \|\| ^8.0` | PSR-20 Clock |
| `nyholm/psr7` | `^1.8` | 1.8.2 | `>=7.2` | PSR-7／PSR-17 標準実装 |
| `nikic/fast-route` | `^1.3` | 1.3.0 | `>=5.4.0` | Router (FastRoute) |
| `symfony/uid` | `^7.4` | 7.4.9 | `>=8.2` | UUIDv7生成 |
| `symfony/console` | `^7.4` | 7.4.14 | `>=8.2` | CLI Component |
| `symfony/dependency-injection` | `^7.4` | 7.4.14 | `>=8.2` | DI Container 標準実装 (7.4 LTS) |
| `symfony/config` | `^7.4` | 7.4.14 | `>=8.2` | DI定義読み込み／結合 |
| `monolog/monolog` | `^3.10` | 3.10.0 | `>=8.1` | Logging Backend (Monolog 3) |

PHP 8.5.7環境で `composer install` が成功したため、Platform Package制約を含む全依存がPHP 8.5へ導入可能であることを検証済み。

## Decisions and Assumptions

- Version Constraint方針：P0-001ではDev Tool（Mago、PHPUnit、Deptrac）をExact Pinしたが、本TaskではRuntime依存にCaret (`^`) を使用した。理由はPackage Typeが `library` であり、`composer validate --strict` が `exact version constraints should be avoided` 警告を出さずにAcceptance Criteria「警告なしで成功」を満たすため。厳密Versionは `composer.lock` で固定し再現性を確保する。P0-001のDev Tool Exact PinはTeam全体でTool Versionを厳密一致させる目的のため維持する。
- Symfony ComponentはSpec 09に従い `^7.4`（7.4 LTS系列、`>=7.4.0 <8.0.0`）へ統一した。`composer update` 時にsymfony/console、symfony/dependency-injection、symfony/configが8.1.1から7.4.14へDowngradeされた。これはDeptrac 4.6.2の `symfony/console ^6.4 || ^7.4 || ^8.0` 制約を満たしつつFW本体は7.4 LTSへ固定するため意図的である。Symfony Component間のMajor Version不整合を防ぐためUIDも含め全Componentを7.4系へ揃えた。
- FastRoute：Task実行時点で安定版は1.3.0、2.0系は `2.0.0-beta1` のみ。Baselineには安定版を採用し `^1.3` で1.3系へ固定した。公式Metadata `requires.php >=5.4.0` はPHP 8.5を含むため互換性要件を満たす。Phase 1でRouter Adapterの実行時挙動を検証する際に2.0安定版がReleaseされていれば再評価する。
- PSR Contractは実装PackageのTransitive依存に任せず、`require` へ直接依存として明示した。Acceptance Criteria「必須Capabilityがすべて直接依存として表現される」を満たすため。
- `psr/http-factory` Constraintを `^1.0` とした。Nyholm PSR-7 1.8.2が `psr/http-factory ^1.0` を要求し、1.1.0が `^1.0` を満たすためLockは1.1.0へ解決された。
- `composer install` でLockを更新するため事前に `composer update` を実行した。`composer install` は既存Lockが最新の場合にのみ成功するため、新規依存追加時のLock再生成はScope内の「Composer Lockの更新」に含む。その後Required Commandの `composer install` を実行し、Lock一致状態でClean Installされることを検証済み。
- Transitive変更：`phpstan/phpstan` 2.2.4 → 2.2.5、`symfony/polyfill-php85` 削除。これらはDev ToolまたはTransitive依存の自動解決結果であり、`composer.json` の直接依存ではない。Auto-generated Lockによる副作用である。
- Phase 0時点では依存固定のみを行い、DI Container Build、Service Provider API、Production Codeは未実装（Out of Scope）。`vendor/` 配下のPackageはInstallのみで扱われていない。

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app php --version` | `PHP 8.5.7 (cli)` |
| `docker compose run --rm app composer validate --strict` | `./composer.json is valid`（警告なし）|
| `docker compose run --rm app composer update --no-interaction` | Lock file更新成功。10 installs、4 updates、1 removal。`No security vulnerability advisories found.` |
| `docker compose run --rm app composer install` | `Installing dependencies from lock file`、`Nothing to install, update or remove`、`No security vulnerability advisories found.` |
| `docker compose run --rm app composer audit` | `No security vulnerability advisories found.` |
| `docker compose run --rm app mago lint` | `INFO No issues found.` |
| `docker compose run --rm app mago analyze` | `INFO No issues found.` |
| `docker compose run --rm app vendor/bin/phpunit` | `OK (2 tests, 2 assertions)`、Runtime PHP 8.5.7 |
| `docker compose run --rm app vendor/bin/deptrac` | Violations 0 / Skipped 0 / Uncovered 0 / Allowed 0 / Warnings 0 / Errors 0 |

未実行Command：なし。Task PacketのRequired Commands 7件すべて実行し成功した。

## Acceptance Criteria

- [x] 必須Capabilityがすべて直接依存として `composer.json` に表現される
- [x] PHP 8.5との互換性を満たす（公式Metadataで確認、`composer install` 成功で検証）
- [x] `composer.lock` が更新される
- [x] `composer validate --strict` が警告なしで成功する
- [x] `composer install` が成功する
- [x] `composer audit` が成功する
- [x] Mago Lint／Analyzeが成功する
- [x] PHPUnitが成功する
- [x] Deptracが成功する
- [x] 各Dependencyの採用理由と用途が内部向け文書へ記録される（`docs/internals/runtime-dependencies.md`）

## Remaining Issues

- FastRoute 2.0安定版が未Releaseのため1.3系へ固定した。Phase 1のRouter Adapter実装時に2.0安定版の有無とPHP 8.5実行時挙動を再評価する。1.3.0は2017年頃Releaseの古いCodebaseだが、MVP範囲ではManifest CompilerがFastRoute用Dispatcher Dataを生成する用途であり、Phase 0では未使用のため実行時Deprecation検証は次Taskへ先送りする。
- Transitive依存の `symfony/polyfill-php85` が削除された。PHP 8.5 RuntimeではPolyfill不要のため削除は妥当だが、PHP 8.4環境での互換性は保証しない（FW要件はPHP 8.5以上、Spec 09）。
- `phpstan/phpstan` が2.2.4 → 2.2.5へ自動更新された。これはDev依存のTransitive解決による副作用で、本Taskの直接変更ではない。Dev Tool VersionはP0-001のExact Pin方針と整合するよう別Taskで再Pin検討可能だが本Task範囲外。

## Suggested Next Action

- Codex ReviewでDependency選定、Constraint方針、7.4 LTS統一、FastRoute 1.3固定判断を確認する。
- Review合格後、Phase 1のInline Vertical Slice（Operation、Value、Envelope、Handler、Result、Dispatcher、Lifecycle、`GET /welcome`）へ進む。DI Container Build処理とService Provider APIはPhase 1で実装する。
- FastRoute 2.0安定版Release状況をPhase 1開始時に確認し、必要ならConstraint再検証をCodexへ提起する。

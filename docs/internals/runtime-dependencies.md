# Runtime Dependencies

BlackOpsのRuntime依存をComposerで固定し、PHP 8.5上で品質検査できるBaselineを構築する。確定仕様の正本は `spec/` である。この文書は各Packageの採用理由、用途、Version Constraint、PHP 8.5互換性の確認結果を記録する。

Phase 0時点では依存を固定するのみで、DI ContainerのBuild処理、Service Provider API、Production Codeは実装しない（P0-002のOut of Scope）。

## 制約方針

- Runtime依存は `composer.json` の `require` へ直接依存として表現する。PSR Contractも実装Packageへ丸投げせず明示する。
- Package Typeは `library` であるため、Version ConstraintにはSemantic VersioningのCaret (`^`) を使用し、Lock Fileで厳密なVersionを固定する。P0-001のDev Tool（Mago、PHPUnit、Deptrac）はTeam全体で厳密一致させるためExact Pinとするが、Runtime依存はCaretで制約を表現する。
- Symfony ComponentはSpec 09に従い `^7.4`（7.4 LTS系列）へ統一する。`^7.4` は `>=7.4.0 <8.0.0` を意味し、7.4 LTS系列に固定される。
- Version ConstraintはTask実行時点でPackagist公式Metadataの `requires.php` を確認しPHP 8.5を満たすものを選定した。

## Runtime依存一覧

### PSR Container (PSR-11)

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `psr/container` | `^2.0` | 2.0.2 | `>=7.4.0` | Spec 09 Runtime and DI |

採用理由：FWのComposition Root、Handler Resolverなどの解決境界が依存するDI Container ContractをPSR-11へ固定する。Symfony DependencyInjectionが実装を提供するが、Contract自体を直接依存へ明示し、Component外の実装差替可能性を保つ。

### Symfony DependencyInjection (7.4 LTS) と Config Component

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `symfony/dependency-injection` | `^7.4` | 7.4.14 | `>=8.2` | Spec 09 Runtime and DI |
| `symfony/config` | `^7.4` | 7.4.14 | `>=8.2` | Spec 09 Runtime and DI |

採用理由：Spec 09が標準実装にSymfony DependencyInjection Component 7.4 LTSを指定している。Constructor Autowiring既定、Container Compile、本番Runtimeの生成済みPHP Container要件を満たす。Config ComponentはDI定義の読み込みと結合に必要なため同系列で追加する。Symfony Component間のMajor Version不整合を避けるため `^7.4` で7.4 LTS系列へ統一した。

### PSR-7、PSR-15、PSR-17 Contract

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `psr/http-message` | `^2.0` | 2.0 | `^7.2 \|\| ^8.0` | Spec 13 MVP Technical Stack |
| `psr/http-server-handler` | `^1.0` | 1.0.2 | `>=7.0` | Spec 13 MVP Technical Stack |
| `psr/http-server-middleware` | `^1.0` | 1.0.2 | `>=7.0` | Spec 13 MVP Technical Stack |
| `psr/http-factory` | `^1.0` | 1.1.0 | `>=7.1` | Spec 13 MVP Technical Stack |

採用理由：HTTP AdapterはPSR-7 Message、PSR-15 Server Request Handler／Middleware、PSR-17 Factoryへ依存する。`HttpMiddleware` はPSR-15 `MiddlewareInterface` を継承するMarker Interfaceとする（Spec 13）。FW内部はPSR Interfaceへ依存し、具体実装を交換可能にするため、各Contractを直接依存へ明示した。

### Nyholm PSR-7／PSR-17実装

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `nyholm/psr7` | `^1.8` | 1.8.2 | `>=7.2` | Spec 13 MVP Technical Stack |

採用理由：Spec 13が標準PSR-7／PSR-17実装にNyholm PSR-7を指定している。軽量で純PHP実装、PSR-7 2.0とPSR-17 1.0の両方を実装する。`^1.8` は `>=1.8.0 <2.0.0` で、PSR-7 2.0系列と互換の1.8系へ固定する。

### FastRoute

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `nikic/fast-route` | `^1.3` | 1.3.0 | `>=5.4.0` | Spec 13 MVP Technical Stack |

採用理由：Spec 13がRouterにFastRouteを採用する。Operation Manifest CompilerがFastRoute用のCompile済みDispatcher Dataを生成し、Runtime Manifestへ含める。Task実行時点で通用可能な最新安定版は1.3.0であり、公式Metadataの `requires.php >=5.4.0` はPHP 8.5を含み互換性を満たす。2.0系は `2.0.0-beta1` のみで安定版未提供のためBaselineから除外し、1.3系へ固定した。Phase 1で実装するRouter Adapterの実行時挙動検証時に再評価する。

### Symfony UID (UUIDv7)

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `symfony/uid` | `^7.4` | 7.4.9 | `>=8.2` | Spec 13 MVP Technical Stack |

採用理由：Spec 13がUUIDv7生成にSymfony UID Componentを指定している。`OperationId`、`AttemptId`、`JournalRecordId` 等のFW固有型でSymfony UIDを包み、公開APIへComponent型を直接露出しない（Spec 13）。他のSymfony Componentと同系列の `^7.4` で統一する。

### Symfony Console

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `symfony/console` | `^7.4` | 7.4.14 | `>=8.2` | Spec 13 MVP Technical Stack |

採用理由：Spec 13がCLI ComponentにSymfony Consoleを採用する。Operation Manifest Compilerや運用Commandの実装基盤になる。DI Configと同じ7.4 LTS系列へ統一した。

### Monolog 3 と PSR-3

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `monolog/monolog` | `^3.10` | 3.10.0 | `>=8.1` | Spec 13 MVP Technical Stack |
| `psr/log` | `^3.0` | 3.0.2 | `>=8.0.0` | Spec 13 MVP Technical Stack |

採用理由：Spec 13がMVP標準Logging BackendにMonolog 3を採用する。FWはExecution Context付与、Journal Record生成、Sensitive Filter、Schema整形を担い、File出力、Buffer、Level処理はMonologへ委ねる。Monolog 3はPSR-3 `^3.0` を実装するため、PSR-3 Contractも直接依存へ明示した。

### PSR-20 Clock

| Package | Constraint | Lock Version | PHP Requirement | Spec |
| --- | --- | --- | --- | --- |
| `psr/clock` | `^1.0` | 1.0.0 | `^7.0 \|\| ^8.0` | Spec 21 Clock and Time |

採用理由：Spec 21が時刻取得にPSR-20 `Psr\Clock\ClockInterface` を採用し、FrameworkとAdapterへDIすると定めている。Framework内部で現在時刻を直接生成せず、TestではClockを固定または制御可能な実装へ差し替えるため、Contractを直接依存へ明示した。

## PHP 8.5 互換性確認

すべてのPackageはTask実行時点のPackagist公式Metadataの `requires.php` を確認し、PHP 8.5を満たすことを検証した。ComposerはPlatform Packageの `php` 制約を満たさないPackageをInstallしないため、`composer install` が成功した時点でPHP 8.5実行環境へ全依存が導入可能であることを確認済みである。

## 品質検査

P0-002のAcceptance Criteriaに基づき次の検査を実施し、すべて成功した。結果の詳細は `orchestration/reports/P0-002-runtime-dependency-baseline.md` に記録する。

- `composer validate --strict`
- `composer install`
- `composer audit`
- `mago lint`
- `mago analyze`
- `vendor/bin/phpunit`
- `vendor/bin/deptrac`
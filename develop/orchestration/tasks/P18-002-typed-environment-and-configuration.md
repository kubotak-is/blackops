# P18-002: Typed Environment and Configuration Closure

Status: Ready

## Goal

Process／DotenvからBootstrapへ渡されたEnvironmentを一度だけ検証するPublic Readonly Snapshotとし、Configuration Fileが型付きClosureで安全に値を取得できるようにする。Configuration評価を`Application::create()`へ遅延してBuilder呼出順をなくし、Quickstart／Composer Skeleton／Community BoardのConfigurationから直接の`$_ENV`参照と手動型変換を除く。

## In Scope

- Public `BlackOps\Application\Environment`
- `string`、`optionalString`、`int`、`positiveInt`、`bool` Accessor
- Raw Valueを含まない`InvalidArgumentException`と既存`ApplicationBootstrapException`境界
- Arrayまたは`static fn (Environment $env): array`を返すConfiguration File
- `withConfiguration()`のDirectory検証と`create()`までの評価遅延
- `withEnvironment()`／`withConfiguration()`呼出順非依存
- 全Configuration Closureへの同一Snapshot一回評価
- Array Configuration互換
- Quickstart／Skeleton SourceとCommunity Boardの`config/*.php`移行
- Public API、Configuration Guide、Internal Bootstrap Documentation
- Unit、Integration、Architecture、Consumer Regression
- Report、TODO、STATE同期

## Out of Scope

- Global `env()` Helper
- Frameworkによる`.env`探索／Dotenv読込
- Environment全体のCompiled Container Service登録
- Environment値のCompiled Artifact／Manifest／Log／Journal保存
- `enum`、`float`、List、Prefix、Nested Environment Accessor
- Application Service Provider、Authenticator、Test Database Helper等、`config/*.php`外の既存`$_ENV`／`getenv()`利用
- Frontend Bound Client
- Command Discovery／Operation Console
- Session Auth Package／Generator
- D109のIdempotency／Outbox
- Documentation Website Publication／Deploy

## Relevant Specifications and Decisions

- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Files Allowed to Change

### Public and Internal Application Runtime

- New `src/Application/Environment.php`
- `src/Application/ApplicationBuilder.php`
- `src/Internal/Application/ApplicationEnvironment.php`
- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationConfigurationSnapshot.php` only if Snapshot composition requires a mechanical type adjustment
- `src/Application/ApplicationBootstrapException.php` only if safe exception chaining needs a non-semantic adjustment

Do not add Environment to Compiled Container definitions, Build Artifact, Manifest, or public `Application` methods beyond the Configuration Closure contract.

### Tests

- New `tests/Application/EnvironmentTest.php`
- `tests/Application/ApplicationTest.php` only for Public API inventory
- `tests/Internal/Application/ApplicationBuilderTest.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/Application/ApplicationRegistrationTest.php` only for Array／Closure registration equivalence
- `tests/Integration/ApplicationHttpRuntimeTest.php` only for one-time Snapshot／Worker-safe composition evidence
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- New or existing focused fixtures under `tests/Internal/Application/Fixture/**`

Do not mechanically rewrite PostgreSQL integration tests that use `getenv()` as Test Harness input.

### Installed Consumers

- `examples/quickstart/config/*.php`
- `examples/community-board/config/*.php`
- Quickstart／Community Board tests directly asserting Configuration shape
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/framework-update-generator.sh` only if the new canonical Configuration shape changes its expectation
- `tests/Consumer/skeleton-create-project.sh` only if the split Skeleton expectation changes
- Community Board Foundation／Clean Install Consumer only for canonical Configuration assertions

`examples/quickstart/bootstrap/app.php`と`examples/community-board/bootstrap/app.php`のDotenv／Process Environment Snapshotは維持する。`config/*.php`外のApplication Sourceを変更しない。

### Documentation and Orchestration

- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/internal/application-bootstrap.md` if present, otherwise the existing closest bootstrap document
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/74-application-ergonomics.md` only for non-semantic clarification discovered during implementation
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P18-002-typed-environment-and-configuration.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへBlockerとして記録する。

## Public Contract

`Environment`は`#[PublicApi]`を持つ`final readonly class`とし、次のSignatureを実装する。

```php
namespace BlackOps\Application;

#[PublicApi]
final readonly class Environment
{
    /** @param array<array-key, mixed> $variables */
    public function __construct(array $variables);

    public function string(string $name, ?string $default = null): string;

    public function optionalString(string $name): ?string;

    public function int(string $name, ?int $default = null): int;

    public function positiveInt(string $name, ?int $default = null): int;

    public function bool(string $name, ?bool $default = null): bool;
}
```

- ConstructorはString Key／String Value以外を拒否する
- Variable名は空文字列を拒否する
- Snapshotは入力Arrayの後続Mutationから影響を受けない
- `string()`は未定義かつDefaultなしで失敗し、空文字列は有効値とする
- `optionalString()`は未定義だけを`null`にし、空文字列は保持する
- `int()`はCanonicalな10進整数だけを受理する
- `positiveInt()`はCanonicalな10進整数かつ1以上だけを受理する
- `bool()`はCase-insensitive `true`／`false`と`1`／`0`だけを受理する
- Defaultは未定義時だけ使い、不正値へFallbackしない
- ExceptionはVariable名と期待型を含めてよいが、Raw Valueを含めない
- Array Dump／Iterator／Getter、Mutation、Global Singletonを追加しない

Canonical整数は`0`または`-?[1-9][0-9]*`とし、`+1`、Whitespace、Decimal、Exponent、Leading Zeroを拒否する。`positiveInt()`は`[1-9][0-9]*`だけを受理する。PHP `int`範囲外も拒否する。

## Configuration Evaluation Contract

- Configuration Fileは`array`またはClosureを返す
- ClosureはPublic `Environment`を一つ受け取り`array`を返す
- Closure以外のCallable、引数数／型不正、戻り値不正をSafe Bootstrap Failureにする
- `withConfiguration($missingDirectory)`の既存Immediate Directory Validationを維持してよい
- Directory登録後のFile Require／Closure実行は`create()`まで遅延する
- `withConfiguration()->withEnvironment($variables)`と`withEnvironment($variables)->withConfiguration()`が同じ結果になる
- `create()`時点の最終Environmentを全Fileへ同一Instanceで一回だけ渡す
- 同じBuilderの`create()`を複数回許す既存Contractがある場合、各`create()`につき一つのSnapshot／一回評価とし、Instance間でStateを共有しない
- ConfigurationのSafe ValidationとRegistration Merge順は既存挙動を維持する
- ClosureまたはAccessor Throwableを`ApplicationBootstrapException`へ包み、HTTP ResponseへDetailを出さない
- EnvironmentをConfiguration Snapshot Array、Compiled Container、Manifestへ追加しない

## Installed Configuration Migration

QuickstartとCommunity Boardの`config/app.php`、`database.php`、`execution.php`、`retention.php`をEnvironment Closureへ移す。Environmentを使わないConfiguration FileはArrayのままでよい。

- Worker Booleanは`Environment::bool()`を使い、従来の曖昧なUnknown Value Defaultを廃止して起動失敗にする
- Port／Lease／Heartbeat／Grace／Retention Dayは該当する型付きAccessorを使う
- Passwordは`string()`で読み、Raw Valueを例外／Test名／Snapshot Artifactへ出さない
- Defaultsは現在のQuickstart／Community Board値を維持する
- `rg` Guardで両Applicationの`config/*.php`に`$_ENV`、`$_SERVER`、`getenv()`がないことを固定する
- QuickstartがSkeleton Publication Sourceである既存Boundaryを維持し、別Skeleton Working Treeを編集しない

## Acceptance Criteria

- [ ] Public `Environment`のSignature、Readonly、Public API Markerが固定される
- [ ] Constructor、String、Optional、Integer、Positive Integer、Boolean、Default、Invalid MatrixがTestされる
- [ ] Raw Invalid ValueがException／Diagnosticsへ出ない
- [ ] ArrayとClosure Configurationが同じSnapshot Shapeを作る
- [ ] Builder呼出順を入れ替えても最終Environmentを一回だけ評価する
- [ ] Missing Directory、Invalid Return、Invalid Closure、Closure ThrowableがSafe Failureになる
- [ ] EnvironmentがCompiled Artifact／Manifestへ保存されない
- [ ] Quickstart／Community Boardの`config/*.php`から直接Environment参照がなくなる
- [ ] Quickstart Setup／E2E、Skeleton Create-project／Framework Updateが回帰しない
- [ ] Community Board Foundation／Clean Installが回帰する。起動中Runtimeを止める必要がある場合は、再起動と復元をReportへ記録する
- [ ] Mago Format／Lint／Analyze、PHPUnit、Deptrac、Management ID Guard、Diff Checkが成功する
- [ ] Documentation Website／Community Boardを外部公開しない
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples/quickstart examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
docker compose run --rm app composer validate --strict --working-dir=examples/community-board
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n '\$_ENV|\$_SERVER|getenv\(' examples/quickstart/config examples/community-board/config --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Consumer Script名がRepository内で異なる場合は、同じ責務の既存Scriptを使い、Reportへ実行名を記録する。Full Commandが環境理由で実行できない場合は未実行理由を記載し、代替で成功扱いにしない。

## Expected Report

`develop/orchestration/reports/P18-002-typed-environment-and-configuration.md` に次を記録する。

- Summary
- Changed Files
- Public Contract and Failure Matrix
- Configuration Evaluation Order Evidence
- Quickstart／Community Board Migration
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

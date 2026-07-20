# P17-004 Structured Outcome Contract Report

## Summary

`Outcome`のOutput ShapeへNative Scalarに加えて、具象`final readonly OutcomeData`、Nullable DTO、`#[ListOf]`で宣言する`list<DTO>`を追加した。Shape Compile、HTTP Projection、PostgreSQL Outcome Store、Canonical Journal、Frontend Manifest／Generated TypeScriptは同じ再帰Contractを使用する。

Frontend ManifestはSchema 3、Generated Tree MarkerはSchema 5、PostgreSQL Outcome CodecはSchema 2へ更新した。旧Frontend Manifest、旧Generated Tree、PostgreSQL Outcome Schema 1と未知VersionはFallbackせず拒否する。

## Changed Files

- Public Contract: `src/Core/OutcomeData.php`、`src/Core/Attribute/ListOf.php`
- Shared Outcome Layer: `src/Outcome/Internal/StructuredOutcomeCompiler.php`、`StructuredOutcomeShape.php`、`StructuredOutcomeField.php`、`StructuredOutcomeNormalizer.php`、`StructuredOutcomeValueCodec.php`
- Frontend: `src/Internal/Frontend/**`のOutcome Contract／Manifest／Hasher入力／Generator Marker／TypeScript Generator
- HTTP: `JsonOperationResponder`と`OperationStatusJsonResponder`の共通Normalizer利用
- PostgreSQL: `PostgreSqlOutcomeCodec` Schema 2と`PostgreSqlJournalValueCodec`のStructured Value利用
- Projection: `SensitiveProjectionFilter`のList Key／順序保持
- Architecture: `deptrac.yaml`のHttp LayerからOutcome Layerへの依存許可
- Public Docs: `docs/guide/core-api.md`、`docs/guide/attributes.md`
- Tests／Fixtures: Public API、Outcome Layer、Frontend、HTTP、Projection、PostgreSQL／Journal、Permanent TypeScript Runtime Fixture
- Orchestration: `develop/TODO.md`、`develop/STATE.md`、本Report

## Decisions and Assumptions

- Structured Outcome共通実装はPublic APIではなく`BlackOps\Outcome\Internal`が所有する。TransportとInternal Frontendは既存のOutcome Layer依存を使い、Http LayerだけOutcome依存を追加した。Http／TransportからInternal Layerへの広い依存やDeptrac Skipは追加していない。
- Orchestrator承認により、Task Packet初版外の`deptrac.yaml`と`tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`を機械的に変更した。Freshness TestはSchema固定値を廃止し、`FrontendContractManifestFile::SCHEMA_VERSION`からCurrent／Legacyを導出する。
- Root OutcomeもStatic／Non-public Propertyを拒否し、Constructor Parameter名がPublic Fieldと一致することを要求する。Nested DTOはさらに全Field／ParameterがPublic Constructor-promotedであることを要求する。
- 永続化された`float`はJSONの`1.0`から`1`への縮退を避けるため、Schema 2内部Valueで明示的なFloat Tagを保持する。HTTP／Frontend JSON Shapeは通常のJSON Numberのままである。
- Empty Listは`#[ListOf]`からElement Classを確定する。Map、Sparse List、Wrong Element ClassはRuntimeで拒否する。
- Structured OutcomeはOutput-onlyであり、OperationValue BinderのArray／Nested Input Contractは変更しない。

## Public API Shape

- `BlackOps\Core\OutcomeData`: Methodを持たない`#[PublicApi]` Marker Interface
- `BlackOps\Core\Attribute\ListOf`: Property Targetの`#[PublicApi] final readonly` Attribute。`class-string<OutcomeData>`を保持し、検証はCompile／Runtime境界で行う
- `OutcomeData`はRoot Return Typeではない。Operation Return Typeと`#[Returns]`は引き続き`Outcome`を要求する

Public API Countは147から149、利用者向けPublic Attribute Countは21から22へ更新し、Source Countと一致する。

## Recursive Contract and Failure Matrix

Supported:

- `string`／`int`／`float`／`bool`とNullable
- 具象`final readonly OutcomeData`とNullable DTO
- 非Nullable `array`＋単一`#[ListOf(ConcreteOutcomeData::class)]`
- 同じDTO Classを複数Fieldから参照する非循環Graph

Fail-fast:

- Untyped／Union／Unsupported Native／任意Object
- ElementなしArray、Nullable List、Unknown／Non-final／Non-readonly Element DTO
- Non-promoted／Non-public／Static Field、余分なDTO Constructor Parameter
- Root／DTO Class Cycle
- Uninitialized Runtime Property、Map／Sparse List、Wrong DTO／Element／Scalar
- Stored Object／PropertyのUnknown／Missing Key、Unknown Class、Wrong Class
- HTTP公開OutcomeのRoot／Nested／List Elementに到達可能な`#[Sensitive]`

ErrorにはClass／Property Pathだけを使用し、Absolute Source PathやRaw Runtime Valueを含めない。

## Frontend Manifest and Generated Decoder Evidence

- Manifest Schema 3はOutcome Typeを`scalar`／`dto`／`list`のDiscriminated再帰SchemaとしてEncode／Decodeする
- Recursive NodeはExact Key、Kind、Nullable、Scalar Kind、DTO FQCN、Nested Fieldsを検証する
- DTO Short NameをOperation-local Readonly Typeへ出力し、Listは`ReadonlyArray<T>`、Nullable DTOは`T | null`とする
- 別FQCNのCase-insensitive Short Name衝突をCompile／Generate境界で拒否する
- Generated Decoderは全Object Exact Key、Safe Integer、Finite Float、DTO再帰Shape、全List Elementを検証する。Fieldは`Object.defineProperty()`でown data propertyとして構築し、`__proto__`もPrototype Setterを起動せず保持する
- Decode成功時にRoot、Nested DTO、List、List Elementを再帰的に`Object.freeze()`する
- Inline `.fetch()`、Deferred `.status()`／`.wait()`は同一`OperationOutcomeField`再帰Schemaを使う
- Permanent FixtureはEmpty／複数Element、Nullable DTO、Wrong Element、Unknown／Missing Nested Field、Wrong Scalar、Wrong List Shape、Sparse List、Recursive FreezeをNode Runtimeで検証する

## HTTP Inline and Status Shape Evidence

両Responderは`StructuredOutcomeNormalizer`を共有する。同一Structured OutcomeをInline 200とCompleted Statusへ渡し、Nested DTO／Nullable DTO／Listを含む`outcome`が完全一致するTestを追加した。Fieldを持たないNested／Nullable／List Element DTOは空PHP Listではなく`stdClass`へ投影し、Inline／StatusともJSON Object `{}`を返す。Invalid ListとJSON Encode失敗は既存Error BoundaryでSafe 500へ閉じ、Raw Valueを返さない。

`EmptyOutcome`はInline 204、Status `{}`を維持する。Fieldを持たない通常OutcomeはInline／StatusともJSON Object `{}`になる。

## Orchestrator Review Fixes

- DTO Short NameのCase-insensitive衝突検査を、Operationの`exportName`本体、`Value`、`UrlParameters`、`Outcome`、`Field`、`Result`、`StatusResult`、`WaitResult`に加え、選択される`InlineOutcomeOperationResult`／`DeferredOperationResult`とOperation ModuleがImportする全固定Type Identifierへ拡張した。大小文字差を含む衝突を生成前に拒否する。
- Crafted Manifestが同一DTO FQCNを複数箇所で参照する場合、Recursive Field Metadataを厳密比較する。同一Schemaの再利用は一つのType Declarationへ集約し、Field、Kind、Nullable、Scalar、Nested Schemaのいずれかが異なる場合は`InvalidArgumentException`で拒否する。
- `FrontendContractManifestCodec::encode()`へ未知Outcome Kindの明示Guardを追加した。Generatorは既存のRecursive Metadata検査で生成前に拒否する。両境界が`UnhandledMatchError`ではなくSafe `InvalidArgumentException`を返す恒久Testを追加した。
- Generated `decodeOutcome()`はDecoded FieldをDirect Assignmentせずown data propertyとして定義する。Permanent Node Runtime Fixtureで`__proto__` Fieldのown property保持、`Object.prototype`不変、Prototype Pollutionなし、Recursive Freeze維持を検証した。
- zero-field `OutcomeData`はNested／Nullable／List Elementの全経路でJSON Object `{}`へ正規化する。Root zero-field Outcomeの既存`{}`も維持し、Normalizer、Inline／Status、Generated Inline／Wait Runtimeの恒久Testを追加した。

## PostgreSQL Outcome and Canonical Journal Evidence

- `PostgreSqlOutcomeCodec::SCHEMA_VERSION`は2
- Root／Nested Class、Exact Property、DTO、List、Nullable、Scalarを再帰保存／復元する
- Schema 1／未知Versionは同じUnsupported Schema Errorで拒否し、旧Decoder／Migrationを追加していない
- Nested DTO＋2 Element List＋Nullable DTO＋`float 1.0`を実際のPostgreSQL Outcome StoreでRound-tripした
- Corrupt JSON、Type Mismatch、Unknown／Extra Field、Unknown Class、Non-OutcomeをSafeに拒否する
- Canonical `operation.completed` Journal Recordで同じStructured Outcomeと`float 1.0`をRound-tripした。Journal Envelope Versionは変更していない

## Sensitive Projection Evidence

- PHP ListはNumeric Keyと順序を保持する
- Nested Object／Listを再帰Projectionする
- Nested `#[Sensitive]`のOmit／Mask／Hashを全階層で適用する
- Associative ArrayのReserved Key除外を維持し、List Element自体をReserved Keyとして誤除外しない

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: valid

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: valid

docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: all successful, no issues

Focused PHPUnit (Orchestrator independent verification)
Result: OK (146 tests, 752 assertions)

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1471 tests, 5810 assertions)

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0, Skipped 0, Uncovered 0, Allowed 2554, Warnings 0, Errors 0

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
Result: already up to date

build:compile -> frontend:generate -> frontend:check -> pnpm test -> clean
Result: Build written, 5 files generated, tree fresh, TypeScript typecheck/runtime/status/wait/module-shape all successful, artifacts cleaned

Orchestrator Review Focused Frontend PHPUnit
Result: OK (47 tests, 431 assertions)

Review Fix Frontend build:compile -> frontend:generate -> frontend:check -> pnpm test -> clean
Result: all successful

Review Fix Mago format --check／lint／analyze、Deptrac
Result: no issues, Deptrac Violations 0

Additional Review Generator PHPUnit
Result: OK (19 tests, 292 assertions)

Additional Review Internal Frontend PHPUnit
Result: OK (48 tests, 440 assertions)

Additional Review Normalizer／HTTP／Generator Focused PHPUnit
Result: OK (52 tests, 437 assertions)

Additional Review Frontend build:compile -> frontend:generate -> frontend:check -> pnpm test -> clean
Result: all successful; __proto__ own-property safety, prototype integrity, zero-field DTO decode, recursive freeze verified
```

## Acceptance Criteria

- [x] `OutcomeData`と`ListOf`がPublic APIとして実装された
- [x] Supported Structured ShapeをBuild／Runtimeで同じ規則により検証する
- [x] Unsupported Shape、Cycle、Sensitive OutcomeをSafeにFail-fastする
- [x] Frontend Manifest Schema 3とMarker 5が旧Artifactを拒否する
- [x] Generated TypeScriptがReadonly DTO／ReadonlyArrayとStrict Recursive Decoderを持つ
- [x] Inline HTTPとDeferred Statusが同じStructured JSONを返す
- [x] PostgreSQL Outcome Codec Version 2がStructured OutcomeをRound-tripする
- [x] Version 1／未知Versionを拒否し互換Layerを追加していない
- [x] Canonical JournalがStructured Completed OutcomeをRound-tripする
- [x] Sensitive ProjectionがNested DTO／List／Numeric Keyを安全に扱う
- [x] Existing Scalar／Nullable／Empty OutcomeとGenerated APIが回帰しない
- [x] OperationValue Input Contractを変更していない
- [x] Community Board／Quickstart／Skeleton／Website Sourceを変更していない
- [x] Public GuideとPublic API件数がSourceと一致する
- [x] Required Quality Gateが成功した
- [x] WorkerはCommitしていない

## Remaining Issues

なし。PostgreSQL Outcome Schema 1の既存Local Rowは仕様どおりDecodeできないため、開発Databaseは再作成が必要である。

## Suggested Next Action

P17-005 Post／Comment Task Packetを確定し、Application Domain実装へ進む。

# P17-004: Structured Outcome Contract

Status: Accepted

## Goal

Outcome OutputへReadonly Nested DTOとTyped `list<DTO>`を追加し、PHP Public Contract、Build Artifact、Generated TypeScript、Inline HTTP、Deferred Status、PostgreSQL Outcome Store、Canonical Journal、Sensitive Projectionで同じShapeを保証する。Community BoardのPost／CommentがJSON StringやFrontend Opt-outなしにPaginated SummaryとComment Listを返せるFramework Capabilityを先に完成させる。

## In Scope

- Public `BlackOps\Core\OutcomeData` Marker Interface
- Public `BlackOps\Core\Attribute\ListOf` Property Attribute
- Outcome Root／Nested DTOの再帰Contract Compiler／Validator／Normalizer
- Scalar、Nullable Scalar、DTO、Nullable DTO、Typed `list<DTO>`
- Cycle、Unsupported Type、Invalid DTO、Invalid List、Sensitive FieldのFail-fast
- Frontend Contract Manifest Schema Version 3と再帰Schema Codec
- Frontend Generation Marker Schema更新とDrift判定
- Operation-local Readonly DTO Type／ReadonlyArray生成
- Inline／StatusのStrict Recursive DecoderとRecursive Freeze
- Inline HTTP／Deferred Statusの共通Structured JSON Projection
- PostgreSQL Outcome Codec Version 2のStructured Round-trip
- Canonical `operation.completed` JournalのStructured Outcome Round-trip
- Sensitive ProjectionのNested DTO／List／Numeric Key対応
- Existing Scalar／Nullable／Empty Outcomeの回帰Test
- Public API／Attributes Guideの更新
- Report、TODO、STATE同期

## Out of Scope

- OperationValueのNested Object／Array HTTP Binding
- Scalar List、Map、Enum、DateTime、Union、Collection、Custom Serializer
- Community BoardのPost／Comment Migration、Repository、Operation、Page
- Quickstart／Skeleton Consumer Contract変更
- Frontend Framework Adapter、OpenAPI、Remote Schema Registry
- Documentation Website Publication／Deploy
- Version 1 PostgreSQL Outcome RowのDecode、Migration Tool、Fallback
- Journal Record Envelope全体のSchema変更

## Relevant Specifications and Decisions

- `develop/decisions/104-structured-outcome-contract.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/spec/72-phase-17-delivery-plan.md`

## Files Allowed to Change

### Public Contract and Runtime

- New `src/Core/OutcomeData.php`
- New `src/Core/Attribute/ListOf.php`
- `src/Internal/Frontend/**`
- `src/Internal/Projection/**`
- New or existing `src/Outcome/Internal/**`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Http/Status/OperationStatusJsonResponder.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeCodec.php`
- `src/Transport/PostgreSql/PostgreSqlJournalValueCodec.php`
- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php` only if composition changes require it
- `deptrac.yaml`（Http LayerからOutcome Layerへの依存許可だけ）

Shared Structured Outcome componentはOutcome Layer所有の`BlackOps\Outcome\Internal`へ置き、Http／Transport／Internal Frontendから共通利用する。DeptracのSkipやHttpからInternal Layerへの広い依存を追加しない。Internal ContractをPublic signaturesへ露出しない。

### Tests and Fixtures

- New or existing `tests/Core/**` for `OutcomeData`／`ListOf` Public Contract
- `tests/Internal/Frontend/**`
- `tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`（Schema Version同期の機械的修正だけ）
- `tests/Internal/Projection/**`
- New or existing `tests/Outcome/Internal/**`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/Status/OperationStatusRequestHandlerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php` only for Structured Completed Outcome evidence
- `tests/Fixtures/Frontend/**`
- `tests/Frontend/fixture/**`
- `tests/Frontend/scripts/**`

### Documentation and Orchestration

- `docs/guide/core-api.md`
- `docs/guide/attributes.md`
- `docs/guide/outcome-retrieval.md` only if Structured Status Outcome usage needs clarification
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-004-structured-outcome-contract.md`
- `develop/spec/73-structured-outcome-contract.md` only when implementation reveals a non-semantic clarification

Do not change `examples/community-board/**`, `examples/quickstart/**`, Skeleton Source, Website Source, release metadata, or other Production Code. If another File is required, stop and report the blocker instead of expanding scope.

## Public PHP Contract

Implement exactly these public names:

```php
namespace BlackOps\Core;

#[PublicApi]
interface OutcomeData {}
```

```php
namespace BlackOps\Core\Attribute;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /** @param class-string<OutcomeData> $type */
    public function __construct(public string $type) {}
}
```

- `ListOf`のConstructorはStringを保持し、Class existence／Interface／Final／Readonly検証はCompile／Normalization Boundaryで行う
- Nested DTOは具象`final readonly class`かつ`OutcomeData`実装を必須にする
- DTO FieldはPublic Constructor-promoted Instance Propertyだけを許可する
- Root Return Typeは引き続き`Outcome`であり、`OutcomeData`をOperation Return Typeとして許可しない
- Existing `Outcome`／`EmptyOutcome`／Typed Self-handled Signature Contractを変更しない

## Recursive Shape Contract

Supported FieldはNative `string`／`int`／`float`／`bool`、それらのNullable、具象`OutcomeData`、Nullable `OutcomeData`、`#[ListOf(T::class)] public array`だけとする。

- List Propertyは非Nullable Native `array`でAttributeを一つだけ持つ
- Elementは具象`final readonly OutcomeData`
- Runtime Valueは`array_is_list()`を満たし、全Elementが宣言Classと完全一致する
- DTO Field Objectも宣言Classと完全一致する
- Map、Sparse List、Subclass、Proxy、Wrong Element、Uninitialized Fieldを拒否する
- Root／DTO Class GraphのCycleをBuild時に拒否する
- Class／Property Pathは診断へ含めてよいが、Absolute File PathとRuntime Valueを含めない
- Field順とSchema順は決定的にする

Public Root Outcomeの既存Scalar Contractは維持する。Structured Fieldを持つRootについては、Runtime Normalization／Persistence時に同じShape検証を必ず通す。

## Frontend Manifest and Generation Contract

- `FrontendContractManifestCodec::SCHEMA_VERSION`を3へ上げる
- Version 2／未知VersionをUnsupportedとして拒否し、Backward Decoderを追加しない
- `FrontendGenerationMarker::SCHEMA_VERSION`を一つ上げ、既存の明示的な旧Marker Cleanup判断は現在の安全契約を維持する
- Outcome Field ContractはScalar／DTO／ListをDiscriminatedな再帰Schemaとして表し、Stringly-typed JSONや`mixed`へしない
- FQCN、Nullable、Scalar Kind、Nested Fields、List Element DTOをArtifactへ決定的に保存する
- Sensitive AttributeはRoot／Nested／List Elementを再帰検査し、HTTP OperationのBuildを拒否する
- DTO Typeは各Operation Module内へPHP Short Class Nameで出力する
- 同一Operationが参照する別FQCNのShort NameがCase-insensitiveに衝突する場合はBuild Errorにする
- DTO TypeはReadonly Property、Listは`ReadonlyArray<T>`とする
- Runtime Decoderは全ObjectのExact Keysと全List Elementを再帰検証する
- Decode後のRoot／Nested Object／Listを再帰的に`Object.freeze()`する
- Inline `.fetch()`とDeferred `.status()`／`.wait()`が同じDecoder Schemaを使う
- Existing Generated API Shape、Operation Object Method、Value Bindingを変更しない

Permanent Frontend Fixtureは少なくともNested DTO、Nullable DTO、Empty List、複数Element、Wrong Element、Unknown／Missing Nested Field、Wrong Scalar、Wrong List Shape、Recursive Freezeを実行時に検証する。

## HTTP Contract

InlineとStatusのOutcome Projectionを同じInternal Normalizerへ集約するか、同一Test Matrixで同一Shapeを証明する。Duplicated Reflection Ruleを別々に進化させない。

- Root `Outcome`からScalar／DTO／Listを再帰的にJSON-safeなArrayへ投影する
- `EmptyOutcome`のInline 204とStatus空Object Contractを維持する
- DTO／List Contract違反は成功JSONを返さず既存Internal Error境界へ到達する
- Raw Runtime Value、Exception Detail、Source PathをHTTP Responseへ反射しない
- Inline 200とCompleted Status `outcome`のNested JSONが同じShapeになるTestを持つ

## PostgreSQL and Journal Contract

- `PostgreSqlOutcomeCodec::SCHEMA_VERSION`を2へ上げる
- Encode／DecodeはVersion 2だけを受理する
- Version 1と未知Versionは同じSafe Unsupported Schema Errorで拒否する
- Version 1 Payload Decoder、Data Migration、In-place Version 1拡張を実装しない
- Root OutcomeとNested OutcomeDataをConstructor Property Contractから再帰Encode／Decodeする
- Stored FieldはExact Keys、Declared Scalar／Nullable／DTO／Listを検証する
- Arbitrary Object Classを復元せず、Nestedは`OutcomeData`だけに限定する
- Canonical `operation.completed` Journal DataもStructured OutcomeをRound-tripする
- Journal Record Envelope VersionをこのTaskで変更しない
- Existing Scalar OutcomeのVersion 2 Round-tripとCorrupt／Unknown Class／Wrong Shapeを検証する

## Sensitive Projection Contract

- `projectArray()`はListならNumeric Keyと順序を保ったListを返す
- Associative ArrayはString Keyだけを扱う既存Reserved Key防御を維持する
- Nested DTO／Listを再帰投影する
- Nested Object PropertyのOmit／Mask／Hashを適用する
- List ElementをReserved Keyとして誤って除外しない
- Raw Sensitive MarkerがObserved Projectionへ残らないTestを追加する

## Documentation Contract

- `docs/guide/core-api.md`のPublic API件数と一覧へ`OutcomeData`／`ListOf`を追加する
- `docs/guide/attributes.md`のAttribute件数と一覧へ`ListOf`を追加する
- JSON Stringを使わない完全なPHP例と、Supported／Unsupported Shapeを簡潔に示す
- Structured OutcomeがOutput-onlyでありArray Inputを有効にしないことを明記する
- Documentation Website Source／公開状態は変更しない

## Testing Contract

最低限、次をPermanent Testにする。

1. Public API marker、Attribute target、readonly shape
2. Scalar／Nullable Scalar／DTO／Nullable DTO／ListのCompile
3. Cycle、Non-final／Non-readonly DTO、Non-promoted／Non-public／Static／Uninitialized、Unknown Element、Array without `ListOf`、Wrong Native TypeのBuild Failure
4. Root／Nested Sensitive FieldのBuild Failure
5. Manifest Schema 3 Encode／Decode／Unknown Version／Corrupt Recursive Schema
6. Deterministic Hash／Generation／Fresh Check
7. Readonly DTO／ReadonlyArray TypeとStrict Recursive Runtime Decoder
8. Inline／Status Structured JSON一致とRuntime Shape Failure
9. PostgreSQL Outcome Version 2のNested／List Round-trip
10. PostgreSQL Outcome Version 1／Unknown／Corrupt／Wrong Class拒否
11. Canonical Completed JournalのNested／List Round-trip
12. Sensitive ProjectionのList Key保持とNested Omit／Mask／Hash
13. Existing Scalar／Empty Outcome、Frontend Scalar Fixture、Status／Wait回帰
14. OperationValue BinderがArray／Nested Inputを引き続き拒否する回帰

## Acceptance Criteria

- [x] `OutcomeData`と`ListOf`がPublic APIとして実装される
- [x] Supported Structured ShapeをBuild／Runtimeで同じ規則により検証する
- [x] Unsupported ShapeとCycleをSafeにFail-fastする
- [x] Frontend Manifest Schema 3とMarker更新が旧Artifactを拒否する
- [x] Generated TypeScriptがReadonly DTO／ReadonlyArrayとStrict Recursive Decoderを持つ
- [x] Inline HTTPとDeferred Statusが同じStructured JSONを返す
- [x] PostgreSQL Outcome Codec Version 2がStructured OutcomeをRound-tripする
- [x] Version 1／未知Versionを拒否し互換Layerを追加しない
- [x] Canonical JournalがStructured Completed OutcomeをRound-tripする
- [x] Sensitive ProjectionがNested DTO／Listを安全に扱う
- [x] Existing Scalar／Empty OutcomeとGenerated Operation APIが回帰しない
- [x] OperationValue Input Contractを変更しない
- [x] Community Board／Quickstart／Skeleton／Website Sourceを変更しない
- [x] Public GuideとPublic API件数がSourceと一致する
- [x] Required Quality Gateが成功する
- [x] WorkerはCommitしない

## Required Commands

Test Class追加に応じてPathは補足してよいが、Focused GateとFull Gateを省略しない。

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Frontend \
  tests/Internal/Projection \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/Status/OperationStatusRequestHandlerTest.php \
  tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
mise exec -- pnpm --dir tests/Frontend run clean
git diff --exit-code -- examples/community-board examples/quickstart
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
git diff --check
```

Generated／Dependency／Build Artifact must be cleaned before handoff. `tests/Frontend/node_modules` may remain ignored, but generated fixture and `.build` output must be clean.

## Expected Report

Create `develop/orchestration/reports/P17-004-structured-outcome-contract.md` with at least:

- Summary
- Changed Files
- Decisions and Assumptions
- Public API Shape
- Recursive Contract and Failure Matrix
- Frontend Manifest／Generated Decoder Evidence
- HTTP Inline／Status Shape Evidence
- PostgreSQL Outcome／Canonical Journal Evidence
- Sensitive Projection Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

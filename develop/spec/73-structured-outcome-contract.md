# Structured Outcome Contract

## Goal

OutcomeのNative Scalarだけでなく、ReadonlyなNested DTOとTyped `list<DTO>`を、PHP、Inline HTTP、Deferred Status、PostgreSQL Outcome Store、Canonical Journal、Frontend Contractで同じShapeとして扱う。

Structured OutcomeはOutput専用である。OperationValueのHTTP Binding、Validation、Input Contractは変更しない。

## Public PHP Model

Frameworkは次のPublic APIを提供する。

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

利用例は次とする。

```php
use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;

final readonly class ListPostsOutcome implements Outcome
{
    /** @param list<PostSummary> $posts */
    public function __construct(
        #[ListOf(PostSummary::class)]
        public array $posts,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}

final readonly class PostSummary implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $authorDisplayName,
        public string $title,
        public string $createdAt,
    ) {}
}
```

`OutcomeData`はOperation成功のRoot Resultではない。Nested Response Shapeだけを表し、OperationのReturn Typeと`#[Returns]`は引き続き`Outcome`を要求する。

## Supported Shape

Outcome Rootと再帰的な`OutcomeData` Fieldは次だけを許可する。

| PHP Field | Contract | TypeScript |
| --- | --- | --- |
| `string` | Scalar string | `string` |
| `int` | Scalar integer | `number` |
| `float` | Scalar float | `number` |
| `bool` | Scalar boolean | `boolean` |
| `?T` | Nullable scalar／DTO | `T \| null` |
| `OutcomeData`具象Class | Nested DTO | Readonly object type |
| `#[ListOf(T::class)] public array` | `list<T>` | `ReadonlyArray<T>` |

Nested DTOは`final readonly class`として`OutcomeData`を実装し、全FieldをPublic Constructor-promoted Propertyで宣言する。Constructor Parameter名とProperty名は一致しなければならない。

`#[ListOf]`はNative Typeが非Nullable `array`のPublic Instance Propertyだけへ付与でき、Element Typeは具象`final readonly OutcomeData`でなければならない。実値は0から連続するPHP Listであり、Map、Sparse Array、Wrong Element TypeをRuntimeで拒否する。空ListでもAttributeからElement Typeを確定する。

次はUnsupportedとし、BuildまたはEncodeでFail-fastする。

- Untyped／`mixed`／Intersection／Scalar以外のUnion
- `OutcomeData`以外の任意Object、Interface／Abstract Element Type
- Element TypeのないArray、Scalar List、Map
- Enum、DateTime、Resource、Callable、Iterable、Collection
- Static／Non-public／Uninitialized Property
- Root OutcomeまたはNested DTOを再び参照するCycle
- `#[ListOf]`の欠落、重複、不正Target、Native Type不一致

同じDTO Instanceを複数Fieldから参照する非循環Graphは許可する。Cycle検出はClass Pathを含む安全なBuild Errorを返すが、Absolute Source PathやRuntime Valueを含めない。

## Frontend Contract and Generated TypeScript

Frontend Contract ManifestはOutcome Fieldを再帰Schemaとして保持する。各NodeはScalar、DTO、ListのKind、Nullable、DTO Class、子FieldまたはElement Schemaを明示し、`any`、`unknown`、Runtime Sampleから推論しない。

- Frontend Contract Manifest Schema Versionを3へ上げる
- 旧Schema VersionはDecodeせず、`build:compile`と`frontend:generate`で再生成させる
- Generation Marker Schema Versionを上げ、旧TreeをFreshとして扱わない
- Operation Moduleは参照するDTOをReadonly Typeとして出力する
- DTO Type名はPHP Short Class Nameを使い、同一Operation内でCase-insensitiveに衝突する別Classがある場合はBuild Errorにする
- Listは`ReadonlyArray<T>`、Nullable DTOは`T | null`として生成する
- Manifest、Type、Field、Import、Decoder Schemaの順序とBytesを決定的にする

Generated Runtime Decoderは全階層で次を検証する。

- ObjectのExact Key
- Scalar Type、IntegerのSafe Integer、FloatのFinite Number、Nullable
- DTOの再帰Shape
- Arrayが連続Listであることと全Element Shape
- Unknown／Missing Field、Wrong Type、Sparse Listを`unexpected_response`へ閉じること

成功時はRoot Object、Nested DTO、Listを再帰的にFreezeし、Status／WaitのCompleted Outcomeにも同じDecoderを使う。

## HTTP Projection

Inline `JsonOperationResponder`とDeferred `OperationStatusJsonResponder`は同じStructured Outcome Normalization規則を使う。

- Rootは`Outcome`、Nested Objectは`OutcomeData`だけを許可する
- Public Contractに含まれるFieldだけを再帰的にJSON Object／Arrayへ変換する
- Empty Outcomeの既存204／空Object Contractを維持する
- Map、Sparse List、Wrong DTO、Uninitialized／Unsupported Fieldを成功ResponseへCastしない
- InlineとStatusで同じOutcomeを異なるShapeへ変換しない

Runtime Contract違反はRaw Value、Class Source Path、CredentialをResponseへ含めず、既存Internal Error境界へ到達させる。

## PostgreSQL Persistence and Canonical Journal

`PostgreSqlOutcomeCodec::SCHEMA_VERSION`は2とする。Version 2だけをEncode／Decodeし、Version 1と未知Versionは`OutcomeStoreException`で拒否する。Version 1 Rowの自動MigrationやFallback Decoderは提供しない。開発中の既存Databaseは再作成する。

Encoded ValueはRoot `Outcome`とNested `OutcomeData`のClass、Property、Listを再帰的に保持し、Decode時に次を検証してConstructorから復元する。

- Root Classが保存RowのOutcome Typeと一致し`Outcome`を実装する
- Nested Classが`OutcomeData`を実装する
- Constructor-promoted Public Propertyと保存FieldがExactに一致する
- Scalar／Nullable／DTO／ListのDeclared ContractとStored Valueが一致する
- Unknown／Missing Field、Unknown Class、Cycle、Wrong Element、Unsupported Typeを拒否する

Canonical `operation.completed` Journal Dataも同じStructured Value規則でOutcomeを保存・復元する。Journal Record全体のSchema VersionとOutcome StoreのSchema Versionは別契約であり、このTaskではJournal Record Envelopeを変更しない。旧Local Dataの互換性は保証しない。

## Sensitive Data

HTTP Frontend ContractのOutcome Rootまたは到達可能なNested DTOに`#[Sensitive]` Propertyがある場合、`build:compile`をFailさせる。ListのElement DTOも再帰検査対象とする。

例外は`EphemeralOutcome` Rootだけとする。Ephemeral OutcomeのCredential Propertyは`#[Sensitive]`を必須とし、Inline HTTP Responseへ一度だけ投影するが、Canonical Journal／Outcome Store／StatusへStructured Valueとして渡さない。Nested DTOへSensitive Propertyを隠す構造は許可せず、Credential FieldをRootで明示する。PropertyなしEphemeral OutcomeはSecret Inputだけを非永続化する用途で許可する。

Observer Projectionの共通Filterは防御的に次を満たす。

- PHP ListのNumeric Keyと順序を保持する
- Nested DTOとListを再帰的にProjectする
- Object Propertyの`#[Sensitive]` Modeを全階層で適用する
- Associative ArrayのReserved Sensitive Keyを全階層で除外する
- List ElementをReserved Key判定の対象として誤って削除しない

Frontend Type、HTTP Shape、Outcome StoreはAccess ControlやEncryptionの代替ではない。Outcomeへ秘密情報を含めるかどうかはApplication責務であり、HTTP公開OutcomeではFrameworkが`#[Sensitive]`をFail-closedに扱う。

## Initial Scope Boundary

- OperationValueのNested Input、Array Binding、Array Validationを追加しない
- Scalar List、Map、Enum、Date／Time、Union、Custom Serializerを追加しない
- OpenAPI、Frontend Framework Adapter、Remote Schema Registryを追加しない
- Community BoardのPost／Comment DomainはStructured Outcome Framework Taskの次に実装する

## Acceptance Criteria

- [ ] Public `OutcomeData`と`#[ListOf]`が安定した型契約を持つ
- [ ] BuildがSupported Shapeを再帰Compileし、Cycle／Unsupported／Sensitiveを拒否する
- [ ] Manifest Schema 3とGenerated Marker更新が旧Artifactを拒否する
- [ ] TypeScriptがReadonly DTO／ReadonlyArrayを生成し、Strict Recursive Decodeする
- [ ] Inline HTTPとDeferred Statusが同じStructured JSON Shapeを返す
- [ ] PostgreSQL Outcome Codec Version 2がStructured OutcomeをRound-tripする
- [ ] Version 1／未知Outcome Codec Schemaを安全に拒否する
- [ ] Canonical JournalがStructured Completed OutcomeをRound-tripする
- [ ] Sensitive ProjectionがNested DTO／List／Numeric Keyを安全に扱う
- [ ] Existing Scalar Outcome、Empty Outcome、Generated APIが回帰しない
- [ ] OperationValue BindingとCommunity Board Domainを変更しない

## Traceability

- Decision: [D104 Structured Outcome Contract](../decisions/104-structured-outcome-contract.md)
- Handler and Result: [Handler and Result](04-handler-and-result.md)
- Frontend Bridge: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Deferred Status: [Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)
- Sensitive Projection: [Sensitive Projection](25-sensitive-projection.md)
- PostgreSQL Layout: [PostgreSQL Table Layout](37-postgresql-table-layout.md)
- Phase 17 Plan: [Phase 17 Delivery Plan](72-phase-17-delivery-plan.md)

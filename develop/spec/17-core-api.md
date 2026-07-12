# Core API

## Marker Interface

Operation Definition、OperationValue、Outcomeは共通Methodを持たないMarker Interfaceとする。

```php
#[PublicApi]
interface Operation
{
}

#[PublicApi]
interface OperationValue
{
}

#[PublicApi]
interface Outcome
{
}
```

業務Classは対応するInterfaceを実装する。Operation DefinitionとValue、Handler、Outcomeの関連付けはAttributeで宣言する。

## Operation Handler

Handlerは `OperationHandler` を実装し、単一の `handle()` Methodを持つ。

```php
/**
 * @template TValue of OperationValue
 * @template TOutcome of Outcome
 */
#[PublicApi]
interface OperationHandler
{
    /**
     * @param OperationEnvelope<TValue> $operation
 * @return OperationResult<TOutcome>
 */
    public function handle(OperationEnvelope $operation): OperationResult;
}
```

Handlerは成功または業務拒否を `OperationResult` で返す。具体的なOperationValue型とOutcome型はPHPDoc Genericで表現する。Manifest CompilerとStatic Analysisは、Operation DefinitionのAttributeを含めて型の整合性を検証する。

## PHP Public API

Framework利用者による直接利用を公式に想定し、SemVer上の後方互換性を管理する型には、BlackOps固有の `#[PublicApi]` Attributeを付ける。

`#[PublicApi]` は実行時の振る舞いを追加するものではない。

- `#[PublicApi]` が付いた型は後方互換性の対象とする
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない
- `#[PublicApi]` がない型は、後方互換性を保証しない
- HTTP APIと区別が必要な文脈では「PHP Public API」と表記する

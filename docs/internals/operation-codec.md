# Operation Codec

Operation Codecは、Deferred OperationをHTTP Process、Database Transport、Worker Processの境界で受け渡すためのCodecである。

PHP Object Serializationは使用しない。MVPの既定実装はUTF-8 JSONを出力し、Operation Type ID、Schema Version、Encoded Payload、Encoded Contextを保持する。

## Public Contract

`BlackOps\Core\Codec\OperationCodec` は次を担う。

- `OperationValue` と `ExecutionContext` を `EncodedOperationMessage` へ変換する
- Encoded Payloadを登録済みOperation MetadataのValue型へ戻す
- Encoded Contextを `ExecutionContext` へ戻す
- サポート外Schema Versionや不正なJSONを `OperationCodecException` として拒否する

`EncodedOperationMessage` はTransport Adapterが `DeferredOperationMessage` を組み立てるための中間表現である。Operation IDやAvailable AtはExecutionContextや受付境界で付与されるため、このCodec結果には含めない。

## Reflection JSON Implementation

`BlackOps\Internal\Codec\ReflectionJsonOperationCodec` はMVP用の内部実装である。

PayloadはOperationValueのPublic PropertyをJSON Objectへ変換する。Decode時はOperation MetadataのValue型ConstructorへJSON Fieldを渡して復元する。

現在サポートするValue Shapeは次の範囲に限定する。

- Constructor PromotionされたPublic Property
- `string`、`int`、`float`、`bool`、`array`、nullable scalar
- ObjectやResourceを含まない配列
- Constructor Default Value

Object Property、Union型、Intersection型、Nested Object Collection、Value Upcaster Chain、Payload Encryptionは後続実装で扱う。

## Execution Context JSON

ExecutionContextは次のJSON Objectへ変換する。

```json
{
  "operation_id": "...",
  "received_at": "2026-07-09T15:00:00.123456Z",
  "correlation_id": "...",
  "causation_id": null,
  "attempt": null,
  "deadline": null
}
```

Attempt開始後は `attempt` にAttempt ID、Attempt番号、開始時刻を含める。時刻は共通TimeCodecのUTCマイクロ秒形式を使用する。

# D054: Runtime Operation Registry API

Status: Decided

## Decision

[DECISION]

Runtime Operation RegistryはOperationMetadataの反復可能値から一度構築する `#[PublicApi] final readonly class` とする。

Type IDとDefinition Classで索引し、検索失敗は例外ではなく `null` を返す。全Metadataは登録順のListとして取得できる。

重複Type IDまたは重複Definition Classは曖昧な解決を生むため、Constructorで `\InvalidArgumentException` として拒否する。例外Messageへ衝突値を含めない。

HTTP Route索引はRoute Metadata実装後に追加する。

[/DECISION]

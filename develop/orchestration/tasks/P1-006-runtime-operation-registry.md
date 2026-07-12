# P1-006: Runtime Operation Registry

Status: Accepted

## Goal

OperationMetadataをType IDとDefinition Classで引ける読み取り専用Registryを実装する。

## In Scope

- OperationRegistry
- 重複Type ID／Definition検査
- Nullable検索と全件取得
- Unit Testと内部文書

## Out of Scope

- Route索引
- Manifest File、Discovery、DI

## Acceptance Criteria

- [ ] RegistryがPublic final readonly classである
- [ ] Type IDとDefinitionで検索できる
- [ ] 未登録検索がnullを返す
- [ ] 重複を安全に拒否する
- [ ] 登録順で全件取得できる
- [ ] 全品質CommandとComment Guardrailが成功する

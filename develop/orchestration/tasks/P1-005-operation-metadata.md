# P1-005: Operation Metadata

Status: Accepted

## Goal

Operation Definitionの宣言Attributeと、解決済みOperationMetadataを生成するInternal Compilerを実装する。

## In Scope

- OperationType、Accepts、HandledBy、Returns、ExecuteWith
- OperationMetadata
- 明示されたDefinition ClassをReflectionするCompiler
- Inline Strategyの既定解決
- Attribute必須数とContract検証
- Unit Testと内部文書

## Out of Scope

- Composer Discovery、Token Scan
- Manifest File生成とAtomic Write
- Runtime Registry索引
- DI ContainerとHandler Instance解決
- HTTP Route Metadata

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/03-execution.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/decisions/053-operation-metadata-api.md`

## Files Allowed to Change

- `src/Core/Attribute/`
- `src/Core/Registry/`
- `src/Internal/Registry/`
- `tests/Core/Attribute/`
- `tests/Core/Registry/`
- `tests/Internal/Registry/`
- `docs/internal/operation-metadata.md`
- `docs/internal/README.md`
- `develop/orchestration/reports/P1-005-operation-metadata.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Acceptance Criteria

- [ ] AttributeとMetadataがPHP Public APIである
- [ ] Operation Type IDの形式を検証する
- [ ] 必須Attributeを正確に一つ要求する
- [ ] AttributeのClassが必要Contractを実装することを検証する
- [ ] ExecuteWith未指定時にInlineへ解決する
- [ ] ObjectやService InstanceをMetadataへ保持しない
- [ ] 全品質CommandとComment Guardrailが成功する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

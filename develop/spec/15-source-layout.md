# Source Layout

## 基本構造

`blackops/framework` の `src/` は責務別に分割する。

```text
src/
  Core/
  Journal/
  Execution/
  Transport/
  Http/
  Logging/
  Console/
  Internal/
```

各ディレクトリは `BlackOps` ルート配下の同名Namespaceへ対応する。

## 公開API

`BlackOps\Core`、`BlackOps\Journal`、`BlackOps\Execution` 等には、利用者が次の目的で参照する公開APIと拡張Contractを置く。

- OperationやHandlerの型宣言
- DI設定
- Adapter実装
- Frameworkの設定と起動

## Internal API

`BlackOps\Internal` にはFramework内部からのみ使用する実装詳細を置く。

- 後方互換性の対象外とする
- 利用者による直接利用を公式にサポートしない
- 公開APIの引数、戻り値、Property等へ `BlackOps\Internal` の型を露出させない

PHPの可視性によって参照を禁止するものではなく、互換性Contractの境界として運用する。

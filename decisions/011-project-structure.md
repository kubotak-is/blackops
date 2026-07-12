# D011: Project Structure

Status: Decided

Updated by: [D064 Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)

D064は、Decision項目2と6の入口別／Internal Directory推奨、および項目13と14のSkeleton未決定状態をFeature-first Skeletonで更新する。Directoryを強制しない原則、FeatureとAction単位で関連Fileを近くへ置く原則、Config／Providerで探索対象を指定する原則は維持する。

## Context

初期バージョンではHttp、Console、InternalのOperationを扱う。

ユーザーから次の構造案が提示された。

```text
app/
  UserInterface/
    Http/
      Operation/
        [Feature]/
          Command/
          Query/
      Middleware/
    Console/
      Operation/
      Middleware/
    Internal/
      Operation/
      Middleware/
    Shared/
  Domain/
  Infrastructure/
config/
```

この設計対話では、入口別Operation、内部Operation、Domain、Infrastructure、Configの推奨配置を決める。FWが配置を強制するのではなく、公式SkeletonとManifest Scannerの既定規約として扱う。

## Question 1: 構造の基本方針

OperationをLayerとFeatureのどちらを中心に整理するか。

### Options

- A: Layerを優先し、Operation、Handler、Value、Outcomeを別々のディレクトリへ置く
- B: Featureを優先し、一つのOperationに関係するDefinition、Value、Handler、Outcome、Responderを近くへ置く
- C: 配置を一切推奨しない

### Recommendation

Bを推奨する。

```text
Http/
  Operation/
    Order/
      CreateOrder/
        CreateOrder.php
        CreateOrderValue.php
        CreateOrderHandler.php
        OrderCreated.php
        CreateOrderResponder.php
```

Operation Manifestによって型の関連を解決できるため、種類別に分散させるより、変更理由が同じファイルを近くへ置く方が保守しやすい。

[ANSWER]

B、Feature方式は不要になったらごっそり消せるしAIフレンドリーだと思います。

[/ANSWER]

## Question 2: Internal Operationの配置

HTTPやConsoleを入口としないInternal Operationをどこへ置くか。

### Options

- A: `UserInterface/Internal/` へ置く
- B: `Application/Operation/Internal/` へ置く
- C: 親Operationと同じHttpまたはConsole Featureへ置く

### Recommendation

Bを推奨する。

Internal Operationはユーザーや外部システムとのInterfaceではなく、アプリケーション内部の処理単位である。UserInterface配下へ置くとLayerの意味が曖昧になる。

[ANSWER]

B

[/ANSWER]

## Question 3: Command／Queryディレクトリ

D001ではCommandとQueryをFWの型として区別しないと決めた。ディレクトリ上ではCommand／Queryへ分けるか。

### Options

- A: ディレクトリ上だけCommand／Queryへ分類し、FW上の意味は持たせない
- B: ディレクトリも分けず、FeatureとAction名で整理する
- C: Command／Query marker interfaceを正式に再導入し、ディレクトリと型を一致させる

### Recommendation

Bを推奨する。

```text
Operation/
  Order/
    CreateOrder/
    GetOrder/
    CancelOrder/
```

型として意味を持たない分類を公式構造へ入れると、POSTによる検索や副作用を伴う参照などの境界で迷いやすい。必要性が生まれた時点でCQRS分類を再検討する。

[ANSWER]

B、ここで出してたCommandとQueryはレイヤードアーキテクチャのUI層ですので、ユーザーが作りたかったら勝手に作る想定でしたが、それすら不要かもですね。Operationだけで完結できればそれでOKです。

[/ANSWER]

## Question 4: Sharedの範囲

`Shared/` をどのように扱うか。

### Options

- A: どのLayerからでも自由に利用できる共通置き場にする
- B: 安定した共通Contractと汎用Value Objectだけに限定する
- C: Sharedディレクトリを禁止する

### Recommendation

Bを推奨する。

対象例：

- 共通ID Value Object
- Pagination Value
- 複数Featureで共有する明確なContract

特定Featureの都合を持つService、Repository、UtilityはSharedへ置かない。所属が曖昧なコードの一時置き場にしない。

[ANSWER]

用途としてはBですが、これもFWで強制ではなく、ユーザーが自由にディレクトリを掘れればいいかもです。
例えばConfigでHTTPとしてのOperationを監視するディレクトリ、ConsoleとしてのOperationを監視するディレクトリ等は設定して、それ以外のディレクトリは好きにやれればいいと思う。Laravelが発展したのはディレクトリ構造のユーザー自身の拡張に対して柔軟だったことだと思います。

[/ANSWER]

## Question 5: Infrastructureの推奨分類

Infrastructure配下をどのように整理するか。

### Options

- A: 技術カテゴリごとに分類する
- B: Featureごとに分類する
- C: すべて直下へ置く

### Recommendation

Aを基本とし、各カテゴリ内を必要に応じてFeature分割することを推奨する。

```text
Infrastructure/
  Persistence/
  ExecutionTransport/
  JournalObserver/
  Authentication/
  Clock/
  IdGenerator/
```

FWのPort実装がどの責務を担うか見つけやすくなる。

[ANSWER]

A

[/ANSWER]

## Question 6: Configの分割

初期SkeletonでConfigをどのように分けるか。

### Options

- A: 一つの `config.php` にまとめる
- B: FWの主要責務ごとに分ける
- C: Configファイルを持たず環境変数だけを使う

### Recommendation

Bを推奨する。

```text
config/
  app.php
  operations.php
  execution.php
  journal.php
  middleware.php
  security.php
```

Secret自体はConfigへ直書きせず、環境変数やSecret Managerへの参照として扱う。

[ANSWER]

B

[/ANSWER]

## Question 7: 推奨Skeleton

ここまでの推奨を統合した初期Skeletonを採用するか。

```text
app/
  UserInterface/
    Http/
      Operation/
        [Feature]/
          [Action]/
      Middleware/
    Console/
      Operation/
        [Feature]/
          [Action]/
      Middleware/
  Application/
    Operation/
      Internal/
        [Feature]/
          [Action]/
    Middleware/
  Domain/
    [Feature]/
  Infrastructure/
    Persistence/
    ExecutionTransport/
    JournalObserver/
    Authentication/
    Clock/
    IdGenerator/
  Shared/
config/
  app.php
  operations.php
  execution.php
  journal.php
  middleware.php
  security.php
```

### Options

- A: 採用する
- B: LayerよりFeatureを最上位にした構造へ変更する
- C: 別案を提示する

### Recommendation

Aを推奨する。

[ANSWER]

C
Sharedは不要です。Skeltonはまだ未検討で良いです。
例えば初期Welcomeページを出力する等が決まったらで良い。これはかなり後工程で良い

[/ANSWER]

## Decision

[DECISION]

1. FWはアプリケーション全体のディレクトリ構造を強制しない。
2. ConfigでHttp、Console、Internalなど入口種別ごとのOperation探索ディレクトリを指定できるようにする。
3. Manifest Scannerは設定された探索ディレクトリだけを対象とする。
4. Operationに関連するDefinition、Value、Handler、Outcome、Responderは、FeatureとAction単位で近くへ配置することを推奨する。
5. Feature単位の配置は公式推奨であり、FWの実行要件とはしない。
6. Internal OperationはUserInterfaceではなくApplication層へ置くことを推奨する。
7. Command／Queryディレクトリとmarker interfaceを公式構造へ導入しない。Operationだけで処理を表現する。
8. ユーザーが独自判断でCommand／Queryなどのディレクトリを追加することは妨げない。
9. `Shared` ディレクトリを公式構造へ含めない。共通コードの配置はアプリケーション側へ委ねる。
10. InfrastructureはPersistence、ExecutionTransport、JournalObserver、Authentication、Clock、IdGeneratorなど技術責務ごとに分類することを推奨する。
11. Configは `app.php`、`operations.php`、`execution.php`、`journal.php`、`middleware.php`、`security.php` など主要責務ごとに分割する。
12. SecretをConfigファイルへ直書きせず、環境変数またはSecret Managerへの参照として扱う。
13. 公式Application Skeletonの完全な構造は現時点では決定しない。
14. Welcome Page、初期Operation、起動方法など初期体験を設計する段階でSkeletonを改めて決定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- ユーザーはDDD、レイヤード、Feature-firstなど、アプリケーションに合う構造を選択できる。
- FWはディレクトリ名ではなく、ConfigとOperation AttributeからMetadataを構築する。
- 不要になったFeatureを関連ファイルごと削除しやすく、AIによるコード探索でも変更範囲を限定しやすい。
- Scannerは入口種別ごとに複数の探索Rootを扱える必要がある。
- 探索Root外のOperationを内部発行時にどうRegistryへ登録するかを決める必要がある。
- Skeleton、Welcome Page、初期設定ファイルの内容は後工程で設計する。

[/CONSEQUENCES]

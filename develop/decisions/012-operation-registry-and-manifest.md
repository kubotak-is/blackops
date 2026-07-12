# D012: Operation RegistryとManifest

Status: Decided

## Context

D008で、本番環境はAttributeを毎リクエスト探索せず、CLIで生成したOperation Manifestを使用すると決定した。

D011では、FWがディレクトリ構造を強制せず、Configで入口種別ごとの探索Rootを指定すると決定した。

この設計対話では、Operation Discovery、外部Packageからの登録、Manifest生成、陳腐化検知、Runtime Registryを決める。

## Question 1: Operation Discovery

設定された探索RootからOperation Definitionをどう発見するか。

### Options

- A: PHPファイルを読み込み、宣言された全クラスを前後比較する
- B: Composer Autoload Metadataを利用し、探索Root配下のクラスだけReflectionする
- C: ファイル名とnamespaceの規則をFWが強制する

### Recommendation

Bを推奨する。

ComposerのPSR-4／Classmap情報から候補クラスを取得し、設定Rootで絞り込んだ後、Operation marker interfaceとAttributeをReflectionする。

開発環境でClassmapが不完全な場合に備え、Root配下をToken Scanしてクラス名を取得するFallbackを用意する。ファイルを無差別に実行して探索しない。

[ANSWER]

B

[/ANSWER]

## Question 2: Package Operationの登録

Composer PackageやPluginが提供するOperationをどうRegistryへ追加するか。

### Options

- A: アプリケーションの探索RootへPackageディレクトリを追加する
- B: PackageがOperation Providerを公開し、ConfigでProviderを登録する
- C: Package Operationを許可しない

### Recommendation

Bを推奨する。

```php
interface OperationProvider
{
    public function definitions(): iterable;
}
```

Packageの内部ディレクトリ構造へ依存せず、公開対象のOperationだけを明示的に登録できる。

[ANSWER]

これどういうこと？そういえばDIコンテナについて議論してなかった。安定したいいライブラリがあればそれに依存してもいいが・・・

[/ANSWER]

## Question 3: Manifestの内容

Manifestに何を保存するか。

### Options

- A: Route情報だけを保存する
- B: Operationの実行に必要な解決済みMetadataを保存する
- C: Reflection ObjectやService Instanceも保存する

### Recommendation

Bを推奨する。

保存対象：

- Manifest Schema Version
- Application Build ID
- Operation Type ID
- Definition、Value、Handler、Outcomeのクラス名
- Adapter種別とRoute／Console Metadata
- Execution StrategyとSupervision Policy
- Authorization Policy
- Middleware Pipeline
- Responder
- Journal Schema Version

Manifestはスカラー値とクラス名だけのPHP配列とし、Object、Closure、Credential、環境Secretを含めない。

[ANSWER]

B

[/ANSWER]

## Question 4: 本番環境のManifest

本番環境でManifestが存在しない、またはSchema Versionが非対応の場合どうするか。

### Options

- A: 実行時に自動ScanしてManifestを生成する
- B: 起動を失敗させ、Deploy／Build工程での生成を要求する
- C: 警告だけ出し、動的ReflectionへFallbackする

### Recommendation

Bを推奨する。

本番での暗黙Scanは性能を不安定にし、Read-only Containerでは書き込みにも失敗する。Build時に `operation:compile` を実行し、起動時はManifestの存在と互換性だけを検証する。

[ANSWER]

B

[/ANSWER]

## Question 5: 開発環境の陳腐化検知

AttributeやOperationクラス変更後、開発用Manifestをどう更新するか。

### Options

- A: 毎リクエスト全OperationをScanする
- B: 対象ファイルのPath、更新時刻、サイズ等からFingerprintを作り、変更時だけ再生成する
- C: 開発者が毎回CLIを手動実行する

### Recommendation

Bを推奨する。

開発中の利便性を保ちながら、変更がないリクエストではManifestを再利用できる。厳密な内容Hashはファイル数が増えると高コストなため、既定は軽量Fingerprintとし、CIでは完全な再生成を行う。

[ANSWER]

B

[/ANSWER]

## Question 6: Manifestの書き込み

Manifest再生成中に別プロセスが読み込む場合の破損をどう防ぐか。

### Options

- A: 対象ファイルへ直接上書きする
- B: 一時ファイルへ完全出力し、検証後にAtomic Renameする
- C: DBへ保存する

### Recommendation

Bを推奨する。

同一Filesystem上の一時ファイルへ書き込み、PHPとして読み込めることを検証してから置き換える。生成Lockも取得し、複数PHP-FPMプロセスによる同時生成を防ぐ。

[ANSWER]

B

[/ANSWER]

## Question 7: Runtime Registry

実行時のOperation Registryをどう構築するか。

### Options

- A: 各解決時にManifest配列を直接検索する
- B: Manifestから読み取り専用Registryを一度構築し、Type ID、Route、Definition Classで索引する
- C: 全MetadataをDI ContainerのServiceとして個別登録する

### Recommendation

Bを推奨する。

```text
Operation Manifest
  -> Operation Registry
     - byTypeId()
     - byHttpRoute()
     - byDefinition()
```

RegistryはRequest間で共有可能な不変Metadataだけを保持し、HandlerなどのService InstanceはDI Containerから必要時に解決する。

[ANSWER]

B

[/ANSWER]

## Follow-up 1: Operation ProviderとDI Containerの違い

Operation ProviderはService Instanceを生成するDI Containerではない。

Composer Packageが、Manifest Compilerへ「このOperation Definitionを登録してほしい」と伝えるBuild時の拡張口である。

```php
interface OperationProvider
{
    /**
     * @return iterable<class-string<Operation>>
     */
    public function definitions(): iterable;
}
```

Package例：

```php
final class HealthCheckOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        yield HealthCheck::class;
        yield ReadinessCheck::class;
    }
}
```

アプリケーションConfig：

```php
return [
    'providers' => [
        HealthCheckOperationProvider::class,
    ],
];
```

Manifest CompilerはProviderをBuild時に読み、返されたOperation Attributeを検証してManifestへ追加する。RuntimeでHandlerを生成する処理はDI Containerが担当する。

```text
Operation Provider
    -> Build時
    -> Operation DefinitionをManifestへ登録

DI Container
    -> Runtime
    -> Handlerと依存Serviceを生成
```

Providerを使うと、Packageの内部ディレクトリをアプリケーション側のScannerへ公開せず、PackageがPublic APIとして提供するOperationだけを登録できる。

### Question

Package Operationの登録にOperation Providerを採用するか。

### Options

- A: 採用する
- B: Packageのディレクトリを通常の探索Rootへ追加する
- C: 初期バージョンではPackage Operationを扱わない

### Recommendation

Aを推奨する。

DI Containerの選定、Service Providerとの統合、Autowiring、Container CompileはD013で別途決定する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Configで指定された探索RootからOperation Definitionを発見する際、ComposerのPSR-4／Classmap Metadataを利用する。
2. Composer Metadataから候補クラスを取得し、探索Rootで絞り込んだ後、Operation marker interfaceとAttributeをReflectionする。
3. 開発環境でClassmapが不完全な場合、PHP Token Scanによってクラス名を取得するFallbackを提供する。
4. ファイルを無差別に実行してクラスを探索せず、ファイル名やnamespaceの固定規則も強制しない。
5. Composer PackageはOperation Providerによって、公開するOperation DefinitionをBuild時にManifest Compilerへ登録できる。
6. Operation ProviderはService Instanceを生成せず、Operation Definitionのクラス名だけを提供する。
7. RuntimeでHandlerと依存Serviceを生成する責務はDI Containerへ分離する。
8. ManifestにはOperation実行に必要な解決済みMetadataを保存する。
9. ManifestはSchema Version、Application Build ID、Type ID、Definition、Value、Handler、Outcome、Adapter Metadata、Execution Strategy、Supervision Policy、Authorization Policy、Middleware Pipeline、Responder、Journal Schema Versionを含められる。
10. Manifestはスカラー値とクラス名だけのPHP配列とし、Object、Closure、Credential、環境Secretを含めない。
11. 本番環境ではManifest生成をBuild／Deploy工程で必須とする。
12. 本番環境でManifestが存在しない、またはSchema Versionが非対応の場合は起動を失敗させる。動的ScanへFallbackしない。
13. 開発環境では対象ファイルのPath、更新時刻、サイズ等による軽量Fingerprintを使い、変更時だけManifestを再生成する。
14. CIではFingerprintに依存せず、Manifestを完全再生成して検証する。
15. Manifestは同一Filesystem上の一時ファイルへ完全出力し、検証後にAtomic Renameで置き換える。
16. 生成Lockを取得し、複数プロセスによる同時生成を防ぐ。
17. RuntimeではManifestから読み取り専用Operation Registryを一度構築する。
18. RegistryはType ID、Adapter Route、Definition Classなどの索引を持つ。
19. Registryは不変Metadataだけを保持し、HandlerなどのService InstanceはDI Containerから必要時に解決する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- ユーザーの自由なディレクトリ構造を保ちながら、設定Root内のOperationを発見できる。
- Packageは内部ディレクトリを公開せず、Public APIとして提供するOperationだけを登録できる。
- 本番Runtimeでは全クラス探索とReflectionを避け、OPcache可能なPHP配列を利用できる。
- Manifest生成時に重複Type ID、重複Route、型不整合、存在しないクラスを検出できる。
- Manifest Compiler、Composer Metadata Reader、Token Scanner、Fingerprint、Atomic Writer、Operation Registryを実装する必要がある。
- Manifest Schema VersionとApplication Build IDの生成規則を決める必要がある。
- Operation Providerの生成方法とPackage自動検出を後続で検討できる。
- DI ContainerのContract、標準実装、Service Provider、Container CompileをD013で決める必要がある。

[/CONSEQUENCES]

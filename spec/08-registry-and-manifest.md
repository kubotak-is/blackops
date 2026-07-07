# Operation Registry and Manifest

## Operation Discovery

Configで指定された探索RootからOperation Definitionを発見する。

ComposerのPSR-4／Classmap Metadataから候補クラスを取得し、探索Rootで絞り込んだ後、Operation marker interfaceとAttributeをReflectionする。

開発環境でClassmapが不完全な場合、PHP Token Scanによってクラス名を取得するFallbackを提供する。ファイルを無差別に実行せず、ファイル名やnamespaceの固定規則も強制しない。

## Operation Provider

Composer PackageはOperation Providerによって、公開するOperation DefinitionをBuild時にManifest Compilerへ登録できる。

```php
interface OperationProvider
{
    /**
     * @return iterable<class-string<Operation>>
     */
    public function definitions(): iterable;
}
```

Operation ProviderはService Instanceを生成しない。RuntimeでHandlerと依存Serviceを生成する責務はDI Containerが担う。

## Manifest

ManifestにはOperation実行に必要な解決済みMetadataを保存する。

対象：

- Manifest Schema Version
- Application Build ID
- Operation Type ID
- Definition、Value、Handler、Outcomeのクラス名
- Adapter Metadata
- Execution Strategy
- Supervision Policy
- Authorization Policy
- Middleware Pipeline
- Responder
- Journal Schema Version

Manifestはスカラー値とクラス名だけのPHP配列とし、Object、Closure、Credential、環境Secretを含めない。

## 環境別の運用

### Production

Build／Deploy工程でManifest生成を必須とする。

Manifestが存在しない、またはSchema Versionが非対応の場合は起動を失敗させ、動的ScanへFallbackしない。

### Development

対象ファイルのPath、更新時刻、サイズ等による軽量Fingerprintを使い、変更時だけManifestを再生成する。

### CI

Fingerprintに依存せずManifestを完全再生成し、Metadataと型の整合性を検証する。

## Atomic Write

Manifestは同一Filesystem上の一時ファイルへ完全出力し、検証後にAtomic Renameで置き換える。

生成Lockを取得し、複数プロセスによる同時生成を防ぐ。

## Runtime Registry

RuntimeではManifestから読み取り専用Operation Registryを一度構築する。

Registryは少なくとも次の索引を持つ。

- Type ID
- Adapter Route
- Definition Class

Registryは不変Metadataだけを保持する。HandlerなどのService InstanceはDI Containerから必要時に解決する。

Core RegistryはType IDとDefinition Classで検索でき、未登録時は `null` を返す。重複Type IDまたはDefinition Classは構築時に拒否する。Route索引はHTTP Metadata実装後に追加する。

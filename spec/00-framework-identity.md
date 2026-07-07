# Framework Identity

## 正式名称

本フレームワークの正式名称は **BlackOps PHP Framework** とする。

文脈上PHPフレームワークであることが明らかな場合は、短縮名として **BlackOps** を使用する。

## PHP名前空間

フレームワーク本体のルート名前空間は `BlackOps` とする。

```php
namespace BlackOps;
```

各コンポーネントは責務に応じて、このルート名前空間配下へ配置する。

```php
namespace BlackOps\Core;
namespace BlackOps\Http;
namespace BlackOps\Database;
```

## 実装上の識別子

```text
Display Name: BlackOps
Descriptive Name: BlackOps PHP Framework
Search Keyword: BlackOpsPHP
PHP Namespace: BlackOps
CLI Binary: blackops
Config Prefix: blackops
```

## Composerパッケージ

公開する場合のComposerパッケージ名は `blackops/framework` を第一候補とする。

```json
{
  "name": "blackops/framework",
  "autoload": {
    "psr-4": {
      "BlackOps\\": "src/"
    }
  }
}
```

パッケージ公開時には、Packagist上で名称が利用可能であることを改めて確認する。

## Brand Message

第一候補は次のとおりとする。

> No operation stays in the dark.

名称は軍事的表現ではなく、すべてのOperationをJournalへ記録し、可観測かつ追跡可能にするFrameworkの思想へ接続する。

## 公開条件

正式公開、パッケージ配布、ロゴ制作の前に、対象地域と対象区分を定めた商標クリアランスを行う。

Composer、GitHub、Domain等の識別子は、取得可能性を確認した時点で最終確定する。

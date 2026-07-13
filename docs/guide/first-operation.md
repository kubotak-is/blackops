# 最初のTyped Self-handled Operation

Install直後のWelcome Featureは、Operation自身が`handle()`を持つTyped Self-handled形式です。ValueとOutcomeはNative Parameter／Return Typeから推論されるため、Handler登録用AttributeやGeneric DocBlockは不要です。

## Operation

```php
<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
final readonly class ShowWelcome implements Operation
{
    public function handle(WelcomeValue $value): WelcomeShown
    {
        return new WelcomeShown('Welcome to BlackOps');
    }
}
```

`#[OperationType]`は永続的なOperation Type IDです。`#[Route]`を持つOperationはCompile時にHTTP Manifestへ登録されます。`handle()`の第一引数は`OperationValue`、Return Classは`Outcome`を実装する必要があります。

## Value

```php
<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromHeader;
use SensitiveParameter;

final readonly class WelcomeValue implements OperationValue
{
    public function __construct(
        #[FromHeader('X-Sample-Token')]
        #[Sensitive(SensitiveMode::Mask)]
        #[SensitiveParameter]
        public string $sampleToken,
    ) {}
}
```

HTTP Binderは`X-Sample-Token` HeaderからValueを構築します。`#[Sensitive]`はObserved Projectionで値をMaskしますが、Canonical JournalのAccess Control、暗号化、Retentionを置き換えるものではありません。

## Outcome

```php
<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Outcome;

final readonly class WelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}
```

値のない成功はReturn Typeを`void`にします。予期された業務上の拒否は`BlackOps\Core\Exception\OperationRejectedException`のCategory Factoryをthrowします。その他のExceptionは自動でRejectedへ丸められず、Failure／Retry Policyへ渡されます。

Application BuildはOperationを探索してSignatureを検証し、Operation自身をHandler Serviceとして自動登録します。標準形では`#[Accepts]`、`#[Returns]`、`OperationHandler`、`OperationResult::completed()`、Operation Providerへの手動列挙は不要です。

次は[Local Runtime](runtime-bootstrap.md)でこのOperationをBuildしてHTTPから実行します。

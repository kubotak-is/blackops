# チュートリアル: Operationを作る

Project CLIから`Billing/CreateInvoice`の骨格を生成し、HTTPで受け付けるDeferred Operationへ仕上げます。

> **Document Channel:** `make:operation`、Project Rootの`blackops`、宣言的Validation Attributeは`main`に実装済みで、Latest Stable `1.0.0`にはまだ含まれません。このPageは[Quickstart](mvp-sample.md)の`main`環境を使います。

## 1. Generatorから始める

Project Rootで実行します。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
```

```text
Created: app/Feature/Billing/CreateInvoice/CreateInvoice.php
Created: app/Feature/Billing/CreateInvoice/CreateInvoiceValue.php
Created: app/Feature/Billing/CreateInvoice/CreateInvoiceOutcome.php
```

Generatorが作るのはBuild可能なOperation、Value、Outcomeの3 Fileです。既存Fileを上書きせず、Route、Execution Strategy、PropertyはApplicationの判断として追加しません。ここから先の3 Fileは利用者が編集する完成形です。

## 2. ValueへInputとValidationを書く

`app/Feature/Billing/CreateInvoice/CreateInvoiceValue.php`を置き換えます。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\CreateInvoice;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use SensitiveParameter;

final readonly class CreateInvoiceValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        #[Length(min: 3, max: 80)]
        public string $customerName,
        #[Email]
        public string $email,
        #[Range(min: 1, max: 100)]
        public int $quantity,
        #[Sensitive(SensitiveMode::Mask)]
        #[SensitiveParameter]
        #[NotBlank]
        public string $apiToken,
    ) {}
}
```

PHP TypeはHTTP Bindingの型境界です。`NotBlank`、`Length`、`Email`、`Range`はBinding後のValueを検証します。`Sensitive`はJournal ProjectionからRaw Tokenを除外し、`SensitiveParameter`はStack Trace上の引数をRedactします。

## 3. Outcomeを書く

`app/Feature/Billing/CreateInvoice/CreateInvoiceOutcome.php`を置き換えます。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\CreateInvoice;

use BlackOps\Core\Outcome;

final readonly class CreateInvoiceOutcome implements Outcome
{
    public function __construct(
        public string $invoiceId,
        public string $customerName,
        public int $quantity,
    ) {}
}
```

Handlerの正常系Return Typeは具象Outcomeだけです。Rejected ResultをReturn Typeへ混ぜず、予期された拒否はFrameworkのExceptionへ委ねます。

## 4. RouteとDeferred Strategyを書く

`app/Feature/Billing/CreateInvoice/CreateInvoice.php`を置き換えます。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\CreateInvoice;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/invoices')]
#[OperationType('billing.invoice.create')]
#[ExecuteWith(Deferred::class)]
final readonly class CreateInvoice implements Operation
{
    public function handle(CreateInvoiceValue $value, ExecutionContext $context): CreateInvoiceOutcome
    {
        return new CreateInvoiceOutcome(
            invoiceId: $context->operationId()->toString(),
            customerName: $value->customerName,
            quantity: $value->quantity,
        );
    }
}
```

Value型とOutcome型は`handle()` Signatureから推論されます。`Accepts`、`Returns`、Handler用Interfaceは不要です。`ExecutionContext`が不要なら第二引数ごと省略できます。

## 5. BuildしてRouteを有効にする

```bash
docker compose run --rm app composer dump-autoload
docker compose run --rm app php blackops blackops:build:compile
docker compose up -d
```

```text
Build artifacts written.
```

BuildはOperation Signature、Metadata、Routeを検証し、ManifestとDI Containerを生成します。RuntimeはSource DiscoveryへFallbackしないため、Source変更後は明示的に再Buildします。

## 6. HTTPで受け付ける

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"customerName":"Acme","email":"billing@example.com","quantity":2,"apiToken":"local-example"}' \
  http://127.0.0.1:8080/invoices
```

```json
{"status":"accepted","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-14T01:23:45.678901Z"}
```

HTTP 202はDurable受付の結果です。HandlerはHTTP Process内でまだ実行されません。`operationId`と`acceptedAt`は実行ごとに変わります。

不正なEmailを送るとHTTP 422になり、HandlerもDeferred受付も実行されません。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"customerName":"Acme","email":"invalid","quantity":2,"apiToken":"local-example"}' \
  http://127.0.0.1:8080/invoices
```

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","category":"validation","code":"validation.failed","violations":[{"field":"email","rule":"email","code":"validation.email"}]}
```

## 7. Workerで実行する

```bash
docker compose run --rm app php blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 1
```

`var/log/journal.jsonl`には受理から完了までがJSON Linesで残り、`apiToken`はMaskされます。HTTP 202で得たOperation IDを指定して実Recordを絞り込みます。

```bash
OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687697'
grep "$OPERATION_ID" var/log/journal.jsonl \
  | grep -E '"event":"(operation.received|operation.completed)"'
```

次は実Encoder Shapeの抜粋です。Operation ID、Attempt ID、`occurredAt`、`startedAt`は実行ごとに変わります。

```jsonl
{"schemaVersion":1,"kind":"journal","event":"operation.received","occurredAt":"2026-07-14T01:23:45.678901Z","sequence":1,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","type":"billing.invoice.create","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","causationId":null},"attempt":null,"data":{"value":{"customerName":"Acme","email":"billing@example.com","quantity":2,"apiToken":"[masked]"}}}
{"schemaVersion":1,"kind":"journal","event":"operation.completed","occurredAt":"2026-07-14T01:23:47.123456Z","sequence":5,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","type":"billing.invoice.create","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","causationId":null},"attempt":{"id":"019f32ab-2c10-7b38-a0a7-1ab2f9687698","number":1,"startedAt":"2026-07-14T01:23:46.123456Z"},"data":{"outcome":{"invoiceId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","customerName":"Acme","quantity":2}}}
```

### Outcomeを読む

現行RuntimeはOutcome用HTTP endpointやCLI Commandを提供しません。ApplicationがController、CLI Command等の入口を実装し、Public `OutcomeReader`へ同じOperation IDを渡します。

```php
use App\Feature\Billing\CreateInvoice\CreateInvoiceOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Outcome\OutcomeReader;
use RuntimeException;

function invoiceOutcome(OutcomeReader $outcomes, string $operationId): array
{
    $record = $outcomes->find(OperationId::fromString($operationId));

    if ($record === null) {
        return ['status' => 'pending_or_unavailable', 'operationId' => $operationId];
    }

    $outcome = $record->outcome();

    if (!$outcome instanceof CreateInvoiceOutcome) {
        throw new RuntimeException('The stored outcome type does not match billing.invoice.create.');
    }

    return [
        'status' => 'completed',
        'operationId' => $record->operationId()->toString(),
        'completedAt' => $record->completedAt()->format('Y-m-d\\TH:i:s.u\\Z'),
        'outcome' => [
            'invoiceId' => $outcome->invoiceId,
            'customerName' => $outcome->customerName,
            'quantity' => $outcome->quantity,
        ],
    ];
}
```

```json
{"status":"completed","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","completedAt":"2026-07-14T01:23:47.123456Z","outcome":{"invoiceId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","customerName":"Acme","quantity":2}}
```

`find()`が`null`を返すだけではPending、未知のID、失敗、保持期限切れを区別できません。Application Status Viewは[Outcome Retrieval](outcome-retrieval.md)で設計します。

作業後はRuntimeを停止します。

```bash
docker compose down
```

次は[Validation](validation.md)で宣言的Rule、Cross-field Validation、Business Rejectionの使い分けを確認してください。

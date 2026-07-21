# チュートリアル: Operationを作る

Project CLIから`Billing/CreateInvoice`の骨格を生成し、HTTPで受け付けるDeferred Operationへ仕上げます。

> **Release:** このTutorialはExperimental Stable `1.1.0`のProject Root `blackops`、`make:operation`、宣言的Validation Attributeを使用します。Step 4以降の`#[Authorize]`とSample Token Authenticationは`main`の未Release Surfaceであり、Repository Quickstart向けです。

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
        public string $billingReference,
    ) {}
}
```

PHP TypeはHTTP Bindingの型境界です。`NotBlank`、`Length`、`Email`、`Range`はBinding後のValueを検証します。`billingReference`は業務上のSensitive値であり、`Sensitive`はObserved JournalでMaskし、`SensitiveParameter`はStack Trace上の引数をRedactします。認証CredentialはこのValueへ追加せずHeader Authenticationへ任せます。

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

use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/invoices')]
#[OperationType('billing.invoice.create')]
#[ExecuteWith(Deferred::class)]
#[Authorize(SampleUserAuthorizationPolicy::class)]
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
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
pnpm test
docker compose up -d
```

```text
Build artifacts written.
```

BuildはOperation Signature、Metadata、Routeを検証し、ManifestとDI Containerを生成します。RuntimeはSource DiscoveryへFallbackしないため、Source変更後は明示的に再Buildします。

## 6. HTTPで受け付ける

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"customerName":"Acme","email":"billing@example.com","quantity":2,"billingReference":"PO-2026-001"}' \
  http://127.0.0.1:8080/invoices
```

```json
{"status":"accepted","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-14T01:23:45.678901Z"}
```

HTTP 202はDurable受付の結果です。HandlerはHTTP Process内でまだ実行されません。`operationId`と`acceptedAt`は実行ごとに変わります。

同じOperation IDをPublic Status Resourceへ渡すと、Worker未起動中の`accepted`を確認できます。

```bash
OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687697'
curl -sS -H 'X-Sample-Token: local-example' \
  "http://127.0.0.1:8080/operations/${OPERATION_ID}"
```

```json
{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"billing.invoice.create","state":"accepted"}
```

不正なEmailを送るとHTTP 422になり、HandlerもDeferred受付も実行されません。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"customerName":"Acme","email":"invalid","quantity":2,"billingReference":"PO-2026-001"}' \
  http://127.0.0.1:8080/invoices
```

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","category":"validation","code":"validation.failed","violations":[{"field":"email","rule":"email","code":"validation.email"}]}
```

## 7. Workerで実行する

```bash
docker compose run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 1
```

`var/log/journal.jsonl`はHTTP ProcessのObserved Projectionです。上で実行した422 RequestはHTTP内で`operation.received`から`operation.rejected`まで進むため、Validation ResponseのOperation IDで安全なProjectionを確認できます。`billingReference`とActor IDはMaskされ、Header Credentialは保存されません。

```bash
VALIDATION_OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687698'
grep "$VALIDATION_OPERATION_ID" var/log/journal.jsonl \
  | grep -E '"event":"operation.(received|rejected)"'
```

次はHTTP Observed Encoder Shapeの抜粋です。Operation IDと`occurredAt`は実行ごとに変わります。

```jsonl
{"schemaVersion":1,"kind":"journal","event":"operation.received","occurredAt":"2026-07-14T01:24:45.678901Z","sequence":1,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","type":"billing.invoice.create","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":{"id":"[masked]","type":"user"},"execution":{"id":"[masked]","type":"user"}}},"attempt":null,"data":{"value":{"customerName":"Acme","email":"invalid","quantity":2,"billingReference":"[masked]"}}}
{"schemaVersion":1,"kind":"journal","event":"operation.rejected","occurredAt":"2026-07-14T01:24:45.679012Z","sequence":2,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","type":"billing.invoice.create","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":{"id":"[masked]","type":"user"},"execution":{"id":"[masked]","type":"user"}}},"attempt":null,"data":{"reason":{"category":"validation","code":"validation.failed","violations":[{"field":"email","rule":"email","code":"validation.email"}]}}}
```

Worker完了後は、同じPublic Status ResourceからTyped Outcomeを取得できます。

```bash
curl -sS -H 'X-Sample-Token: local-example' \
  "http://127.0.0.1:8080/operations/${OPERATION_ID}"
```

```json
{"schemaVersion":1,"operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","operationType":"billing.invoice.create","state":"completed","outcome":{"invoiceId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","customerName":"Acme","quantity":2}}
```

Frontendでは生成したOperation Objectから同じ経路を型付きで使います。`.fetch()`は受付だけ、`.status()`は一回だけ取得し、`.wait()`はAbort可能な有限待機です。

```ts
import { createBlackOpsClient } from './resources/js/blackops';

const blackops = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch: event.fetch,
  headers: { 'X-Sample-Token': 'local-example' },
});
const accepted = await blackops.CreateInvoice.fetch({
  customerName: 'Acme',
  email: 'billing@example.com',
  quantity: 2,
  billingReference: 'PO-2026-001',
}, { idempotencyKey: 'invoice-po-2026-001' });

if (accepted.ok && accepted.kind === 'accepted') {
  const current = await blackops.CreateInvoice.status(accepted.data.operationId);
  const controller = new AbortController();
  const terminal = await blackops.CreateInvoice.wait(accepted.data.operationId, {
    signal: controller.signal,
    maxWaitMilliseconds: 15_000,
  });

  if (terminal.ok && terminal.kind === 'completed') {
    terminal.data.outcome.invoiceId;
    terminal.data.outcome.customerName;
    terminal.data.outcome.quantity;
  }

  void current;
}
```

Canonical PostgreSQL Journalは監査と再現の正本としてActor IDとRaw Valueを保持します。Database暗号化、Access Control、RetentionはApplication／運用の責務です。PHP AdapterからOutcomeだけを読む低Level ContractはPublic `OutcomeReader`です。Pending、Terminal、Expiredを区別するときは[Outcome Retrieval](outcome-retrieval.md)のStatus Queryを主経路にしてください。

作業後はRuntimeを停止します。

```bash
docker compose down
```

Getting Startedを続ける場合は[Directory Structure](directory-structure.md)でApplicationが所有する配置を確認してください。宣言的Rule、Cross-field Validation、Business Rejectionの詳細は[Validation](validation.md)を参照します。

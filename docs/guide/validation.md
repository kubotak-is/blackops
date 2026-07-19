# ValidationとRejected境界

BlackOpsはHTTP InputをProtocol、Binding、Value、Businessの順で扱います。一般的な単項Ruleは`OperationValue`へ宣言し、Cross-fieldや外部状態を使う判断は`handle()`内でFrameworkのRejection Exceptionをthrowします。内部BackendにはSymfony Validatorを使いますが、ApplicationのPublic ContractはBlackOps所有Attributeです。

## 動く完全例

次のHTTP Valueは、現行Scalar Binderで利用できる6 Attributeを使います。7つ目の`Count`は後述するHTTP Binding制約があります。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\SubmitInvoice;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;

final readonly class SubmitInvoiceValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        #[Length(min: 3, max: 80)]
        public string $customerName,
        #[Email]
        public string $email,
        #[Range(min: 1, max: 100)]
        public int $quantity,
        #[Regex('/^[A-Z0-9-]{4,20}$/')]
        public string $reference,
        #[Choice(['JPY', 'USD'])]
        public string $currency,
        #[Choice(['JP', 'US'])]
        public string $country,
    ) {}
}
```

`Range`は数値そのもの、`Length`は文字数を検証します。曖昧な`Min`／`Max`はありません。`Choice`は重複のないScalar List、`Regex`は有効なPCRE Patternを受け取ります。

正常系Outcomeは次の具象型です。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\SubmitInvoice;

use BlackOps\Core\Outcome;

final readonly class InvoiceSubmitted implements Outcome
{
    public function __construct(
        public string $reference,
        public int $quantity,
    ) {}
}
```

Operationは宣言的Ruleを通過した後にだけ呼ばれます。Cross-fieldの組合せは`validation()`、業務上受理できない状態は`businessRule()`や`conflict()`で表します。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Billing\SubmitInvoice;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/invoices/submit')]
#[OperationType('billing.invoice.submit')]
final readonly class SubmitInvoice implements Operation
{
    public function handle(SubmitInvoiceValue $value): InvoiceSubmitted
    {
        if ($value->country === 'JP' && $value->currency !== 'JPY') {
            throw OperationRejectedException::validation('invoice.currency_country_mismatch');
        }

        if ($value->quantity > 50) {
            throw OperationRejectedException::businessRule('invoice.manual_review_required');
        }

        if ($value->reference === 'DUPLICATE') {
            throw OperationRejectedException::conflict('invoice.reference_already_exists');
        }

        return new InvoiceSubmitted($value->reference, $value->quantity);
    }
}
```

実Applicationでは`reference`の重複をRepositoryへ問い合わせます。Repository InterfaceのBindingだけを[Service Provider](application-bootstrap.md#operationとservice)へ登録し、Operation自体はBuildが自動登録します。

Sourceを追加したらAutoloadとArtifactを更新します。

```bash
docker compose run --rm app composer dump-autoload
docker compose run --rm app php blackops build:compile
docker compose up -d
```

成功InputはHandlerを実行し、具象OutcomeをHTTP 200 JSONへ変換します。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"customerName":"Acme","email":"billing@example.com","quantity":2,"reference":"INV-1234","currency":"JPY","country":"JP"}' \
  http://127.0.0.1:8080/invoices/submit
```

```json
{"reference":"INV-1234","quantity":2}
```

## 7 Attributeの用途

| Attribute | 対象 | 意味 |
| --- | --- | --- |
| `NotBlank` | Scalar／String | 空文字や空相当を拒否します。 |
| `Length(min, max)` | `string` | 文字数の下限／上限を検証します。 |
| `Range(min, max)` | `int`／`float` | 数値の下限／上限を検証します。 |
| `Email` | `string` | Email形式を検証します。 |
| `Regex(pattern)` | `string` | PCRE Patternとの一致を検証します。 |
| `Count(min, max)` | `array`等のCollection | Validatorは要素数を検証します。ただし現行HTTP BinderはArray Inputを`binding.type`で拒否するため、HTTP Valueではまだ利用できません。 |
| `Choice(choices)` | Scalar | 許可Listへの厳密一致を検証します。 |

## HTTPとJournalの境界

### Protocol Errorは400

壊れたJSONは`http.malformed_json`、JSON Object以外は`http.body_not_object`です。Operationとして受理できないためOperation IDとLifecycle Journalを作りません。

```json
{"status":"error","code":"http.malformed_json"}
```

### Binding Failureは422

必須Field欠落やPHP Type不一致は、RouteからOperationを特定できた後の拒否です。Operation IDを発行し、`operation.rejected`だけをSequence 1へ記録します。`operation.received`はまだ記録しません。

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","category":"validation","code":"validation.failed","violations":[{"field":"quantity","rule":"required","code":"binding.required"}]}
```

Path、Query、Headerの値はWire上で文字列です。Value Constructorで`int`、`float`、`bool`を宣言すると、BlackOpsは次のCanonical形式だけを型変換します。Canonicalとは、同じ値を常に一つの曖昧さのない文字列で表す形式です。

| 宣言型 | 受理するPath／Query／Header | 拒否例 |
| --- | --- | --- |
| `string` | 入力文字列をそのまま保持。空文字も受理 | 文字列以外 |
| `int` | `0`、`-1`、`42`などPHP Integer範囲内の10進整数 | `+1`、`01`、`-0`、`1.0`、`1e2`、範囲外 |
| `float` | `42`、`-0`、`1.5`、`1.25e+2`などJSON Number形式の有限数 | `+1`、`01`、`.5`、`1.`、`NaN`、`Infinity`、Overflow |
| `bool` | 小文字の`true`または`false` | `TRUE`、`False`、`1`、`0`、`yes` |

前後空白はすべて拒否します。Nullableな型でも空文字や文字列`null`を`null`へ変換しません。QueryやHeaderそのものがMissingの場合だけConstructor Defaultを使います。

JSON Bodyは別の境界です。JSON Numberの`42`やBooleanの`false`はNative型として受理しますが、文字列`"42"`を`int`へ、`"false"`を`bool`へ変換しません。変換できないNon-body Scalarも同じ`binding.type`の422となり、Raw入力や変換理由はResponseとObserved Journalへ出力しません。

### 宣言的Value Validationは422

BindingでTyped Valueを作った後、Execution Strategyを選ぶ前にPropertyへ付いた利用可能なAttributeを検証します。全Violationを集約し、`field`、`rule`、安定`code`だけをResponseとRejected Journalへ残します。Raw Input、Sensitive Value、Attribute設定は出しません。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"customerName":"Acme","email":"invalid","quantity":0,"reference":"INV-1234","currency":"JPY","country":"JP"}' \
  http://127.0.0.1:8080/invoices/submit
```

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","category":"validation","code":"validation.failed","violations":[{"field":"email","rule":"email","code":"validation.email"},{"field":"quantity","rule":"range","code":"validation.range"}]}
```

Journalは`operation.received`をSequence 1、`operation.rejected`をSequence 2へ記録し、Handlerを実行しません。InlineとDeferredのどちらもHTTP受付中に422を返すため、Validation FailureがDeferred 202になることはありません。

422 ResponseのOperation IDでObserved Journalを絞り込むと、同じViolationだけを安全に確認できます。

```bash
VALIDATION_OPERATION_ID='019f32ab-2be0-7b38-a0a7-1ab2f9687698'
grep "$VALIDATION_OPERATION_ID" var/log/journal.jsonl \
  | grep -E '"event":"operation.(received|rejected)"'
```

```jsonl
{"schemaVersion":1,"kind":"journal","event":"operation.received","occurredAt":"2026-07-14T01:24:45.678901Z","sequence":1,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","type":"billing.invoice.submit","schemaVersion":1,"strategy":"inline","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","causationId":null},"attempt":null,"data":{"value":{"customerName":"Acme","email":"invalid","quantity":0,"reference":"INV-1234","currency":"JPY","country":"JP"}}}
{"schemaVersion":1,"kind":"journal","event":"operation.rejected","occurredAt":"2026-07-14T01:24:45.679012Z","sequence":2,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","type":"billing.invoice.submit","schemaVersion":1,"strategy":"inline","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","causationId":null},"attempt":null,"data":{"reason":{"category":"validation","code":"validation.failed","violations":[{"field":"email","rule":"email","code":"validation.email"},{"field":"quantity","rule":"range","code":"validation.range"}]}}}
```

### 手動Value／Business ValidationはHandler内

Cross-fieldやCustom Ruleは`OperationRejectedException::validation('stable.code')`を使います。Inline HTTPは422とCategory／Codeを返し、Journalは受理とAttemptの後にRejectedを記録します。宣言的ViolationではないためResponseへ`violations`を追加しません。

```json
{"status":"rejected","category":"validation","code":"invoice.currency_country_mismatch"}
```

外部状態や業務判断には`conflict()`、`businessRule()`等を選びます。InlineではConflictが409、Business Ruleが400です。

```json
{"status":"rejected","category":"conflict","code":"invoice.reference_already_exists"}
```

Deferredでは一般Validationを通過した時点でHTTP 202を返します。その後WorkerのHandlerが手動Value／Business Rejectionをthrowした場合、Rejected StateとJournalへ記録されます。すでに返した202を422や409へ変更しません。

## Capability Matrix

| 入力／判断 | 実行場所 | HTTP | Operation ID | Journal | Handler |
| --- | --- | --- | --- | --- | --- |
| 壊れたJSON／Object以外 | Protocol | 400 | なし | なし | 実行しない |
| 必須Field欠落／型不一致 | Binding | 422 | あり | rejectedのみ | 実行しない |
| 宣言的Attribute違反（HTTPでは6種） | Value Validation | 422 | あり | received → rejected | 実行しない |
| Cross-field／Custom | Handler | Inline 422／Deferredは受付後State | あり | Attempt後にrejected | 実行する |
| Conflict／Business Rule | Handler | Inline 409／400、Deferredは受付後State | あり | Attempt後にrejected | 実行する |

## 現在のGap

Array／Nested ObjectのHTTP Binding、宣言的DB照合、Cross-field Attribute、Custom Callback、明示的なString Parser、Enum／DateTime等の高水準変換は未実装です。`Count` Attribute自体とCollection Validationは存在しますが、現行HTTP BinderはNon-scalar Inputを`binding.type`として拒否します。HTTP RequestからArrayやObjectをPHP Valueへ自動構築できると想定しないでください。必要な判断はTyped Valueを作った後のHandler／Domainへ置き、安定したRejection Codeを返します。

全Public Attributeの付与対象は[Attributes Reference](attributes.md)、ExceptionのCategoryは[Operation Authoring](operations.md#rejection)を参照してください。

# Tenant and Storage Protection

このページは、Tenant付きOperationとFramework-owned Protected Storageを安全に導入するための実装済みJourneyです。BlackOps 1.xはExperimentalで、旧Plaintext StorageとのRuntime互換はありません。公開済みExperimental Stable `1.2.0`を固定してから手順を実行してください。

:::info[Experimental Stable 1.2.0]
以下の手順は公開済みExperimental Stable `1.2.0`のFramework／Skeleton Surfaceです。`TenantRef`、`StorageKeyProvider`／BOPD Storage Protection、`storage:protection:plan`／`storage:protection:rotate`をApplication-owned Provider境界とともに確認してください。
:::

:::warning[Breaking boundary]
保護対象Tableに旧Plaintext Rowが残るUpgradeは、安全に停止します。Frameworkは既存Dataを自動削除、Tenant推測、Plaintextからの暗黙変換をしません。Database Reset／Recreate、またはFramework外で承認済みのOffline変換を選びます。
:::

## 1. Tenantを入口で確定する

Tenantは認証Credentialや`OperationValue`から推測しません。`BlackOps\Core\TenantRef`はOpaqueな`type`／`id`だけを持つ不変Value Objectです。Tenant IDを知っていることはAuthorizationではありません。

入口ごとのProviderをApplicationで構成します。

| 入口 | TenantのSource | 伝播 |
| --- | --- | --- |
| HTTP | `AuthenticationResult`またはApplication-owned Resolverが検証した`TenantRef` | AnonymousはTenantなし。未検証Headerだけでは設定しない |
| Console | `BlackOps\Console\ConsoleTenantProvider` | Actor Providerとは別に構成 |
| Scheduled | `BlackOps\Scheduling\ScheduledTenantProvider` | Scheduleごとに明示。失敗時はTenantなしへFallbackしない |
| 直接Dispatch | `Dispatcher::dispatch(..., ?TenantRef $tenant = null)` | 呼出側が末尾引数で明示 |

`ExecutionContext::tenant()`は読み取り専用です。Child、Deferred Worker、Retry、Lease Recovery、Transactional Outboxは親Tenantを不変に継承します。Cross-tenant処理はTenant Overrideではなく、専用Authorizationを持つ別Rootとして設計します。

HTTP AuthenticatorはCredentialを検証した後だけTenantを返します。Tenant Headerをそのまま信頼しません。

```php
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Core\TenantRef;

return AuthenticationResult::authenticated(
    actor: $verifiedActor,
    tenant: new TenantRef('account', $verifiedAccountId),
);
```

Quickstartの`/invoices`をこのTenant付きHTTP Journeyで動かす場合は、既存の`examples/quickstart/app/UserInterface/Http/SampleTokenAuthenticator.php`の`authenticate()`成功分岐を、次の完全な置換へ変更します。Tokenの空／不一致分岐は既存のままです。

```php
use BlackOps\Core\ActorRef;
use BlackOps\Core\TenantRef;
use BlackOps\Http\Authentication\AuthenticationResult;

return AuthenticationResult::authenticated(
    actor: new ActorRef('quickstart-user', 'user'),
    tenant: new TenantRef('account', 'local-example'),
);
```

この変更後に[QuickstartのHTTPをTenant付きにする](configuration.md#quickstartのhttpをtenant付きにする)を確認し、`build:compile`してから、後述の`/invoices` curlでHTTP 202と`status: "accepted"`を確認します。Productionでは`local-example`をToken検証後のApplication-owned Tenant Resolverへ置き換えます。

Console／Scheduled ProviderはActor Providerと別にBindingします。

```php
use BlackOps\Console\ConsoleTenantProvider;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;
use BlackOps\Scheduling\ScheduledTenantProvider;

final readonly class ApplicationConsoleTenantProvider implements ConsoleTenantProvider
{
    public function __construct(private ApplicationTenantResolver $tenants) {}

    public function tenant(): ?TenantRef
    {
        return $this->tenants->forConsole();
    }
}

final readonly class ApplicationScheduledTenantProvider implements ScheduledTenantProvider
{
    public function __construct(private ApplicationTenantResolver $tenants) {}

    public function tenant(ScheduleContext $context): ?TenantRef
    {
        return $this->tenants->forSchedule($context);
    }
}
```

`ApplicationTenantResolver`はApplicationが所有する実装です。Consoleの認証済みOperator Context、またはScheduledの`ScheduleContext`から検証済み`TenantRef`を返し、未登録・解決失敗を別TenantやTenantなしへFallbackしません。`TenantRef`をConstructorへ直接置くとCompiled Containerが値を自動生成できないため、Resolverを介してください。Resolverと2つのProviderは、Containerで解決できるApplication ServiceだけをConstructorへ受け取る形にします。

ResolverのApplication-owned Contractは次の形です。実装はApplicationの認証済みConsole Context／Schedule Contextを読み、Contextがなければ`null`を返してください。実装Class（例:`ConfiguredApplicationTenantResolver`）をInterfaceへBindingし、ProviderのConstructorへ`TenantRef`を直接渡さないことが重要です。

```php
interface ApplicationTenantResolver
{
    public function forConsole(): ?TenantRef;

    public function forSchedule(ScheduleContext $context): ?TenantRef;
}

final readonly class ConfiguredApplicationTenantResolver implements ApplicationTenantResolver
{
    public function forConsole(): ?TenantRef
    {
        return new TenantRef('account', 'local-example');
    }

    public function forSchedule(ScheduleContext $context): ?TenantRef
    {
        return new TenantRef('account', 'local-example');
    }
}
```

`ConfiguredApplicationTenantResolver`は実行可能なLocal例です。Productionでは`local-example`を使わず、Console Operator Context／Schedule Contextから認証済みTenantを解決するApplication実装へ置き換えてください。Frameworkはその取得方法を決めませんが、Resolverを具象Classとして`ApplicationTenantResolver::class`へBindingしてからBuildする点は変わりません。

## 2. Key ProviderをApplicationへ登録する

Protected Storageを使うRuntimeでは`StorageKeyProvider`が必須です。Quickstartの登録境界はApplication Service Providerです。

```php
use App\Security\ApplicationTenantResolver;
use App\Security\ConfiguredApplicationTenantResolver;
use App\Security\SampleStorageKeyProvider;
use App\Security\ApplicationOperationDataReadAuthorizer;
use App\Security\ApplicationOperationStatusAuthorizer;
use App\Security\ApplicationConsoleTenantProvider;
use App\Security\ApplicationScheduledTenantProvider;
use BlackOps\Console\ConsoleTenantProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Scheduling\ScheduledTenantProvider;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ApplicationTenantResolver::class, ConfiguredApplicationTenantResolver::class);
        $services->autowire(StorageKeyProvider::class, SampleStorageKeyProvider::class);
        $services->autowire(OperationDataReadAuthorizer::class, ApplicationOperationDataReadAuthorizer::class);
        $services->autowire(OperationStatusAuthorizer::class, ApplicationOperationStatusAuthorizer::class);
        $services->autowire(ConsoleTenantProvider::class, ApplicationConsoleTenantProvider::class);
        $services->autowire(ScheduledTenantProvider::class, ApplicationScheduledTenantProvider::class);
    }
}
```

Raw Value、Outcome、Reason、ResponseをPolicyへ渡さず、Clear SubjectのActor／Tenant一致だけで最小のAllow／Denyを返す例です。ProductionではResource、Purpose、Role、Membership、Operation Typeの条件をApplication Policyへ追加してください。

```php
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;

final readonly class ApplicationOperationDataReadAuthorizer implements OperationDataReadAuthorizer
{
    public function decide(OperationDataReadAuthorizationRequest $request): OperationDataReadAuthorizationDecision
    {
        $currentActor = $request->currentActor();
        $originActor = $request->originActor();
        $currentTenant = $request->currentTenant();
        $originTenant = $request->originTenant();

        if ($currentActor === null || $originActor === null || $currentTenant === null || $originTenant === null) {
            return OperationDataReadAuthorizationDecision::deny();
        }

        return $currentActor->type() === $originActor->type()
            && $currentActor->id() === $originActor->id()
            && $currentTenant->type() === $originTenant->type()
            && $currentTenant->id() === $originTenant->id()
            ? OperationDataReadAuthorizationDecision::allow()
            : OperationDataReadAuthorizationDecision::deny();
    }
}
```

Status参照は同じPolicyへ混ぜず、既存の[Policyを実装してBindingする](authorization.md#policyを実装してbindingする)にある`OperationStatusAuthorizer`の完全例を使います。両方をApplication Service ProviderへBindingし、Authorizerへ復号済みDataを渡さないでください。

ProviderはSecret Manager／KMS等からKeyを解決し、次の実装済みContractだけを満たします。上のProvider名はApplication-owned Classへ置き換え、Key MaterialをExampleへ書きません。

```php
public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey;
public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey;
```

QuickstartのLocalでは、既存の`examples/quickstart/app/Security/SampleStorageKeyProvider.php`をそのままApplication-owned ProviderとしてBindingできます。Environmentからstrict base64の32-byte Secretを読み、Active／歴代Key IDを分けて解決する完全な例です。Secret値そのものは掲載しません。

```php
use BlackOps\Core\TenantRef;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;

final readonly class SampleStorageKeyProvider implements StorageKeyProvider
{
    private const string KEY_ID = 'quickstart';

    private StorageKey $key;

    public function __construct()
    {
        $encoded = $_ENV['BLACKOPS_STORAGE_KEY'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            throw new InvalidArgumentException('BLACKOPS_STORAGE_KEY must be strict base64 for exactly 32 bytes.');
        }
        $material = base64_decode($encoded, strict: true);
        if (!is_string($material) || strlen($material) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('BLACKOPS_STORAGE_KEY must be strict base64 for exactly 32 bytes.');
        }
        $this->key = new StorageKey(self::KEY_ID, $material);
    }

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key;
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        if ($keyId !== self::KEY_ID) {
            throw new InvalidArgumentException('Unknown storage key identifier.');
        }
        return $this->key;
    }
}
```

このLocal Providerの`BLACKOPS_STORAGE_KEY`取得箇所をProductionのSecret Manager／KMS adapterへ置き換えてください。Vendor固有の接続、Key生成、保存、失効はFrameworkの範囲外です。`StorageKey`のConstructorがKey IDと32-byte長を検証し、Unknown Key IDは安全な例外へ縮約します。Secret Sourceの応答、Key ID、Tenant Raw ID、MaterialをLog、Artifact、Exception、Reportへ出しません。

`activeKey()`は新規Write用、`key()`はEnvelope HeaderのKey IDを読むためだけに使います。Key MaterialはRepository、`.env` Snapshot、Compiled Container、Manifest、Exception、Log、Journal、CLI、Reportへ保存しません。Build Artifactに残るのはProvider Class Reference／Binding Metadataだけです。

### BindingをBuildし、3入口で同じTenantを実行する

上の`ApplicationServiceProvider`をApplicationのServiceとして登録します。`ServiceRegistry`で使える登録は`autowire()`と`set()`だけです。`TenantRef`やKey Materialを`set()`へ渡さず、値を解決するApplication-owned Resolver／Providerを`autowire()`してください。

```php
// config/app.php
return [
    'services' => [
        App\ApplicationServiceProvider::class,
    ],
];
```

[Application Command](configuration.md#application-command)の`services`配列へ既存登録と一緒に追加し、次の順でArtifactを作ります。Application-owned `StorageKeyProvider`と`OperationDataReadAuthorizer`／`OperationStatusAuthorizer`の実装手順も同じ[Tenant and Protected Storage](configuration.md#tenant-and-protected-storage)にあります。

```bash
php blackops build:compile
php blackops list
```

次に、Applicationが既に持つ`#[Route]`付きHTTP Operation、Console Command、Scheduled Operationを同じTenant解決設定で実行します。固定Route、Fixture、Credential、Tenant IDはFrameworkが提供しないため発明せず、各正本の例へ置き換えてください。[6. HTTPで受け付ける](first-operation.md#6-httpで受け付ける)、[3. Human／JSONで実行する](console-command.md#3-humanjsonで実行する)、[Migration、Build、初回実行](scheduled-operation.md#migrationbuild初回実行)を順番に参照します。

```bash
# First Operationの既存HTTP Routeへ検証済みCredentialを渡す（HTTP 202／`status: "accepted"`を確認）
curl -i -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"customerName":"Acme","email":"billing@example.com","quantity":2,"billingReference":"PO-2026-001"}' \
  http://127.0.0.1:8080/invoices

# Console Commandの既存Sampleを実行する（Exit 0、HumanのCompletedまたはJSONの`status: "completed"`を確認）
php blackops report:export --report-name=weekly --json

# Schedule ContextからTenantを解決する既存Scheduleを一回評価する（Exit 0、JSONの`status: "ok"`とCountを確認）
php blackops operation:schedule:run --json
```

HTTPはAuthenticatorが返したTenant、Consoleは`ApplicationConsoleTenantProvider`、Scheduledは`ApplicationScheduledTenantProvider`が返したTenantをRoot `ExecutionContext`へ設定します。HTTPの期待JSONは`{"status":"accepted","operationId":"<operation-id>","acceptedAt":"<timestamp>"}`です。ConsoleはExit `0`と`status: "completed"`、ScheduledはExit `0`の`status: "ok"`と`evaluated`／`accepted` Countを確認します。受付または評価されたOperationのContext、Journal、Status／Outcome、Deferred／Outbox／Retryへ同じ`TenantRef`が伝播することも確認します。Providerが未登録または解決に失敗した場合は安全なConfiguration／Authorization Errorで停止し、別TenantやTenantなしへFallbackしません。

## 3. Fresh Databaseを起動する

同じReleaseのArtifact、Database／Schema、Provider BindingをHTTP、Worker、Console、Scheduled、Outbox Relayへ渡します。Install後は暗黙のDDLやBuildに頼らず、次の順で実行します。

```bash
php blackops database:migrate
php blackops build:compile
php blackops database:seed
```

旧保護対象Tableが非空なら、Migrationは内容を検査せず、変更（ALTER／変換）前にSafe Migration Errorで停止します。空Tableだけが保護Schemaへ進めます。欠落Header、Malformed、Unknown Key、Tampered TagはMigrationの検出対象ではなく、Runtime Protection Failureとしてだけ扱います。どちらの境界でもPayload、Row内容、Key Materialを出力しません。

## 4. Status／Journal／Outcomeを認可後に読む

Statusの既定AuthorizerはDenyです。Status QueryはClear SubjectをTenant Predicate付きで先に読み、Allow後だけDetail／OutcomeのProtected BlobをDecodeします。UnknownとDenyは同じUnavailable／404で、Operation IDはSecretではありません。

ApplicationがStatus PolicyをBindingしたうえで、End-userのTyped Outcomeは既存Status Resource（`GET /operations/{operationId}`、Generated Clientの`.status()`／`.wait()`）から取得します。Raw Canonical JournalをPublic HTTPへ公開しません。

PHP AdapterからJournal／Outcomeを読む場合も、Raw ReaderではなくDefault-deny Queryを使います。

```php
use BlackOps\Core\Identifier\OperationId;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeQuery;

$result = $outcomes->find(
    OperationId::fromString($operationId),
    $currentActor,
    $currentTenant,
    OperationDataPurpose::fromString('support.read'),
);

if ($result instanceof OperationOutcomeFound) {
    $record = $result->record();
}
```

`OperationJournalQuery::records()`と`OperationOutcomeQuery::find()`は、Operation ID、Current Actor、Current Tenant、Application-owned `OperationDataPurpose`を受け取ります。Deny、Tenant不一致、Unknown、Retention削除は`OperationJournalUnavailable`／`OperationOutcomeUnavailable`です。Allow前にPayload／Outcome／CredentialをDecodeしません。

## 5. DatabaseにRaw Valueがないことを確認する

保護対象はCanonical Journal、Deferred Payload／Context、Outcome Payload、Outbox Payload／Context、Dead Letter Reason、Idempotency Response／Resultです。Queryに必要なTenant、Operation、State、Sequence、Timestamp、Schema Versionなどの最小MetadataだけがClear Columnに残ります。Key IDはClear ColumnではなくBOPD Envelope Headerにあります。

FrameworkのEnvelopeはBOPD v1（XChaCha20-Poly1305）です。HeaderはVersion／Algorithm／Key ID／Nonce／Ciphertext／Authentication Tagを持ち、AADはStorage Purpose、Record／Operation Identity、Schema Version、Tenantの有無・Type・IDへ結び付きます。別Row、別Field、別Purpose、別TenantのCiphertext差し替えはTag検証で拒否されます。

ApplicationのRead-only DB確認では本文を表示せず、列の存在とBOPD Prefixだけを数える全範囲の集計です。これはBounded Scanではありません。接続先をApplicationのSchemaへ置き換え、Read-only RoleのTransactionで実行し、CredentialをCommandへ直書きしません。

```sql
WITH checks AS (
    SELECT 'journal' AS table_name, 'encoded_record' AS column_name,
           count(encoded_record) AS non_null_rows,
           count(encoded_record) FILTER (WHERE substring(encoded_record FROM 1 FOR 4) = decode('424f5044', 'hex')) AS bopd_rows
    FROM blackops.journal
    UNION ALL SELECT 'operations', 'encoded_payload', count(encoded_payload), count(encoded_payload) FILTER (WHERE substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.operations
    UNION ALL SELECT 'operations', 'encoded_context', count(encoded_context), count(encoded_context) FILTER (WHERE substring(encoded_context FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.operations
    UNION ALL SELECT 'outcomes', 'encoded_payload', count(encoded_payload), count(encoded_payload) FILTER (WHERE substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.outcomes
    UNION ALL SELECT 'outbox_records', 'encoded_payload', count(encoded_payload), count(encoded_payload) FILTER (WHERE substring(encoded_payload FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.outbox_records
    UNION ALL SELECT 'outbox_records', 'encoded_context', count(encoded_context), count(encoded_context) FILTER (WHERE substring(encoded_context FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.outbox_records
    UNION ALL SELECT 'dead_letters', 'encoded_reason', count(encoded_reason), count(encoded_reason) FILTER (WHERE substring(encoded_reason FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.dead_letters
    UNION ALL SELECT 'idempotency_records', 'encoded_response', count(encoded_response), count(encoded_response) FILTER (WHERE substring(encoded_response FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.idempotency_records
    UNION ALL SELECT 'idempotency_records', 'encoded_result', count(encoded_result), count(encoded_result) FILTER (WHERE substring(encoded_result FROM 1 FOR 4) = decode('424f5044', 'hex')) FROM blackops.idempotency_records
)
SELECT table_name, column_name, non_null_rows, bopd_rows,
       (non_null_rows = bopd_rows) AS all_non_null_rows_are_bopd
FROM checks
ORDER BY table_name, column_name;
```

接続ContextはApplicationの`config/database.php`で指定したFramework Schema（既定`blackops`）とRead-only Roleです。Nullable列では`count(column)`がnon-null行だけを数えます。空Tableは`non_null_rows=0`、`bopd_rows=0`となり、確認対象のDataがないため一致と解釈します。一致条件は各行の`non_null_rows = bopd_rows`です。結果はTable、Column、件数、BOPD一致Booleanだけで、値、Outcome、Reason、Response、Nonce、Tag、Tenant Raw IDを返しません。

同じ形で、本文列を選択せずに9 Purposeの全Storageを確認します。

| Storage Purpose | Protected column（Prefixだけを数える） |
| --- | --- |
| `journal_record` | `journal.encoded_record` |
| `deferred_payload` | `operations.encoded_payload` |
| `deferred_context` | `operations.encoded_context` |
| `outcome_payload` | `outcomes.encoded_payload` |
| `outbox_payload` | `outbox_records.encoded_payload` |
| `outbox_context` | `outbox_records.encoded_context` |
| `dead_letter_reason` | `dead_letters.encoded_reason` |
| `idempotency_response` | `idempotency_records.encoded_response` |
| `idempotency_result` | `idempotency_records.encoded_result` |

この集計は`count(column)`とBOPD Prefixの`count(column) FILTER (...)`だけを返します。`convert_from`、JSON展開、復号、Payload／Outcome／Reason／Responseの選択は行いません。Malformed／Unknown／Tampered EnvelopeはMigration検出ではなく、RuntimeのProtection Failureとして安全に拒否されます。

Payload、Outcome、Reason、Response、Nonce、Tag、Tenant Raw IDを`SELECT`、Log、Support Ticket、Screenshotへ出さないでください。Disk／Volume／Managed Database EncryptionはEnvelopeの代替ではなく追加層です。

## 6. Rotationを安全に完走する

RotationはProviderのActive Write Key切替と既存EnvelopeのRe-encryptを分けます。まず旧Keyと新KeyをRead可能にし、Replica／Backup／Dead Letter／Retention Windowの確認責任を運用側に残します。旧Keyを先に削除しません。

### Plan（Read-only）

```bash
php blackops storage:protection:plan \
  --purpose=journal_record \
  --old-key-id=old:v1 \
  --new-key-id=new:v1 \
  --batch=100 \
  --checkpoint=journal-v1-to-v2 \
  --json
```

`--purpose`は9つの`StoragePurpose`のいずれか、Tenant Scopeは`--tenant-type`と`--tenant-id`を両方指定するか省略します。Key IDは異なる必要があり、`--batch`は1〜1000（既定100）、Checkpointは小文字の識別子です。PlanはHeader／Clear Metadataだけを読むRead-only集計で、Write、Decode、Audit更新を行いません。Batchを超えるRowを一度に変更しないBounded処理はConfirm時のRotationだけです。

### Rotate（Dry-run／Confirm）

`storage:protection:rotate`は既定でPlan相当のDry-runです。変更には`--confirm`、明示的な`--checkpoint`、非空の`--actor`と`--reason`が必要です。`--dry-run`を付けた場合はConfirmより優先され、bytesは変わりません。

```bash
php blackops storage:protection:rotate \
  --purpose=journal_record \
  --old-key-id=old:v1 \
  --new-key-id=new:v1 \
  --batch=100 \
  --checkpoint=journal-v1-to-v2 \
  --actor=maintenance \
  --reason='scheduled key migration' \
  --confirm --json
```

JSON／Human出力は`purpose`、Key ID、Checkpoint、`selected`、`rotated`、`skipped`、`failed`、Key別`remaining`、`state`だけを返します。`remainingBoundary`の`database-current-scope`は、Replica、Backup、Dead Letter scope、Retention windowを別途確認する必要があることを示します。

Exit Codeは成功（`0`）、入力／確認／設定Error（`2`）、Storage／Protection／Runtime Failure（`1`）です。CASで競合RowをSkipし、CheckpointとAuditを同じTransactionで進めます。CrashやSIGKILL後は同じScope／Checkpointで再実行し、`remaining`が0になるまでBounded Batchを繰り返します。Failed Auditはsafe fingerprintだけを記録し、修復後に再開します。

## 7. Retention、Replay、Outbox、Idempotency

RetentionはClear Tenant、Operation ID、State、Timestamp、HoldだけでPlan／Purgeし、CiphertextをDecodeしません。Observer ReplayはInfrastructure AuthorityとしてRowごとのTenant／Protection Contextで復号し、現在のSensitive Projection後だけをObserverへ渡します。Outbox、Dead Letter、Idempotencyも同じBOPD保護とTenant／AAD境界を使います。

## Troubleshooting

| 症状 | 安全な確認 | 次の判断 |
| --- | --- | --- |
| Provider未登録でBootstrap停止 | Service Providerの`StorageKeyProvider` Bindingだけを確認 | KeyをArtifactへ埋め込まずProviderを登録 |
| Unknown Key／Tag不一致 | Safe Protection Errorと固定Fingerprintだけを確認 | 旧KeyをRead可能にし、Envelope／AAD Scopeを修復 |
| Tenant付きStatusが404 | Current／Origin TenantとStatus Authorizer Bindingを確認 | Tenant IDをCredentialやHeaderから推測しない |
| Upgradeが旧Plaintextで停止 | MigrationのSafe Errorと空／非空判定だけを確認 | Reset／Recreateまたは承認済みOffline変換 |
| Rotation後`remaining`が0にならない | Purpose、Tenant Scope、Old Key ID、Checkpointを照合 | Replica／Backup等の別境界も確認し、旧Keyを削除しない |

どのFailureでもPayloadやKeyを公開せず、表のSafe確認で原因を切り分けてProvider／Policy／Migrationを修正し、同じScopeのPlanまたは確認済みRuntimeから再実行します。

Provider未登録、Unknown Key／Tag Tamper、非空旧Schema、`remaining`未解消の具体的な手順は、[`StorageKeyProvider`が未登録](troubleshooting.md#storagekeyproviderが未登録)、[Unknown Key／Tag Tamper](troubleshooting.md#unknown-keytag-tamper)、[非空の旧Protected SchemaでMigrationが停止](troubleshooting.md#非空の旧protected-schemaでmigrationが停止)、[Rotationの`remaining`が0にならない](troubleshooting.md#rotationのremainingが0にならない)を参照してください。その他の運用は[Security](security.md)、[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)、[Releases](mvp-status.md)を参照してください。

## 次に観測結果を安全に送る

Protected Dataを含む実行を観測する場合は、[Observability](observability.md)でProvider、Health、Collectorの境界を確認します。

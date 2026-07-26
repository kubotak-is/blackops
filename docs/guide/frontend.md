# Frontend

BlackOpsはUI、Template Engine、Next.js／Nuxt／SvelteKit Adapterを提供しません。`#[Route]`を持つHTTP OperationからJavaScript／TypeScriptのGenerated ClientとOperation Objectを作り、FrontendとHTTP Contractを接続します。

## Generated Clientの境界

Generated ClientはOperationの入力名、型、Request Binding、Typed Outcome、`.url()`、`.toRequest()`、Typed `.fetch()`を提供します。Credential、Token、実値、Secret、Global Mutable Clientは生成物へ含めません。Server-onlyの`createBlackOpsClient()`へRequestごとのSessionをBindingする設計はApplicationが所有します。

## Frontend Frameworkの選択

同一のGenerated Clientを、Applicationが選んだNext.js、Nuxt、SvelteKitなどから呼び出せます。Same-origin BFF、CSRF、CORS、Browser Storage、Token Rotation、Base URL、Source Mapの公開範囲はApplicationの責任です。BlackOpsがFramework固有のUI ComponentやData Fetching Adapterを提供するという意味ではありません。

Deferred OperationのStatusと有限`.wait()`は[Inline and Deferred](execution.md)の契約を使います。Frontend ContractのSensitive境界は[Frontend Operation Contractの境界](security.md#frontend-operation-contractの境界)で確認してください。

## Clientを生成する

HeadlessなBlackOpsはUIを提供せず、OperationのHTTP ContractからJavaScript／TypeScript Client Codeを生成します。既定のGenerated Treeは`dirname(__DIR__) . '/resources/js/blackops'`です。

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

`config/frontend.php`でApplication所有の出力先へ変更できます。

```php
return [
    'output' => dirname(__DIR__) . '/resources/js/generated/blackops',
];
```

Generated Treeを直接編集せず、Application-owned WrapperからImportします。Wrapperの呼出単位でBase URLとCredentialを注入し、`.url()`、`.toRequest()`、`.fetch()`を使います。Deferred Operationは受付後に返されたOperation IDから`.status()`、有限`.wait()`でOutcomeを取得します。

OperationやRouteを変更したら、Compile、Generate、Checkを同じ順序で再実行します。`frontend:check`がGenerated Driftを報告した場合は手編集で合わせず、Sourceを直して再生成し、差分をReviewします。Next.js、NuxtJS、SvelteKitの選択はApplication側で行い、BlackOpsは固有AdapterやUI Componentを提供しません。

## Server Requestから呼ぶ

Quickstartと同じく、Generated Rootから`createBlackOpsClient`と`operationOptions`をImportし、RequestごとにBase URL、Fetch、Credentialを注入します。ClientはServer-only Moduleに置き、Browser BundleへTokenを持ち込みません。

```ts
import { createBlackOpsClient } from './resources/js/blackops';

const blackops = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch: globalThis.fetch,
  headers: { 'X-Sample-Token': sessionToken },
});

const completed = await blackops.ShowWelcome.fetch({});
const accepted = await blackops.GenerateReport.fetch({
  reportName: 'weekly',
  recipientEmail: 'reports@example.com',
});

if (!accepted.ok || accepted.kind !== 'accepted') {
  throw new Error('Report was not accepted');
}
```

`.fetch()`はHTTP Resultを一回取得し、Deferred受付後に自動Pollingしません。Operation IDから`.status()`を一回、またはAbortSignalと正の`maxWaitMilliseconds`を指定した有限`.wait()`を呼びます。

```ts
const controller = new AbortController();
const result = await blackops.GenerateReport.wait(accepted.data.operationId, {
  signal: controller.signal,
  maxWaitMilliseconds: 15_000,
});

const current = await blackops.GenerateReport.status(accepted.data.operationId, {
  signal: controller.signal,
});
```

FactoryへBase URL、Fetch、CredentialをBindingしたClientでは、`.wait()`のOptionsへsignalとmaxWaitMillisecondsだけを渡します。直接Operation Objectを使う場合は、Call単位の`operationOptions()`でBase URLやCredentialを注入できます。`build:compile`はContract ArtifactとCompiled Containerを作成します。`frontend:generate`と`frontend:check`はCompileを暗黙実行しないため、SourceまたはOperationを変更したときは3 Commandを明示順序で実行します。`frontend:check`の終了値は一致時`0`、Generated TreeのDrift時`1`、入力／実行エラー時`2`です。SvelteKitなどのFramework Contextを使う場合は、そのContextのFetchを明示的にFactoryへ渡します。

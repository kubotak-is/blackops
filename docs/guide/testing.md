# Testing Overview

BlackOps ApplicationのTestは、Operationの業務規則、HTTP BindingとValidation、Inline／Deferredの実行境界、Databaseを含むWorker経路を分けて確認します。BlackOps専用のTesting APIやTest Runnerは提供していないため、Applicationが選んだPHP Test Frameworkと実Runtimeを組み合わせてください。

## 確認する層

| 層 | 確認すること | 既存の入口 |
| --- | --- | --- |
| Operation | 型付きValueからOutcomeまたは業務Rejectedを返す | [Operation Authoring](operations.md) |
| HTTP Boundary | Route、Binding、宣言的Value Validation、Status／JSON | [Validation](validation.md) |
| Inline／Deferred | 同じOperation ModelでResponseと受付境界が分かれる | [HTTP、Inline、Deferred](execution.md) |
| Frontend Contract | Generate／Drift、DOMなしStrict Type、`.url()`／`.toRequest()`／`.fetch()`／`.status()`／`.wait()`のRequestとResult | [Quickstart](mvp-sample.md#3-generated-operation-objectから呼ぶ) |
| Consumer E2E | Build、Migration、HTTP、Worker、Journal、Outcomeを実Processでつなぐ | [Quickstart](mvp-sample.md) |
| Full-stack Browser | Application-owned Identity、Framework Session、Ephemeral Auth、SvelteKit BFF、Inline／Deferred UI、Sensitive Boundary、Accessibility | [BlackOps Board](community-board.md) |

Unit TestだけでDeferred処理のDurabilityを保証したと判断しないでください。少なくともApplicationと同じPostgreSQL SchemaへMigrationを適用し、HTTP 202のOperation IDを使ってWorker後のJournalとOutcomeを確認します。

## Validation Failureを固定する

成功例だけでなく、壊れたJSONの400、Binding Failureの422、宣言的Value Validationの422、Handler内の業務Rejectedを別Caseとして固定します。Sensitive値を使うTestでは、Canonical StoreのAccess制御とObserved JournalのMask／Exclude／Hashを混同せず、公開するLogやFixtureへRaw Secretを残さないでください。

## Generated Frontendを実HTTPへ接続する

QuickstartはFrozen Frontend Lockfileと次の順序を正本にします。

```bash
pnpm install --frozen-lockfile
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
pnpm test
```

Consumer E2EではGenerated Welcome／Report／Order／Diagnostics ObjectをWorker Mode HTTPへ接続し、200 `completed`、202 `accepted`、422 `validation`、500 `internal`、Fetch Throwの`transport`を確認します。`.url()`、`.toRequest()`、Readonly Metadataも同じCompiled Contractと比較します。

Deferred Journeyでは、`.fetch()`が一回のPOSTだけで202を返すこと、`.status()`が`accepted`を一回取得すること、Nodeの有限`.wait()`中にShell側でWorker Retryを進めてTyped Completed Outcomeへ到達することを確認します。別Operationの短いDeadlineは`poll_timeout`になり、その後のWorker処理を壊しません。不正Credentialは401、Anonymous／Unknown／Denyは404、Non-terminalだけに`Retry-After`、全Responseに`private, no-store`があることも実HTTPで固定します。

Browser Testはnative `AbortController`、DOMなしNode Testは購読可能なStructural Signal Helperを使います。Sensitive Input、Credential、Actor ID、Worker ID、Raw Transport ErrorをGenerated Tree、Typed Result、Application／Observed Logで検索し、非露出を固定してください。

QuickstartはFrameworkの最短Contractを実HTTPへ接続します。BlackOps BoardはそのContractをApplication-owned Identity、Framework Session Core、Ephemeral Auth Operation、Domain／Infrastructure、SvelteKit Same-origin BFF、Deferred Progress UIへ広げたReference Applicationです。[BlackOps Board Guide](community-board.md)では、Clean Installと個別Consumer、実Browser E2Eの使い分けを確認できます。

再現可能なInput／Outputは[チュートリアル](first-operation.md)、失敗時の調査順は[Troubleshooting](troubleshooting.md)を参照してください。

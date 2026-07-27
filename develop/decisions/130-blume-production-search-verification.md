# D130: Blume Production Search Verification

Status: Decided

## Context

P20-009FでCloudflare Pages Production Deployは成功し、TopとInstallation PageはHTTP 200になった。一方、D129と運用手順はAstro Starlight時代の`/pagefind/pagefind.js`をSearch確認対象として残しており、Blume移行後のProductionではHTTP 404になった。

Blume設定のSearch ProviderはOramaであり、static buildは`/blume-search.json`とSearch client chunkを生成する。`/blume-search.json`はProductionでHTTP 200を返した。

## Decision

[DECISION]

1. Blume Documentation WebsiteのSearch Live Verificationは`/blume-search.json`を安定した公開Artifactとして確認する。
2. `/pagefind/pagefind.js`はBlume Production Contractに含めず、404をDeploy Failureとして扱わない。
3. Production Live VerificationはTop、Installation Page、`blume-search.json`のHTTP 200に加え、Browser上のSearch操作を確認する。
4. D129のDecision 7にあるPagefind AssetをBlume Orama Search Indexへ置き換える。
5. Historical Starlight／Pagefind Checkpointは履歴として書き換えない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Runtimeと運用手順がBlumeの実Artifactへ一致する。
- Search provider変更後も、存在しない旧AssetをLive Gateに使わない。
- Production Searchの静的Index到達性とBrowser操作を別々に検証できる。

[/CONSEQUENCES]

## References

- [D116 Blume Documentation Site](116-blume-documentation-site.md)
- [D129 Documentation Website Publication](129-documentation-website-publication.md)
- [Specification 57 Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)

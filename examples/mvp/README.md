# MVP Sample

このSampleは `GET /welcome` のInline実行と、`POST /reports` のPostgreSQL Deferred実行を定義する。

Operation／HTTP ManifestとDI Containerは次のProvider Configからcompileする。

```text
examples/mvp/operation-providers.php
examples/mvp/service-providers.php
```

完全なPostgreSQL E2EはRepository Rootで次を実行する。

```bash
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
```

実行内容とDeployment境界は `docs/guide/mvp-sample.md` を参照する。

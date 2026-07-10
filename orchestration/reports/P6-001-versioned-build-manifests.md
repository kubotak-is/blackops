# P6-001: Versioned Build Manifests Report

Status: Accepted

## Summary

- Operation ManifestとHTTP Manifestを`schemaVersion`、`applicationBuildId`、`payload`からなるPHP配列Envelopeへ変更した。
- 現行Schema Versionを`1`として定義し、LoaderでVersion、非空Build ID、既存Payload Shapeを検証するようにした。
- Unified Build Commandと個別Manifest Compile Commandへ必須の`--application-build-id`入力を追加した。
- Unified Build Commandは両Manifestへ同じBuild IDを書き込み、fingerprintがfreshでも要求Build IDまたは既存Manifest間のBuild IDが一致しなければ再生成する。
- Production Runtime Artifact Loaderは両ManifestのBuild ID不一致をContainer Artifact読込前に拒否する。
- Build手順とProduction StartupのFail Fast条件を内部向け・利用者向けDocumentationへ追加した。

## Changed Files

- `src/Internal/Registry/OperationManifestArtifact.php`
- `src/Internal/Registry/OperationManifestFile.php`
- `src/Http/Routing/HttpOperationManifestArtifact.php`
- `src/Http/Routing/HttpOperationManifestArtifactCodec.php`
- `src/Http/Routing/HttpOperationManifestPayloadCodec.php`
- `src/Http/Routing/HttpOperationManifestFile.php`
- `src/Internal/Console/BuildArtifactFingerprintInputs.php`
- `src/Internal/Console/BuildArtifactFreshnessChecker.php`
- `src/Internal/Console/CompileOperationManifestCommand.php`
- `src/Internal/Console/CompileHttpManifestCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/Runtime/ProductionRuntimeArtifactLoader.php`
- `tests/Internal/Registry/OperationManifestFileTest.php`
- `tests/Http/HttpOperationManifestFileTest.php`
- `tests/Internal/Console/CompileOperationManifestCommandTest.php`
- `tests/Internal/Console/CompileHttpManifestCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Internal/Runtime/ProductionRuntimeArtifactLoaderTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `docs/internals/bootstrap.md`
- `docs/internals/runtime-container.md`
- `docs/guide/runtime-bootstrap.md`
- `orchestration/tasks/P6-001-versioned-build-manifests.md`
- `orchestration/reports/P6-001-versioned-build-manifests.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Versioned EnvelopeのField名は既存Manifest Metadataと同じcamelCaseに合わせ、`schemaVersion`、`applicationBuildId`、`payload`とした。
- `load()`の既存Payload返却APIは維持し、`loadArtifact()`でEnvelope Metadataを含むArtifactを返す。どちらの入口も同じEnvelope検証を通る。
- Framework内の個別File WriterをBuild Command外から呼ぶ場合は、空でない一意なstandalone Build IDを生成する。Production向けCompile Commandは常に明示Build IDを要求する。
- fingerprint hashの既存File Input規則は変更せず、fresh判定時に両Manifestを検証し、要求Build IDとの一致を追加条件にした。
- Container ArtifactへのBuild ID埋め込み、Schema Migration、動的ScanへのFallbackは追加していない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationManifestFileTest|HttpOperationManifestFileTest|CompileOperationManifestCommandTest|CompileHttpManifestCommandTest|CompileBuildArtifactsCommandTest|ProductionRuntimeArtifactLoaderTest|ProductionRuntimeSmokeTest'
Result: OK (40 tests, 76 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (459 tests, 1393 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1049 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

実装途中の初回`mago lint`では、Envelope検証追加により2 Classが複雑度閾値を超えた。Artifact Codec、Payload Codec、fingerprint input/freshness判定へ責務分離した後、最終`mago lint`は成功した。

## Acceptance Criteria

- [x] Operation ManifestにSchema VersionとApplication Build IDが保存される
- [x] HTTP ManifestにSchema VersionとApplication Build IDが保存される
- [x] Manifest LoaderがVersion、Build ID、Payloadを検証する
- [x] 欠落または非対応Schema VersionのManifestが拒否される
- [x] Build Commandが明示されたApplication Build IDを両Manifestへ保存する
- [x] Production Runtime Artifact LoaderがOperation / HTTP ManifestのBuild ID不一致を拒否する
- [x] Production Runtime Smoke TestがVersioned Manifestを通して成功する
- [x] Build Artifact Documentationが新しい入力とFail Fast条件を説明する
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- Phase 6の次Task PacketとしてPublic API Architecture Guardを作成する。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更されていることを確認した。
- Versioned Envelope、既存Payload検証、Build ID不一致のFail Fast、fingerprint fresh時の再生成条件を差分Reviewした。
- Targeted PHPUnitを再実行し、`OK (40 tests, 76 assertions)`を確認した。
- Mago Lintを再実行し、`INFO No issues found.`を確認した。
- Deptracを再実行し、Violations / Warnings / Errorsがすべて`0`であることを確認した。
- 管理番号Comment検査と`git diff --check`が成功することを確認した。
- Review指摘およびBlockerはない。

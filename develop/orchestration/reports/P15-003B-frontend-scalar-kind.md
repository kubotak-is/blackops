# P15-003B Frontend Scalar Kind Report

Status: Accepted

## Summary

Frontend Contract ArtifactをSchema Version 2へ更新し、PHP Native Scalar KindをValue／Outcomeの両方で`string`／`integer`／`float`／`boolean`として保持するようにした。

PHP `int`と`float`はそれぞれ`integer`と`float`へCompileされる。CodecはVersion 2と4 Kindだけを受理し、Legacy Version 1、旧`number`、未知Kindを拒否する。Application-aware BuildとLegacy BuildはOperation／HTTP／Frontend Artifactへ同じBuild IDを書き、Freshness CheckはVersion 1 Frontend ArtifactをStaleとして扱う。

Production HTTP／Worker RuntimeへのFrontend Artifact接続は追加していない。

## Changed Files

### Production

- `src/Internal/Frontend/FrontendScalarTypeCompiler.php`
- `src/Internal/Frontend/FrontendContractManifestCodec.php`

`FrontendContractManifestFile`はCodecのSchema Version定数を参照する既存構造で同期できるため変更していない。Field ContractのProperty名`type`と既存Invariantも変更していない。

### Tests

- `tests/Internal/Frontend/FrontendContractCompilerTest.php`
- `tests/Internal/Frontend/FrontendContractManifestFileTest.php`
- `tests/Internal/Console/BuildArtifactFreshnessCheckerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`

Quickstart Architecture Testは既存の四Operation／Artifact配置回帰で要件を満たし、Production Fixtureの変更を必要としなかった。

### Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P15-003B-frontend-scalar-kind.md`

Public PHP API、TypeScript／JavaScript、Migration、Database Schema、Guide／Websiteは変更していない。

## Decisions and Assumptions

- Artifact Property名`type`を維持し、Schema Versionだけを2へ上げた。後続ConsumerはSchema Versionと4 Kind Enumを同時に検証できる。
- Scalar Kind EnumはCodec内の単一定数に集約し、Value／Outcome Decoderで同じ集合を使う。
- TypeScriptでは`integer`と`float`をともに`number`へ写像できるが、ArtifactではD101のRequest Canonical Encodeと将来のOutcome Decodeに必要なNative Kindを失わない。
- Nullable／Required／Binding／Sensitive／Validation／Outcome Mode、Field順、Operation順、Associative Key順は変更していない。
- Freshness CheckerはFrontend Artifact FileのCodec Decode Failureを既存どおりStaleへ変換するため、Production Checkerの変更は不要だった。
- Legacy拒否Testでは旧`number`を意図が明確なFixtureとして一件だけ残した。旧Kind不在Guardはこの拒否Fixtureを除外してpositive contract assertionsを検査し、Fixture側は一致件数が一件であることを別Guardで固定した。

## Schema Version / Scalar Matrix

| PHP Declaration | Artifact `type` | Codec v2 | Future TypeScript |
| --- | --- | --- | --- |
| `string` | `string` | Accepted | `string` |
| `int` | `integer` | Accepted | `number` |
| `float` | `float` | Accepted | `number` |
| `bool` | `boolean` | Accepted | `boolean` |
| Legacy numeric kind | `number` | Rejected | N/A |
| Unknown kind | `decimal` | Rejected | N/A |

Compiler TestはValueに4 Kind、Outcomeに4 Kindを含め、`int`／`float`を区別する。Codec TestはVersion 2の4 KindをValue／OutcomeでDecodeし、旧`number`と未知Kindを両Sectionで拒否する。Legacy Version 1はOperationが空でもEnvelope境界で拒否する。

## Build ID / Freshness Evidence

- Application-aware Build TestはOperation／HTTP／Frontend ArtifactをLoadし、3 ArtifactのBuild IDが`application-build-authorization`で一致することとFrontend Schema Version 2を検証した。
- Legacy Build Testは同じ3 ArtifactのBuild ID一致とFrontend Schema Version 2を検証した。
- Freshness TestはVersion 2かつ同一Build IDの3 ManifestだけをFreshとし、Frontend ArtifactだけをVersion 1へ置き換えるとStaleになることを検証した。その後、Version 2でもFrontend Build IDが異なればStaleとなる既存回帰も維持した。
- Quickstart実SourceのWelcome／Report／Order／Failure四Operation Compile、決定的順序、Inline／Deferred、Sensitive入力、Credential／Default／Example／Absolute Source Path不在は既存Frontend targetで継続して成功した。
- Runtime Import GuardはProduction HTTP／Worker Runtime CompositionからFrontend Artifact参照がないことを確認した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P15-003B required targets>
Result: OK (35 tests, 335 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1298 tests, 4810 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2257 / Warnings 0 / Errors 0。

Management Comment ID、Runtime Frontend Artifact Import、Legacy Scalar Kind対象限定、TypeScript／JavaScript追加、Migration追加、Public PHP API差分、git diff --check Guard
Result: 成功。
```

最初のDocker実行はSandbox内からDocker APIへ接続できず失敗した。承認済みのDocker Compose実行へ切り替え、同じTargetを最終Codeで成功させた。Mago Format CheckはTest追加直後に一件を検出し、Repository Formatter適用後に再実行して成功した。

## Acceptance Criteria

- [x] Frontend Artifact Schema Versionが2になる
- [x] PHP `int`を`integer`、`float`を`float`としてValue／Outcomeで区別する
- [x] `string`／`boolean`、Nullable／Required、Binding／Sensitive／Validationを回帰させない
- [x] CodecがVersion 1、旧`number`、未知Kindを拒否する
- [x] Application／Legacy BuildがOperation／HTTP／Frontendへ同じBuild IDを書く
- [x] FreshnessがVersion 1 Frontend ArtifactをStaleとして扱う
- [x] Quickstart四HTTP Operationを決定的にCompileできる
- [x] Credential、Default、Example、Absolute Source PathをArtifactへ含めない
- [x] HTTP／Worker RuntimeはFrontend Artifactを読み込まない
- [x] TypeScript／JavaScript、Public PHP API、Migration、Database Schemaを追加しない
- [x] Required PHP Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

P15-003Bを妨げるBlockerはない。

TypeScript Source生成、`.url()`、`.toRequest()`、`.fetch()`、Frontend Output Config、CLI、Atomic Treeは後続TaskのScopeである。Documentation WebsiteはUser判断どおり未公開であり、Publication／Deployを実行していない。

## Suggested Next Action

OrchestratorがSchema Version 2、Value／Outcome Scalar Kind、Legacy拒否、Build ID／Freshness、Quickstart機密境界、Runtime非接続を独立Reviewする。Accepted後、P15-003 Operation Object and Request Generationへ進む。

## Orchestrator Review

Schema Version 2、Value／Outcomeの4 Native Scalar Kind、Legacy Version 1／旧`number`／未知Kind拒否、Application／Legacy Buildの三Manifest Build ID一致、Legacy Frontend ArtifactのStale化、Quickstart機密境界、Production Runtime非接続を独立Reviewし、Acceptance Criteriaを満たすと判断した。

Task Packet記載のTargetを再実行し、OK（35 tests、335 assertions）を確認した。Full PHPUnitはOK（1298 tests、4810 assertions）、Composer Root／Quickstart、Mago format／lint／analyze、Deptracも成功し、DeptracはViolations 0／Warnings 0／Errors 0だった。Management Comment ID、Runtime Frontend Import、TypeScript／JavaScript、Migration、`git diff --check`のGuardも成功した。

Legacy `number`はCodec拒否を明示する一件のFixtureだけに残し、通常Contract Assertionから消えていることを確認した。Runtime LoaderとPublic PHP APIへの変更はない。

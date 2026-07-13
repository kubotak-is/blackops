# Phase 8 Delivery Plan

## Goal

`examples/quickstart/` を `blackops/skeleton` Composer Project Packageとして決定的に生成・検証し、最終的に次の公式CommandでApplicationを作成できる配布境界を完成させる。

```bash
composer create-project blackops/skeleton my-app
```

Phase 8はMain Repository内のSource、Local split artifact、Distribution Repository、Packagistの順に境界を広げる。外部Push／公開はRepositoryとCredentialを明示したTaskだけで行う。

## P8-001: Post-create Initialization

- Skeleton所有の再実行可能なSetup Entrypoint
- `.env` がない場合だけ `.env.example` をCopy
- `var/build`／`var/log` のLocal Directory準備
- 次に実行するDocker／Build／Migration Command表示
- Composer `post-create-project-cmd` 接続
- 既存 `.env` 非上書き
- Network／Docker／Database／Migration／BuildのSide Effect不在
- `--no-scripts` 時の手動Setup導線

Setup Entrypointは配布後も利用者が明示再実行できるProject Toolとし、Composer Scriptから同じFileを呼ぶ。Root Repositoryの汎用 `scripts/` Directoryは再導入しない。

## P8-002: Local Split and Create-project Smoke

- `examples/quickstart/` だけをPackage Rootへ決定的に抽出
- Split結果のComposer Metadata／Source Allowlist検証
- Framework／Skeleton VersionとConstraintの検証
- Split結果にLock、Vendor、Path Repository、Generated Stateがないことを検証
- Local Composer Repositoryから通常 `create-project` を実行
- `--no-scripts` create-projectとManual Setupを実行
- Post-create再実行と既存 `.env` 非上書きを検証
- Install中にDocker／Database／Migration／Buildを実行しないことを検証

Local Smokeは外部PackagistやDistribution RepositoryへPushせず、一時DirectoryとLocal Package Artifactだけを使用する。

## P8-003: Distribution Publication

- Read-only Skeleton Distribution Repositoryへの自動Split
- Framework Release Tagと同一Tagの生成
- Tag／Framework Constraint整合性Guard
- Distribution Root validationとInstall Smoke
- Packagist update境界
- Release Secret／Credential境界

実Repository、Default Branch、Push先、Packagist連携方法が必要になった時点で、外部状態を確認してTask Packetを確定する。CredentialをRepositoryへ保存しない。

## P8-004: Phase 8 Closeout

- Remote `composer create-project blackops/skeleton my-app` 証拠
- 通常／`--no-scripts` Install後Smoke
- Guide／README／Release手順
- Full Quality Suite
- Phase 9 Handoff

## Dependency Order

```text
P8-001 Post-create Initialization
  -> P8-002 Local Split and Create-project Smoke
    -> P8-003 Distribution Publication
      -> P8-004 Phase 8 Closeout
```

## Phase Acceptance Criteria

- [x] SkeletonのPost-createが安全かつ再実行可能である
- [x] `--no-scripts` でも同じSource Treeと手動Setup導線が成立する
- [x] Split結果のRootが正しい `blackops/skeleton` Packageである
- [x] Framework／Skeleton Version Policyが機械検証される
- [x] Split／DistributionへLock、Vendor、Path Repository、Generated Stateが混入しない
- [x] 通常の `composer create-project` が成功する
- [x] `--no-scripts` create-projectとInstall後Smokeが成功する
- [x] FrameworkとSkeletonへ同一Release Tagを付けるPublication Boundaryが成立する
- [x] Remote Packagist Packageから公式Commandが成功する
- [x] Full Quality SuiteとConsumer／Install Smokeが成功する

## Traceability

- Decision: [D065 Composer Skeleton Publication](../decisions/065-composer-skeleton-publication.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Publication Contract: [Composer Skeleton Publication](46-composer-skeleton-publication.md)
- Installed Source: [Feature-first Quickstart Application](49-feature-first-quickstart-application.md)
- Phase 7 Status: [Installed Application Status](../../docs/internal/installed-application-status.md)

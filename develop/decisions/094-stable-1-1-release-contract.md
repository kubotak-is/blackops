# D094: Stable 1.1 Release Contract

Status: Decided

## Context

Phase 11では、Stable `1.0.0`以後に`main`へ追加したProject Root CLI、Generator、Application Migration、Typed Self-handled Operation、OperationValue Validation、FrankenPHP Worker Mode、Documentation改善をFramework／Skeleton `1.1.0`として公開する。

2026-07-16時点の公開状態は次のとおりである。

- Framework Repositoryの最新Stable Tagはannotated tag `1.0.0`である。
- Packagist `blackops/framework`と`blackops/skeleton`は`1.0.0`と`dev-main`を公開している。
- Skeleton Distribution Repositoryの`main`と`1.0.0`は同じSplit Commitを指す。
- Main RepositoryのActions Secretには`SKELETON_DEPLOY_KEY`が登録済みである。
- GitHub Releaseはまだ作成されておらず、`CHANGELOG.md`とUpgrade Guideもない。
- `1.0.0`から現在の`main`までは53 Commitである。

既存Publication Contractは、FrameworkとSkeletonへ同じbare SemVer名を付け、SkeletonのFramework ConstraintをRelease Major／Minorへ合わせる。したがって`1.1.0`ではSkeletonの`blackops/framework` Constraintを`^1.1`へ更新する必要がある。

Preliminary Compatibility Auditでは、`#[PublicApi]`型の削除は見つかっていない。`RejectionReason::validation()`はOptional Parameter追加、Validation関連は新規APIであり、旧`blackops:*` Command名はAliasとして残っている。ただし正式なRelease GateではPublic API Signature、Stable `1.0.0`からのFramework Update、旧`bin/blackops` Entrypoint、Database Metadata、Environment／Configurationの互換性を機械検証する。

## Phase 11 Delivery Outline

### P11-001: Release Surface Reset

- `1.0.0`とRelease Candidateの`#[PublicApi]` Signature比較
- 旧`blackops:*` BlackOps CLI Aliasと予約の削除
- Project Root `blackops`とPrefixなしCanonical Commandの検証
- Database Migration Metadata、Configuration、Environment、HTTP Responseの互換性確認
- Breaking／Additive Surfaceの分類とUpgrade入力の作成

### P11-002: Release Documentation and Metadata

- Skeleton Constraintを`^1.1`へ更新
- Stable `1.0.0`から`1.1.0`へのUpgrade Guide
- User-facing Changelog／Release Note
- README、Installation、Quickstart、Current Statusから`main`／Stable差分を解消
- Framework／Skeleton Package MetadataとDocumentation Version表示を同期

### P11-003: Release Candidate Gate

- Composer、Mago、PHPUnit、Deptrac
- 全Consumer E2E、Worker Mode、Generator Update、Skeleton Publication Dry Run
- `1.1.0` Split Artifactから通常／`--no-scripts` Create-project Smoke
- Release Candidate CommitのGitHub Actions CI確認
- Release Commit SHA、Gate結果、既知制約をReportへ固定

### P11-004: Stable Publication

- Release Candidate CI成功後にannotated tag `1.1.0`をRelease Candidate Commitへ作成
- Framework TagをPushし、Skeleton Publication Workflowを監視
- Framework／SkeletonのTag、Split Commit、Packagist Metadataを検証
- 公開済みPackageからRemote Create-projectとDocumented Quickstartを実行
- Publication成功後にGitHub Releaseへ確定Release Noteを掲載

Tag Push、Skeleton Distribution更新、Packagist反映、GitHub Release作成は外部の不可逆なPublicationである。Question 5の回答Bにより、Release Candidate CI成功を条件とした一連のPublicationを本Decisionで明示承認する。

## Question 1: Compatibility Window

Stable `1.0.0`のApplication EntrypointとCommand名をいつまで維持するか。

### Options

- A: `bin/blackops` Bootstrap Compatibilityと旧`blackops:*` Aliasを1.x全体で維持し、2.0候補でのみ削除を検討する
- B: `bin/blackops`は1.xで維持するが、旧Command Aliasは1.1で非推奨化し、1.2以降で削除できるようにする
- C: `1.1.0`でProject Root `blackops`への移行を必須にし、旧Entrypoint／Aliasを保証しない

### Recommendation

Aを推奨する。

Project EntrypointはApplication所有FileなのでFramework Updateでは自動移動しない。1.x Minor Releaseで旧Entrypointまたは旧Commandを壊すと既存Applicationが更新できない。新SkeletonはProject Root `blackops`を採用し、既存Applicationには任意のMigration手順を示せばよい。

[ANSWER]

C
既存ユーザーは存在しないので問題なし

[/ANSWER]

## Question 2: Skeleton Framework Constraint

Skeleton `1.1.0`が要求するFramework Versionをどうするか。

### Options

- A: 現行Publication Contractどおり`^1.1`へ更新する
- B: `^1.0`を維持し、Skeleton `1.1.0`からFramework `1.0.x`も許容する
- C: `1.1.0`へ完全固定する

### Recommendation

Aを推奨する。

Skeleton `1.1.0`はProject Root CLI、Generator、Validation、Worker Mode等のFramework `1.1`機能を前提にする。`^1.0`ではComposerが古いFrameworkを選べるためSkeletonとの不整合が生じ、完全固定ではPatch Updateを受け取れない。

[ANSWER]

A

[/ANSWER]

## Question 3: Release Documentation

Stable Releaseの変更履歴とUpgrade情報をどこへ残すか。

### Options

- A: Repository Rootの`CHANGELOG.md`と`UPGRADE.md`を正本にし、Publication成功後に同じ内容を要約したGitHub Releaseを作成する
- B: GitHub Releaseだけを作成し、Repository内にはUpgrade Guideだけを置く
- C: TagとPackagistだけを公開し、既存DocumentationのCurrent Statusだけを更新する

### Recommendation

Aを推奨する。

Package Sourceだけを取得した利用者も変更点とMigrationを確認でき、GitHub上ではRelease単位に到達できる。Documentation Websiteを公開していない現在でも、RepositoryとPackageの中で情報が完結する。

[ANSWER]

A

[/ANSWER]

## Question 4: Release Freeze

`1.1.0` Release Candidateへ含める変更範囲をどうするか。

### Options

- A: Featureを現在の`main`でFreezeし、Compatibility、Test、Documentation、Release Automationの修正だけを許可する
- B: Phase 11中に見つかった小規模Featureも`1.1.0`へ追加できる
- C: Phase 12 Middlewareの一部まで先に含める

### Recommendation

Aを推奨する。

現在の目的は53 Commit分の未Release機能をStableへ固定することである。Feature追加を続けるとCompatibility AuditとRelease Noteの対象が動き、Phase 12との境界も曖昧になる。

[ANSWER]

A

[/ANSWER]

## Question 5: Publication Approval

Release Candidateから外部Publicationへ進む承認境界をどうするか。

### Options

- A: Release Candidate Commitと全Gateを確定後に停止し、Userの最終承認を得てからannotated tag `1.1.0`をPushする
- B: Release CandidateのCI成功を条件に、追加確認なしでOrchestratorがTagをPushする
- C: CodexはRelease Candidateまでを作り、Tag／PublicationはUserが手動で行う

### Recommendation

Aを推奨する。

準備と検証は自動化しつつ、移動できないStable TagとPackagist公開の直前だけを明確な承認点にできる。承認後はCodexがWorkflow監視、Packagist確認、Remote Smokeまで一気に実行する。

[ANSWER]

B

[/ANSWER]

## Question 6: Breaking Change Version

Question 1のCは、公開済み`1.0.0`が保証していたApplication EntrypointとCommand名の互換性を`1.1.0`で保証しない選択である。D078は`1.0.0`以後の互換性管理をSemantic Versioningに従うと決定しているため、Release Versionとの整合をどうするか。

### Options

- A: 互換Layerを削除し、Phase 11のRelease Versionを`2.0.0`へ変更する
- B: Release Version `1.1.0`を維持し、Question 1だけAへ変更して1.x互換を維持する
- C: Release Version `1.1.0`のまま互換Layerを削除し、既存利用者がいないことを理由にSemantic Versioningの例外を明示する

### Recommendation

Aを推奨する。

Question 1で選択した「旧契約を保証しない」をそのまま実現しながら、Packagist利用者がVersion番号から互換性を判断できる。Release番号を`1.1.0`に保つことを優先する場合はBが適切である。Cは公開PackageのVersion Contractを曖昧にするため推奨しない。

[ANSWER]

C
そんなに簡単にメジャー上げないで、このPJは実験でユーザーがいることを想定していません。
フルスタックで用意できたら公開するのでそれまではアグレッシブにやります

[/ANSWER]

## Decision

[DECISION]

1. Phase 11のFramework／Skeleton Release Versionは`1.1.0`とする。
2. BlackOpsはFull-stack機能が揃いPublic Readinessを別Decisionで宣言するまで実験Projectとして扱い、Minor Releaseでも破壊的変更を許容する。
3. `1.1.0`では旧`bin/blackops` Entrypointを互換対象とせず、旧`blackops:*` BlackOps CLI Aliasを削除する。新SkeletonはProject Root `blackops`だけを公式Entrypointとする。
4. Skeleton `1.1.0`の`blackops/framework` Constraintは`^1.1`とする。
5. Repository Rootの`CHANGELOG.md`と`UPGRADE.md`をRelease情報の正本とし、Publication成功後に同内容を要約したGitHub Releaseを作成する。
6. `1.1.0`へ新Featureを追加せず、Compatibility Audit、互換Layer整理、Test、Documentation、Metadata、Release Automationだけを変更する。
7. Release Candidate Commitの全Local GateとGitHub Actions CIが成功したら、追加User確認なしでannotated tag `1.1.0`をPushし、Skeleton、Packagist、GitHub Release、Remote Smokeまで継続する。
8. 実験期間中も公開済みTagは移動または削除せず、Framework／Skeletonの同一Version、Publication Source、Credential境界は既存Contractを維持する。
9. D078の「`1.0.0`以後の互換性管理をSemantic Versioningに従う」というConsequencesは、Public Readiness宣言まで本Decisionが置き換える。Versionは変更量の目安として使用するが、1.x Minor間のBackward Compatibilityを保証しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Packagist上ではStable SemVerとして解決されるが、README、CHANGELOG、UPGRADE、GitHub ReleaseでExperimentalかつBackward Compatibility未保証であることを明示する。
- `1.0.0` Applicationは`1.1.0`へ無変更でUpdateできるとは限らず、Project Root EntrypointとCanonical CommandへのMigrationが必要になる。
- 旧Command Aliasの削除後はApplicationが同名Commandを定義でき、Frameworkは予約しない。
- Full-stack Public Readinessを宣言するDecisionでは、互換性開始点、Versioning Policy、Deprecation Windowを改めて固定する。
- Q5の回答Bにより、Release Candidate CI成功後のannotated tag Pushと外部Publicationは本Decisionで明示承認済みである。

[/CONSEQUENCES]

## References

- [D076 Framework and Skeleton Repository Naming](076-framework-and-skeleton-repository-naming.md)
- [D078 Initial Stable Release Version](078-initial-stable-release-version.md)
- [D079 Immutable Release Publication Recovery](079-immutable-release-publication-recovery.md)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [Composer Skeleton Publication](../spec/46-composer-skeleton-publication.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)

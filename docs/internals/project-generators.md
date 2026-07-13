# Project Generator Internals

Project Generatorは、Project所有の薄い`bin/blackops`からFramework Package内のCommandとStubを遅延利用する。Application BootstrapやSkeletonはCommand実装とStubを複製しない。

## Operation Generator Flow

`ApplicationConsoleKernel`は`make:operation`の名前、Description、Argument、Optionだけを常時登録する。実行時に`ApplicationConsoleCommandFactory`がApplication Base PathとFrameworkの`resources/stubs/`を使って`MakeOperationCommand`を構成する。このため`list`と`help`はSource Discovery、Build、Database接続を行わない。

処理順序は次のとおりである。

1. `OperationGeneratorInput`が`<Feature>/<Action>`の2 SegmentとOperation Typeを検証する
2. `OperationGenerator`がFramework Package内の3 Stubを読み、Namespace、Class、Typeを展開する
3. `ProjectFileWriter`が全Targetの相対Path、既存ancestor、衝突をWrite前に検証する
4. 同じTarget Directory内のTemporary Fileへ3 Fileを完全にWriteする
5. Hard Linkによる非上書きPublish後、Temporary Fileを除去する
6. 失敗時は今回のTemporary File、公開済みTarget、新規Directoryだけを逆順に除去する

## Path and File Boundary

Project Relative PathはApplication Base Pathから組み立て、Absolute Path、Backslash、空Segment、`.`、`..`、制御文字を拒否する。既存ancestorは`realpath()`で解決し、Application Root外を指すsymlinkがあればWrite前に拒否する。Directory作成後も解決先を再検証する。

全Targetを事前検査するため、既存File衝突ではDirectoryもTemporary Fileも作らない。Publishは既存Targetを置換しない。Rollbackは事前に存在したFile、Directory、symlinkを削除しない。

Preflight後に別ProcessがTargetを作った場合も、Publishはその内容を上書きまたは削除せず失敗する。先に公開した今回のTargetとTemporary FileはRollbackする。Transaction内のFilesystem warningは外へ出さず、Project Relative PathまたはgenericなFramework Errorへ正規化する。

Stub読込ErrorはFramework内のAbsolute Stub Pathを公開しない。存在確認後にStubが消えた場合のPHP warningも捕捉し、同じgeneric Errorへ正規化する。成功出力と衝突ErrorはProject Relative Pathを使用する。

## Generated Contract

Operation Stubは`#[OperationType]`を持つTyped Self-handled Operationを生成する。Valueは`OperationValue`、Outcomeは`Outcome`を実装する空の`readonly` Classである。

Stubへ次を追加しない。

- `#[Accepts]`と`#[Returns]`
- `OperationHandler` Generic DocBlock
- `OperationResult`
- Value Narrowing Guard
- Route、Execution Strategy、`ExecutionContext`

生成結果はApplicationのOperation Discoveryによって次回Build時に検出される。Generator自身はDiscoveryまたはBuildを実行しない。

## Migration Generator Flow

`make:migration`もLazy Descriptorとして名前、Description、必須Argumentだけを常時登録する。実行時に次の順序で一つのFileを生成する。

1. `MigrationGeneratorInput`がPascalCaseのPHP Identifierを検証する
2. PSR Clockの時刻をUTCへ変換し、`VersionYYYYMMDDHHMMSS`を決定する
3. Framework Package内のMigration StubへVersionとDescriptionを展開する
4. `ProjectFileWriter`が`migrations/<Version>.php`の衝突とPathを検査する
5. Temporary Fileを完全にWriteし、非上書きでPublishする

`migrations/`はWriterが必要時だけ作成する。入力、Stub読込、Write、Publishに失敗した場合、今回作成したFileと空DirectoryだけをRollbackする。同一秒の再実行は既存Versionを変更しない。

生成Classは`App\Migrations`のDoctrine `AbstractMigration` subclassで、constructorを宣言しない。このためApplication Migration RuntimeはDBAL ConnectionとLoggerだけを渡すDoctrine標準Constructorで生成できる。GeneratorはDB Connection、Migration Runner、Build、Composer、Source Discoveryを構成しない。

## Framework Update Verification

Consumer SmokeはRepository History上の旧Commitを固定せず、Repository外の一時Directoryへ同じFramework Sourceから2つのLocal Composer Versionを作る。旧版相当はFramework所有Stubへ有効な識別Marker、Operation／Migration Command出力へ`Legacy Created:` Prefixを持つ。Current版はRepositoryのStubとCommand Sourceをそのまま持つ。

検証順序は次のとおりである。

1. 旧版相当FrameworkをConsumerへInstallし、Operation／Migrationを生成する
2. Project `bin/blackops`と生成済みSourceのSHA-256を保存する
3. Composer Lock内のFramework以外のPackage Version集合を保存する
4. `blackops/framework`だけをCurrent版へUpdateする
5. Entrypointと生成済みSourceのhash、および他Dependency集合が不変であることを確認する
6. Vendor内Stubと2 Command SourceがCurrent Framework Sourceとbyte一致することを確認する
7. 新規Operation／Migrationを生成し、出力がCurrent `Created:` Prefixで旧Marker不在であることを確認する
8. 新規生成SourceのCurrent ContractとApplication Build成功を確認する

一時Package、Consumer、Composer Homeは成功／失敗のどちらでもCleanupし、Main Working Tree、Remote Repository、Credentialを変更しない。

# D083: Project Root BlackOps Entrypoint

Status: Decided

## Context

Installed ApplicationはProject所有の薄いConsole Entrypointを`bin/blackops`へ配置してきた。Command実装はFramework Packageが所有するため、Entrypointの配置はFramework Update追従性へ影響しない。

Documentation Websiteの利用者Reviewでは、Framework固有Commandを毎回`php bin/blackops`で起動するより、Laravelの`php artisan`と同じProject Rootの短い入口として`php blackops`を使う方が発見しやすく、記憶しやすいという指摘があった。

## Decision

[DECISION]

1. 次回Skeleton ReleaseからProject所有Console EntrypointをProject Rootの`blackops`へ配置する。
2. 公式Command表記は`php blackops <command>`へ統一する。
3. `blackops`はComposer Autoloaderと`bootstrap/app.php`を読み、Framework Console Kernelの終了Codeを返すだけの薄いEntrypointとする。
4. Framework Command、Generator Stub、Command RegistrationはFramework Packageが所有し、Project Entrypointへ複製しない。
5. `bin/blackops` Aliasは新しいSkeletonへ残さない。入口を一つにしてDocumentationと実体の不一致を避ける。
6. Stable `1.0.0`で作成済みのApplicationは`bin/blackops`を継続利用できる。Framework PackageはEntrypoint Pathへ依存しないため、既存ProjectのFramework Updateを壊さない。
7. `main`のDocumentationは`php blackops`を標準とし、Current Status／Upgrade NoteでStable `1.0.0`との差を明示する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 新規ApplicationのCommandが短くなり、Project固有CLIであることを理解しやすくなる。
- Skeleton、Compose Process、Setup、Consumer Test、Publication Guard、Guideを同じ変更単位で更新する必要がある。
- Stable `1.0.0`の配布物はImmutableであり変更しない。既存利用者は必要に応じてEntrypointをProject Rootへ移動できる。
- Framework Update後もProject所有Entrypointを変更せず、新しいCommandとStubを利用するContractは維持する。

[/CONSEQUENCES]

## Supersedes

Project Entrypointの配置に限り、D063、D064、D068、D080の`bin/blackops`表記を置き換える。Entrypointの責務とFramework所有境界は維持する。


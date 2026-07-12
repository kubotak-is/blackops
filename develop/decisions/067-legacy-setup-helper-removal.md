# D067: Legacy Setup Helper Removal

Status: Decided

## Context

D066ではDocumentationから参照されていることを理由に、`docker/db-smoke-test.php` と `scripts/install-docker-ubuntu.sh` を使用中と判断して残した。

再監査した結果、どちらもCompose、CI、Composer Script、PHPUnit、Runtimeから呼ばれていなかった。参照はDevelopment Setupの手動Commandと過去Task／Report／STATEの実行記録だけだった。

PostgreSQL接続はCompose Health CheckとDatabase Integration Testで継続的に検証される。Docker Engine導入はRepository固有処理ではなく、開発環境の前提としてDocumentationから公式手順を示せる。

## Decision

[DECISION]

1. `docker/db-smoke-test.php` を削除する。
2. Fileがなくなるため、空の `docker/` Directoryも削除する。
3. `scripts/install-docker-ubuntu.sh` を削除する。
4. Fileがなくなるため、空の `scripts/` Directoryも削除する。
5. Development Setupから両Helperの実行導線を削除する。
6. Docker導入要件と公式APT Packageの説明はDocumentationに残す。
7. PostgreSQL接続確認はCompose Health CheckとDatabase Integration Testを正規の検証経路とする。
8. Historical Task、Report、STATEにある過去の実行記録は変更しない。
9. D066のDecision項目10と11は本Decisionで更新する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Repository Rootから現在のBuild／Testに参加しないHelper Directoryがなくなる。
- Docker Engine導入はRepository Scriptではなく、開発者が管理するEnvironment Prerequisiteになる。
- Database接続だけを確認する専用PHP Scriptはなくなり、Health CheckとIntegration Testへ検証経路を集約する。
- 過去の実行証拠は履歴として参照可能なまま維持する。

[/CONSEQUENCES]

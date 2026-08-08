# P20-016G Storage Key Rotation Report

## Summary

Boundedで監査可能なProtected Storage Key Rotationを実装し、read-only plan、既定dry-run、明示confirm、scope-bound checkpoint、CAS、crash resume、safe auditを全9 protected purposeへ適用した。Orchestratorの独立ReviewとConsumer journeyを完了し、P20-016GをAcceptedとした。

## Changed Files

- BOPD header reader、rotation scope／validation／result、plan／rotate CLI、application composition
- PostgreSQL rotation query／engine／row transaction／CAS／audit／checkpoint persistence
- Rotation audit／checkpoint migrationとFramework migration／package inventory
- Focused unit／integration／all-purpose／two-process／crash-resume testsとConsumer fixture
- Task Packet、Report、STATE、TODO

## Decisions and Assumptions

- PlanはEnvelope headerとclear metadataだけをbounded scanし、decryptもwriteも行わない。
- Confirmed rotateはpositive batch、checkpoint、actor、reasonを必須とし、tenant scopeを省略した場合もscope hashへ明示的に束縛する。
- Row transaction内でcurrent envelope bytes、key ID、record identity、tenant identityをCASし、CAS成功時だけcheckpointとaudit countを同じtransactionで進める。
- Already-new-keyとconcurrent replacementは上書きせずskipする。失敗時はsafe fingerprintだけを記録し、failed cursorから再開できる。
- Audit／CLIへtenant raw ID、record ID一覧、payload、ciphertext、nonce、tag、key materialを出力しない。
- Old key削除、replica／backup／retention上の消去保証、KMS vendor操作はFramework CLIの責務外とする。

## Commands and Results

- Focused storage／scope／console／migration／kernel／PostgreSQL suites: `51 tests / 495 assertions` PASS。
- Full PHPUnit: `2,104 tests / 8,643 assertions` PASS、既存Deprecation 1。初回は無関係なOutbox heartbeat timing testが1件失敗したが、同一Test単独は`1 test / 4 assertions` PASS、assertionを変更せずFull Suite再実行でPASS。
- `bash tests/Consumer/storage-protection-rotation.sh`: PASS。fresh temporary quickstartでplan byte invariance、deterministic checkpoint、two-process、SIGKILL後resume、audit reconciliation、redactionを確認。
- 全9 Purpose matrix: journal、deferred payload／context、outcome、outbox payload／context、dead-letter reason、idempotency response／resultについてold→new、AAD decrypt、remaining count、resume byte invarianceを確認。
- Real PostgreSQL concurrency: competing replacementをCAS skipし、同じRowを二重更新しないことを確認。
- Crash recovery: committed rowを再暗号化せず、未処理rowだけを同じcheckpointから完走し、interrupted auditのsafe failure stateを確認。
- Wrong／Unavailable key、tampered envelope: raw dataを露出せずsafe failure fingerprintを記録し、修復後resumeを確認。
- `docker compose run --rm app composer validate --strict`: PASS。
- `docker compose run --rm app mago format --check src tests`: PASS。
- P20-016G Production changed-source `mago lint`／`mago analyze`: No issues。
- Broad `mago lint`: 既知baselineの81 findings／9 errors。P20-016G Productionへの新規findingなし。
- Broad `mago analyze`: 既知baselineの25 warnings／0 errors。
- `docker compose run --rm app vendor/bin/deptrac`: vendor `NikicFileReferenceVisitor.php:106`の既知PHP 8.5 parser incompatibilityでparse停止。
- 管理番号Comment Guard、Consumer shell syntax、`git diff --check`: PASS。
- Pre-commit exact `bash tests/Consumer/framework-package-export.sh`: 未追跡migrationを`git archive HEAD`が含めないため、期待どおりVersion20260808100000 missingで停止。
- Working Tree Composer Archive contract: migration inventory、許可Root、除外Path、strict validation、production autoloadをPASS。
- Commit `d869606`後のexact `bash tests/Consumer/framework-package-export.sh`: Git／Composer両Archive、Version20260808100000、許可Root、除外Path、strict validation、production autoloadをPASS。

## Acceptance Criteria

- PlanはPurpose／Tenant／old-key scopeごとのsafe countをread-onlyかつboundedに返す。
- Rotateは既定dry-runでbytesを変更せず、明示confirm／actor／reason／checkpoint時だけ更新する。
- Human／JSON outputとexit 0／1／2を固定し、safe fieldだけを返す。
- CAS、two-process、crash／resume、checkpoint ownership、durable audit progressを実Databaseで確認した。
- 全9 protected purposeを対象にし、writerと同じrecord identity／AADで再暗号化する。
- Existing CLI list／help／collision／lazy runtime compositionを維持する。
- Full Suite、Consumer、required quality commandsを実行し、新規Task scopeのfailureはない。
- Report／STATE／TODOを同期し、Workerはcommit、push、deployしていない。

## Remaining Issues

- Broad Mago baselineとDeptrac PHP 8.5 vendor parser blockerは既存Issueであり、このTaskでは範囲外。
- Replica、backup、retention上の旧key残存確認とold key削除はApplication運用者の別境界。

## Suggested Next Action

P20-016HのTenant／Storage Protection Documentationへ進む。External push／deployは行わない。

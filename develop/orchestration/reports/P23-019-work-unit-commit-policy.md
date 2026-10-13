# P23-019: Work-unit Commit Policy Report

Status: Accepted — persistent policy verified; separated delivery recorded in command receipts
Updated At: 2026-10-13T22:03:02+09:00

## Summary

Ownerの継続指示をAGENTSとspec109へ反映した。まとまった成果の検証・Orchestrator Reviewが完了したら、その時点でコミットする。
親Task/Release完了待ち、別worktreeのcommitと元worktreeの未整理差分の混同を防ぐ。

## Changed Files

AGENTS.md、develop/spec/109-ai-development-workflow.md、本Task/Report。STATEはOrchestratorが正規toolで更新する。
この単位にはF5の129/130/D179、Product、公開Documentation、primaryの既存混在差分を含めない。

## Decisions and Assumptions

新しい独立作業へ進む前とSession終了前にGit差分を照合する。未コミットで残す対象・理由・Next Actionを記録する。
必要な検証/Review、WorkerのReview前コミット禁止、既存差分保護、push/公開の別認可を維持する。
Ownerが変更作業を依頼したRepositoryで、この継続ルールを適用する。read-only依頼から変更やcommitを発生させない。

## Commands and Results

Rootのscope/link/同一内容/diff確認はPassed。両worktreeのSTATE check/dry-run/CAS/checkはPassed、実コマンド/結果/時間をE13/work-unit-policy-state-receipt.jsonに保存した。
対象4 pathとRoot Reviewの記録はwork-unit-policy-validation.json、実際のstage/commit/deliveryは同じprefixの個別receiptへ保存する。
Product入力不変の管理変更なのでPHP/Node Product tests、browser、CI/export/deployは未実施。全件の再実行0回。

## Acceptance Criteria

Root Review: Owner指示との一致、単位終了時のcommit、次の独立作業/Session終了前のcheckpoint、明示留保、WorkerのReview前commit禁止、無関係差分保護、remote確認をすべて確認した。
公開/Productの新しい検証は不要。ルールの受け入れとGit配送は区別し、後者はexact index/commit receiptで判定する。

## Release Documentation Impact

開発運用のみ。公開API/Capability/Command/Configuration、公開Source/routes/Search/LLM artifact、Stable1.2.1/未Release1.3は変更しない。
公開配信やProduct検証の成功をこの管理変更から推定しない。

## Remaining Issues

primaryには既存の混在差分が残る。ルール変更のコミットはその受け入れ/整理完了を意味しない。
F5の保存契約変更は独立Review後に別単位でコミットし、未発行のF5 Productは完全Packetの確認後にLunaへ委譲する。

## Suggested Next Action

残り工程: ルールの実装・検証は完了。独立commit/deliveryはreceiptで確認する。上位1.3はF4/F5/F6、D/E/G、004A、005/Owner preview、006のCI/export/publication/delivery。
Next Action: Rootが本ルールを独立コミットし、F5保存契約を次の単位として進める。

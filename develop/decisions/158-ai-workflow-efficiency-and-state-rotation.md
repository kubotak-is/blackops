# D158: AI Workflow Efficiency and STATE Rotation

Status: Decided — implemented by P23-007

## Context

ユーザーは2026-09-08に全件PHPUnit/静的解析の必要性を問い、恒久的な開発効率改善、STATEのローテーション、AIワークフロー全体の改善を依頼した。
STATEは14,660行/1,156,699 bytesに達し、再開のたびに履歴の大量読込が起きていた。
Taskの反復Gateと最終Gateが同じ検証を含み、管理文書の1語修正に複数laneの再確認が発生していた。
既存ProfileはworkerにもSTATE更新を指示し、共有fileの所有権が重複していた。

## Decision

仕様109に従い、現在状態を小さく保つ自動archive付き更新Toolを導入する。
検証とReviewは変更で影響した証拠を更新し、同じ入力に対する無条件のやり直しをなくす。
チームは通常Orchestrator/workerを基本とし、独立した実装・調査・専門Reviewだけを並行化する。
STATE更新と統合はOrchestratorが所有し、Taskでfile/検証/共有resourceの担当を決める。

既存のD2S1全件PHPUnitは128MiBメモリ不足で失敗している。この失敗を隠すためのgate緩和には使わず、別Taskの未完了境界として保存する。
Stable/公開API/Release品質の契約は維持する。製品の完了とWorkflowの完了を混同しない。

## References

- [AI Development Workflow](../spec/109-ai-development-workflow.md)
- [OpenAI Subagents](https://learn.chatgpt.com/docs/agent-configuration/subagents): 独立作業の委譲と役割別の設定を利用する。人数と役割の選択はこのRepositoryの運用判断とする。
- [OpenAI Model Guidance](https://developers.openai.com/api/docs/guides/latest-model): 変更に応じた検証範囲と、理由のない繰り返しを避ける指示を参照した。Modelの移行は行わない。

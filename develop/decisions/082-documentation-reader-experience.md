# D082: Documentation Reader Experience

Status: Decided

## Context

Phase 10でAstro Starlight Website、利用者向け18 Page、Stable／main表示、Cloudflare Pages配信境界まで整備した。現行文書は仕様との整合性を優先しており、BlackOpsを初めて読む中級PHP開発者にとって、採用理由、既存Frameworkとの対応、概念同士の関係、実行結果までの連続した体験が不足している。

Userは2026-07-13に、初見読者向けの導線、概念図、完全なFirst Operation Tutorial、Glossary、Troubleshooting、Security責任分界、Core API／Attribute Reference、読者向けToneへの全面調整を要求した。

## Decision

[DECISION]

1. LandingとGetting Startedの間に`Why BlackOps`と`Core Concepts`を置き、採用理由から操作手順へ進む順序にする。
2. `Headless`は「UIを提供しない」だけでなく、HTTP、CLI、Worker等の入口から独立したOperation Runtimeであることを説明する。
3. 「No operation stays in the dark」を設計原則として掲げ、FrameworkがOperationとして受理した処理はInline／Deferredを問わずLifecycle Journalへ記録することを明示する。Route不一致や解析不能なProtocol InputはOperation受理前であり、この原則の対象外とする。
4. Operation、OperationValue、Outcome、Journal、ExecutionContext、Execution Strategyの関係を一つの概念図と短い定義で示す。
5. Laravel／Symfony経験者向けの対応表を提供する。ただし概念は一対一の置換ではなく、移行時のMental Modelであることを明記する。
6. Inline／Deferred Sequence、Lifecycle State、Operation／Attempt／Correlation／Causation IDをMermaidで図示し、図が読めない環境向けに本文またはTableでも同じ意味を保持する。
7. First OperationはSource記述、Compile、HTTP Input、Response JSON、Sensitive Projection済み`journal.jsonl`、Outcome取得までを一つのTutorialとして完結させる。InputとOutputを必ず対で示し、実Repositoryで再現可能なCommandとOutputを使用する。
8. Glossary、Troubleshooting／FAQ、Security & Sensitive Data、Core API Types、Attributesの利用者向けPageを追加する。
9. Public Guideは日本語主体の能動態とし、PHP Symbol／API NameはCode表記を維持する。Domain Termの初出には一行の定義またはGlossary Linkを置く。
10. FrameworkとApplicationのSecurity責任を混同しない。認証・認可・保存時暗号化・Sink Access Control・Retention PolicyはApplication／運用構成の責務として表にする。
11. 全変更でDocument Channel `main`、Latest Stable `1.0.0`、Current Statusの既知制約を維持し、main機能をStable機能として説明しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 公開Page数とNavigationが増えるため、Content Map、Sidebar、Link、Search、Artifact Testを同じ変更単位で更新する。
- MermaidはVersionを固定したBuild Dependencyとして導入し、外部CDNへ依存しない。Diagram Sourceだけを表示する状態を成功扱いにしない。
- Code／CLI／JSONL例は見た目より再現性を優先し、Quickstart Source、Public API、実行結果と照合する。
- 全GuideのTone調整は仕様を簡略化して消す作業ではない。詳細をGlossaryやReferenceへ移した場合もLinkで到達可能にする。
- Reader Experience改善はP10-005A／P10-005Bの二つのReview／Commit単位に分け、P10-006 Closeoutより先に完了する。

[/CONSEQUENCES]

## References

- [D081 Documentation Website Delivery Contract](081-documentation-website-delivery-contract.md)
- [Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)
- [Documentation Reader Experience](../spec/59-documentation-reader-experience.md)


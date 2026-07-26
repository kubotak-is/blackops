# D127: Journal Documentation

Status: Decided

## Context

DocumentationのLandingはJournalをBlackOpsの主要な特徴として案内しているが、CTAはLifecycle Pageへ遷移し、Journal自体を説明する独立Pageがない。Canonical Journal、Observed Journal、JSONL、Sensitive Projection、Replay、Retentionの説明は複数Pageへ分散しており、利用者が「何が記録され、どのJSONを観測でき、どう設定するか」を一つの導線で理解できない。

OpenTelemetryはRoadmapに存在するが、Repository `main`にはAdapter、Exporter、Configurationが未実装である。将来構想を現在利用できる機能として記載してはならない。

## Decision

[DECISION]

1. Reader-facing Guideへ独立した`Journal` Pageを追加し、Public Routeを`/concepts/journal`とする。
2. Sidebarでは`Operation` Sectionの`Lifecycle`直後へ`Journal`を配置する。
3. LandingのJournal CTAはLifecycleではなくJournal Pageへ直接遷移する。
4. Journal PageはCanonical JournalとObserved Journalを区別し、公開するJSON例をSensitive Projection後のObserved JSONL Contractとして明示する。
5. Observed JSONLのTop-level Field、Operation、Actor、Attempt、Event固有`data`、10個のLifecycle Eventを現在の実装へ照合して説明する。
6. JSONLの有効化、絶対Path、`best_effort`／`required`、Observer Replay、Retention、Access Control、保存時暗号化の責任境界を既存Guideへ接続する。
7. OpenTelemetryは将来構想として独立Sectionへ記載し、Adapter、Exporter、Configurationが現在未実装であることを明示する。
8. Operation／AttemptをSpan、Lifecycle EventをSpan Event、Retry／Rejected／Failure／Dead LetterをMetricへ対応させる案は候補方向としてのみ記載し、確定したPublic Contractとして扱わない。
9. 将来のObservability AdapterもCanonical PayloadではなくSensitive Projection後のObserved Recordを受け取る安全境界を維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Landingで訴求するJournalから、JSON構造、設定、Replay、Securityまで一続きで読める。
- 利用者はCanonical Storeの内部表現と外部Observer向けJSONLを混同しない。
- OpenTelemetryの方向性を示しつつ、未実装機能を利用可能と誤認させない。
- Journal固有のPublic Route、Navigation、Link、JSON Parse、未実装表現をWebsite Regressionで固定する。

[/CONSEQUENCES]

## References

- [D004 Journal Schemaとセキュリティ](004-journal-schema-and-security.md)
- [D028 Journal Record Schema](028-journal-record-schema.md)
- [D031 Sensitive Projection](031-sensitive-projection.md)
- [D034 MVP Lifecycle Events](034-mvp-lifecycle-events.md)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [Specification 94 Journal Documentation](../spec/94-journal-documentation.md)

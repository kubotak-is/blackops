# D058: FrankenPHP Runtime Premise

Status: Decided

## Context

BlackOpsはHTTP API、Deferred Worker、PostgreSQL Transport、Compiled Container、Operation Manifestを組み合わせて動作する。

これまでの仕様はPHP 8.5以上、PSR-7/PSR-15、PSR-11、Symfony DependencyInjection、本番向けCompiled Containerを定めている。一方、実際のHTTP RuntimeとDeployment前提は未指定だった。

## Decision

[DECISION]

BlackOpsのMVPおよび公式Reference EnvironmentはFrankenPHPを前提にする。

Framework Contractは引き続きPSR-7、PSR-15、PSR-17、PSR-11を境界にし、CoreやOperation APIをFrankenPHP固有APIへ結合しない。

公式Guide、Docker Compose、Production Bootstrap、HTTP Front Controller、Worker運用の検証はFrankenPHPを主対象にする。

PHP-FPM、RoadRunner、Swoole等は将来のCompatibility TargetまたはAdapter候補とし、MVPの主要検証対象にはしない。

長期Processで動作することを前提に、Operation Scope、Logger Scope、Database Connection、Observer Buffer、Worker LoopはOperation境界またはProcess Lifecycleで明示的にreset / flush / health-checkできる設計を維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 公式Runtime GuideとDocker ComposeをFrankenPHP中心に整理できる。
- PHP-FPM前提のRequestごとのProcess初期化に頼らない設計を明確にできる。
- Long-running Processで状態が混線しないよう、Scope終了、Buffer flush、Connection resetを必須設計として扱える。
- Core ContractをPSR境界へ留めることで、将来ほかのRuntime Adapterを追加する余地を残せる。
- FrankenPHP-specificな最適化やBootstrapはAdapter / Runtime Composition層へ閉じ込める必要がある。
- MVP検証ではFrankenPHP環境を優先し、PHP-FPM互換性は主目標にしない。

[/CONSEQUENCES]

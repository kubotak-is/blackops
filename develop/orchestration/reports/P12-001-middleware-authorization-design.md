# P12-001: Middleware and Authorization Design Report

Status: Awaiting Decision

## Summary

Phase 12の既存D010／Spec 06とCurrent Runtimeを監査し、そのまま実装するとCurrent Operation Authoringと衝突する前提を特定した。

Current APIに合わせたD095たたき台を作成し、Phase Scope、Operation Middleware API、Pipeline Compile、Authentication境界、Durable Actor、Deferred Failureの6問へ絞った。Production Code／Testは変更していない。

## Current Runtime Audit

- `Operation`は一種類で、HTTP接続は`#[Route]`、Deferredは`#[ExecuteWith]`で表現する
- Typed Self-handledはNative `OperationValue`／Optional `ExecutionContext`を受け、Native `Outcome`を返す
- 予期された拒否は`OperationRejectedException`がInternal `OperationResult`へNormalizeされる
- HTTP RuntimeはPSR-15 Request Handlerだが、Middleware Pipelineを構成しない
- Inline Dispatcher／Deferred Acceptor／Worker RuntimeにOperation Middlewareの挿入点はない
- Operation／HTTP ManifestにMiddleware／Authorization Metadataはない
- `ExecutionContext`とDeferred CodecにActorはない
- HTTP RequestからExecutionContextへActorを引き渡す経路はない
- Rejection Category／HTTP ResponderはUnauthorized 401／Forbidden 403をすでに扱える
- ServiceProvider／Compiled ContainerはApplication所有Policy／Authenticator／Repository／MiddlewareのDIに再利用できる

## Superseded and Compatible Decision Surface

D010の下記はCurrent Designと互換である。

- HTTP固有MiddlewareとOperation Middlewareを分離する
- 両方を`next`前後の玉ねぎPipelineとする
- CredentialをOperationValue／ExecutionContext／Journal／Transportへ含めない
- `#[Authorize]`でPolicyを宣言する
- Deferredは受付とWorker実行時の両方で認可する
- origin／authorization／execution Actorを区別する
- PipelineをBuild時に固定し、不正順序をCIで拒否する

D010の下記はD011／D074／D075とCurrent Runtimeに合わせて置き換えが必要である。

- `HttpOperation`／`ConsoleOperation`の入口別marker interface
- `OperationResult::rejected()`を利用者のOperation Middleware APIへ露出する前提
- HTTP／Console／Internal Adapterを同じ初期Scopeで実装する前提
- Actorの許可済みAttribute／ClaimをDurable Contextへ持ち込み得る曖昧さ
- Actor不在／権限剥奪とPolicy Backend障害のFailure分類不在

## User Decision Questions

`develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`に6問の`[ANSWER]`欄を用意した。全問Aを推奨する。

## Changed Files

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P12-001-middleware-authorization-design.md`
- `develop/orchestration/reports/P12-001-middleware-authorization-design.md`
- `develop/STATE.md`

## Decisions and Assumptions

- D095確定前にPublic API名、Config Schema、Manifest Schema、Journal Schemaを実装しない。
- `OperationResult`のInternal存続とPublic DeprecationはPhase Delivery Planで別に監査し、D095では新Middleware APIへ露出しない方針だけを問う。
- Session／JWT／IdPの具体LibraryはApplicationの選択とし、Phase 12 Coreの必須Dependency候補にしない。

## Commands and Results

| Command | Result |
| --- | --- |
| Source of Truth／D009／D010／D011／D071／D074／D075／D093 read | Current APIと旧前提を照合 |
| Runtime／Manifest／ExecutionContext／Codec／HTTP／Worker／DI Source read | 未実装挿入点と再利用基盤を確認 |
| `git diff --check` | Success |

## Acceptance Criteria

- [x] Current Runtime Auditを記録した
- [x] Superseded／Compatible Decision Surfaceを分離した
- [x] D095の6問とRecommendationを作成した
- [ ] User AnswerとFinal Decisionを反映する
- [ ] D010／Spec 06の更新入力を固定する

## Remaining Issues

D095の6問はPublic API、Security、Durable Data、Failure Lifecycleを変えるため、User Answer前に推測で実装しない。

## Suggested Next Action

UserがD095の各`[ANSWER]`へ回答する。OrchestratorがDecisionを確定し、Spec 06、Phase 12 Delivery Plan、Task Packetを作成する。

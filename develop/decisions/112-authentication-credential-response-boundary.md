# D112: Authentication Credential Response Boundary

Status: Proposed

## Context

D111は、`make:auth`がRegister／Login／Logout Operationを生成し、Fresh ApplicationでBearer Session Authenticationを完走できることを要求している。同時に、Raw Session TokenをDatabase、Journal、Outcome、Log、Command Output、Reportへ保存／出力しないことを不変条件としている。

現在のBlackOpsでは、Operationの正常な返り値は`Outcome`である。Inline OperationでもCompleted OutcomeはHTTP ResponseだけでなくCanonical `operation.completed` Journalへ記録される。Deferred OperationではOutcome Storeにも保存され、Status APIから再取得できる。したがってLogin OperationがRaw Tokenを通常のOutcome Propertyとして返す実装は、D111のSecurity Contractと両立しない。

`#[Sensitive]`でPropertyをMaskするだけでは解決しない。現在のSensitive境界はObserver Projection用であり、Canonical OutcomeへCredentialを保存してよいというContractではない。またFrontend CompilerはSensitive OutcomeをBuild Errorとして拒否する。

P18-006BのTask Packetを確定する前に、CredentialをHTTP Clientへ一度だけ返すTransport境界を決める必要がある。

## Question 1: Raw Session Tokenを返す境界

### Option A: Inline-only Ephemeral HTTP ResultをFrameworkへ追加する

通常の`Outcome`とは別に、HTTP Responseへ一度だけ投影できるPublicなEphemeral Result Contractを追加する。

- `#[Route]`付きInline Operationだけが返せる
- Deferred、Console公開、Status／Wait、Outcome Store、Canonical Journal Dataでは拒否する
- LifecycleはOperationのReceived／Started／Succeeded／Completedを記録するが、Completed DataはCredentialを含まない安全な完了表現だけを保持する
- Raw Tokenは同一Request中のHTTP Responderへだけ渡し、Response生成後に再取得できない
- Frontend Contractは直接`fetch()`のResponse Shapeだけを生成し、Status／Wait対象にしない
- Register／LoginはこのContractでTokenを返し、Logoutは通常の`void` Operationとする

利点は「HTTPもOperation」というBlackOpsの中心モデルを維持し、将来のAPI Key／Password Reset Token等にも同じ安全境界を再利用できることである。欠点は、Operation Return、Manifest、HTTP Responder、Frontend Generator、Journalの複数境界に新しい概念を追加するため、P18-006Bの前に独立実装Taskが必要になることである。

### Option B: Credential発行EndpointをPSR-15 HTTP Adapterとして生成する

通常のOperation Contractは変更せず、AuthenticationをOperation実行前のHTTP Security Boundaryとして扱う。

- Register Userだけを通常のOperationにできるが、Raw Tokenを発行するLogin／Registration ResponseとLogoutは生成PSR-15 Handler／Routerが担当する
- HandlerはApplication Domain Serviceと`SessionManager`を呼び、Raw TokenをHTTP Responseへ一度だけ書く
- CredentialをOutcome／Journal／Status／Frontend Operation Contractへ載せない
- 認証Endpoint用のFrontend Helperが必要ならApplication-owned Codeとして別途生成する

利点は既存Lifecycleを崩さず、最小のSecurity Surfaceで実装できることである。欠点はLogin／Logout HTTP EndpointがOperationではなくなり、「HTTPもConsoleもJobもすべてOperation」という説明に明示的なAuthentication例外が生じることである。

### Option C: Raw Tokenを通常のSensitive Outcomeとして返す

Login OutcomeへRaw Tokenを持たせ、HTTP Response、Journal、Outcome Store、StatusでMaskまたは暗号化する。

これはCanonical Outcomeと再取得契約を複雑にし、D111の「Raw TokenをOutcomeへ出さない」という不変条件にも反するため採用しない。

### Recommendation

Aを推奨する。

BlackOpsのOperation中心モデルを維持しながら、CredentialをDurable Outcomeから型レベルで分離できる。実装Scopeは増えるが、Raw Secretを一度だけ返す用途を場当たり的なAuth専用Side Channelにせず、Build-timeにDeferred／Console／Status利用を拒否できる。

[ANSWER]



[/ANSWER]

## Decision

[DECISION]

User回答待ち。

[/DECISION]

## Consequences

[CONSEQUENCES]

User回答後にP18-006BのTask分割、Generated File Contract、Fresh Consumer Contractを確定する。

[/CONSEQUENCES]

## Traceability

- Session Authentication: [D111](111-session-auth-package-contract.md)
- Application Ergonomics: [Spec 74](../spec/74-application-ergonomics.md)
- Phase 18 Delivery: [Spec 75](../spec/75-phase-18-delivery-plan.md)
- HTTP Outcome: [Spec 5](../spec/05-http.md)
- Structured Outcome: [Spec 73](../spec/73-structured-outcome-contract.md)

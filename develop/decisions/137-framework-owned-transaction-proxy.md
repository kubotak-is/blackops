# D137: Framework-owned Transaction Proxy Contract

Status: Decided

## Context

D096とSpecification 09／11は、`#[Transactional]`をOperationとDI管理されたApplication／Command Serviceの双方へ適用し、`#[AfterCommit]`をTransaction ScopeへQueueする契約を確定した。D108はRay.AopのTokenizer問題を理由に、Ray.Aopを直ちに削除せず、汎用AOPではないFramework-owned Build-time Proxyを独立Phaseで設計してから置き換える方針を決めた。

P21-001のRead-only auditでは、現在の構成と未確定境界を次のとおり確認した。

- `src/Internal/Aop/RuntimeAopCompiler.php`はContainer DefinitionをID順に走査し、`dirname(container)/aop`を毎回空にしてからRay.Aop Compilerを呼ぶ。失敗時も同じディレクトリを削除する。
- `AopServiceDefinitionCompiler`は`Definition::setClass($proxyClass)`だけを変更し、`AopRuntimeBindingRegistrar`が`_setBindings` MethodCallとInterceptor Service Referenceを追加する。Definitionのその他の値は同一オブジェクトに残るが、factory／lazy／decoration等の互換性は検証されていない。
- `AopBindingFactory`はClass／Method Attributeを読み、Method-level Transactionalを優先する。`AopMethodBindingFactory`はOperationのTransactional bindingを`FoundationMethodInterceptor`（pass-through）にし、一般ServiceだけをTransaction Interceptorへ接続する。`OperationMetadataCompiler`と`InlineDispatcher`／`DeferredWorkerRuntime`がOperationの固定Lifecycle Transactionを所有するため、この分離を次のProxyでも維持する必要がある。
- Ray.Aop 2.20.0（`composer.lock`）はSubclass、Tokenizer、`filemtime`／binding／directory由来のCRC名、直接`file_put_contents`を使う。`readonly` Class、Union／Intersectionの一部は生成できるが、`final` Class／Method、抽象Class、非Public／Static等はSubclass interceptionに適さない。
- `RuntimeContainerDumper`はContainer PHPを一時ファイルへ書いて`rename`するためContainer単体はatomic replaceだが、AOP Proxyファイルはatomic writeではない。既存のBuild Fingerprintはpath／mtime／sizeだけで、AOP Source Content／Generator ID／Build IDを含まない。

Production Code、Test、Fixture、Dependencyは本Decisionの監査・確定では変更しない。確定した契約は`spec/101`、`spec/102`、Phase 21 Production Task Packetへ分割する。

## Inherited Contract

- D096のNamed Connection、Required Nested Transaction、Manual Transactionとの混在Fail-fast、After Commit Best-effort、Operation Terminal／Outcomeとの保証差を維持する。
- D108の汎用AOP Engine禁止、Runtime Source Scan／Temporary Proxy生成禁止、Ray.Di非採用、Repository内だけでRay upstreamを追跡する方針を維持する。
- Attributeは無効な対象へ付けたまま無視せず、Build-timeにSafe Diagnosticで拒否する。Credential、Payload、Throwable全文、Resolved Connection ParameterをDiagnosticへ出さない。

## Current Evidence Inventory

| Area | Evidence | Audit finding |
| --- | --- | --- |
| Ray composition | `src/Internal/Aop/RuntimeAopCompiler.php:18-50`, `AopServiceDefinitionCompiler.php:20-61` | Definitionを直接差し替え、Proxy file listをDumperへ渡す。Runtime compilerがSourceを再走査する入口はないが、Ray CompilerはBuild時にSourceをTokenizerで読む。 |
| Attribute semantics | `AopBindingFactory.php:22-57`, `AopMethodBindingFactory.php:30-69` | Class-level Transactionalは候補Public instance methodへ適用し、method-levelはconnectionをoverrideする。AfterCommitはMethod-levelのみ。重複AttributeはReaderが拒否する。 |
| Signature guards | `AopClassValidator.php:13-21`, `AopMethodValidator.php:15-65`, `tests/Internal/Aop/RuntimeAopCompilerTest.php:151-210` | final class／final method／private／static／constructor／destructor、AfterCommitのnon-void／reference return／reference parameter／generatorを拒否する。Class-level Transactionalのfinal methodは現在は静かに非対象となるため、次Contractでは明示Diagnosticが必要。 |
| Transaction lifecycle | `src/Internal/Registry/OperationMetadataCompiler.php:194-227`, `src/Internal/Execution/InlineDispatcher.php:545-575`, `src/Internal/Execution/HandlerInvoker.php:22-44`, `src/Internal/Execution/DeferredWorkerRuntime.php:170-215` | Operation Attributeはmetadataから固定Lifecycleへ入り、Handler invocationの外側でTerminal／Outcomeまで調整される。一般Service proxyがOperationを再度Transactionalに包んではならない。 |
| DI integration | `AopServiceDefinitionCompiler.php:58-59`, `AopRuntimeBindingRegistrar.php:20-68`, `vendor/symfony/dependency-injection/Definition.php` | `setClass`と`addMethodCall`は同一Definitionの他フィールドを保持する。AliasはDefinitionではなくContainerBuilder aliasなので、置換対象IDとalias targetを同時に確認する必要がある。 |
| Artifact | `AopArtifactDirectory.php:13-66`, `vendor/ray/aop/src/AopPostfixClassName.php`, `vendor/ray/aop/src/Compiler.php:82-145` | `aop` directoryを先に全削除し、生成失敗時にも削除する。Proxy fileは直接書込みで、file nameはmtime／binding／directory／generation CRCで、Application Build IDやcontent hashを持たない。 |
| Container write | `RuntimeContainerDumper.php:72-89` | Container PHPはtemporary file＋`rename`でatomic replaceする。Proxy artifactは同一atomic boundaryにない。 |
| Current dependency | `composer.json:28`, `composer.lock:1276-1334` | `ray/aop` `^2.19`、lock resolved 2.20.0、`ext-tokenizer` runtime dependency。Removal gateは未定義。 |

## PHP Signature Matrix (proposed Framework-owned generator boundary)

`support`は生成Proxyで意味を保てること、`reject`はAttribute付きServiceをBuild Errorにすること、`not-applicable`はAttributeを受け付けない／Proxyを生成しないことを示す。Generator実装前のMatrixであり、User回答後にSpecification 101へ固定する。

| Signature / declaration | Status | Boundary and diagnostic |
| --- | --- | --- |
| Instantiable, non-final concrete class | support | Subclass proxy candidate。DI-managed service only。 |
| `readonly` concrete class | support | Generated subclass must remain `readonly` and use immutable binding state。Golden compile/runtime test required。 |
| `final` class | reject | `#[Transactional]`／`#[AfterCommit]`付きDI serviceはBuild Error。Explicit APIまたはunattributed serviceへ移行。 |
| abstract class / interface / trait | not-applicable | No concrete DI instance to replace; class-level Attribute is a Build Error if discovered as service target。 |
| Inherited public non-final instance method | support | Class-level Attribute applies with declaring signature preserved; method-level Attribute overrides class connection。 |
| public `final` method | reject | Method-level Attribute is Build Error。Class-level Attribute must also report the method rather than silently skip it. |
| protected/private method, constructor, destructor | reject | Cannot be intercepted by public service proxy; Attribute is Build Error。 |
| static method | reject | No instance scope; Attribute is Build Error。 |
| generator (`yield`) | reject | `AfterCommit` is already invalid; initial Framework-owned release also rejects Transactional generators until transaction lifetime across iteration is proven. |
| by-reference return | reject | `AfterCommit` must remain `void`; initial release rejects reference returns for both attributes to avoid proxy forwarding ambiguity. |
| by-reference parameter | reject | Initial release rejects reference parameters for both attributes; a later compatibility Decision may add exact `&` forwarding after fixture evidence. |
| variadic parameter | support | Preserve `...` and argument order; include named/positional invocation tests. |
| union / intersection / DNF parameter or return type | support | Preserve Reflection type spelling and parentheses; compile against PHP 8.5 fixtures. |
| nullable, `never`, `mixed`, `static`, `self`, `parent` | support with exact compatibility tests | Proxy must preserve LSP and scope resolution; `static` return requires explicit test. |
| default scalar / array / constant-expression value | support | Copy Reflection default exactly; reject inaccessible/non-constant defaults at compile time with safe location. |
| parameter/method/class attributes unrelated to BlackOps | support | Preserve source attributes in generated signature where PHP permits; BlackOps target attributes remain metadata-only and are not duplicated into runtime callback. |
| `#[Transactional]` and `#[AfterCommit]` on the same method | reject (recommended) | Prevent ambiguous nesting/order. Use a separate transactional method that calls an AfterCommit method. |
| Attribute on property or parameter; repeated Attribute | reject | Current target validator/reader evidence; never silently ignore. |

## Attribute Precedence and Interception Ownership

1. Operation Definition／self-handled `handle()` `#[Transactional]` is compiled into `OperationMetadata` and executed exactly once by the fixed Inline／Deferred Operation Transaction Lifecycle. The generated service proxy MUST bind this method as pass-through (or omit Transactional binding), while `#[AfterCommit]` keeps its dedicated queue semantics only where an invocation is actually made inside a Scope.
2. Non-Operation DI service class-level `#[Transactional]` applies to eligible public instance methods. Method-level `#[Transactional]` overrides the class connection for that method. A method without either Attribute is untouched.
3. An Operation used as a handler is identified from the source class/metadata before class replacement; generated subclass identity MUST NOT make it look like a general service. Runtime must reject a proxy whose ownership marker or source-class map is inconsistent.
4. Framework-owned proxy and legacy Ray.Aop proxy are mutually exclusive per service definition. A build must fail on a generated-proxy marker or alias path that would cause two Transactional interceptors. No runtime “try both” fallback is allowed.
5. `AfterCommit` queues the original receiver/method/arguments through the Framework Transaction Runtime. It does not open a second transaction and does not alter committed Operation Outcome.

## Symfony DI Definition Preservation Matrix

The generator should mutate only the service class reference plus framework binding metadata. The replacement must preserve the original service ID and alias graph.

| Definition feature | Proposed status | Contract |
| --- | --- | --- |
| Constructor arguments, autowire, named bindings, properties | support | Preserve exact values and references; generated constructor remains LSP-compatible. |
| public/private visibility | support | `Definition::isPublic()` unchanged; aliases retain their original visibility rules. |
| shared scope | support | `isShared()` unchanged; repeated `get()` returns the same proxy instance when original was shared. |
| tags / autoconfigured / `instanceof` conditionals | support | Preserve tag attributes and compiler-applied conditionals; tests compare complete tag map. |
| method calls / configurator | support with order test | Existing calls remain in original order; `_setBindings` is appended at a defined point and must not replace configurator behavior. |
| alias to service ID | support | Alias target remains original ID; `get(alias)` returns the same generated proxy. Alias visibility and deprecation remain unchanged. |
| lazy service | reject initially | Symfony lazy proxy plus Framework subclass proxy creates two proxy layers and can violate `readonly`/signature guarantees. Add an explicit future compatibility task rather than silently changing `lazy`. |
| factory definition | reject initially | Factory may return an unrelated/original object and bypass `setClass` replacement. Require an explicit factory adapter in a later decision. |
| synthetic definition | not-applicable | Current compiler skips synthetic definitions; runtime-injected services cannot be generated at build time. |
| abstract definition | reject | No direct instance; child definitions must be resolved and audited individually, never replaced at the template level. |
| decoration (`setDecoratedService`) | reject initially | Decoration ordering can create an outer/inner proxy chain. Require explicit decorated-service compatibility evidence before enabling. |
| file/configurator/deprecation | support with fixture | Preserve fields; safe diagnostic must identify service ID only, not arguments or secrets. |

If any unsupported feature carries a BlackOps Attribute, `build:compile` fails with a stable code and service/method location. It must not fall back to the original unproxied service.

## Generated Artifact, Build ID, Drift, and OPcache Contract

The Framework-owned generator should use a staging directory adjacent to the target container, for example `<build-dir>/aop/.staging/<build-id>-<nonce>`, and publish a manifest plus proxy files as one build unit.

- Build ID is the same validated `app.build.application_build_id` already stored in the compiled Container. The AOP manifest records build ID, generator contract version, PHP major/minor, source class FQCN, source file content hash, normalized Attribute/signature metadata hash, proxy FQCN, and file hash.
- Input hash is content-based (not only mtime/size) and includes every attributed source file, service-provider/config input, generator version, PHP target, and normalized connection names. A source edit with unchanged size/mtime resolution must invalidate the artifact.
- Generate every file in staging, verify PHP parse/class declaration, manifest references, and Definition map, then atomically rename the completed staging directory or manifest into the active location. Never expose a partially written proxy. The existing direct proxy `file_put_contents` path is insufficient evidence for this contract.
- Stale cleanup happens only after a successful publish by comparing the active manifest. A failed build leaves the last known-good active artifact available to the caller or leaves no newly selected container; it must not pre-delete the only usable artifact.
- The compiled Container requires only manifest-listed proxy files from its same build directory. Loader checks build ID, manifest hash, and class/source mapping; it does not scan source or regenerate at Runtime. OPcache reset/invalidation is an operational deployment concern, but the artifact path/build ID must change on every accepted build so stale OPcache classes cannot satisfy a new manifest.
- Safe diagnostics expose stable code, service ID, source class, and build ID only. They must not include DSN, password, payload, generated source text, or full Throwable details.

## Migration and Compatibility Period

Migration is build-profile based, not runtime chaining. During the compatibility period each Application selects exactly one mode for each build: `ray` (current path) or `framework` (new generator). The mode and generated manifest are recorded in the build artifact; the same Definition, alias, and service must never receive both modes.

Each migration slice must run the same compatibility fixture matrix against both modes where the signature is in the support set: transaction begin/commit/rollback, nested Required, AfterCommit queue/rollback, method return/reference/variadic/type behavior, Operation terminal atomicity, DI definition preservation, shared identity, aliases, and failure diagnostics. A fixture that is unsupported by the new mode remains on Ray only until the application removes or refactors the Attribute; it is not silently unproxied.

Rollback is selecting the previous complete build directory and its matching Container/manifest, not enabling a second runtime interceptor. The compatibility period ends only after all first-party and consumer fixtures pass in framework mode and no service resolves a Ray proxy.

## Ray.Aop Removal Gate

`ray/aop` and `ext-tokenizer` may be removed from `composer.json`／`composer.lock` only when all gates are independently evidenced:

1. Signature Matrix fixtures cover every support row and every reject row produces Build Error with safe stable diagnostics.
2. Framework generator publishes deterministic content-hashed artifacts with manifest/build ID, atomic replace, stale cleanup, drift detection, and no Runtime Source Scan.
3. Symfony DI preservation tests cover aliases, visibility, shared scope, tags, method calls, factory/lazy/synthetic/decoration rejection, and generated Container loading.
4. Operation Lifecycle tests prove one Transaction owner and no double intercept for Inline, Deferred, self-handled, and general Service paths.
5. Compatibility fixtures run with Ray and Framework modes; migration/rollback and OPcache-safe artifact selection are verified.
6. Full repository `rg` finds no Ray namespace, `ray/aop` dependency, `WeavedInterface`, AOP fixture, or proxy artifact contract outside historical Decision/Report evidence; package export and clean-install checks pass without Ray.
7. A separate accepted Production Task removes Ray source adapters, tests/fixtures, Composer entries, and any compatibility switch. This Decision itself does not remove them.

## Question 1: Framework-owned boundary

### Options

- A: `#[Transactional]`／`#[AfterCommit]`専用のFramework-owned Build-time Subclass Proxyを採用し、DI-managed concrete serviceへ限定する。汎用AOP、Runtime Scan、Ray.Diは導入しない
- B: Interface Binding／Symfony Decoratorを必須にし、Concrete Class Attribute interceptionを廃止する
- C: 一般ServiceのAttribute interceptionを廃止し、明示Transaction APIへ移行する

### Recommendation

Aを推奨する。D108／D096のDeveloper Experienceを維持しつつ、用途を二つのDatabase Attributeへ限定する。B／CはPublic ContractとMigrationを大きく変更するため、別Decisionなしには採用しない。

[ANSWER]
A
[/ANSWER]

## Question 2: Unsupported Signature handling

### Options

- A: Attribute付きの対応不能Signature／DefinitionはBuild Errorにし、未対応ServiceへRuntime fallbackしない
- B: 対応不能Methodだけを明示的に非intercept対象として許可する
- C: Ray.Aop互換のため未対応Signatureをbest-effort生成する

### Recommendation

Aを推奨する。Transactionが一部Methodだけ抜けると保証が静かに変わるため、現在のvalidator方針をClass-level final methodにも拡張する。

[ANSWER]
A
[/ANSWER]

## Question 3: Attribute precedence and co-location

### Options

- A: Method-level TransactionalがClass-levelをoverrideし、OperationはLifecycle-owned。TransactionalとAfterCommitの同一Method併記はBuild Errorにする
- B: 同一Methodの二属性を定義順で実行する
- C: Class-level Attributeだけを受理し、Method-level overrideを廃止する

### Recommendation

Aを推奨する。定義順依存のInterceptor nestingを公開せず、AfterCommitは別Methodとして呼び出すことでD096のQueue semanticsを保つ。

[ANSWER]
A
[/ANSWER]

## Question 4: Symfony DI Definition preservation

### Options

- A: Same ID／alias graphを保ち、arguments／bindings／properties／visibility／shared／tags／calls／configuratorを保存する。factory／lazy／synthetic／abstract／decorationは初期ReleaseでBuild Error／N/Aとする
- B: すべてのSymfony Definition featureを自動的にProxyへ変換する
- C: Proxy対象ServiceはPublic／sharedへ正規化する

### Recommendation

Aを推奨する。未検証のfactory／lazy／decorationを自動変換すると、Service identityと安全なConstructor契約を破壊し得る。

[ANSWER]
A
[/ANSWER]

## Question 5: Artifact and drift contract

### Options

- A: Content-hashed staged artifacts、manifest（Build ID／generator／PHP／source／signature／file hash）、atomic publish、post-success stale cleanup、manifest drift checkを必須にする
- B: 既存のmtime／binding CRCと事前全削除を維持する
- C: RuntimeでSourceを再走査し、必要ならTemporary Proxyを再生成する

### Recommendation

Aを推奨する。Bは同一mtime／size変更とpartial writeを検出できず、CはProduction Runtimeの決定性と秘密境界を破る。

[ANSWER]
A
[/ANSWER]

## Question 6: Migration and compatibility period

### Options

- A: Build profileごとにRayまたはFrameworkを一方だけ選び、同一Definitionへの二重Proxyを禁止する。Golden compatibility fixturesとprevious-build rollbackを必須にする
- B: Ray Proxyの上にFramework Proxyを重ね、段階的に片方を無効化する
- C: Rayを削除してから失敗したServiceだけRuntime fallbackする

### Recommendation

Aを推奨する。二重Transaction、二重AfterCommit、異なるScopeの混在を構造的に防ぎ、Rollbackを完全Artifactの切替として扱える。

[ANSWER]
A
[/ANSWER]

## Question 7: Ray.Aop Removal Gate

### Options

- A: Signature／DI／Lifecycle／Artifact／Migration／Consumer package-export gatesを全てPASSしてから、別Production TaskでComposer／Source／Fixtureを削除する
- B: Framework Proxyのfocused testだけPASSした時点でRayを削除する
- C: Rayをrequire-devへ移してProduction dependencyだけ先に削除する

### Recommendation

Aを推奨する。Rayは現在Production dependencyであり、Clean install、Composer lock、namespace scan、artifact loadingまで含めて削除の証明が必要である。

[ANSWER]
A
[/ANSWER]

## Decision

UserはQuestions 1〜7についてすべてOption Aを選択した。これにより、次をD137の確定契約とする。

1. Framework-owned Build-time Subclass Proxyは`#[Transactional]`／`#[AfterCommit]`専用、DI-managed concrete service限定とする。汎用AOP、Runtime Source Scan、Ray.Diは導入しない。
2. 対応不能Signature／Definitionへ付いたAttributeはBuild Errorとし、非intercept／Runtime fallbackを許可しない。初期Releaseはgenerator、by-reference return、by-reference parameterを両Attributeでrejectする。
3. Method-level TransactionalはClass-levelをoverrideし、Operation TransactionはFramework Lifecycleだけが所有する。同一MethodのTransactional＋AfterCommit併記はBuild Errorとする。
4. Same ID／alias graphを保ち、Definitionのarguments／bindings／properties／visibility／shared／tags／calls／configurator等を保存する。factory／lazy／decoration／abstractは初期Releaseでreject、syntheticはN/Aとする。
5. Content-hashed staged artifact、manifest（Build ID／generator／PHP／source／signature／file hash）、atomic publish、post-success stale cleanup、manifest drift check、Runtime no-scan、OPcache-safe pathを必須とする。
6. MigrationはRayまたはFrameworkのmutually-exclusive build profileで行い、同じDefinitionへ二重Proxyを適用しない。Golden compatibility fixturesとprevious complete build rollbackを必須とする。
7. Signature／DI／Lifecycle／Artifact／Migration／Consumer package-export／clean-install／namespace-removal gatesを全PASSした後、別Production TaskでRay source／fixtures／Composer dependencyを削除する。

### P21-006 Compatibility Exceptions

P21-006の実証で、Ray 2.20.0はPHP 8.5の`never` return methodに対して`return`を含むproxyを生成し、`A never-returning method must not return`でcompile不能になることが判明した。UserはLegacy Rayを一時修正する案ではなく、`never`を互換期間の明示例外として記録し、P21-007でRayを削除する案を選択した。

その後の完全Matrix実証で、Rayの`func_get_args()`経路はextra named variadic valueを保持せず、`variadic(prefix: 'named', values: 4)`が`named4`ではなく`named`を返すことも判明した。UserはこれもLegacy Ray限定の第二例外として記録し、P21-007でRayと一緒に削除する案を選択した。

二つの例外はLegacy Rayの互換fixtureだけに適用する。Framework-owned generatorの`never`／named variadic support、Build Error／no-fallback契約、その他すべてのshared Signature／DI／Lifecycle matrixは変更しない。P21-006はFrameworkで両Signatureのcompile／runtime evidence、Rayのbounded failure／value-loss evidence、その他の両profile parityを必須とし、P21-007受入れ時に例外対象そのものを削除する。

### Confirmed Consequences

Specification 101、Phase 21 Delivery Plan 102、Production Task PacketをこのDecisionから分割する。Production実装は下記Task順で行い、各段階でRay pathを保持し、P21-007のRemoval GateまではRayを削除しない。

1. Contract／Signature Matrix／safe diagnostic and ownership guard
2. Generator／manifest／content hash／atomic publish／drift loader
3. Symfony DI Definition preservation and alias/unsupported-definition guard
4. Transaction／AfterCommit runtime binding and Operation no-double-intercept proof
5. Ray compatibility profile, migration, rollback, and consumer matrix
6. Ray removal／Composer/package export closeout

Each Production Task must retain a selectable previous-build/Ray path until its acceptance gate is complete. No Task may introduce Runtime Source Scan or a general-purpose interceptor.

## Traceability

- [D096 Phase 13 Database and Transaction Runtime](096-phase-13-database-and-transaction-runtime.md)
- [D108 Ray.Aop Upstream and Phase Order](108-ray-aop-upstream-and-phase-order.md)
- [Runtime and Dependency Injection](../spec/09-runtime-and-di.md)
- [Durable Journal and Transactions](../spec/11-durable-journal-and-transactions.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [Phase 13 Delivery Plan](../spec/64-phase-13-delivery-plan.md)
- [Specification 101 Framework-owned Transaction Proxy](../spec/101-framework-owned-transaction-proxy.md)
- [Specification 102 Phase 21 Delivery Plan](../spec/102-phase-21-delivery-plan.md)
- [P17-007A AOP Class Constant Attributes](../orchestration/tasks/P17-007A-aop-class-constant-attributes.md)
- [P17-007A Report](../orchestration/reports/P17-007A-aop-class-constant-attributes.md)

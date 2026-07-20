# P17-007A AOP Class-constant Attributes Report

## Summary

Ray.Aop Build-time Proxyの複数typed class-constant Attribute gapをRoot最小fixtureで再現し、locked `ray/aop 2.20.0`と一つ前の`2.19.1`を比較した。両Versionで同じSource生成破壊と`ParseError`が発生するため2.20.0固有Regressionではなく、Composer Repositoryから解決可能な新しいStable Releaseもなかった。

安全なdependency-native解決は存在しない。BlackOps Adapterで吸収するにはSource加工、Vendor Compiler実装の複製、またはProxy Reflection／Attribute Semanticsの変更が必要になり、TaskのSafety Boundaryに反する。そのためProduction Code、Dependency、Exampleの変更は行わず、P17-007でAccepted済みのliteral Deferred Strategy回避を維持したままBlockerとして返す。

## Reproduction and Root Cause

一時的なRoot fixtureは次のContractを持たせた。

- `Operation` implementation
- `#[ExecuteWith(Deferred::class)]`
- `#[Authorize(FixtureAuthorizationPolicy::class)]`
- method-level `#[Transactional]`
- typed Value／Outcome
- `RuntimeAopCompiler`からSymfony Container DefinitionをcompileするTest

Ray.Aop 2.20.0で次を実行すると`CompilationFailedException`になった。

```text
docker compose run --rm app vendor/bin/phpunit tests/Internal/Aop/RuntimeAopCompilerTest.php --filter multipleTypedClassConstant
```

`Ray\Aop\AopCode`で同じfixtureとFoundation bindingからProxy Sourceを直接生成して元`ParseError`を確認した。生成16行目は次のように壊れていた。

```php
#[Authorize999 extends Authorize (FixtureAuthorizationPolicy::class)]
```

元Errorは次のとおりである。

```text
syntax error, unexpected token "extends", expecting "]" at generated line 16
```

原因は`Ray\Aop\AopCode::parseClass()`が`token_get_all()`のすべての`T_CLASS`をclass declarationとして扱うことにある。最初の`Deferred::class`の`T_CLASS`でclass内部判定を開始し、その次の`T_STRING`である`Authorize`をclass nameと誤認する。`addClassName()`がそこへPostfixと`extends`を挿入するため、実class declarationへ到達する前にAttribute構文が破壊される。

Ray.Aopの公開`CompilationFailedException`は元`ParseError`をprevious exceptionとして保持しないため、通常のBlackOps compile境界では次の抽象化されたErrorだけが見える。

```text
Ray\Aop\Exception\CompilationFailedException: class:BlackOps\Tests\Fixtures\Aop\TypedAttributedTransactionalOperation Compilation failed in Ray.Aop.
```

調査用fixture／testは原因確定後に作業差分から除去した。既知のdependency failureを成功Contractとして恒久固定せず、解決可能なReleaseが出た時点でTaskのReproduction Contractどおりの成功Regressionを追加する。

## Dependency Version Comparison

| Version | Result | Evidence |
| --- | --- | --- |
| 2.20.0 | Failed | Root fixtureで`Compiler.php:97`から`CompilationFailedException`。生成16行目のAttribute破壊を確認 |
| 2.19.1 | Failed | 同じRoot fixtureで`Compiler.php:94`から同じ`CompilationFailedException` |

`docker compose run --rm --no-deps app composer show ray/aop --all`では解決可能な最新Stableが2.20.0、直前が2.19.1だった。`composer update ray/aop:2.22.0 --with-dependencies`も、Repositoryが2.19.0、2.19.1、2.20.0だけを候補として返して解決不能となった。比較後は`composer.lock`と`vendor`を2.20.0へ戻し、Root locked installで変更なしを確認した。`composer.json`／`composer.lock`に最終差分はない。

## Resolution and Safety Boundary

Resolution Priorityを次の順で評価した。

1. 新しいReleased Version: Composer Repositoryから2.20.0より新しいStableを解決できず不成立
2. 2.19.1 pin: 同じ失敗を再現したため不成立
3. BlackOps Build-time Adapter: Ray.Aop `Compiler`／`AopCode`はfinalでSource入力境界を公開しない。吸収には生成Sourceの置換、元Sourceのstaging加工、Vendor Compilerの複製、または中間SubclassによるReflection変更が必要
4. 上記はいずれもTaskで禁止されたSource加工、Fork相当、Attribute Semantics／Proxy Reflection変更へ入るため採用しない

Vendor直接修正、Fork、Composer patch、unreleased branch、Runtime Source Scan、Runtime Proxy生成、Public Attribute API変更は行っていない。`GenerateWeeklyDigest`はAccepted済みの`#[ExecuteWith('BlackOps\\Core\\Execution\\Deferred')]`とtyped `#[Authorize(AuthenticatedUserPolicy::class)]`を維持する。

## Changed Files

- `develop/TODO.md`: Released dependencyによる解決待ちを明記
- `develop/STATE.md`: Blocker Checkpointと次Actionを同期
- `develop/orchestration/reports/P17-007A-aop-class-constant-attributes.md`: 再現、比較、安全境界を記録

Production Code、Root Test、Fixture、Composer Dependency、Community Board、Quickstart、Specには最終差分がない。

## Commands and Results

- Ray.Aop 2.20.0 focused fixture: Error、`CompilationFailedException`を再現
- Direct `AopCode` diagnostic: malformed generated line 16と元`ParseError`を確認
- Ray.Aop 2.19.1 temporary update＋same fixture: Error、同じ失敗
- Ray.Aop 2.22.0 temporary constraint: Composer Repositoryに解決可能Versionがなく失敗
- Ray.Aop 2.20.0 restore: 成功、Composer差分なし
- Root `composer validate --strict`: 成功
- Root locked `composer install --no-interaction --prefer-dist --no-progress`: 成功、変更なし
- `git diff --check`: 成功
- Scope／Artifact guard: Production／Test／Example／Composer差分なし、生成物なし

Root Mago／PHPUnit／Deptrac、Community Board compile／PHPUnit、Digest E2Eは未実行である。安全な解決がなくProduction／Test／Example差分を全てrevertしたBlocked結果であり、Orchestrator指示により既にAccepted済みのBaseline full gatesを再実行しなかった。

## Acceptance Criteria

- [x] 複数typed class-constant Attribute失敗を一時Root fixtureで再現した
- [x] 生成Proxyの正確なParseErrorと原因を記録した
- [x] dependency-nativeな解決を優先し、Vendor Fork／直接修正を行っていない
- [x] Runtime Source ScanとRuntime Proxy生成を追加していない
- [ ] `GenerateWeeklyDigest`を両方typed `::class`表記でcompileできる
- [ ] 成功Regression fixtureをRoot Testへ恒久追加できる
- [ ] Community Board compile／PHPUnit／Digest E2Eを修正後に成功させる
- [ ] Root Mago／PHPUnit／Deptracを修正後に成功させる
- [x] Root Composer locked installとArtifact／Scope Guardが成功した
- [x] Report／TODO／STATEがBlocked結果と一致する
- [x] WorkerはCommitしていない

## Remaining Issues

P17-007Aは安全なReleased Dependencyまたは許可範囲内AdapterがないためBlockerである。現在のliteral Strategy回避は動作し、P17-007のAccepted gateを維持するが、公開例と同じ完全typed表記へ戻せない。

## Suggested Next Action

`develop/decisions/108-ray-aop-upstream-and-phase-order.md`で、literal workaroundを維持してP17-008を先行するか、upstreamへIssue／PRを作成するかを決定する。Stable Releaseが利用可能になった後はこのTaskを再開し、成功Regression、Community Board compile／E2E、Root full gatesを実行してliteral workaroundを除去する。

## Orchestrator Review

2026-07-21T02:09:09+09:00にBlocked判定を受け入れた。Ray.Aop `AopCode::parseClass()`のToken処理、生成Sourceの破壊箇所、2.19.1／2.20.0比較、最終DiffにProduction／Test／Dependency／Example変更がないことを確認した。

BlackOps側での置換やVendor Compiler複製はDependency境界を壊すため採用しない。P17-007はliteral Deferred Strategyでcompile／Runtime／E2Eが成功済みであり、本BlockerはVisual Design実装の技術依存ではない。次の行動はD108のUser回答に従う。

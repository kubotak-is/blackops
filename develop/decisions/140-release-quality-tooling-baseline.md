# D140 Release Quality Tooling Baseline

Status: Decided

## Context

P22-003のFinal Fixed Candidate `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5`は、全23 Consumer、Frontend、Website、Package、Skeleton Publication Dry-run、Full PHPUnit、Mago Format／Analyzeを完走した。一方、broad Mago lintは既存の186件（14 errors）で失敗し、Deptrac 4.6.2はProject Sourceの依存Graphを解析する前にvendor `NikicFileReferenceVisitor.php`のPHP 8.5 parse errorで停止した。

Magoの14 errorsは9つの既存Runtime／Metadata Classに集中するcomplexity／parameter-count系13件とerror-control-operator 1件である。Release直前にこれらのClassを横断的に再設計するとRuntime riskが大きい。Rule全体の無効化やSeverity引下げは、既存問題だけでなく将来の新規問題も見逃す。

DeptracはD022／Specification 16によりPHP 8.5構文をCIで解析できなければならない。公式4.7.1は4.6.2の不正な`$node instanceof $resolver->getNodeType()`を`$node instanceof ($resolver->getNodeType())`へ修正済みであり、現在のexact-pin方針のまま限定更新できる。

## Decision

Mago 1.42.0がFinal Fixed Candidateのbroad lintから生成するissue単位のstrict baselineをRepositoryへ追跡する。`mago.toml`の`[linter]`へbaseline pathと`baseline-variant = "strict"`を設定し、通常の`mago lint`はbaselineに存在しない新規Issueで失敗させる。CIは通常Lintに加えて`mago lint --verify-baseline`を実行し、Issueの追加だけでなく解消・移動・変化でbaselineが古くなった場合も失敗させる。

BaselineはRule無効化でもAcceptance Waiverでもない。Magoが生成した内容を手書きで増やさず、減らす場合も変更Sourceと再生成差分をReviewする。`--ignore-baseline`はDebt可視化と件数照合に使い、Release Gateの成功判定にはbaseline適用済み通常／同期検査を使う。

DeptracはComposerのexact development constraintを`4.7.1`へ更新し、`--with-all-dependencies --minimal-changes`でLockを限定更新する。Architecture rules、Layer、allowed dependencyは変更しない。Vendor patch、未追跡PHAR、PHP Version downgrade、失敗のWaiverは採用しない。

このTooling変更はProduction PHP Behaviorを変えないが、Composer Metadata、CI Contract、Quality Configurationを変更するため`08ad61f`をRelease Candidateとしてsupersedeする。P22-003AのReview／Commit後のSHAをreplacement candidateとし、P22-003 Full Gateを最初から再実行する。

## Rejected Alternatives

- 186件をRelease前に一括Refactorする: Runtime変更範囲と回帰RiskがRelease Blocker解消に対して大きすぎる。
- Complexity Rule／parameter-count Ruleを無効化またはSeverity downgradeする: 新規Issueまで恒久的に見逃すため採用しない。
- Deptrac 4.6.2のvendor fileを直接Patchする: Composer installで再現せず、Package Integrityを損なうため採用しない。
- Deptrac失敗を既知BaselineとしてAcceptedにする: Specification 16のPHP 8.5 Architecture Guard契約を満たさないため採用しない。

## Consequences

- Existing Mago debtはtracked strict baselineとして可視であり、新規／変化したIssueはCIを失敗させる。
- Production Class refactorはRelease Publicationから分離できるが、baseline削減Taskとして継続管理する。
- DeptracはPHP 8.5上でProject Graphへ到達し、0 violation／uncovered／errorを再び実証する必要がある。
- Mago baseline、Deptrac version、CI verification wiringはVersion Guardでdriftを拒否する。
- Tooling Commit後はPackage／Consumer／Websiteを含む完全なfixed-SHA GateとRemote CIを再取得する。

## Traceability

- [Namespace Dependencies Decision](022-namespace-dependencies.md)
- [Namespace Dependencies Specification](../spec/16-namespace-dependencies.md)
- [Stable 1.2 Release Plan](../spec/103-stable-1-2-release-plan.md)
- [P22-003 Release Candidate Gate](../orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md)
- [P22-003A Quality Tooling Blocker Resolution](../orchestration/tasks/P22-003A-release-quality-tooling-blockers.md)

# D088: Validation Backend

Status: Decided

## Context

P10-005E1ではBlackOps所有の`NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Count`、`Choice` AttributeとViolation Contractを先に実装した。利用者向けAPIをFramework所有にする判断はD086で確定しているが、各Ruleの入力評価まで独自実装すると、一般的なValidation LogicをFramework内で保守し続けることになる。

Symfony Validator ComponentはStandaloneでRaw Scalar／ArrayをConstraintへ渡して検証でき、初期7 Ruleに対応する標準Constraintをすべて持つ。BlackOpsは既にSymfony 7.4系Componentを複数利用している。

Userは、利用可能なValidation Libraryがある場合は内部評価へ利用し、車輪の再開発を避ける方針を指定した。

## Options

- A: BlackOps AttributeをPublic APIとして維持し、Internal Adapterが`symfony/validator` Constraintへ変換する
- B: Symfony Constraint AttributeをBlackOps利用者へ直接公開し、BlackOps Attributeを廃止する
- C: Runtime Dependencyを追加せず、現在の独自Rule Evaluatorを維持する

## Decision

[DECISION]

1. Aを採用し、`symfony/validator` 7.4系をRuntime Dependencyへ追加する。
2. 利用者は引き続き`BlackOps\Core\Validation\Attribute`だけを使用する。
3. Symfony Validatorの型、Constraint、Message、ViolationをBlackOps Public API、HTTP Response、Journalへ露出しない。
4. Internal Adapterは7つのBlackOps Ruleを対応するSymfony Constraintへ変換し、Raw Value Validation APIで評価する。
5. `Length`はUnicode Code Point、`Choice`はStrict Comparison、`Count`はBlackOps ContractどおりArrayだけ、`Range`は有限なint／floatだけという既存ContractをParity Testで固定する。
6. BlackOpsのViolationはField、Rule、安定Codeだけを保持し、Symfony Message、Invalid Value、Constraint設定を転記しない。
7. Attribute Constructorの設定値検証と、BlackOps固有の対象型境界／決定的集約順はFramework Adapterの責務として維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 一般的な文字列、数値、Collection、Email、Regex、Choice評価を実績あるLibraryへ委譲できる。
- BlackOpsのLifecycle／Sensitive／安定Code ContractはBackend差し替え境界の外側に維持する。
- `composer.json`と`composer.lock`へRuntime Dependencyが追加される。
- Symfony ValidatorのVersion更新時は7 Rule Parity TestでBehavior Driftを検出する。
- Public AttributeをSymfony Attributeへ置き換えないため、将来Backendを変更してもApplication Sourceを壊さない。

[/CONSEQUENCES]

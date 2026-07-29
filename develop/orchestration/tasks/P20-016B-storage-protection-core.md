# P20-016B: Storage Protection Core

Status: Ready

## Goal

Framework固定のXChaCha20-Poly1305 Envelope、Canonical AAD、Public Storage Key Provider Contract、Application Compositionを実装し、Protected Adapterが利用できるFail-closedな暗号化Coreを提供する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`

## Dependencies

- P20-016A Accepted

## In Scope

- Public Storage Purpose、Storage Key、Storage Key Provider
- Internal Protection Context、AAD Codec、Envelope Codec
- `BOPD` Version 1 Binary Envelope
- libsodium XChaCha20-Poly1305-IETF
- Key／Provider ValidationとSafe Failure
- Application Builder／Compiled Runtime Binding
- Known-answer、Randomized Round-trip、Tamper Matrix
- Artifact／Log／Exception Secret Exposure Guard

## Out of Scope

- PostgreSQL Schema／Migration
- Journal／Transport／Outcome／Outbox／Idempotency Adapter配線
- Read Authorization
- Rotation CLI
- KMS Vendor Adapter
- 任意Algorithm Plugin
- Public Guide／Website

## Files Allowed to Change

- `src/StorageProtection/**`
- `src/Internal/StorageProtection/**`
- `src/Application/ApplicationBuilder.php`
- `src/Internal/Application/**`
- `src/Internal/DependencyInjection/**`
- `src/Application/ApplicationBootstrapException.php`
- Corresponding files under `tests/StorageProtection/**`
- Corresponding files under `tests/Internal/StorageProtection/**`
- `tests/Internal/Application/**`
- `tests/Internal/DependencyInjection/**`
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016B-storage-protection-core.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Algorithmはlibsodium XChaCha20-Poly1305-IETFだけに固定する
- Envelope Layout、Byte Order、Magic、Version、Algorithm ID、Nonce／Tag LengthをSpecificationどおりにする
- New WriteごとにCSPRNG Nonceを生成し、Caller指定NonceをPublic APIへ出さない
- AADへEnvelope Version、Algorithm、Key ID、Purpose、Record／Operation／Tenant IdentityをLength-prefixでBindingする
- Key IDは1〜128 byte Safe ASCII、Key Materialは32 byteを必須にする
- Key Materialへ`#[SensitiveParameter]`を適用し、String化／JSON化／Debug表示を提供しない
- ReadはEnvelope Key IDでProviderを引き、Active Keyへ置換しない
- Unknown Header／Version／Algorithm／Key、Malformed Length、Tag不一致をPlaintextへFallbackしない
- Safe ExceptionへCiphertext、Key、Nonce、Tag、Tenant Raw ID、Provider Detailを含めない
- Compiled ArtifactへResolved Key／Credentialを保存しない
- Core追加時点ではExisting Adapterを暗号化済みと見なさない
- New Composer Dependencyを追加しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] Public Key Provider ContractがApplication-wide／Per-tenant／Per-purposeを表現できる
- [ ] Version 1 EnvelopeがKnown-answerとRandomized Payloadでround-tripする
- [ ] Empty／binary／large Payloadを扱える
- [ ] Wrong Tenant／Purpose／Record／Operation／KeyでDecryptを拒否する
- [ ] Header／Length／Nonce／Ciphertext／Tagの改ざんを拒否する
- [ ] Unknown Version／Algorithm／KeyをSafe Failureにする
- [ ] Key MaterialがArtifact／Exception／Log／Journal／Test Failureへ露出しない
- [ ] Provider BindingがClassic／Worker／CLI Compositionで同じである
- [ ] Existing Adapter挙動とFull Suiteを維持する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016B-storage-protection-core.md`へ必須項目とKnown-answer／Tamper Evidenceを記録する。

# Root verification/resource contract for real checkpoint production

Status: candidate for the complete independently reviewed F5 implementation packet.
No runtime lease, Product implementation, test execution or Acceptance is issued
by this document. C starts at070c752 on agent/p23-006-accepted-integration.
All commands execute from /home/kubotak/projects/blackops; C-relative operations
use explicit paths or the owned Compose override, never an alternate cwd.

## 1. Fixed migration and retained gates

Reserve migrations/postgresql/Version20261012000000.php for the consolidated29
statements in checkpoint-issuance-map-r1.md AppendixA. This path is absent in
both primary and C at reservation; the latest C migration is20261001000000.
This is one real-production migration,not a standalone accepted primitive.
Schema/migration native statement parity,all seven collections and both dispatch
indexes,immutable row enforcement,tenant/boundary/F2 joins and reverse teardown
must be proved with actual PostgreSQL. No baseline,threshold,512M or release gate
changes are assigned. F1/F2/F3 existing fields/wires stay fixed.

The complete worker allowlist must merge the frozen composition/history maps,
reviewed corrections,installation delta and online/delegate import map. Its
expected PHPUnit discovery inventory retains all3986 accepted predecessor cases
and adds every named new test. The accepted F3 proof is not future F5 coverage.
After iterations,run one fixed-candidate full suite; do not rerun same-condition
partial suites already contained in that successful full run. Native isolated
matrix and real database concurrent/ambiguity scenarios have distinct conditions
and remain mandatory. Review the exact changed security/transaction boundary and
public documentation; Root alone performs Acceptance and separated commits.

## 2. Ownership and two distinct resource lanes

Root owns lease creation/closing,snapshots,container/DB exclusivity and final
qualification. The Luna implementation owner may run only actions assigned by
that lease and writes its own Report. No two processes may run mutation suites
on the shared DB or generated output. Root's complete packet must contain exact
test files,source/cfg/lock/vendor hashes,command argv and output destinations
before the first runtime action. No current closed F3/native lease is reused.

Filesystem lane: one disposable pinned-image container per case,network none,
read-only root,UID/GID1000,cap-drop ALL,no-new-privileges,pids32,memory/swap768MiB.
PHP uses512M. /tmp is16MiB tmpfs, /fault32MiB private tmpfs,both noexec/nosuid/nodev;
/build is64MiB private exec tmpfs solely for compiling the diagnostic library.
Mount only C/src,C/vendor,the exact test-fixture/support files,and a dedicated
fresh output path. No repository-root,.env,DB socket/network,credential,real
seed/archive or broad host mount. The EROFS case adds only its explicitly owned
synthetic file mount. Compiler/loader calibration precedes protected-owner cases
in the actual tested image; no implicit dependency installation or silent skip.

Database lane: reuse the accepted Compose service/environment with an explicit
F5 override pointing /app at C,only under the exclusive Root lease. Preserve
runtime/environment bytes and do not print credentials. Tests use the repository's
existing unique-schema/native-connection cleanup convention. Actual C3/F2/F5
COMMIT-response-loss tests require real server commit plus bounded loss of that
response,not fake coordinator success. Do not apply LD_PRELOAD to the DB client
supervisor,Composer,Mago or unrelated tests. Pin selected child process/socket
ownership separately in the implementation test packet before executing faults.

## 3. Literal quality command families

Define APP as the exact argv prefix:

```text
docker compose -f compose.yaml -f .phpunit.cache/f5-orchestration/20261013/compose.override.yaml run --rm -T app
```

The override is a fresh owned F5 input,not an edit/reactivation of the F3 runner.
Every APP command below expands to that prefix; its final source input hashes
are captured immediately before the assigned run and compared afterwards.

| Phase | Exact command suffix / entry |
| --- | --- |
| Composer | APP composer validate --strict |
| Required PHP formatting | APP mago format --check src tests |
| Static and baseline | APP mago lint; APP mago lint --verify-baseline; APP mago analyze |
| Architecture dependency | APP php vendor/bin/deptrac analyse --no-cache |
| Discovery | APP php vendor/bin/phpunit --list-tests-xml .phpunit.cache/f5/test-inventory.xml |
| Final full | APP php vendor/bin/phpunit --display-all-issues --cache-directory .phpunit.cache/f5 --log-junit .phpunit.cache/f5/full.xml |
| PHP management-ID absence | Run the literal command below; expected exit1 with no hits. |
| Package inventory | bash .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-required-inventory.sh |
| Committed export | bash .phpunit.cache/f2-orchestration/accepted-integration/tests/Consumer/framework-package-export.sh; only after the accepted source unit is committed |

```bash
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO[.]md:[0-9]+' .phpunit.cache/f2-orchestration/accepted-integration/src .phpunit.cache/f2-orchestration/accepted-integration/tests --glob '*.php'
```

The table's semicolon-separated static entries denote separate recorded commands,
not a command that masks a preceding failure. Node byte-vector/golden tests use
node --test --test-isolation=none --test-reporter=tap on the exact issued fixture;
compare PHP and Node bytes using independently derived expected vectors.
The import map must name that concrete fixture,not leave a wildcard test target.
CLI projection/help/collision and owned native suites are iteration targets; the
complete packet supplies their exact file list before execution.

## 4. Mandatory filesystem case identities and observations

The native fixture is tests/Fixtures/Audit/authority-filesystem-native.php and
its child-only interposer is authority-filesystem-fault.c in the same directory.
No Product injection hook,alternative stream wrapper,native extension or callback
is added. Each case uses actual issued custody owners and independent post-state
checks. Compiler/source/library/image hashes,literal argv,case/control ID,
timing,terminal result,hit count,numeric return and cleanup are retained.

| Case family (each listed variant is distinct) | Required discriminating result |
| --- | --- |
| process-control | Real proc_open/control pipes,observed child state,signal9,terminal status and reap. No ext-posix requirement or timing-only assumption. |
| permission-mode / permission-erofs | Distinguish metadata admission failure from actual OS write denial; no chmod-after-open revocation fiction. |
| scratch-enospc / durable-enospc | Real isolated tmpfs exhaustion with independent byte/identity observation; no partial transcript or authority result. |
| short-write-progress / zero-write / partial-then-write-error | Actual positive-prefix completion,immediate no-progress failure,and actual prefix followed by induced error are separate. Check exact bytes and bounded loop. |
| post-cast-flush-error / file-sync-error / immutable-file-sync-error / directory-sync-error | Target the calibrated reached route. Error prevents the actual owner's protected result and later reference advancement. Scratch has no invented fsync. |
| seek-error / truncate-error / unlock-error | Three exact route/result cases. Unlock failure still makes the one cleanup close attempt and denies success. |
| consumed-close-error / nonconsuming-close-counterexample | Retain supported masked close and deliberately unsupported descriptor retention separately; no fake PHP negative PASS. Check actual resource/fd result and no second close. |
| scratch-unlink-error / scratch-rmdir-error | No nonempty payload write until required namespace removal; inspect before fixture cleanup. |
| two-process-lock-death / anonymous-capture-death | Real contention/kill/reap/fresh retry or anonymous bytes/disappearance. Signal death is not destructor proof. |
| floor-partial-write-death / floor-post-sync-death | Torn state fails closed without repair; exact already-durable state recovers under a fresh owner. Preserve actual inode and trusted floor. |
| archive-link-death / archive-directory-sync-death | Observe real published-link window and checked retry. Signed archived objects allow positive link count with exact authenticated bytes and renewed durability; never enumerate/delete unknown aliases. |
| cold-unicode-maintenance / cold-unicode-installation | Actual fresh assembly initializes fixed codec/Normalizer closure before either floor. Non-ASCII replay adds no late listed class/table load or unauthorized call; empty/late/missing initialization must fail a discriminating check. |

Every selected fault has a no-fault control and exactly one intended native hit;
unhit/extra-hit/malformed-plan/trace overflow fails verification. All observable
negative branches assert finite failure,null previous,safe diagnostics,restored
error handler and zero protected result. Partial state is inspected before the
fixture deletes only its own identified resources. A case cannot pass while a
child/container/descriptor/lock remains unexpectedly owned. Bound handshake to8s,
primitive case to15s and outer container to60s; these are test containment,not
Product history limits. Larger actual full-history cases have separately measured
resources under512M and may not cap accepted histories to fit a fixture deadline.

## 5. Mandatory actual-production and CI joins

Native PostgreSQL cases retain every candidate-map obligation: ordinary/control
origin separation; real exact-target evaluator/C3 barriers; bootstrap/root
revision/staged/pending/exact-accepted publication; current root/delegate
compromise and key rotation; complete captured history and fenced endpoint
equality; F2 one-time Clock/UUID and replay; all reservation/preparation/candidate/
persisted-outcome crash points; real winner before/during RR wait,uniqueness
conflict and fresh-root recovery; requested and unavailable COMMIT-response loss
with both cached entries permanently refusing the invalid graph. No signed
ingress acknowledgement/provider/Required release/seal is fabricated while later
F4/D consumers are absent. The actual completed work remains pending/unavailable
at the externally missing continuation,not an overall success substitute.

Native setup/cases must also run in CI on the same final source SHA. The exact
workflow delta and test fixture are part of the worker packet; no existing
required job is removed or silently extended beyond its timeout. The actual CI
image is calibrated independently of the local image ID. Root checks suite
discovery,assertions,inherited diagnostics,peak memory,process/DB cleanup and
all package inventories. Public configuration/CLI/support documentation gets
the assigned source/artifact/browser review and final Owner preview. Parent006
still owns complete same-SHA release CI/export,Framework/Skeleton publication,
Documentation delivery and live proof. No current action is a release claim.

## 6. Remaining issuance bindings

This fixes resources,command families and semantic case requirements. Before
issuing execution,Root merges exact native fixture/class declarations,PHPUnit/
Node targets,CI workflow and the imported authority/custody call map,records all
input hashes and performs complete independent packet review. These are explicit
unissued bindings,not permission for a worker to invent an empty native gate.

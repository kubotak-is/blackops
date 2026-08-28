# D146: ProjectionBuildSet PHP Filesystem Boundary

Status: Proposed — P23-002A1/A2/B/C Accepted; complete projection activation implemented for unreleased 1.3

Updated At: 2026-08-27T23:20:37+09:00

## Current P23-002C preflight contract

Current status: `Accepted — Schedule Projection and Atomic Activation`.
Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted.

P23-002C is the sole Production integration owner for the A/B/C atomic slice.
Initial Sol xHigh preflight returned Changes Requested
`P1=2/P2=2/P3=0`.
The bounded corrections below were frozen as exact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`.
Fresh Sol xHigh re-preflight returned Green `P1=0/P2=0/P3=0`, so Production
implementation is authorized within the exact P23-002C Task Packet.

Luna Max implementation is complete within the corrected exact 41-file Task
scope. The first Orchestrator Final Gate stopped at canonical `mago lint` with
exactly two new cyclomatic-complexity errors; pre-correction aggregate
`0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5` is
Historical. A behavior-neutral class-DocBlock correction passes focused
format, canonical lint, and impacted PHPUnit (`15 tests / 118 assertions`).
The Orchestrator refroze corrected aggregate
`be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`,
restarted the complete Final Gate, and reproduced the same ending aggregate.
Format, Composer strict, canonical lint/baseline, analyze errors `0`, full
PHPUnit `2468/10930`, Deptrac violations `0`, version baseline, Website
release-source, management-ID, diff, and staged0 are Green. That `be0b7c...`
freeze and its Final Gate are Historical/superseded after fresh Sol returned
Changes Requested `P1=0/P2=2/P3=1` and the bounded fixture correction.
The current Orchestrator-frozen exact32 aggregate is
`15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`; its
complete Final Gate is Green with PHPUnit `2472/10972`, canonical lint
baseline filtered 186 with no issues, analyze errors `0`, Deptrac violations
`0`, version baseline, release-source, management-ID, diff-check, staged0,
and ending exact32 unchanged.
Fresh Sol Max closure review independently matched the exact32 and exact9
freezes, returned Green `P1=0/P2=0/P3=0`, and supports Acceptance. The
Orchestrator accepts P23-002C; BlackOps `1.3` remains unreleased.

P23-002C explicitly selects `dirname(app.build.container)` as the
ProjectionBuildSet root.
This is a C decision, not an A/B inference, and it adds no public
configuration key.
`ApplicationBuildConfiguration::projectionBuildSetRoot` is the single
calculation authority used by the status source, Kernel, and build command.
The configured build directory must already exist before publication; the
existing build pipeline has the same requirement for the runtime container.
All unknown entries remain protected and count toward the D146 directory cap.

At runtime, a missing `app.build` section or missing configured build directory
maps to read-only `absent` without creating a directory, lock, or root.
A present but invalid build configuration, invalid build ID, or
symlink/special/unresolvable directory is a configuration-input validation
failure, not an artifact `malformed` state.
After a valid locator is established, permission, I/O, and unreadable failures
remain typed operational failures.

`ProjectionBuildSetStatusSource::withStatus()` is the command-facing seam.
Its Production adapter resolves only the configured build ID and root, then
holds one shared lock through all-three validation, selected-row rendering,
and output write.
Production does not read Source, Provider, Container, DB, or an older manifest
to recalculate the three expected input hashes.
Instead, it validates the root descriptor, all three descriptor/file bindings,
each recomputed projection input hash, and the configured build ID.
The A2/B caller-injected exact-three hash API remains a component-test seam;
Production does not use it.
The Reader's Production method is
`withPublishedStatus(expectedApplicationBuildId, callback, selectedKey)`;
the existing `withReadStatus(expectedApplicationBuildId, exact3, callback,
selectedKey)` contract remains unchanged for component regression tests.

P23-002C advances target metadata to exact 31 rows by adding canonical
`schedule:list` with alias `schedule:ls`.
The existing lazy Framework command-name inventory remains exact 25 and the
pre-release reservation registry remains exact 13.
The Kernel activates one BlackOps-owned `list` family plus route and schedule
families only when all three use this same status source.

`build:compile` produces command, route, and schedule projections from the same
in-memory compilation and calls the publisher once after all existing artifact
writes succeed.
The root remains the last publication commit.
`published=true, cleanupComplete=false` is a committed build with incomplete
cleanup: stdout remains `Build artifacts written.\n`, stderr is exactly
`Projection build-set cleanup is incomplete.\n`, and the command exits `1`
without claiming rollback.

## Accepted P23-002B checkpoint before P23-002C

Current status: `Accepted — non-activated Route Projection and List`.
Initial Sol xHigh preflight returned Changes Requested `P1=3/P2=1/P3=0`.
The bounded correction separates command-only projection status from route
success JSON, assigns B's exact30 target metadata and C's exact31 activation,
requires a callback-shaped typed-status read that holds the shared lock through
render/write, and closes Human/JSON/stderr exact wires. The first re-preflight
returned Changes Requested `P1=1/P2=1/P3=0`; the second bounded correction
separates the intentional 29-to-30-row golden update from lock-seam invariance
on identical input and adds all allowed tests plus Kernel nonactivation to the
Fast Gate. The current bounded correction changed only the five requested
Production lint sites; activation, publication, and public capability remain
unchanged.

Closure Sol xHigh re-preflight matched exact9 aggregate
`991150cdbb60a004657c5c940639ab76f7d0d4826bd27738f6419af40fb5e1ab`
and returned Green `P1=0/P2=0/P3=0`.

Luna Max completed the exact Files Allowed implementation. Fresh Sol review of
the superseded `3ebe655...` freeze returned Changes Requested
`P1=1/P2=2/P3=0`. The bounded correction restores Stable GET `/operations/`
dispatch by separating runtime matching from the nonempty collision predicate,
adds duplicate/cap/closed-row, invalid Boolean, non-selected projection, and
executable purity-sentinel fixtures, and synchronizes this current handoff.

Latest correction-focused HTTP/Route PHPUnit is `80 tests / 396 assertions`
PASS and Task Fast Gate is `128 tests / 1221 assertions` PASS. Canonical `mago
lint`, `mago lint --verify-baseline`, and `mago analyze` (errors `0`),
allowed-path format, management-ID, `git diff --check`, and staged-path `0` all
PASS. No baseline or unrelated analyze warning was changed.

The accepted ordered 41-file Source/Test aggregate is
`174bc94c42b733a7611885f2edc4ab78aad1628c02a3bdd4ed5a48eb2d3df6fa`.
The prior Final Gate on `3ebe655...` is Historical/superseded and is not reused.
The current Final Gate is Green: format, Composer strict, lint/baseline, analyze
errors `0`, PHPUnit `2435/10739`, Deptrac violations `0`, version baseline,
Website release-source, management-ID, diff, staged0, and ending freeze all PASS.
Fresh Sol xHigh implementation review matched the exact Source/Test and
management hashes and returned Green `P1=0/P2=0/P3=0`. Orchestrator Acceptance
is complete without activation or partial publication.

Remaining: none within P23-002B. The higher atomic slice still requires
P23-002C schedule/final activation.

Historical Next Action at P23-002B Acceptance was to begin the P23-002C Task
Packet. The current P23-002C preflight contract above supersedes that handoff.
Do not commit, stage, push, run remote CI, deploy, publish, tag, or release.

## Accepted P23-002A2 checkpoint

Current status: `P23-002A2 Accepted — non-activated Command Projection and List`.
The bounded formatter correction is frozen. P1 correction hashes are List
`1293ba223a43c7edfa925606cf3ef34166101155f7ca93c2846131d4d41bf816`, Renderer
`b5921d015a672feef961b906ff5eafd199eab9dd73c0c304c7989cff142b2fb6`, and
ListTest `2c3f8528fe11c41b4134663db00f7530d248e548cb35face9845ce5ae5ab73d8`.
Correction-focused Projection Command tests are `35/320` PASS; focused lint,
format, analyze error0, management-ID, diff, and staged checks PASS.

The exact ordered 30-file Source/Test aggregate is
`69a3bd57cef3dcea92f2edf4bc4d57c50e7655ac8c2ef3bd0cfe22802cf30740`, identical
before and after the gates. Fast Gate is `130 tests / 1166 assertions` PASS.
Final Gate is PASS: format, Composer strict, lint with baseline186 filtered,
current lint baseline, analyze error0, PHPUnit `2408/10398` (nonblocking
Deprecations1, PHPUnit deprecations2, Notices13), Deptrac `883/883` with
violations0 and Allowed4932, Stable1.2/historical1.1 version baseline,
`release:check:source`, management-ID, `git diff --check`, and staged0.

The bounded Sol xHigh closure review matched the frozen Source/Test and
management hashes and returned Green `P1=0/P2=0/P3=0`. Orchestrator Acceptance
is complete at `2026-08-26T21:03:51+09:00`.

The prior `0999e0ab…` and `1c1614e8…` freezes and failed gates are Historical
only. Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.
The A2 component remains non-activated: no partial publication, Production
locator, build connection, Console replacement, public documentation, or
generated-artifact change is claimed. Website full test/build, Browser,
Network, CI, deploy, publication, tag, release, commit, and stage remain
Not Verified or unapproved.

Remaining: none within P23-002A2. The higher atomic slice still requires
P23-002B route and P23-002C schedule/final activation.

Historical Next Action at P23-002A2 Acceptance was to begin P23-002B without
partial activation. P23-002B is now Accepted and the current P23-002C preflight
contract supersedes that handoff. No commit, stage, push, CI, deploy,
publication, tag, or release is authorized.

## Context

P23-001 accepted `ProjectionBuildSet v1` as the only future command／route／
schedule projection wire. Its contract requires one shared/exclusive `flock`,
same-handle validation, immutable generation files, root-last publication,
directory `fsync`, and generation-safe GC.

The accepted prose also used “nofollow” as though PHP exposed an `openat(2)` or
`O_NOFOLLOW` file-open primitive. The supported PHP runtime exposes `fopen`,
`lstat`, `fstat`, `flock`, `fsync`, and `rename`, but not a portable kernel
nofollow/openat API. Claiming kernel-level nofollow would make the contract
unimplementable without a new native extension or FFI dependency.

The accepted contract did not choose a public configuration key or derive a
production path for the ProjectionBuildSet root. P23-002A/B must not guess that
integration detail because P23-002C alone owns final activation.

## Decision

### Delivery slices

P23-002A is delivered as two bounded packets inside the existing P23-002A/B/C
atomic acceptance slice:

1. P23-002A1 implements the shared wire, injected locator, verified-handle
   filesystem boundary, lock, reader, publisher, and bounded GC.
2. P23-002A2 implements the command producer, embedded Framework projection,
   `list`／`ls`, global reservation registry, Stable input compatibility, and
   the bounded `operation:list --strategy` filter.

Neither packet activates a partial projection root. P23-002B adds the route
producer/commands. P23-002C adds the schedule producer/commands and is the only
packet that may connect all three producers to `build:compile`, choose the
production locator/configuration boundary, and activate the Console readers.

P23-002A2 keeps target projection/activation metadata and pre-release
reservations as separate inventories. One exact 29-row registry owns the
metadata rendered for the current 25 BlackOps commands plus target `list`/`ls`,
`help`, `completion`, and hidden `_complete`; the Kernel's 25 BlackOps
registrations and the command projection producer consume that same registry.
Current Symfony `list` aliases `[]` and target aliases `['ls']` are one explicit
future delta, and A2 does not activate that alias. The other three Symfony
default rows match the target registry in every field. The global pre-release
reservation set remains the exact set in Specification 105 and is applied
inside both public collision-validator entrypoints, independently from names a
caller supplies.

P23-002B extends that disconnected target metadata to exact 30 rows by adding
canonical `route:list` with alias `route:ls`, description
`List compiled HTTP routes.`, and `hidden=false`. This does not change the
current 25-name `FrameworkCommandNames` runtime inventory and does not activate
the command. P23-002C adds `schedule:list`/`schedule:ls` as row 31 and remains
the sole activation owner.

The command producer accepts explicit Application metadata,
collision-filtered discovered Application metadata, and Operation console
metadata as three separate inputs. It includes all three sources, preserves the
existing explicit-overrides-discovered class rule, and never discovers source
or invokes the publisher. This does not connect the producer to
`build:compile`; P23-002C remains the only activation owner.

Phase 3A adds an additive typed reader status seam over the accepted A1
filesystem boundary. Its component API accepts the expected application build
ID and exact command, route, and schedule input hashes. The seam returns only
`available|absent|malformed|unsupported|stale|build_mismatch`; permission/I/O/
unreadable failures remain typed filesystem exceptions. It validates root and all
three projections under one shared lock, applies the fixed status precedence
independently of traversal order, and leaves the existing `read()` API intact.

P23-002C adds the Production published-identity seam described in the current
preflight contract above.
It does not weaken or silently overload the component API.

P23-002B replaces the return-then-render use of that seam with a callback-shaped
typed-status read. The shared lock remains held through complete-set
validation, selected-row rendering, and output write. For an identical exact
30-row input, migrating A2 `list` to the callback does not change its public
bytes or exit behavior. B's intentional 29-to-30-row target expansion updates
the command projection and every BlackOps-owned list available/fallback golden
with exactly one `route:list` row; it does not change the unregistered Stable
Symfony `list` bytes. B route uses the callback from its first implementation.

### Locator

P23-002A1 receives an absolute dedicated directory through an injected
`ProjectionBuildSetLocator`. It does not add an `app.build` key and does not
infer a directory from `container`, `command_manifest`, the working directory,
or source paths. Inside that directory the fixed names are:

- root descriptor: `projection-build-set.json`
- lock: `.blackops-projection-build-set.lock`

For A1/A2/B component use, the caller owns creation, permissions, and
immutability of the dedicated
directory. That directory is an installation trust boundary, not a
cryptographic authenticity claim. Mutation by an untrusted principal inside
the trusted directory is outside the v1 threat model.

For Production use, P23-002C explicitly derives the root from
`dirname(app.build.container)`.
The derived value is exposed only by
`ApplicationBuildConfiguration::projectionBuildSetRoot`; callers do not
repeat the path calculation.
The Production status source never creates that directory during a read.
A missing `app.build` section or missing directory is `absent`; a present but
invalid configuration or unsafe existing directory is a configuration-input
validation failure.

### Current wire and field bounds

The root map is not interchangeable. Its exact mapping is `command ->
blackops_command_projection`, `route -> blackops_route_projection`, and
`schedule -> blackops_schedule_projection`. Swapped or duplicate kinds fail.
The descriptor `path` is a single basename, never a subdirectory path:

- command: `command-<sha256>.json`
- route: `route-<sha256>.json`
- schedule: `schedule-<sha256>.json`

`<sha256>` is the descriptor's exact lowercase 64-hex raw-file digest. Slash,
backslash, dot segments, any other basename, and a basename whose prefix or
digest does not match its root key fail. This single-directory v1 namespace
supersedes the earlier general root-relative POSIX-path allowance.

All strings are valid UTF-8, NFC, and control-free unless a stricter grammar is
stated. Inclusive record bounds are:

| Projection | Field | Current v1 contract |
| --- | --- | --- |
| command | `name`, each `aliases[]` | 1..256 bytes; no tab, comma, CR/LF, or empty colon segment |
| command | `aliases` | 0..32 unique strings in unsigned UTF-8 order; no canonical/alias collision |
| command | `description` | 0..256 bytes; one line |
| command | `origin`, `hidden` | `framework|application`; Boolean |
| route | `method` | 1..16-byte uppercase ASCII HTTP token |
| route | `path` | 1..2048 bytes and begins with `/` |
| route | `operationType` | null or 1..256-byte dot-separated lowercase identifier |
| route | `strategy` | null or 1..1024-byte strategy wire |
| route | `origin` | `operation_manifest|framework_fixed`; operation rows require non-null type/strategy, fixed rows require both null |
| schedule | `name`, `operationType` | 1..256-byte dot-separated lowercase identifier |
| schedule | `cron` | 1..256-byte one-line valid five-field POSIX cron |
| schedule | `timezone` | `UTC` or an available IANA identifier, 1..128 bytes |
| schedule | `strategy` | 1..1024-byte strategy wire |

A dot-separated lowercase identifier is exactly
`^[a-z0-9]+(?:\.[a-z0-9]+)*$`. A command name/alias also matches
`^[^:]+(?::[^:]+)*$` before the stated separator/control exclusions. An
uppercase HTTP token uses RFC 9110 `tchar`, rejects every lowercase ASCII
letter, and obeys the 16-byte cap.

A strategy wire is exactly `inline`, `deferred`, or
`custom:<canonical-FQCN-without-leading-backslash>`. A custom FQCN contains
one or more ASCII PHP identifier segments separated by one backslash; each
segment starts with a letter or underscore and continues with letters, digits,
or underscores. Command records sort by
`(name,aliases)` where the alias list compares lexicographically; route records
sort by `(path,method,origin,operationType,strategy)` with null first; schedule
records sort by `(name,operationType)`. All comparisons use unsigned UTF-8
bytes. Every record object is closed and rejects missing, duplicate, unknown,
wrong-type, unsorted, duplicate-record, non-NFC, invalid-grammar, cap+1, or
cross-origin values. The `inputHash` formula's `sourceRecords` is exactly the
same normalized/sorted array stored as the projection's `records` member.

### Filesystem namespace

The dedicated directory reserves the two fixed basenames above and these exact
families:

- immutable generations: `^(command|route|schedule)-[0-9a-f]{64}\.json$`
- abandoned temporaries:
  `^\.blackops-projection-build-set-(command|route|schedule|root)-[0-9a-f]{32}\.tmp$`

The 32-hex suffix is a 16-byte random nonce. A publisher tries at most `16`
exclusive-create names per temporary role and fails before publication if all
collide. Near misses are unknown entries: they count toward the scan bound and
are never removed. A basename matching a reserved family but represented by a
symlink, directory, FIFO, socket, device, or other non-regular entry fails the
publication preflight.

An existing generation target is reused only when verified as a regular file
and its exact bounded bytes equal the newly canonicalized bytes, including the
one final LF; its digest must equal both descriptor and basename. Any mismatch
fails before a temporary, generation, root, or deletion mutation. The
publisher never replaces an existing generation target. The dedicated
directory must not be used for unrelated files that match a reserved family.

### Verified-handle protocol

Within this trusted directory, “nofollow” in the earlier proposal means the
following PHP-implementable fail-closed policy; it does not claim that `fopen`
sets a kernel `O_NOFOLLOW` flag:

1. Validate the dedicated directory as an absolute, existing, non-symlink
   directory and retain its canonical path for the operation.
2. Because every v1 file is a single basename, reject `/` and `\\` before path
   assembly. Before opening an existing lock, root, projection, generation, or
   recognized temporary file, use `lstat` and require the expected regular-file
   type. No intermediate component exists below the validated root.
3. Open the path once. Before reading or writing any bytes, use `fstat` on that
   handle and a second `lstat` on the path. Require regular-file mode plus the
   same device and inode in all observations.
4. Read, hash, validate, flush, and `fsync` through that same handle. Do not
   close and reopen a validated path for the same operation.
5. Recheck the held lock path against its locked handle after acquiring
   `flock` and before publication or reader output. A missing, replaced,
   symlink, directory, FIFO, socket, or device path fails closed.

An absent lock is a typed `absent` read failure and readers never create it.
For the first publication, the publisher performs a read-only directory-count
preflight and accepts at most `255` existing entries, reserving the 256th slot
for the lock. It may then initialize the lock with exclusive-create `x+b`
semantics; `EEXIST` caused by another initializer switches to the existing-lock
path. An existing path is opened without truncation. The empty fixed lock is a
permanent bootstrap artifact, not projection publication data. If a later
under-lock preflight fails, that newly created lock may remain; no temporary,
generation, root, or deletion mutation may occur.

After exclusive `flock`, the publisher rechecks the lock handle/path and scans
the directory again. `.` and `..` are excluded; root, lock, generations,
temporaries, and unknown entries all count. At most `256` entries are accepted;
the implementation may observe only one 257th sentinel to detect overflow and
does not inspect its content. Readers do not perform GC scans.

Before the first temporary write, the publisher also calculates the
pre-GC committed entry count: current entries plus each absent new generation
target plus one when the fixed root is absent. That count must remain at most
`256`. Because replacing an existing root briefly adds a root temporary next
to that root, `projectedCommitted + (rootExists ? 1 : 0)` must also be at most
`256`. Later GC must not be needed to make a current or crash-left directory
scannable. Temporary creation and same-directory rename are sequential, so
this formula covers the sole additional temporary entry.

The publisher acquires exclusive `flock` before creating, writing, renaming, or
removing any generation or temporary file. Readers acquire shared `flock`
before opening the root and hold it through all-three validation, row selection,
and rendering.

### Publication and GC

The publisher accepts all three complete typed projection inputs in one call.
It canonicalizes and validates the exact complete wire before filesystem
mutation and rejects a missing, duplicate, or swapped kind. Under the exclusive
lock it validates an existing root as either absent or a complete valid old
set, validates every existing generation target with the matrix above, then
writes new `x+b` temporary files, flushes and `fsync`s them, and atomically
renames only absent immutable generation targets. It `fsync`s the directory,
then writes/fsyncs/atomically replaces the fixed root path last and `fsync`s
the directory again. It never overwrites a generation referenced by the old
root; replacing the fixed root path is the single publication commit.

The durable root rename plus following directory `fsync` is the publication
commit point. Before it, failure leaves the old root current and performs no
GC. After it, GC failure cannot roll the root back: the publisher returns a
typed result with `published=true` and `cleanupComplete=false`, leaving the
remaining candidates for a later bounded run. Successful or partial deletion
is followed by another directory `fsync`.

GC is deterministic and bounded. One publication accepts at most `256`
directory entries and removes at most `32` verified regular paths matching the
exact generation/temporary families. It computes and unsigned-byte-sorts the
post-publication candidate set before the first generation write. A 257th entry
or 33rd candidate fails before projection mutation, subject only to the stated
first-lock bootstrap artifact. Unknown files are never deleted. The fixed
root/lock and all three generation files referenced by the newly current root
are never eligible.

P23-002C connects publication only after the runtime container, Operation,
HTTP, Frontend, and command artifacts have all been written successfully.
It creates all three projections from that one in-memory compilation and makes
one publisher call.
A failure before the root commit emits no success line and leaves the old or
absent projection root current.
A post-commit cleanup failure leaves the new root current and is reported by
the exact stdout, stderr, and exit contract in the current preflight section.
This is atomic publication of the three ProjectionBuildSet files only.
It is not a transaction across the already-written runtime container,
Operation, HTTP, Frontend, and command artifacts, and those earlier writes are
not rolled back when projection publication fails.
`ApplicationBuildCompileCommand` accepts an optional exact-root Publisher for
deterministic component tests and constructs one Production Publisher by
default.

## Consequences

- No native extension, FFI, shell helper, dependency, or platform-specific
  binary is introduced.
- Static symlink/special-file attacks and detectable path replacement fail
  closed. The contract does not claim protection from a principal that can
  mutate the trusted directory during the short `lstat`／`fopen` interval.
- A1 can be tested with a temporary injected root and synthetic complete
  command/route/schedule inputs without publishing a partial production root.
- Public Stable `1.2`, its Symfony-backed `list`, public configuration, docs,
  generated artifacts, Release Authority, and runtime behavior do not change.
- P23-002C cannot activate the feature until the Production status source and
  all three real producers are wired and the atomic A/B/C gate is Green.

## Rejected Alternatives

- Inferring a Production root during A1/A2/B: rejected because those packets do
  not own the integration boundary. P23-002C now explicitly selects
  `dirname(app.build.container)`; `dirname(command_manifest)` remains rejected
  because the optional manifest override can diverge from the required build
  directory.
- Requiring FFI or a new native extension solely for `openat`/`O_NOFOLLOW`:
  rejected as a new deployment dependency for metadata listing.
- Treating `lstat` before `fopen` alone as nofollow: rejected because the
  opened handle and the post-open path must also be compared before I/O.
- Activating command-only roots in A: rejected because A/B/C is one atomic
  complete-set acceptance slice.

## Remaining Steps

P23-002A1/A2/B are Accepted and remain non-activated.
P23-002C has a Green complete Final Gate after the bounded fixture/management
correction. The prior exact32 freeze and Final Gate are Historical/superseded.
Fresh Sol Max closure review returned Green `P1=0/P2=0/P3=0`, and the
Orchestrator accepts P23-002C. No work remains within P23-002C. The later
Specification 106 tasks and P23-006 remain.

## Suggested Next Action

Begin the P23-002D `diagnostics:check`／aggregate-only `queue:status` preflight.
Do not commit, stage, push, run remote CI, deploy, publish, tag, or release.

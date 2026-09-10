# P23-008: Archify Documentation Diagrams Report

Status: Verification in progress — diagrams and standalone review Green

## Summary

Added two real Archify diagrams to the public Core Concepts and Outbox guides:
HTTP/Operation CLI through the shared execution model, and same-transaction
Outbox registration through Commit, Relay, PostgreSQL Transport and Worker.
Japanese prose and alt text explain the same facts. Inline PNGs have a named,
keyboard-scrollable region; same-tab links open the interactive viewer, with
its English controls disclosed and browser Back returning to the guide.
Existing four Mermaid sources remain byte-identical.

## Changed Files

- `docs/guide/core-concepts.md`, `outbox.md`, and two `assets/diagrams/*.png`.
- `docs/website/public/diagrams/{runtime,outbox}.{html,json}`.
- `docs/website/diagrams/manifest.json`, `offline-fonts.patch` and README.
- Website manifest/visual helper, content/artifact/reader/release guards, focused
  failure fixtures, package scripts and figure-only CSS.
- Task/Report, STATE/archive, spec Index/TODO; prior D2S1 delivery receipt only.

No PHP source, PHP tests, dependency lockfile, global configuration, public H1,
slug, navigation or Release Authority change. Root owns final integration,
verification and STATE; Luna workers owned separate diagram/website files.
Documentation Reviewer remained read-only.

## Decisions and Assumptions

Use [Archify](https://github.com/tt-a1i/archify) pinned to
`2ead014aa8ec91f104cd052f1a6ca82de5e26c31` (package2.17.0-dev.1).
The actual CLI validates and delivers each diagram; this is not a visual
imitation. Disable update checks during generation. Ordinary build checks
saved local artifacts without installing Archify or contacting a CDN.

The explicit manifest binds owner source/route, JSON, HTML, PNG and receipts.
Only exact registered viewer/source links are permitted. Guards cover tracking,
real paths/symlink parents, hashes, missing/orphan files, owner links, release
claims and byte-identical build copies. Unknown flat HTML is rejected; only
registered viewers skip BlackOps-specific page layout assertions. The normal
40 reader routes, 41 Search records, raw/LLM parity and common guards remain.

A documented generation-time patch removes upstream Google Fonts and embeds
the exact MIT notice. One dataflow stage-heading SVG class changes from t-dim
to t-muted so the contrast correction also survives canonical PNG export.
No delivered HTML is edited. Root replayed the mechanical two-file patch from
the pristine pinned archive and verified both resulting source hashes.
Patch SHA256: `33c5b2263ea0162e8d143158f313c5ffa17a45df13aead72e9cb78ebf457ee30`.
Template/renderer before-after hashes are preserved in the manifest.

The stock upstream browser runner timed out when reusing Chrome at the fourth
viewport. The optional repository helper calls exported unmodified
`runVisualCheck` with a fresh original `ChromeVisualBrowser` per observation;
measurement, capture and assertions are unchanged. This helper, not the bare
CLI browser command, produced the final visual receipts.

The first website content check found a pre-existing Stable/current-source
mismatch: 218 PublicApi types versus the correct Stable216. Root identified
nine exact files absent from the immutable Stable source: two Audit providers,
six CLI commands and ApplicationAuditConfiguration. The Packet permits only
exact-file exclusions plus the sole added ApplicationBuilder method
`withOperationalHealthQuery`, all bound to the current Stable framework tuple.
Tests cover unknown paths, similarly named methods and authority changes. No unreleased API is added to guides,
no broad namespace is excluded, and216/25 coverage counts remain required.
Ordinary checks continue to work offline in a shallow checkout.

## Frozen Diagram Evidence

| Diagram | JSON SHA256 | HTML SHA256 | PNG SHA256 |
| --- | --- | --- | --- |
| runtime | `d561437dce625ca0dd72cdaee07e1e3ebdea1482433a884e16ee570f6b61cf68` | `368b7549cd34c47f03970990459dd7565e7cc4d90a4ff2e07a25755e7a48b034` | `0664ee78da0fa9a3eb26d910eebd2e797763c28d2b6903142ed6689306cb3716` |
| outbox | `6fd3791102bbb74bf38f0961f7ecdb4d0620d26d3df7c2c1c77f79d2f9eea43d` | `71d044365e32d628d56c9b48b59f71627d211bb927522d4ca9fb0d88f45134bb` | `77a975eba0feefcf122e0e7ac74dc0af9bc195671eb430391d2872ddff70396c` |

Both actual validate/deliver receipts pass showcase9/9 with zero errors/warnings.
Runtime PNG is5160x2020,493404bytes; Outbox is4320x2000,483976bytes.
PNG export used the real viewer Export → Image → PNG in Light. A second export
after zoom is byte-identical, proving canonical output excludes viewport crop,
viewer controls and overlays. Export observed no external requests/page errors.

The final browser helper passes containment, readability and viewer controls at
1440x900,1600x1000,1920x1080,2048x1320. Light/Dark screenshots at1440 and2048
were inspected independently. Minimum projected node text is9px for runtime,
7px for Outbox at overview scale; the viewer provides zoom and the inline PNG
keeps an authored1728px width inside its scroll region.

Runtime facts had two P1 findings (Value binding must precede strategy selection;
CLI means Operation CLI, while Response mapping is HTTP-only). Both were fixed
and independently closed against Stable source. Outbox transaction/delivery
facts and both Japanese guide/alt additions are Green. The stage-heading P3 is
closed on the final Outbox HTML/PNG. Standalone review: P1=0/P2=0/P3=0.
Upstream receipts retain `visualReview: pending`; that automated field is not
misrepresented as human review. This Report records the independent review.

## Commands and Results

Final website and integrated browser results are pending. Evidence directory:
`/tmp/blackops-archify/gate/` and `/tmp/blackops-archify/site-review/`.

The initial real `pnpm --dir docs/website run content:check` failed in2.472s at
the PublicApi216/218 boundary above; preserved as `gate/initial-content-check.*`.
The next1.600s check reached the sole added ApplicationBuilder method, recorded
as `gate/second-content-check.*`; the exact method exclusion closes that drift.
Sandbox Node child spawn returned EPERM with empty output despite child status0.
Worker default-isolation test summaries therefore do not count as acceptance.
Root's full package test uses `--test-isolation=none` outside that sandbox.
Browser checks use cached Playwright1.61.1 Chromium in Docker, network disabled;
temporary browser-only dependencies live in /tmp, not the repository.

Additional evidence in `/tmp/blackops-archify/`:
- `semantic-preservation.json`: final authored changes after facts review are geometry only.
- `mermaid-preservation.json`: all four existing Mermaid block hashes unchanged.
- `release-occurrences.json`: changed guide version occurrences unchanged from HEAD.
- `stable-reference-boundary.json`: all nine excluded paths absent from Stable.
- `stable-public-api-drift.json`: exhaustive PublicApi source diff; only the
  two new types and ApplicationBuilder differ, with one added public method.
- `final-v3/generator-provenance.json`: patch replay and generation provenance.
- `final/` and `final-v3/`: runtime and final Outbox CLI/browser/export receipts.

PHPUnit, PHPStan, Mago, Deptrac and package export are not rerun for this
public-documentation/Node-tooling change. The separate D2S1 completion remains
Accepted at `70f9c4733bed8bb0960165cddf8c451b78b96184`, with clean same-SHA
Git/Composer export and its own final evidence. P23-007 workflow checkpoint is
`4fbd647`; these prior results are not used as diagram delivery evidence.

## Acceptance Criteria

Pending final website gate, actual guide/viewer navigation and integrated
visual review. Real generator/facts, canonical PNGs, standalone desktop review
and exact provenance are complete.

## Release Documentation Impact

Experimental Stable1.2.0 /1.3 unreleased. Framework peeled source
`3332fd1dd0738fc7e79750facd93d49a59054ecf`, Skeleton
`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`; no new Capability ID/API.
Owners: `/concepts/core-concepts/`, `/execution/outbox/`. Supplemental assets
are manifest-controlled. Historical version allowlist is unchanged. Source,
Artifact, Search/raw/LLM checks and positive/negative fixtures belong to the
website gate. Remote same-SHA CI/documentation delivery: Not Run.
Production deploy: none. This is local documentation work, not release closeout.

## Remaining Issues

Finish local website/browser verification and closeout. Public delivery has not
been requested. D2S Status adapter, other reader children, D3/D4/D5, parent D
integration and post-Accepted-F control remain separate development work.

## Suggested Next Action

Freeze the bounded source-reference correction, run the final website gate,
inspect both guide/viewer routes at desktop Light/Dark and390px, then record
acceptance and rotate STATE through its guarded writer.

# P23-018 Report

Status: Accepted — isolated delivery and canonical-origin proof complete

## Summary

The owner's Landing Hero Reel handoff is isolated on
`agent/p23-018-landing-hero-reel` (merged through PR14), based on published main
`08e4751c62a2a83ba0401f5aee97164984a27f5c`, in
`/tmp/blackops-reel-20260927/candidate`. D5P and earlier documentation remain in
the original worktree and index. No PHP source or Release Authority is changed.

D167 retains the large video hero and What's BlackOps/Install CTAs, with the full
Stable installation command and Operation example immediately below. Both the
Reel and Execution Walkthrough autoplay under reduced motion as explicitly
selected by the owner. Effective pause, lifecycle suspension and accessible
fallbacks remain ordinary Acceptance requirements.

The first same-SHA production delivery succeeded at3f38dd85, but actual Pages
chapter seeking failed because its media responses do not support HTTP Range.
The correction was delivered through PR15 at `dc74bbf95c84576dad5dc9ef82084e67e84a934d`.
It lazily uses the selected format only after an explicit chapter request; ordinary
autoplay is unchanged. Same-SHA CI, production delivery, canonical-origin proof
and independent delivery review are complete.

## Changed Files

The Task lists the sixteen inherited paths. Bounded corrections additionally use
`scripts/landing-reel.mjs`, `tests/landing-reel.test.mjs`, website README,
package.json and the ten-line Playwright lock delta. Root owns this Report/Task,
D167, the narrow specs59/85/86/96/110 amendments, index/TODO and STATE/archive.
The only theme.css delta in the candidate is the appended Reel block and separator.
Downloaded fonts, rendered frames, node_modules and dist are excluded from Git.

## Decisions and Assumptions

- D167 supersedes only the conflicting layout/motion clauses of D118/D119/D159;
  the historical decisions are unchanged. Install remains available before
  scrolling at desktop sizes; the copyable command follows the hero.
- Manual pause stops phase progression AND arrow animation. Reader pause intent
  survives viewport and page lifecycle reinitialization until explicit resume.
  Other reduced-motion transition rules remain. This is not a WCAG certification.
- The movie is illustrative. Journal/Operation IDs/counts and terminal examples
  are labeled; inspection output is an explanatory annotation, not fabricated
  stdout. The corrected Headless example supplies reportName/recipientEmail,
  guards the accepted result and bounds wait with signal/maxWaitMilliseconds.
  Sensitive defaults to omission in Observed Journal; explicit Mask is distinct
  from unchanged Canonical Input. Lifecycle event/state mapping is corrected.
- Playwright-core1.63.0 is now a pinned repository devDependency, paired with
  its Docker browser. ffmpeg >=6 with libx264/libvpx-vp9 is checked explicitly.
  Ordinary site builds use committed assets and do not render video. Equal
  sizes from the inherited run were not accepted as byte determinism evidence.
- Render portability required two actual corrections: accept a static ffmpeg
  version suffix, and create the nested read-only dependency mount target.

## Commands and Results

Evidence root: `/tmp/blackops-reel-20260927/`. Each `run-*/receipt.json` records
argv, working directory, input hashes before/after, start/end, elapsed seconds,
exit status, helper hashes and output.log SHA256. `command-catalog.json` indexes
these receipts. Root serially owns render/build/browser outputs; the worker edits
only assigned candidate files. All commands originate in the WSL repository root.

Inherited Claude claims (177 tests, build/check42 pages, Chromium browser matrix,
equal-size rerender) were reported by the owner without raw logs or input hashes.
They are attributed and are not adopted as current candidate/same-SHA evidence.

| Verification | Result / evidence |
| --- | --- |
| Own dependency install | PASS, 7.598463s; run-20260927T030528546216-install |
| Final correction-focused tests | PASS6/6, 1.258565s; run-20260928T003138120411-focused |
| Ten source-frame visual/bounds checks | PASS, 3.235248s; run-20260927T235133853666-inspect-reel |
| Final1080-frame render | PASS, 223.605905s; run-20260927T235137123290-render |
| Release source guard | PASS, 1.262593s; run-20260927T235711680154-source |
| Website test/check/build/artifact before media-failure correction | PASS175/175,274.970907s; check16.806389s/build30.741716s/artifact2.048365s; run-20260927T235712975777-test and run-20260928T000153601829-check /000211074951-build /000242571795-artifact |
| Final browser matrix | PASS12/12, 68.3281s; run-20260928T003711797150-browser |
| Final player build | PASS, 28.622154s; run-20260928T003139412154-build |
| Page Visibility event emulation | PASS, 1.931471s; run-20260928T003657799949-hidden |
| Lighthouse mobile | Completed, 12.000824s; run-20260928T003659764714-lighthouse;63/100/100/100 |
| Final whole-website tests | PASS175/175, 273.681656s; run-20260928T003822352827-test; log SHA256 ba6602108d2e1422ab3368c6ec0ffe0de9d2098c83dfa7ad558b035c2d4e177f |
| Final check / artifact / diff | PASS13.757723s /2.018565s /0.120673s; run-20260928T004305225798-check /004319015574-artifact /004321065868-diff |
| Independent final review | Green, no open P1/P2/P3; documentation-final-review.md; SHA256 0aaf94a991ec897020aa0f161de2de9626218d940bde109cd705227c52562080 |
| First same-SHA CI/documentation production delivery | PASS at3f38dd85; live seeking failed and was corrected through PR15; see post-delivery evidence |

Failed attempts are retained: sandbox dependency-cache/network failure; two
focused expectation/test-order corrections; CLI footer/Typed card bounds and
retry-label overlap; first render's missing nested mount target. No failed run is
relabelled PASS, and the inherited earlier images do not prove final asset bytes.
The image overlap was independently cleared on the final source and actual PNG.

Initial browser run-20260928T000356001320-browser failed overall. Natural36-second
play/end/no-loop passed, but chapter seek used a server without HTTP Range, and
the autoplay rejection fixture still allowed native attribute autoplay. Those
verification conditions are corrected, including viewport scroll/screenshot checks.
A real all-source404 failure left fallback text hidden; the worker adds source-error
aggregation and preserves independent terminal video errors. Initial partial
browser success is not used as final page Acceptance. Range response is now
206 with exact100-byte body for bytes100-199. Earlier long page screenshots
are not accepted as first-viewport visual proof.

The first locally accepted media handling covers source-error aggregation, a terminal video error,
pending play promise rejection, and an error arriving before enhancement. One
failed WebM source still permits MP4; complete failure pauses pending playback
and preserves the media-specific message. Final full browser evidence confirms
both fault paths and autoplay rejection. Normal cases have no JavaScript errors;
intentional404 fixtures retain their expected network errors.

The first locally accepted matrix covers1440x900,1920x1080 and390x844 in both themes, keyboard focus,
CTA/heading visibility, no horizontal overflow, unobstructed native controls,
36-second natural end/no-loop, explicit replay/chapter seek, no-JS native controls,
reduced-motion autoplay and effective pause for both players, offscreen and
pagehide/pageshow cleanup. Screenshots are viewport captures at scrollY0.
Axe on changed hero/source/walkthrough scopes reports zero violations in both
1440px themes; its incomplete aria/contrast/caption checks are not a certification.
Source-token manual contrast (`control-contrast.json`) is3.39:1(light) and14.81:1(dark) against the player, and
control text exceeds9:1. The video has no audio and retains chapter/guide text.

Native tab switching under Docker/Xvfb never changed document.visibilityState;
three failed harness runs are retained. `hidden.json` explicitly records the
replacement's scope: emulated Page Visibility properties/event, observing real
video and trace pause/resume plus persistent reader pause. This verifies the
visibility handler, not physical tab switching in a desktop browser.

Lighthouse13.5.0, mobile simulation, records Performance63, Accessibility100,
Best Practices100 and SEO100, with FCP4.731s/LCP10.286s/CLS0/TBT0 and no runtime
warning. This uncompressed loopback server has no cache headers; these are not
production CDN measurements or evidence of fast mobile loading. Total measured
transfer is4,446KiB, including2,808,983 bytes for the selected WebM and173,798 bytes
for its poster; MP4 is not also requested. Render-blocking shared CSS/font work
and the large media contribute. Preserve this limitation and measure the deployed
origin; do not infer performance compliance from the asset-size limit.

`environment.json` pins Node24.18.0, pnpm11.12.0, Playwright-core1.63.0 and
Docker mcr.microsoft.com/playwright:v1.63.0-noble digest
`sha256:eff16c30e6f3f4af0a03fa4b706120d5e9b0891c344a27d64559aff5900a4a27`.
Actual ffmpeg7.0.2-static SHA256 is
`e7e7fb30477f717e6f55f9180a70386c62677ef8a4d4d1a5d948f4098aa3eb99`.
The full Noto font is verified as
`c2f3b4d463500a2ddcd3849cded1fceeb9fd6d1c32e6cbecd568453ba50fc68f`
from the existing pinned Google font revision. The font is not committed.

`encoded-media/manifest.json` records successful complete decoding of both movies:
1920x1080,30fps,36.00s, H.264/VP9, no audio. The reviewed source SHA256 is
`b5f765eeee304b0319ad2c9d0fccda9ab9b30edeb15c5f6df96a0d5cdff17362`.
Root inspected actual encoded frames at17.5/23.9/27.2/33.5s; source-frame evidence also
covers all scenes. Asset identities:

| Asset | Bytes | SHA256 |
| --- | ---: | --- |
| blackops-reel.mp4 | 4221436 | fd0dc4cba895679cad34a8adafec9471b8a3a2ce615af19dff6486682e663756 |
| blackops-reel.webm | 2808763 | 7e89407a3e9b22d4081ce93844f4c74d6e7f05ff3a92cb29bdabf33548881f5e |
| blackops-reel-poster.jpg | 173586 | fdc1709e9321b5e5c4bc312cec0091e48ead5e8523577bd268bd11d5c579be81 |

Total repository media addition is7203785 bytes. Every asset is below the
[Cloudflare Pages25MiB per-file limit](https://developers.cloudflare.com/pages/platform/limits/)
checked2026-09-27. Browser transfer is measured above; live Content-Type/bytes require delivery
proof. [W3C2.2.2](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide.html)
and [2.3.3](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html)
were reviewed for D167; this Task does not claim full WCAG or Level AAA conformance.

## Release Documentation Impact

Changed Capability ID: none (documentation-site presentation only); no Framework/Skeleton
API, command behavior or configuration change. Landing `/` and the three media
assets change, so this is not an absence-of-documentation-impact claim.
Experimental Stable1.2.1 remains authoritative;1.3.0 stays unreleased.
Authority SHA256:
`bfdc4ac892bfcf41ce499d73301eb8bfd8232bd723cd4ca3a804e6dfbbd44b43`.
Framework1.2.1 direct/peeled:
`1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3` /
`4efee09f13bebedc1639333f79550a36a4e8ca91`.
Skeleton1.2.1 direct/peeled:
`7a47e344ecba6193628119f0e95cb23dc5e0be83` /
`87a025380df8abcd0c514abf50e139dbbc1953c7`.
Remote references and immutable CLI/API source were checked; the clean Stable
Skeleton confirms the input example and project-root CLI. Current dirty1.3 source
was not substituted for released implementation evidence.

`public-source-inventory.json` pins64 guide/map/diagram text sources and41 public
routes. Every source byte,159 version occurrences and six historical allowlist
entries equal the base. Existing Stable/current, historical and unreleased roadmap
classifications are unchanged and pass the Source guard. The Reel contains no
Framework/Skeleton release version numbers (PHP8.5 is the target platform). Its authoring HTML is outside the automated release-source
scanner: static tests plus independent guide/immutable-source review cover it;
we do not claim automated release coverage that the scanner does not provide.

Final full tests cover positive/negative Release Authority fixtures, current/stale
claims, mapping lanes, historical misuse, diagram drift and HTML/Search/raw/LLM
boundaries. The final local `seek-artifact-manifest.json` pins417 files/39,863,386
bytes, including41 raw Markdown and41 MDX files, Search/LLM outputs and registered
diagrams; manifest SHA256 is `ad3875e18de2f53c3d12f694b5b5dc1b15c3ccf403d6fa112511ee997b29f240`.
The original pre-seek `artifact-final-manifest.json` remains historical evidence;
only the player bundle and its HTML reference changed after it. Source/media and
415 other generated files remain exact. Clean PR/production artifacts and actual
public HTTP responses are bound below to the reviewed payloads. Local PHP gates
are not needed for this documentation-only delta; the existing full remote CI
workflow passed all six jobs on the delivered main SHA.

## Review and scope evidence

`documentation-source-review.md` records the initial P1/P2 findings;
`documentation-correction-source-review.md` records their source closure and the
final P3 visual overlap closure. `documentation-final-review.md` independently clears the final source, encoded media, browser evidence and all final gates. The first seven local criteria were accepted with the stated visibility/accessibility/performance evidence limits. Actual production seeking reopened the affected player/browser criteria; the corrected source, browser and delivery reviews now close them. Unchanged movie/source evidence remains valid.
`scope-preservation-final.json` reconfirms at2026-09-28T00:40:17+09:00 all sixteen original
handoff files and the original staged patch are unchanged, the older theme prefix
is preserved, the isolated candidate contains its base CSS prefix and no PHP delta.
D5P local Acceptance is complete independently; no D5P commit/tag/release is made.

## Acceptance Criteria

All nine Packet criteria are Accepted. The isolated correction and resulting main
merge have identical trees. Frozen local gates, independent source/browser/media
review, same-SHA CI/production delivery, exact Artifact binding and actual canonical
origin chapter/end/pause/replay behavior support the completed result. The earlier
production seek failure remains recorded as a failure that was corrected.

## Remaining Issues

No unresolved P1/P2/P3 or required P23-018 gate remains. Measured mobile Lighthouse
Performance78/LCP6.180s leaves performance improvement room; no numeric budget was
specified or relaxed. Native no-JS play/pause remains usable, while native seeking
still has the Pages limitation. Chromium-only, physical-tab/BFCache and accessibility
sampling limits remain explicit. No whole-site WCAG conformance is claimed.

## Suggested Next Action

残り工程: P23-018 はなし。上位1.3では D5 rotation/replay/generic Outbox、通常構成への統合、E/F/G、Accepted F 後の D-control、hardening、documentation/local user review、release が残る。
Next Action: 元の1.3作業を再開するときに公開済みReelのmain履歴と保存したhandoffを照合する。D5Pは未コミットのまま保持し、古いReelのhandoffをそのままstageしない。次の実装は別Task Packetで扱う。

## Post-delivery finding and correction history (2026-09-28)

Isolated commit301acc1 (32 paths) was merged through PR14 into
3f38dd85c0a4bc369f849565f306d9d7b58e0c0b, with identical Git trees.
CI36335317880 (all six jobs) and Documentation36335318003 passed on that main
push/actual checkout. Production deployaa20f00c completed; downloaded417-file
Artifact exactly matches the PR artifact. The PR run head301acc1 is distinct from
its synthetic checkout5f71f624b83588ae082e8cc00d289f89782bfd68. Local/PR differences
are only seven enumerated generated identifiers; both font payloads and all
normalized417 files match. Independent remote-artifact-review.md confirms this.

Live browser run021043616894 fails overall: natural36s playback/end and no-JS
pass; chapter/currentTime seek resets to zero. Range GET returns200/full bytes.
[Pages serving behavior](https://developers.cloudflare.com/pages/configuration/serving-pages/)
explicitly documents200 for Range requests (checked2026-09-28). The earlier local
206 server failed to model this condition. A bounded player/test correction is
required; this result is not Accepted or relabeled PASS. Lighthouse live was not
run because the serial browser gate failed.

Python's default User-Agent receives Cloudflare403/error1010; ordinary browser
User-Agent and curl receive200. The failed requests are retained separately.
The live HTML also has a Cloudflare Pages Analytics footer absent from the uploaded
Artifact, so raw HTML equality is false. `cloudflare-analytics-classification.json` pins the exact214-byte injected suffix.
`live-http-classified.json` passes49 HTTP comparisons after removing that exact
suffix only; media/Search/raw/LLM match without normalization. Both Range probes
return200/full identical media, accurately recorded as full-response behavior,
not partial-range support. No Cloudflare access/security setting is changed.

The direct canonical-origin probe (`probe-seek.json`) confirms that both fully
buffered formats have seekable[0,0]; native28.2s seeking returns0. Fetching the
selected bytes as a Blob gives seekable[0,36] and successful28.2s seeking for
WebM and MP4. No media rerender is needed. A local4330 server now models200/full
responses instead of206. New browser cases delay fallback fetches and check latest
chapter, manual pause, payload reuse, cleanup/abort/revoke and failure guidance.

The first corrective focused run022923549640 failed1/6 in1.643638s: latest chapter
expected12.5 but remained0. Root also found that a paused loading video exposed a
play action, so pending autoplay could not be paused through the custom control.
The worker is correcting both before final gates. This failed receipt is retained.

The second focused run023331902044 failed at the same latest-chapter assertion
in1.26112s. Source review additionally found that the pending toggle's play label
did not match its pause action. Root reassigns the focused unit command alone to
Luna for a verified correction; Root retains serial build/browser/final ownership.

Worker's exact focused command subsequently passed6/6 in1.333819693s
(`worker-seek-focused-pass4-output.log`, Node duration938.120616ms). The fixed
observable async wait replaced an insufficient fixed microtask count; pending
control labels/actions and native-autoplay races also received actual corrections.
Root build024049160763 passed in28.811262s with unchanged inputs. Actual no-Range
run024119478077 passed5/6 in17.793381s: delayed latest target/manual pause/reuse,
pending resume, fetch abort, MP4 selected format and503 guidance/native playback.
The end-frame lifecycle case failed: re-init restored the native source with
load(), resetting36s/ended to0s/not ended. Although playback stayed paused, this
did not preserve the ending frame. Worker corrects the unnecessary reload before
final Acceptance. The failed receipt and screenshot remain evidence.

Removing that reload also removed the unnecessary shadow-ended state. Focused
6/6 passed in1.322180843s (`worker-seek-ended-pass-output.log`), build024333884866
passed in27.300368s, targeted no-Range6/6 passed in15.802996s, full no-Range12/12
passed in71.287127s, and normal206 affected4/4 passed in19.883023s. Those automated
results covered their stated assertions; they did not prove that every visible
status was correct. Root's actual mobile screenshot review and independent
`seek-source-review.md` identified three remaining reader-facing findings:

- SEEK-01 (P2): successful paused/hidden chapter loading leaves the loading status.
- SEEK-02 (P3): pending pause action retains a play icon; mobile hides the text.
- SEEK-03 (P2): an intentionally interrupted play promise can show an autoplay
  error, and immediate post-chapter pause intent needs explicit real-state proof.

Root assigns these together before the final whole-website gate. Browser
assertions now include settled pause/no obsolete status, and mobile captures of
pending pause/resume icons plus completed paused media. No successful automation
is relabeled as full visual Acceptance while these findings remain open.

## Final corrective local Acceptance (2026-09-28)

The final player uses a native seekable range when available; otherwise an
explicit chapter request shares one selected-format Blob fetch and waits for
its actual seekable range. The latest target and reader pause/resume intent
survive pending loading. Native keyboard controls, successful/aborted status
cleanup and mobile action icons agree with actual playback. Stale or deliberately
interrupted play promises cannot overwrite current status with an autoplay error.
Cleanup aborts/revokes resources without reloading the selected media, preserving
the real ended frame across reinitialization; explicit replay still works.

SEEK-01/P2, SEEK-02/P3 and SEEK-03/P2 are closed by actual screenshots and browser
assertions, not only unit expectations. The final targeted suite also catches
pending abort/re-init, native keyboard resume and ended/replay after URL revoke.
A separate smooth-scroll harness race was traced in `trace-pause.json`: offscreen
suspension had already changed the action to play before the test clicked it.
The harness now establishes the viewport/pause action before clicking. No product
workaround was added for this test condition; earlier failed logs remain retained.

Final Product source SHA256:

- Player: `d97b05d66a6c4df5c2e6aa3c70ec58dd9cc3bf5745440eacc9624be2437ee28b`.
- Test: `b82c7f048887b0435aefa404eba379c9799060ae6f32938d26515630f1e17816`.
- All final Root receipts use unchanged before/after inputs
  `46b5faaa4d8bc745896454f9eaf061c1175ff4901527e7b22b064779fa0b3592`.

Luna's focused6/6 pass (`worker-seek-cleanup-pass-output.log`,1.330341169s,
Node917.660539ms) is reused; Root does not repeat it after the full test gate.
`seek-final-gates.json` indexes the following receipts; each directory contains
argv/environment, exact inputs, elapsed time, output log and its SHA256.

| Final Root receipt | Result | Seconds | Output log SHA256 |
| --- | --- | ---: | --- |
| `run-20260928T025656700204-build` | PASS | 27.553523 | `89b0afe5588651e9507c2bb053be8eda67c0d28a4424d1d6aaa383c52378624c` |
| `run-20260928T025725610021-seek-no-range` | 9/9 PASS | 15.855401 | `74634f44f761bbc7ee607389c6d4eb9344121ac6f80ea150ff2085a26a6fca03` |
| `run-20260928T025741498088-browser-no-range` | 12/12 PASS | 69.851751 | `94ecbe12fcdde777e1bf6feacc1c32ce63d92a6b1fcd78bfc01717fd83eba4c8` |
| `run-20260928T025854262243-browser-range` | 4/4 PASS | 24.277898 | `073cd69f673bb85ce33d20420a1ab1a0055de2f9b3133db1b6232cacff24e988` |
| `run-20260928T025920195121-test` | 175/175 PASS | 267.661438 | `9463cd04241a2c4d01a27ab366a2c950e63a2844d56bc1759e0aba8b09a9ceec` |
| `run-20260928T030400129315-check` | 0 errors, 0 warnings, 4 hints | 13.441213 | `57d4d478631e4a24cceb1d9611f5b1bef39c0df1c7f788b650b4487740a2cb6d` |
| `run-20260928T030413601119-artifact` | PASS | 1.983032 | `6c0c7bbe52c324e0f72f466825554ad40950301dad44818ffa369a0b8b48aa60` |
| `run-20260928T030415615741-diff` | PASS | 0.036335 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |

The unchanged64 public inputs/Release Authority retain their successful Source
guard (`seek-public-source-preservation.json`); no new source claim or historical
allowlist change is introduced. No PHP file changed, so no local PHP gate is
selected. Remote same-SHA CI remains required.

The frozen final Artifact has417 files/39,863,386 bytes; its manifest SHA256 is
`ad3875e18de2f53c3d12f694b5b5dc1b15c3ccf403d6fa112511ee997b29f240`.
`seek-artifact-post-tests-proof.json` proves dist remains exactly equal after all
gates. Compared with the first accepted Artifact,415 files are byte-identical;
only the Reel script and its index.html reference change, a net3,447-byte increase.
Media, guides, Search, raw/LLM and public routes remain unchanged. No rerender.

Independent `seek-final-review.md` SHA256 `38ec5d2c958a252661f8c4927ba9e3b05efae01afdf7f30f57a6b15c6c3ac130`
confirms Green local readiness with no unresolved P1/P2/P3. The reviewer reused
receipts and inspected actual screenshots; it did not repeat gates or edit source.
The range-ignoring server now matches Pages'200/full behavior; this remains local
evidence until the follow-up deployment passes actual canonical-origin checks.

## Corrective same-SHA delivery (2026-09-28)

The isolated correction commit `ef0638340afc651668fcb68334cf982fa06fd3c6`
contains exactly8 approved paths: player/test, Task/Report/D167/STATE and two
STATE archives. The candidate was clean before push. PR15 merged it into
`dc74bbf95c84576dad5dc9ef82084e67e84a934d`; both Git trees are
`3e8c522cba5eca112ad00663b6e79abd36253a1f`.

| Phase | CI | Documentation delivery | Actual documentation checkout |
| --- | --- | --- | --- |
| [PR15](https://github.com/kubotak-is/blackops/pull/15), head `ef063834` | [36339794713](https://github.com/kubotak-is/blackops/actions/runs/36339794713), all6 success | [36339794715](https://github.com/kubotak-is/blackops/actions/runs/36339794715), preview step success | `28fe1ba7632d8d23fac66b53ebc1bc58e1c87c84` (synthetic PR merge) |
| Main push `dc74bbf9` | [36340317799](https://github.com/kubotak-is/blackops/actions/runs/36340317799), all6 success | [36340317763](https://github.com/kubotak-is/blackops/actions/runs/36340317763), production step success | `dc74bbf95c84576dad5dc9ef82084e67e84a934d` |

GitHub metadata, step conclusions and actual checkout/deploy logs are pinned in
`seek-pr-remote-provenance.json`, `seek-production-remote-provenance.json` and
`github-run-<id>.{json,log}`. The exact gh argv, environment/cwd and elapsed
metadata retrieval times are in the corresponding receipt; job start/end times
record CI/deployment durations. Production deployment is `d7813b52`; the verified
public canonical URL is <https://blackops-php.pages.dev>.

The417-file PR and production Artifacts are byte-identical. Comparison to the
frozen local Artifact passes after only the same seven previously reviewed
checkout-dependent font/CSS/scope identifiers are mapped. Font/video payloads
and the corrected Reel bundle are exact. No new normalization is allowed.
Proofs: `seek-pr-artifact-proof.json`, `seek-production-pr-proof.json` and
`seek-production-local-proof.json` (comparison command wall times0.04s/0.01s/0.04s).

`verify-live.py <production-artifact> dc74bbf9 36340317763 seek-live-http.json`
passes50 HTTP comparisons: all41 public HTML routes, Search/LLM/raw sources,
all three Reel media assets and the actual bound player JavaScript. HTML differs
only by the previously pinned214-byte Pages Analytics footer. Both media Range
probes still return200/full exact bytes. The public host needs no access-setting
change. Browser behavior and Lighthouse are recorded separately below.

`d5p-preservation-after-reel.json` compares the original primary worktree to the
D5P frozen full input map: all11 candidate and1,699 runtime files remain exact,
and HEAD stays8106c355. `scope-preservation-seek-commit.json` also preserves all16
original handoff paths, original index and pre-Reel theme prefix. None of those
original primary-worktree Product bytes is replaced or staged by this delivery.

## Corrected canonical-origin proof (2026-09-28)

`run-20260928T032956147832-browser-live` passes5/5 in53.323532s;
log SHA256 `2e3656792352d0936723b1c7631399a5508886b3d9561cfab535d00e49d8ae7f`.
This uses default Chromium policy, actual HTTPS origin/media and1440x900 Light,
390x844 Dark, natural36-second end, reduced-motion controls and no-JS cases.
There are no page errors or failed requests in these normal cases. The first
chapter actually reaches3.6s on the loaded Blob; CLI seeking, manual pause,
end/replay, guide links and both players' reduced-motion behavior pass their
assertions. The ordinary unseeked movie reaches36s/ended/paused with loop=false.
Root inspected actual desktop/mobile screenshots and the effective controls.
Primary Ubuntu Sans/Mono and Noto Sans JP webfonts load from the delivered URLs.
Unavailable local fallback aliases in Linux are not mislabeled missing webfonts.

The selected WebM body is2,808,763 bytes. In both recorded layout cases, native
playback transfers2,809,063 bytes including response overhead; the explicit seek
fetch reports only300 transferred bytes with the same encoded body size, consistent
with cache reuse in this session. This is not a guarantee that every browser can
reuse the media cache. Unselected MP4 is not downloaded in those normal cases.
Both encoded alternatives plus poster total7,203,785 bytes in the Artifact; each
file remains below the verified25MiB Pages limit. HTTP verification took1.814225s
and its exact responses, comparison hashes and footer classification are retained.

The first paused mobile screenshot catches a native video spinner soon after
seeking. A focused actual-origin probe (`live-paused-probe.mjs/.json`) records
200ms, another2s and another5s: video remains2.6s/paused, seeking=false,
readyState4, networkState1, no error throughout. Root inspected all three images;
the spinner is gone by the second capture and remains absent. No persistent media
loading failure or Product correction is inferred from this transient native UI.
The probe interval is8.676s; its script/output/screenshots are retained separately.

`run-20260928T033052294361-lighthouse-live` passes execution in12.370325s;
log SHA256 `88d5d8338ab1cb19d5e8b57cb7505f14d353268fa6973e0dcbb084ace483eb81`.
Lighthouse13.5.0 against the actual canonical origin, mobile simulated throttling:
Performance78, Accessibility100, Best Practices100, SEO100. FCP1.230s, LCP6.180s,
CLS0 and TBT11ms; no runtime error or run warning. `lighthouse-live.json/.html`
and its summary retain the result. Performance still has improvement room; no
numeric budget was added or relaxed. This is one measured run, not a performance
or whole-site accessibility certification. Browser/physical-tab/BFCache/axe limits
from the local review remain explicit; successful delivery does not erase them.

Both browser/performance receipts retain identical before/after snapshot
`813f2efbfd33f67f725330f1095cd3d657d64586ab53238afdd31de83d418b9f` on the actual
main merge. The Product inputs are unchanged from local Acceptance; only HEAD
and management records advanced. The complete live HTTP comparison passed before
these serial browser/performance commands. No PHP/Framework release was performed.

## Orchestrator Acceptance and closeout

Accepted by Root at2026-09-28T15:10:26+09:00. Independent delivery review
`seek-delivery-review.md`, SHA256 `2dfa0f3b8708cb4aa2eabca6b4f6c124a277e0a2fbdf52677dae89eccc283374`,
is Green with no unresolved P1/P2/P3. It verifies the same-SHA chain, actual live
behavior, artifact/HTTP normalization, measured transfer and performance limits.
The two Root-owned verification HTTP servers have been stopped after matching
PID and exact script identity (`cleanup-owned-servers.json`); shared database and
unrelated services are untouched.

Task/Report/D167/TODO/spec index and STATE are synchronized. This administrative
closeout changes no Product, test, public source, media or build input. It reuses
the completed Product gates under spec109. Its separate commit records the accepted
production state above; final commit/delivery metadata remains observable in the
repository CI and documentation workflow without embedding a self-referential SHA.
No Framework tag/release, D5P commit or unrelated staged content is included.

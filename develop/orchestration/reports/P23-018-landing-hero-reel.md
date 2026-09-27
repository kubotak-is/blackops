# P23-018 Report

Status: Accepted (local) — isolated commit and production delivery pending

## Summary

The owner's Landing Hero Reel handoff is isolated on
`agent/p23-018-landing-hero-reel`, based on published main
`08e4751c62a2a83ba0401f5aee97164984a27f5c`, in
`/tmp/blackops-reel-20260927/candidate`. D5P and earlier documentation remain in
the original worktree and index. No PHP source or Release Authority is changed.

D167 retains the large video hero and What's BlackOps/Install CTAs, with the full
Stable installation command and Operation example immediately below. Both the
Reel and Execution Walkthrough autoplay under reduced motion as explicitly
selected by the owner. Effective pause, lifecycle suspension and accessible
fallbacks remain ordinary Acceptance requirements.

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
| Same-SHA CI/documentation production delivery | Pending; no delivery claim yet |

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

Final media handling covers source-error aggregation, a terminal video error,
pending play promise rejection, and an error arriving before enhancement. One
failed WebM source still permits MP4; complete failure pauses pending playback
and preserves the media-specific message. Final full browser evidence confirms
both fault paths and autoplay rejection. Normal cases have no JavaScript errors;
intentional404 fixtures retain their expected network errors.

The final matrix covers1440x900,1920x1080 and390x844 in both themes, keyboard focus,
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
boundaries. The initial complete generated Artifact passes all checks and contains417 files, including41 raw Markdown and41 MDX files, Search/LLM outputs and registered diagrams. Public-boundary scans are clean; encoded media hashes match. The final replacement `artifact-final-manifest.json` pins417 files/39,859,939 bytes; SHA256 `f786a4abb7bb9163b61b9ab65b278560bb0d65e3a55bef76bdb41bf8126b1962`. Its Build, Browser and Lighthouse receipts share unchanged input manifest `19ecdb3d70572bb850d456dc9d739e28a5f1abebeb6a173dbce61db62a1f7751`. The earlier manifest is retained separately.
PHP verification is not needed for this documentation-only delta; repository CI
remains the existing full workflow and must pass on the delivered SHA.

## Review and scope evidence

`documentation-source-review.md` records the initial P1/P2 findings;
`documentation-correction-source-review.md` records their source closure and the
final P3 visual overlap closure. `documentation-final-review.md` independently clears the final source, encoded media, browser evidence and all final gates. Root accepts the seven local criteria with the stated visibility/accessibility/performance evidence limits; production delivery is still a separate gate.
`scope-preservation-final.json` reconfirms at2026-09-28T00:40:17+09:00 all sixteen original
handoff files and the original staged patch are unchanged, the older theme prefix
is preserved, the isolated candidate contains its base CSS prefix and no PHP delta.
D5P local Acceptance is complete independently; no D5P commit/tag/release is made.

## Acceptance Criteria

The seven local criteria are Accepted. Independent Review is Green with no open P1/P2/P3. The isolated clean commit and same-SHA CI/production proof remain open; local Acceptance does not assert delivery. Known evidence limitations above are retained.

## Remaining Issues

The isolated commit and existing PR/main CI/documentation production delivery remain, followed by public artifact/browser/performance confirmation.
The main Ruleset requires a PR; no bypass or force push is selected.

## Suggested Next Action

Commit/push the frozen isolated candidate and deliver through the existing PR
workflow. Record the delivered SHA, CI/deployment URLs and external
artifact/interaction/performance evidence before completion.
残り工程: Isolated commit, same-SHA CI, production Documentation delivery and
live verification. D5P stays Accepted locally and uncommitted; the1.3 parent is
not completed or released by this documentation delivery.
Next Action: Root commits the authorized candidate and opens its delivery PR.

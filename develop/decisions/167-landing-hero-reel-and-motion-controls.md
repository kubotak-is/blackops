# D167: Landing Hero Reel and Motion Controls

Status: Decided — owner handoff2026-09-27; implementation and local review Accepted2026-09-28

## Context

The owner requested a large explanatory video on the documentation top page,
allowed replacement of the former hero, retained What's BlackOps and Install,
and selected autoplay for both the Reel and Execution Walkthrough even when
prefers-reduced-motion is reduce. Claude Code supplied the uncommitted video,
authoring files, hero and walkthrough changes. P23-018 reviews and delivers only
that change; D5P and earlier uncommitted documentation remain separate.

## Decision

1. The Reel is the hero's primary visual. Preserve the existing framework-name
   hierarchy, concise value statement, What's BlackOps and Install links. At
   1440x900 the first viewport must make identity, value, both CTAs and the Reel
   legible. The complete copyable Stable install command and Operation example
   may occupy the immediately following source section. This replaces spec86's
   requirement that the command itself remain in the first viewport. It follows
   the owner's preference for a large hero video and continued installation
   access; the Install CTA remains visible before scrolling.
2. The36-second Reel autoplays once, does not loop, keeps its ending frame and
   restarts only on explicit replay or chapter selection. Chapters expose guide
   links. Content is illustrative and must be supported by the released source
   and guides; do not fabricate inspection output or present1.3 proposals as
   Stable capabilities. Omitting version numbers from video is not an exemption
   from release review. The copyable webpage command follows Release Authority.
3. The reduced-motion autoplay exception is limited to Reel and Execution
   Walkthrough. Preserve other reduced-motion/reduced-transition rules, normal
   reading order, keyboard/focus behavior and readable static content. Do not
   treat the explanatory purpose as a claim that all motion is WCAG-essential.
4. Readers must have an effective way to stop each player, including moving arrow
   traces. Explicit pause persists across viewport/visibility changes until an
   intentional resume. D159/spec110's earlier trace-continuation-on-pause behavior
   is replaced: the existing pause control stops both phase progression and the
   arrow trace, and resume starts both again. The same pause/resume control remains
   enabled under reduced motion. No-JS or failed enhancement must not leave autoplaying video with
   no stop control. Offscreen/hidden/cleanup suspension remains required.
5. This is a narrow amendment to specs59/85/86/96/110, preserving guide content,
   public routes, framework-name hierarchy, CTA destinations, link integrity,
   Search/banner and the existing artifact/delivery boundary. D118/D119/D159
   remain historical decisions; conflicting presentation clauses are superseded
   only to the extent explicitly described here. No PHP behavior changes.
6. Rendering must be reproducible from this repository's documented tooling.
   Pin Playwright as an owned development dependency and align its Docker browser
   image; declare/validate ffmpeg with H.264/VP9 encoders. Preserve the pinned
   full-font source/hash and keep downloaded fonts/frames out of Git. Normal
   website builds consume reviewed committed assets and do not rerender them.
   Record actual tool versions and visual/metadata evidence; equal file sizes
   alone are not a determinism proof.
7. Review an isolated commit candidate containing only this Task's approved
   changes. Preserve the pre-Reel theme prefix and all unrelated staged/unstaged
   work. Same-SHA CI and production documentation delivery use the reviewed clean
   artifact through the existing workflow; final external checks prove delivery.
   This does not authorize a Framework release/tag or an unrelated D5P commit.

## Accessibility and delivery evidence

The owner accepts automatic motion under reduced-motion preferences for these
two players. This decision does not declare WCAG conformance. Test actual pause,
keyboard, no-JS, visibility and replay behavior; accessibility findings remain
ordinary acceptance findings despite the explicit autoplay choice.

The [W3C explanation of2.2.2](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide.html)
requires usable pause/stop/hide behavior for applicable automatic moving content.
[2.3.3](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html)
addresses disabling interaction-triggered motion at Level AAA. Verify the selected
controls without claiming a whole-site or higher-level certification.

[Cloudflare Pages limits](https://developers.cloudflare.com/pages/platform/limits/),
checked2026-09-27, set25MiB per asset. Verify each actual media file and the delivered
Content-Type/response, and measure browser transfer separately from this platform
limit. The combined size of alternative MP4/WebM files is not normally the chosen
format's single-browser transfer; confirm requests in browser evidence.

## Remaining work

P23-018 local source/browser/media review and verification are Accepted.
Isolated commit, same-SHA CI, delivery and external verification remain.
The D5P Task and the unreleased1.3 parent remain independently managed.

# 110. Documentation Diagrams and Presentation

Status: Active
Authority: P23-008 / P23-008A, user directions on2026-09-09; Decision159.

Public diagram presentation uses Archify for every explanatory diagram.
Canonical typed JSON and delivered HTML remain reproducible maintenance
artifacts; the public article must be understandable from its full-width image
and equivalent Japanese prose. Diagram images use width:100%, max-width:100%,
height:auto. Author layouts that remain readable at mobile article width;
forced horizontal scrolling, clipping and unreadable panoramic shrinking fail.
Complex diagrams may be split into focused figures while retaining all facts.
Existing Mermaid-only sizing/count contracts are superseded for this migration.

The landing introduces BlackOps before installation. Its hero links are,
in order, What's BlackOps (/concepts/why-blackops) and Install
(/getting-started/installation). Quickstart remains available in the learning
journey and navigation; it is not the second hero action. The public wordmark,
route structure,40 reader pages,41 Search records and current Release boundary
are unchanged. Historical Install/Quickstart hero assertions are superseded.

The user subsequently rejected the whole visual design and removed any duty to
follow it. Redesign TOP, header/navigation and documentation reading surfaces as
one system. Use confident typography, a compact integrated execution demo,
graphite/paper surfaces and restrained lime actions on Astro/Blume/native CSS.
Existing colors, layout and font choices are not preservation constraints.
The user's subsequent visual reference is https://reactbits.dev, while the
BlackOps lime accent remains. Use its deep neutral surfaces, strong type,
thin panel boundaries, deliberate spacing and responsive interaction as visual
direction. The user asks for a more dynamic visual treatment and scroll
animation. Use visible lime ambient light behind the hero and staged reveals
for its content and the primary TOP sections. Preserve readable content,
keyboard access, no-JS visibility and reduced-motion support; no forced scroll
or replacement of the existing timed execution loop. Ambient light/gradient in
the hero is permitted by this
explicit reference; previous blanket decorative-gradient prohibitions are
superseded. Preserve legible light/dark themes and restrained motion. Astro,
Blume and native CSS remain the implementation foundation.
Remove decorative overline labels such as BlackOps / PHP Framework and
02 / HTTP PROCESSING from markup. They add no information to the real heading.
Do not introduce replacements. Functional phase counts and necessary release
context remain distinct. The user also authorized a matching persistent update
to the local design-taste-frontend Skill.
Use local Japanese fonts and verify actual rendered glyph faces, including PNG
generation and SVG exports; Chinese CJK fallback is not acceptable for Japanese
authored text. No fabricated product UI, claims, social proof or decorative
architecture diagrams. Current source and artifacts must match these directions.

Accept on actual desktop/mobile screenshots, readable complete diagrams,
working hero/navigation/search/keyboard flows, accessibility checks and a
recorded Lighthouse run. Preserve exact asset registration, tracked sources,
output hashes, source/raw/Search/LLM parity and Release checks. Task109 governs
verification reuse and team/state ownership. Local preview is authorized;
remote publication and production deployment remain separate actions.

The user's latest correction removes the separate Core Concepts diagram from
TOP because it duplicates the animation. Keep the concepts figure in its guide,
and make the animated diagram's elements explorable on TOP instead. The latest
user direction replaces direct navigation with native node buttons. Selecting
a node pauses playback and changes the explanation below the figure to its role,
explanation and a clearly labelled 詳しく見る link. The controls derive their
bounds from the canonical generated geometry and remain keyboard/touch operable
at mobile width. Journal detail links belong with the event panel. Keep accurate HTTP, Operation CLI and Schedule entry
explanations/links without adding a second overview drawing.

The user additionally requested a looping landing animation that explains the
processing flow together with the Journal events produced at each stage.
Use the HTTP execution flow with Inline and Deferred paths. Do not add a public
"example" disclaimer to restate what the interactive presentation already shows.
Synchronize active generated diagram nodes/relationships, a short
Japanese explanation and actual event names. Clearly distinguish durable
acceptance from later Worker completion; illustrative events are not live logs.
Automatic playback loops within the selected Inline or Deferred mode, with
pause/resume and manual progression. Only explicit tab selection switches modes.
Each tab starts at its first phase and its own loop resets the illustrative
Journal. Accessible Inline/Deferred tabs above the graph have a prominent mode
explanation: Inline completes within the HTTP Request and returns its result;
Deferred commits acceptance, returns202 with Operation ID, and a separate Worker
continues execution. Inline is the initial selected mode, including SSR/no-JS.
Use one consistent tab design, without mixing outlined buttons and an attached
underline. One shared graph/panel reflects the selected tab with keyboard tab
navigation and a selected-state relationship. Reduced
motion shows a complete static/manual explanation; background/offscreen work
pauses. Do not announce every automatic frame to assistive technology.
The animated visual uses the canonical Archify SVG export and its exact node
and relationship geometry. Website playback may style that generated SVG and
place native controls/event text around it without modifying delivered HTML
or inventing a parallel graph. Preserve canonical SVG provenance and hashes.
The Core Concepts guide's overview still includes HTTP, Console and Schedule;
TOP retains their entry links alongside the single HTTP animation.

Element inspection is separate from execution state. Preserve the underlying
phase and accumulated Journal, highlight the selected node and temporarily
replace the flow explanation/event display with the detail panel. Playback
resume, manual progression or mode selection restores the normal flow panel.
Node selection never navigates; only 詳しく見る opens the guide. Expose selection
and panel relationships to assistive technology. Keep the changed panel and
diagram near each other on mobile and make selection apparent.

The latest user review rejects the narrow figure/sidebar composition. Use the
full available landing width for the figure, with a spacious explanation BELOW
it at every viewport. Desktop uses a landscape flow; mobile uses a readable
portrait layout. Wider landing composition and stronger visual scale are required
across the hero and demo, with the real code aligned to the main hero content.
Avoid excessive empty space and cramped small side panels. Both layouts come from the
same pinned Archify Skill, preserve the same seven node/edge IDs and execution
meaning, and have registered canonical JSON/HTML/SVG and export provenance.
They are responsive layouts of one explanation, not two adjacent diagrams.
Use Journalイベント as the panel heading, followed by a short definition of Journal.
Show the current phase number
in both the diagram and panel, including the mode and total (Inline4/7,
Deferred6/9). Numbers represent phases, not permanent node identities. Make
active edge motion and newly added events obvious. Do not show ambiguous empty
labels such as 追加なし or repeat a no-event notice on each phase. Show only actual
events. Remove the 全段階とJournalイベントを表示 disclosure; no-JS users retain
a static figure, useful explanation and guide navigation. Remove the redundant
アプリケーションの仕事を、受付から完了まで読める実行単位にします。 sentence.
Keep the current phase number visible during node inspection: the phase badge
stays with the actual current phase, while selection has its own highlight.
Show the same paused mode/number with the selected element explanation. Arrowheads retain a restrained size when an edge is
active or focused. Manual pause freezes the execution phase and Journal while
the active arrow trace keeps moving. Reduced motion, an offscreen figure, a
hidden page and player cleanup stop the trace. Responsive replacement preserves
the separate phase and trace states. Inspection retains its selected-node view.

Automatic progression uses exactly5000ms of active playback per phase. A progress
ring in the native pause/resume button shows elapsed time and the portion remaining
until the next phase, using the same clock as progression. Manual pause, reading
holds and hidden/offscreen states freeze elapsed time; resuming consumes the
remaining time. New phases, mode selection and manual stepping reset the clock.
This presentation cadence does not describe Framework runtime duration. Hovering
over the explanation with a pointer or focusing within it holds progression.
This reading hold does not stop the arrow trace and
must not create sticky hover behavior for touch users. Every phase includes a
visible 詳しく見る link to its relevant guide/section; each Journal event links
to its documented reference. Initial/no-JS content also includes the phase link.

The GenerateReport.php hero code card and existing HTTP walkthrough card surfaces
use translucent tinted backgrounds with native backdrop blur, letting the lime
background show through. Keep text/code/diagram contrast readable in both themes,
without redundant opaque inner fills or extra separators. Reduced transparency
and unsupported backdrop filtering use opaque readable fallbacks. The progress
control preserves a44px target, keyboard focus, clear play/pause state and quiet
assistive output; hidden/offscreen/cleanup stop progress work.

TOP proceeds from framework overview to execution flow and then to trying it.
Remove the duplicate 実際のPHPコードから始める authoring section: its reference
link did not fulfil the implied tutorial promise. Replace 必要な場所から読む and
the generic six-category link grid with BlackOpsを動かす, an ordered journey to
installation, running the included sample and creating an Operation. Labels and
descriptions describe those actual guide destinations. Show heading/lead beside
the three ordered links on desktop and stack them on mobile. Preserve the hero
code, primary actions and HTTP/Console/Schedule/CLI navigation.

The user's renewed React Bits reference calls for a stronger background: broad
asymmetric lime light bands, subtle texture and spatial depth across TOP instead
of small diffuse glows. Build this decoration with native CSS and bounded
transform/opacity motion, preserving the existing scroll reveals, neutral
light/dark surfaces and readable foreground. No new dependency, remote font,
canvas/WebGL render loop or raster payload is required. The background is
noninteractive and aria-hidden, has a static no-JS/reduced-motion form and stops
ambient work when offscreen, hidden or cleaned up. Decorative clipping must
never conceal real content overflow or reduce diagram readability.

Remove the separator under 現在の処理段階, duplicate rules below 詳しく見る and
the Journal timeline's left rules. Linked Journal events appear as tags, with
newly added events distinguished visually. Remove the redundant dynamic
この段階で記録されるJournalイベント text. Remove the source/example/timing
disclaimers; the sole public timing note is
※Deferredでは受付Commit後にHTTP 202の応答とWorkerの実行が重なることがあります。
Right-align the walkthrough's intro lead. Mode definitions fill their available
width with natural Japanese wrapping; avoid balanced short lines and forced
breaks. Preserve legibility, focus and link targets across both themes and widths.

The BlackOps hero word arrives with irregular per-letter neon light pulses and
then stays readable. Keep the effect finite, restrained in pulse frequency and
free of layout shift. It must preserve the accessible heading and source text,
show the complete static title with no-JS/reduced motion, and settle/cancel when
hidden, offscreen or cleaned up. Do not add a recurring full-word blink loop.

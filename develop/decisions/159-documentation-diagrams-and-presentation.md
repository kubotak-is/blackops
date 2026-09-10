# 159. Unified Skill Diagrams and Orientation-first Landing

Status: Accepted direction; implementation/review in P23-008 / P23-008A

The user rejected the first Archify pass because only some diagrams used the
new style and the wide images required horizontal scrolling. They requested
all diagrams use the Skill, responsive full-width presentation, and a more
polished landing whose actions are What's BlackOps then Install.

Use Archify for the entire explanatory diagram inventory. Author readable
article compositions and allow focused splits of complex flows. Public PNG
exports and equivalent prose complete the reader journey; the tool viewer is
a maintenance capability and need not be the public reading interface.
Keep the existing Astro/Blume stack and brand, revise composition/typography,
and retain Quickstart in the learning journey. Spec110 is the current authority;
old Mermaid sizing and Install/Quickstart hero snapshots are historical.

Prototype one figure and the landing, inspect actual widths/themes, and then
expand. This avoids repeating full generation and broad tests for a rejected
visual direction. Final acceptance still requires semantic, browser, asset,
Release, accessibility and source/Search/LLM evidence.

The user additionally placed the concepts overview on the landing, requested
per-element detail links and Schedule as an entrypoint. Extend the pilot to
this overview and derive native links from the generated image geometry.

The user then requested an automatically looping execution explanation with
synchronized Journal events. Add a clearly labelled HTTP Inline/Deferred
example using a canonical Archify SVG and a native website player. The actual
generated topology remains authoritative; playback is presentation state.
Pause/manual controls and reduced-motion fallback preserve reader control.

The user subsequently rejected the entire presentation, explicitly removed the
constraint to follow the existing design, and clarified that TOP, article and
navigation need a coherent modern redesign. Spec110 now governs a full visual
reset; old green-gray/teal/orange colors and layout snapshots are not constraints.
The duplicate Core Concepts drawing is removed from TOP. Detail links are merged
into the animated diagram; the concepts drawing remains in its guide.

Browser glyph inspection also confirmed that Japanese diagram text used
WenQuanYi Zen Hei Mono, with Latin text in Liberation Mono. Use an explicit local
Japanese font, carry it through actual PNG/SVG export, and verify rendered faces
instead of trusting a CSS family declaration. These corrections require new
visual evidence; successful old execution semantics remain reusable separately.

The latest user direction uses React Bits as a visual reference while retaining
the lime BlackOps accent. Widen the landing, align the code with the hero and
place the explanation below the full-width graph at every viewport. Node clicks
inspect an element; the 詳しく見る link navigates. Preserve the paused phase
number during inspection and distinguish its position from the selected node.
Remove the redundant hero sentence, full-transcript disclosure and empty-event
labels. Add finite native scroll reveals with keyboard, reduced-motion and
no-JS support. The explanatory loop remains independent of scrolling.
The user also authorized a permanent local design Skill update eliminating
decorative microheadings while preserving useful progress numbers.

The user then found the2.8-second cadence too fast and requested direct links
for each item, plus arrows that continue moving during manual pause. P23-008A
separates progression from trace motion, allows10–16 seconds according to the
new reading content, and holds progression while the explanation is hovered or
focused. Phase explanations and individual events link to existing references.
Reduced motion, visibility and lifecycle safeguards still stop trace work.

The user found automatic Inline/Deferred alternation confusing. Replace it with
explicit accessible tabs above the graph and explain the selected mode there.
Only the current mode loops; selecting another tab starts its first phase.
The illustration resets its Journal at the selected mode's loop boundary.
The former alternating-mode playback direction is superseded.

The user then rejected the repeated authoring section and vague lower-resource
grouping. Keep a clear overview → flow → hands-on progression. Remove the
authoring repeat and generic purpose grid; use a single BlackOpsを動かす section
linking to the actual installation, sample and First Operation tutorials.

The user again cited React Bits and requested stronger background design.
The observed reference uses broad asymmetric luminous bands and subtle texture.
Use that direction in native lime/neutral decoration, with bounded motion and
foreground readability, instead of importing a React rendering stack.

The next visual review makes Inline the default and asks for unmistakable tabs,
fewer separators, linked Journal event tags and a short Journal definition.
Remove example/status boilerplate; retain only the requested Deferred Commit,
HTTP202 and Worker overlap note. Right-align the intro lead and restore natural
mode-description wrapping. The user also asks for a broken-neon arrival of the
BlackOps wordmark: a brief irregular per-letter pulse sequence settles to a
readable title, preserving reduced/no-JS/accessibility and lifecycle behavior.

The subsequent user request supersedes the variable10–16-second allowance with
a uniform five seconds of active playback. P23-011 adds elapsed/remaining progress
to the pause button, preserving elapsed time across holds and resetting on phase
changes. Arrow trace motion remains independent from manual pause. The same
request adds translucent frosted surfaces to the GenerateReport.php card and
HTTP walkthrough cards, retaining the existing lime background and readable
light/dark and reduced-transparency fallbacks.

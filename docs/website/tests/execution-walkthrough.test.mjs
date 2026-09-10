import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import test from 'node:test';
import {
  EXECUTION_MODE_DESCRIPTIONS,
  EXECUTION_PHASE_DURATION_MS,
  EXECUTION_WALKTHROUGH_EVENT_HREFS,
  EXECUTION_WALKTHROUGH_STEPS,
  JOURNAL_DEFINITION,
  initExecutionWalkthrough,
} from '../scripts/execution-walkthrough.mjs';

const step = (mode, id) => EXECUTION_WALKTHROUGH_STEPS.find((candidate) => candidate.mode === mode && candidate.id === id);
const mobileLayout = {
  meta: { viewBox: [420, 560] },
  components: [{ id: 'http-entry', pos: [20, 28], size: [160, 64] }],
};
const desktopLayout = {
  meta: { viewBox: [1000, 420] },
  components: [{ id: 'http-entry', pos: [40, 64], size: [140, 80] }],
};

const controlledFixtureMarkup = () => `<div data-execution-walkthrough data-execution-svg-src="/diagrams/execution-overview.svg" data-execution-data-src="/diagrams/execution-overview.json" data-execution-initial-step="0" data-execution-stage-count="16">
  <div role="tablist" aria-label="実行経路">
    <button type="button" id="controlled-tab-inline" role="tab" aria-selected="true" aria-controls="controlled-mode-panel" tabindex="0" data-execution-mode="inline" data-execution-enhance>Inline</button>
    <button type="button" id="controlled-tab-deferred" role="tab" aria-selected="false" aria-controls="controlled-mode-panel" tabindex="-1" data-execution-mode="deferred" data-execution-enhance>Deferred</button>
  </div>
  <div data-execution-mode-panel id="controlled-mode-panel" role="tabpanel" aria-labelledby="controlled-tab-inline">
    <p data-execution-mode-description>${EXECUTION_MODE_DESCRIPTIONS.inline}</p>
    <div data-execution-graph-header><strong data-execution-graph-stage></strong><span data-execution-selection-status hidden></span></div>
    <div data-execution-svg-frame role="group" aria-label="HTTPの処理経路">
      <div data-execution-svg-shadow><template shadowrootmode="open"></template></div>
      <img data-execution-fallback src="/diagrams/execution-overview.svg" alt="">
      <p data-execution-svg-error hidden></p>
      <span data-execution-stage-badge hidden></span>
      <button type="button" data-execution-region="http-entry" data-execution-link="http-entry" data-execution-inspect data-execution-detail-label="HTTP" data-execution-detail-role="RequestをOperationへ渡す入口" data-execution-detail-description="RequestをOperationへ渡します。Inlineは完了後に応答し、Deferredは受付後にHTTP 202とOperation IDを返します。" data-execution-detail-href="/execution/http-and-deferred#inline-http" aria-controls="controlled-inspection" aria-pressed="false" data-execution-enhance disabled style="left:5%;top:5%;width:35%;height:14%"><span class="sr-only">HTTPの要素を確認</span></button>
    </div>
    <div data-execution-reading-column>
      <aside data-execution-inspection-panel id="controlled-inspection" hidden><span data-execution-inspection-stage></span><h3 data-execution-inspection-label></h3><p data-execution-inspection-role></p><p data-execution-inspection-description></p><a data-execution-inspection-link href="#" hidden>詳しく見る</a><button type="button" data-execution-inspection-resume data-execution-enhance disabled>フローに戻る</button></aside>
      <div data-execution-phase-copy><div data-execution-phase-mode></div><span data-execution-phase-number></span><h3 data-execution-phase-label></h3><p data-execution-phase-description></p><a data-execution-phase-link href="/execution/http-and-deferred#inline-http">詳しく見る</a></div>
      <div data-execution-journal-panel><div data-execution-journal-status></div><div data-execution-journal></div></div>
      <p data-execution-status role="status"></p>
      <div data-execution-controls>
        <button type="button" data-execution-action="previous" data-execution-enhance>前へ</button>
        <button type="button" data-execution-action="toggle" data-execution-enhance><span data-execution-progress aria-hidden="true"></span><span data-execution-toggle-label>一時停止</span></button>
        <button type="button" data-execution-action="next" data-execution-enhance>次へ</button>
      </div>
    </div>
  </div>
  <script data-execution-walkthrough-data>${JSON.stringify({ steps: EXECUTION_WALKTHROUGH_STEPS })}</script>
</div>`;

const createControlledHarness = ({ reducedMotion = false } = {}) => {
  const dom = new JSDOM(controlledFixtureMarkup(), { url: 'https://blackops.test/' });
  const root = dom.window.document.querySelector('[data-execution-walkthrough]');
  const originals = new Map();
  const timers = [];
  const layoutListeners = new Set();
  const motionListeners = new Set();
  let desktopMatches = false;
  let nextTimerId = 1;
  let now = 0;
  let observedTarget;
  let observerCallback;
  let motionPreference = reducedMotion;

  const globals = {
    window: dom.window,
    document: dom.window.document,
    Element: dom.window.Element,
    HTMLButtonElement: dom.window.HTMLButtonElement,
    DOMParser: dom.window.DOMParser,
    AbortController: dom.window.AbortController,
    fetch: async (url, options) => {
      if (options?.signal?.aborted) throw new dom.window.DOMException('Aborted', 'AbortError');
      if (String(url).endsWith('.json')) {
        return { ok: true, text: async () => JSON.stringify(mobileLayout) };
      }
      return {
        ok: true,
        text: async () => '<svg viewBox="0 0 20 20" role="img"><defs><marker id="arrowhead" markerWidth="10" markerHeight="7"><path d="M0 0L10 3.5L0 7Z" /></marker></defs><g data-node-id="http-entry" role="button" tabindex="0"><rect class="c-frontend" width="20" height="20" /></g><path data-edge-id="request-input" d="M0 0 L20 20" marker-end="url(#arrowhead)" /><path data-edge-id="input-strategy" d="M0 0 L20 20" marker-end="url(#arrowhead)" /><path data-edge-id="deferred-accept" d="M0 0 L20 20" marker-end="url(#arrowhead)" /></svg>',
      };
    },
    IntersectionObserver: class {
      constructor(callback) {
        observerCallback = callback;
      }

      observe(target) {
        observedTarget = target;
      }

      disconnect() {}
    },
  };

  for (const [name, value] of Object.entries(globals)) {
    originals.set(name, { exists: Object.hasOwn(globalThis, name), value: globalThis[name] });
    globalThis[name] = value;
  }
  Object.defineProperty(dom.window.document, 'hidden', { configurable: true, value: false });
  Object.defineProperty(dom.window.performance, 'now', { configurable: true, value: () => now });

  dom.window.matchMedia = (query) => {
    const layout = query.includes('60rem');
    const listeners = layout ? layoutListeners : motionListeners;
    return {
      matches: layout ? desktopMatches : motionPreference,
      addEventListener(_event, listener) {
        listeners.add(listener);
      },
      removeEventListener(_event, listener) {
        listeners.delete(listener);
      },
    };
  };
  dom.window.setTimeout = (callback, delay) => {
    const timer = { id: nextTimerId++, callback, delay, cleared: false, fired: false };
    timers.push(timer);
    return timer.id;
  };
  dom.window.clearTimeout = (id) => {
    const timer = timers.find((candidate) => candidate.id === id);
    if (timer) timer.cleared = true;
  };

  const activeTimers = () => timers.filter((timer) => !timer.cleared && !timer.fired);
  const runNextTimer = (advanceBy = EXECUTION_PHASE_DURATION_MS) => {
    const timer = activeTimers()[0];
    assert.ok(timer, 'a phase timer is scheduled');
    now += advanceBy;
    timer.fired = true;
    timer.callback();
    return timer;
  };
  const advanceTime = (duration) => {
    assert.ok(Number.isFinite(duration) && duration >= 0, 'controlled time must be non-negative');
    now += duration;
  };
  const flush = async () => {
    for (let index = 0; index < 5; index += 1) await Promise.resolve();
  };
  const setViewport = (visible) => observerCallback?.([{ isIntersecting: visible }]);
  const setReducedMotion = (matches) => {
    motionPreference = matches;
    for (const listener of motionListeners) listener({ matches });
  };
  const setDesktop = (matches) => {
    desktopMatches = matches;
    for (const listener of layoutListeners) listener({ matches });
  };
  const dispatchPointer = (target, type, pointerType) => {
    const event = new dom.window.Event(type, { bubbles: true });
    Object.defineProperty(event, 'pointerType', { value: pointerType });
    target.dispatchEvent(event);
  };
  const dispatchKey = (target, key) => target.dispatchEvent(new dom.window.KeyboardEvent('keydown', { bubbles: true, key }));
  const dispose = (cleanup) => {
    cleanup?.();
    for (const [name, original] of originals) {
      if (original.exists) globalThis[name] = original.value;
      else delete globalThis[name];
    }
    dom.window.close();
  };

  return {
    dom,
    root,
    timers,
    activeTimers,
    runNextTimer,
    advanceTime,
    flush,
    setViewport,
    setReducedMotion,
    setDesktop,
    dispatchPointer,
    dispatchKey,
    observedTarget: () => observedTarget,
    dispose,
  };
};

test('execution walkthrough data preserves the two reviewed paths and cumulative Journal events', () => {
  assert.equal(EXECUTION_WALKTHROUGH_STEPS.filter(({ mode }) => mode === 'inline').length, 7);
  assert.equal(EXECUTION_WALKTHROUGH_STEPS.filter(({ mode }) => mode === 'deferred').length, 9);
  assert.deepEqual(step('inline', 'complete').journalEvents, [
    'operation.received',
    'attempt.started',
    'attempt.succeeded',
    'operation.completed',
  ]);
  assert.deepEqual(step('deferred', 'complete').journalEvents, [
    'operation.received',
    'operation.accepted',
    'attempt.started',
    'attempt.succeeded',
    'operation.completed',
  ]);
  assert.deepEqual(step('inline', 'complete').newJournalEvents, [
    'attempt.succeeded',
    'operation.completed',
  ]);
  assert.deepEqual(step('deferred', 'accept').newJournalEvents, [
    'operation.received',
    'operation.accepted',
  ]);
  assert.ok(EXECUTION_WALKTHROUGH_STEPS.every(({ guideHref }) => guideHref.startsWith('/')));
  assert.ok(EXECUTION_WALKTHROUGH_STEPS.every(({ journalEvents, newJournalEvents }) => [
    ...journalEvents,
    ...newJournalEvents,
  ].every((event) => EXECUTION_WALKTHROUGH_EVENT_HREFS[event])));
  assert.equal(EXECUTION_MODE_DESCRIPTIONS.inline, 'HTTPリクエストを受けたプロセスで処理を実行し、完了した結果をレスポンスとして返す方式です。');
  assert.equal(EXECUTION_MODE_DESCRIPTIONS.deferred, '受付の保存が確定すると、HTTP 202とOperation IDを返します。処理の実行と完了は、別プロセスのWorkerが引き継ぎます。');
  assert.equal(EXECUTION_PHASE_DURATION_MS, 5_000);
  assert.doesNotMatch(JSON.stringify(EXECUTION_WALKTHROUGH_STEPS), /authority|playback-contract|temporary/i);
});

test('execution walkthrough uses native controls and isolates the canonical SVG instance', async () => {
  const dom = new JSDOM(`<div data-execution-walkthrough data-execution-svg-src="/diagrams/execution-overview.svg" data-execution-data-src="/diagrams/execution-overview.json" data-execution-desktop-svg-src="/diagrams/execution-overview-desktop.svg" data-execution-desktop-data-src="/diagrams/execution-overview-desktop.json" data-execution-initial-step="0" data-execution-stage-count="16">\
    <div data-execution-svg-frame role="img" aria-label="HTTPの処理経路">\
      <div data-execution-svg-shadow><template shadowrootmode="open"></template></div>\
      <img data-execution-fallback src="/diagrams/execution-overview.svg" alt="">\
      <p data-execution-svg-error hidden></p>\
      <span data-execution-stage-badge hidden></span>\
      <button type="button" data-execution-region="http-entry" data-execution-link="http-entry" data-execution-inspect data-execution-detail-label="HTTP" data-execution-detail-role="RequestをOperationへ渡す入口" data-execution-detail-description="RequestをOperationへ渡します。Inlineは完了後に応答し、Deferredは受付後にHTTP 202とOperation IDを返します。" data-execution-detail-href="/execution/http-and-deferred#inline-http" aria-controls="execution-inspection" aria-pressed="false" data-execution-enhance disabled style="left:5%;top:5%;width:35%;height:14%"><span class="sr-only">HTTPの要素を確認</span></button>\
    </div>\
    <div role="tablist" aria-label="実行経路">\
      <button type="button" id="execution-tab-inline" role="tab" aria-selected="true" aria-controls="execution-mode-panel" tabindex="0" data-execution-mode="inline" data-execution-enhance>Inline</button>\
      <button type="button" id="execution-tab-deferred" role="tab" aria-selected="false" aria-controls="execution-mode-panel" tabindex="-1" data-execution-mode="deferred" data-execution-enhance>Deferred</button>\
    </div>\
    <div data-execution-mode-panel id="execution-mode-panel" role="tabpanel" aria-labelledby="execution-tab-inline" data-execution-mode-panel>\
      <p data-execution-mode-description>${EXECUTION_MODE_DESCRIPTIONS.inline}</p>\
      <div data-execution-graph-header><strong data-execution-graph-stage></strong><span data-execution-selection-status hidden></span></div>\
    <div data-execution-phase-copy><div data-execution-phase-mode></div><span data-execution-phase-number></span><h3 data-execution-phase-label></h3><p data-execution-phase-description></p><a data-execution-phase-link href="/execution/http-and-deferred#inline-http"></a></div>\
    <div data-execution-journal-panel><div data-execution-journal-status></div><div data-execution-journal></div></div>\
    <aside data-execution-inspection-panel id="execution-inspection" hidden><span data-execution-inspection-stage></span><h3 data-execution-inspection-label></h3><p data-execution-inspection-role></p><p data-execution-inspection-description></p><a data-execution-inspection-link href="#" hidden>詳しく見る</a><button type="button" data-execution-inspection-resume data-execution-enhance disabled>フローに戻る</button></aside>\
    <p data-execution-status role="status"></p>\
    <button type="button" data-execution-action="previous" data-execution-enhance>前へ</button>\
    <button type="button" data-execution-action="toggle" data-execution-enhance>一時停止</button>\
    <button type="button" data-execution-action="next" data-execution-enhance>次へ</button>\
    <button type="button" data-execution-step="0" data-execution-enhance></button>\
    <button type="button" data-execution-step="7" data-execution-enhance></button>\
    </div>\
    <script data-execution-walkthrough-data>${JSON.stringify({ steps: EXECUTION_WALKTHROUGH_STEPS })}</script>\
  </div>`, { url: 'https://blackops.test/' });
  const root = dom.window.document.querySelector('[data-execution-walkthrough]');
  const originals = new Map();
  let observedTarget;
  let desktopMatches = false;
  const layoutListeners = new Set();
  const globals = {
    window: dom.window,
    document: dom.window.document,
    Element: dom.window.Element,
    HTMLButtonElement: dom.window.HTMLButtonElement,
    DOMParser: dom.window.DOMParser,
    AbortController: dom.window.AbortController,
    fetch: async (url, options) => {
      if (options.signal.aborted) throw new dom.window.DOMException('Aborted', 'AbortError');
      if (String(url).endsWith('.json')) {
        return {
          ok: true,
          text: async () => JSON.stringify(String(url).includes('desktop') ? desktopLayout : mobileLayout),
        };
      }
      return {
        ok: true,
          text: async () => '<svg viewBox="0 0 20 20" role="img"><style>:root,svg{display:block}</style><defs><marker id="arrowhead" markerWidth="10" markerHeight="7"><path d="M0 0L10 3.5L0 7Z" /></marker></defs><g data-node-id="http-entry" role="button" tabindex="0"><rect class="c-frontend" width="20" height="20" /></g><path data-edge-id="request-input" d="M0 0 L20 20" marker-end="url(#arrowhead)" /></svg>',
      };
    },
    IntersectionObserver: class {
      constructor(callback) {
        this.callback = callback;
      }

      observe(target) {
        observedTarget = target;
      }

      disconnect() {}
    },
  };
  try {
    for (const [name, value] of Object.entries(globals)) {
      originals.set(name, { exists: Object.hasOwn(globalThis, name), value: globalThis[name] });
      globalThis[name] = value;
    }
    dom.window.matchMedia = (query) => ({
      matches: query.includes('60rem') ? desktopMatches : true,
      addEventListener(_event, listener) {
        if (query.includes('60rem')) layoutListeners.add(listener);
      },
      removeEventListener(_event, listener) {
        layoutListeners.delete(listener);
      },
    });

    const cleanup = initExecutionWalkthrough(root);
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(observedTarget, root.querySelector('[data-execution-svg-frame]'), 'visibility observer tracks the actual visual frame');
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.dataset.executionLayout, 'portrait');
    assert.equal(root.querySelector('[data-execution-phase-number]').textContent, '01 / 07');
    assert.equal(root.querySelector('[data-execution-phase-mode]').textContent, 'Inline');
    assert.equal(root.querySelector('[data-execution-mode-panel]').getAttribute('aria-labelledby'), 'execution-tab-inline');
    assert.equal(root.querySelector('[data-execution-mode-description]').textContent, EXECUTION_MODE_DESCRIPTIONS.inline);
    assert.equal(root.querySelector('[data-execution-mode="inline"]').getAttribute('aria-selected'), 'true');
    assert.equal(root.querySelector('[data-execution-mode="inline"]').tabIndex, 0);
    assert.equal(root.querySelector('[data-execution-mode="deferred"]').tabIndex, -1);
    assert.equal(root.querySelector('[data-execution-phase-link]').getAttribute('href'), '/execution/http-and-deferred#inline-http');
    assert.equal(root.querySelectorAll('[data-execution-journal-event]').length, 0);
    assert.equal(root.querySelector('[data-execution-journal-status]').hidden, false);
    assert.equal(root.querySelector('[data-execution-journal-status]').textContent, JOURNAL_DEFINITION);
    assert.equal(root.querySelector('[data-execution-graph-stage]').textContent, 'Inline 01 / 07');
    assert.equal(root.querySelector('[data-execution-action="toggle"]').disabled, true);

    root.querySelector('[data-execution-mode="deferred"]').click();
    assert.equal(root.dataset.executionMode, 'deferred');
    assert.equal(root.querySelector('[data-execution-phase-number]').textContent, '01 / 09');
    root.querySelector('[data-execution-action="next"]').click();
    root.querySelector('[data-execution-action="next"]').click();
    root.querySelector('[data-execution-action="next"]').click();
    assert.equal(root.dataset.executionPhase, 'accept');
    assert.deepEqual([...root.querySelectorAll('[data-execution-journal-event]')].map((element) => element.textContent), [
      'operation.received',
      'operation.accepted',
    ]);
    assert.deepEqual([...root.querySelectorAll('[data-execution-journal-event]')].map((element) => element.dataset.executionNew), [
      'true',
      'true',
    ]);
    assert.equal(root.querySelector('[data-execution-journal-status]').textContent, JOURNAL_DEFINITION);
    assert.equal(root.querySelector('[data-execution-journal-status]').hidden, false);
    assert.equal(root.querySelector('[data-execution-phase-number]').textContent, '04 / 09');
    assert.equal(root.querySelector('[data-execution-graph-stage]').textContent, 'Deferred 04 / 09');
    assert.equal(root.querySelector('[data-execution-phase-link]').getAttribute('href'), '/execution/http-and-deferred#deferredの受付');
    assert.deepEqual([...root.querySelectorAll('[data-execution-journal-link]')].map((element) => element.getAttribute('href')), [
      '/concepts/journal#lifecycle-event',
      '/concepts/journal#lifecycle-event',
    ]);
    assert.equal(root.querySelector('[data-execution-action="previous"]').disabled, false);
    assert.equal(root.querySelector('[data-execution-action="next"]').disabled, false);
    assert.equal(root.querySelector('[data-execution-mode="inline"]').disabled, false);
    assert.equal(root.querySelector('[data-execution-step="0"]').disabled, false);

    root.querySelector('[data-execution-mode="inline"]').click();
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.dataset.executionPhase, 'request');
    assert.equal(root.querySelector('[data-execution-phase-number]').textContent, '01 / 07');
    assert.equal(root.querySelector('[data-execution-mode="inline"]').getAttribute('aria-selected'), 'true');
    assert.equal(root.querySelector('[data-execution-mode="inline"]').tabIndex, 0);
    assert.equal(root.querySelector('[data-execution-mode="deferred"]').tabIndex, -1);
    assert.equal(root.querySelector('[data-execution-mode-panel]').getAttribute('aria-labelledby'), 'execution-tab-inline');
    assert.equal(root.querySelector('[data-execution-mode-description]').textContent, EXECUTION_MODE_DESCRIPTIONS.inline);
    assert.equal(root.querySelector('[data-execution-phase-link]').getAttribute('href'), '/execution/http-and-deferred#inline-http');
    await new Promise((resolve) => setTimeout(resolve, 0));
    const mount = root.querySelector('[data-execution-svg-shadow]');
    const svg = mount.shadowRoot?.querySelector('svg');
    assert.ok(svg, 'canonical SVG is mounted inside the shadow root');
    assert.equal(root.querySelector('[data-execution-fallback]').hidden, true);
    assert.equal(svg.querySelectorAll('[data-node-id], [data-edge-id]').length, 2);
    assert.equal(svg.getAttribute('data-execution-playing'), 'false');
    const instanceStyle = [...mount.shadowRoot.querySelectorAll('style')].at(-1);
    assert.match(instanceStyle.textContent, /data-execution-playing="true"/);
    assert.match(instanceStyle.textContent, /path\[data-edge-id\]/);
    assert.match(instanceStyle.textContent, /data-execution-trace/);
    assert.match(instanceStyle.textContent, /stroke-dasharray: 6 24/);
    assert.match(instanceStyle.textContent, /stroke-dasharray: none !important/);
    assert.equal(svg.querySelector('marker').getAttribute('markerUnits'), 'userSpaceOnUse');
    assert.equal(svg.querySelector('[data-execution-trace="request-input"]').getAttribute('d'), 'M0 0 L20 20');
    assert.deepEqual([...svg.querySelectorAll('[data-node-id], [data-edge-id]')].map((element) => [element.getAttribute('data-node-id'), element.getAttribute('data-edge-id'), element.getAttribute('data-execution-active')]), [['http-entry', null, 'true'], [null, 'request-input', 'false']]);
    assert.equal(svg.querySelector('[data-node-id="http-entry"]').getAttribute('data-execution-active'), 'true');
    assert.equal(svg.querySelector('[data-node-id="http-entry"]').getAttribute('role'), null);
    assert.equal(svg.querySelector('[data-node-id="http-entry"]').getAttribute('tabindex'), null);

    const region = root.querySelector('[data-execution-region="http-entry"]');
    region.click();
    assert.equal(root.dataset.executionInspecting, 'true');
    assert.equal(root.dataset.executionInspectionId, 'http-entry');
    assert.equal(root.querySelector('[data-execution-inspection-panel]').hidden, false);
    assert.equal(root.querySelector('[data-execution-phase-copy]').hidden, true);
    assert.equal(root.querySelector('[data-execution-journal-panel]').hidden, true);
    assert.equal(root.querySelector('[data-execution-inspection-label]').textContent, 'HTTP');
    assert.equal(root.querySelector('[data-execution-inspection-role]').textContent, 'RequestをOperationへ渡す入口');
    assert.equal(root.querySelector('[data-execution-inspection-description]').textContent, 'RequestをOperationへ渡します。Inlineは完了後に応答し、Deferredは受付後にHTTP 202とOperation IDを返します。');
    assert.equal(root.querySelector('[data-execution-inspection-stage]').textContent, '停止中：Inline・段階 01 / 07');
    assert.equal(root.querySelector('[data-execution-inspection-link]').getAttribute('href'), '/execution/http-and-deferred#inline-http');
    assert.equal(root.querySelector('[data-execution-inspection-link]').hidden, false);
    assert.equal(region.getAttribute('aria-pressed'), 'true');
    assert.equal(root.querySelector('[data-execution-stage-badge]').hidden, false);
    assert.equal(root.querySelector('[data-execution-selection-status]').textContent, '選択中：HTTP');
    assert.equal(root.querySelector('[data-execution-selection-status]').hidden, false);
    assert.equal(root.querySelector('[data-execution-graph-stage]').textContent, 'Inline 01 / 07・停止中');
    assert.equal(svg.querySelector('[data-node-id="http-entry"]').getAttribute('data-execution-inspection-active'), 'true');
    assert.equal(svg.querySelector('[data-edge-id="request-input"]').getAttribute('data-execution-active'), 'false');
    assert.match(root.querySelector('[data-execution-status]').textContent, /詳しく見るリンクがあります/);

    root.querySelector('[data-execution-inspection-resume]').click();
    assert.equal(root.dataset.executionInspecting, 'false');
    assert.equal(root.hasAttribute('data-execution-inspection-id'), false);
    assert.equal(root.querySelector('[data-execution-inspection-panel]').hidden, true);
    assert.equal(root.querySelector('[data-execution-phase-copy]').hidden, false);
    assert.equal(root.querySelector('[data-execution-journal-panel]').hidden, false);
    assert.equal(region.getAttribute('aria-pressed'), 'false');
    assert.equal(root.querySelector('[data-execution-selection-status]').hidden, true);
    assert.equal(root.querySelector('[data-execution-graph-stage]').textContent, 'Inline 01 / 07');
    assert.equal(svg.querySelector('[data-node-id="http-entry"]').getAttribute('data-execution-inspection-active'), 'false');

    region.click();
    root.querySelector('[data-execution-action="next"]').click();
    assert.equal(root.dataset.executionInspecting, 'false', 'manual next exits inspection');
    assert.equal(root.querySelector('[data-execution-phase-copy]').hidden, false);
    region.click();
    root.querySelector('[data-execution-mode="deferred"]').click();
    assert.equal(root.dataset.executionInspecting, 'false', 'mode selection exits inspection');

    desktopMatches = true;
    for (const listener of layoutListeners) listener({ matches: true });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(root.dataset.executionLayout, 'landscape');
    assert.equal(root.querySelector('[data-execution-svg-frame]').style.aspectRatio, '1000 / 420');
    assert.equal(region.style.left, '4%');
    assert.equal(region.style.top, `${(64 / 420) * 100}%`);
    assert.equal(root.querySelector('[data-execution-svg-shadow]').shadowRoot.querySelectorAll('svg').length, 1);

    desktopMatches = false;
    for (const listener of layoutListeners) listener({ matches: false });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(root.dataset.executionLayout, 'portrait');
    assert.equal(root.querySelector('[data-execution-svg-frame]').style.aspectRatio, '420 / 560');
    assert.equal(region.style.left, `${(20 / 420) * 100}%`);

    cleanup();
    assert.equal(root.dataset.executionWalkthroughInitialized, 'stopped');
  } finally {
    for (const [name, original] of originals) {
      if (original.exists) globalThis[name] = original.value;
      else delete globalThis[name];
    }
    dom.window.close();
  }
});

test('execution walkthrough keeps a paused phase while its active trace continues and loops one mode', async () => {
  const harness = createControlledHarness();
  let cleanup;
  try {
    cleanup = initExecutionWalkthrough(harness.root);
    await harness.flush();
    const root = harness.root;
    const toggle = root.querySelector('[data-execution-action="toggle"]');
    const next = root.querySelector('[data-execution-action="next"]');
    assert.equal(harness.observedTarget(), root.querySelector('[data-execution-svg-frame]'));
    assert.equal(root.dataset.executionSvgState, 'ready');
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.dataset.executionPhase, 'request');
    assert.equal(root.querySelector('[data-execution-phase-number]').textContent, '01 / 07');
    assert.equal(root.dataset.executionPlayback, 'playing');
    assert.equal(root.dataset.executionTraceState, 'playing');
    const svgMount = root.querySelector('[data-execution-svg-shadow]');
    assert.equal(svgMount.shadowRoot.querySelector('svg').getAttribute('data-execution-playing'), 'true');
    assert.equal(Number(root.dataset.executionPhaseDuration), EXECUTION_PHASE_DURATION_MS);
    assert.equal(Number(root.dataset.executionPhaseDelay), EXECUTION_PHASE_DURATION_MS);
    assert.equal(Number(root.dataset.executionPhaseElapsed), 0);
    assert.equal(Number(root.dataset.executionPhaseRemaining), EXECUTION_PHASE_DURATION_MS);
    assert.equal(Number(root.dataset.executionPhaseProgress), 0);

    harness.advanceTime(EXECUTION_PHASE_DURATION_MS / 2);
    harness.runNextTimer(0);
    assert.equal(root.dataset.executionPhase, 'request', 'half a phase does not advance the step');
    assert.equal(Number(root.dataset.executionPhaseElapsed), EXECUTION_PHASE_DURATION_MS / 2);
    assert.equal(Number(root.dataset.executionPhaseRemaining), EXECUTION_PHASE_DURATION_MS / 2);
    assert.equal(Number(root.dataset.executionPhaseProgress), 0.5);
    assert.equal(root.querySelector('[data-execution-progress]').style.getPropertyValue('--execution-progress'), '50%');

    toggle.click();
    const pausedElapsed = Number(root.dataset.executionPhaseElapsed);
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(harness.activeTimers().length, 0);
    harness.advanceTime(EXECUTION_PHASE_DURATION_MS);
    assert.equal(Number(root.dataset.executionPhaseElapsed), pausedElapsed, 'manual pause freezes the monotonic phase clock');
    toggle.click();
    harness.advanceTime(EXECUTION_PHASE_DURATION_MS / 2 - 1);
    harness.runNextTimer(0);
    assert.equal(root.dataset.executionPhase, 'request');
    harness.advanceTime(1);
    harness.runNextTimer(0);
    assert.equal(root.dataset.executionPhase, 'bind', 'resume consumes only the remaining active time');
    assert.equal(Number(root.dataset.executionPhaseElapsed), 0, 'a phase transition resets the progress ring');
    assert.equal(root.querySelector('[data-execution-progress]').style.getPropertyValue('--execution-progress'), '0%');

    root.querySelector('[data-execution-mode="deferred"]').click();
    assert.equal(root.dataset.executionMode, 'deferred');
    assert.equal(root.dataset.executionPhase, 'request');
    next.click();
    next.click();
    next.click();
    assert.equal(root.dataset.executionPhase, 'accept');
    const svg = svgMount.shadowRoot.querySelector('svg');
    assert.equal(svg.querySelector('[data-execution-trace="deferred-accept"]').getAttribute('data-execution-active'), 'true');
    const pausedPhase = root.dataset.executionPhase;
    const pausedJournal = root.querySelector('[data-execution-journal]').textContent;
    toggle.click();
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(root.dataset.executionTraceState, 'playing');
    assert.equal(svg.getAttribute('data-execution-playing'), 'true');
    assert.equal(harness.activeTimers().length, 0);
    assert.equal(root.dataset.executionPhase, pausedPhase);
    assert.equal(root.querySelector('[data-execution-journal]').textContent, pausedJournal);

    toggle.click();
    assert.equal(root.dataset.executionPlayback, 'playing');
    assert.equal(root.dataset.executionTraceState, 'playing');
    assert.ok(harness.activeTimers().length > 0);
    for (let index = 0; index < 6; index += 1) harness.runNextTimer();
    assert.equal(root.dataset.executionMode, 'deferred');
    assert.equal(root.dataset.executionPhase, 'request');
    assert.equal(root.querySelectorAll('[data-execution-journal-event]').length, 0, 'the Deferred Journal resets at its own loop boundary');

    next.click();
    next.click();
    next.click();
    assert.equal(root.dataset.executionPhase, 'accept');
    assert.equal(root.querySelector('[data-execution-phase-link]').getAttribute('href'), '/execution/http-and-deferred#deferredの受付');
    assert.deepEqual([...root.querySelectorAll('[data-execution-journal-link]')].map((link) => [link.textContent, link.getAttribute('href')]), [
      ['operation.received', '/concepts/journal#lifecycle-event'],
      ['operation.accepted', '/concepts/journal#lifecycle-event'],
    ]);

    root.querySelector('[data-execution-mode="inline"]').click();
    for (let index = 0; index < 7; index += 1) harness.runNextTimer();
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.dataset.executionPhase, 'request');
    assert.equal(root.querySelectorAll('[data-execution-journal-event]').length, 0, 'the Inline Journal resets at its own loop boundary');
  } finally {
    harness.dispose(cleanup);
  }
});

test('execution walkthrough holds reading without stopping the trace and provides keyboard mode tabs', async () => {
  const harness = createControlledHarness();
  let cleanup;
  try {
    cleanup = initExecutionWalkthrough(harness.root);
    await harness.flush();
    const root = harness.root;
    const phaseCopy = root.querySelector('[data-execution-phase-copy]');
    const inlineTab = root.querySelector('[data-execution-mode="inline"]');
    const deferredTab = root.querySelector('[data-execution-mode="deferred"]');
    const panel = root.querySelector('[data-execution-mode-panel]');

    harness.dispatchPointer(phaseCopy, 'pointerenter', 'mouse');
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(root.dataset.executionTraceState, 'playing');
    assert.equal(root.querySelector('[data-execution-svg-shadow]').shadowRoot.querySelector('svg').getAttribute('data-execution-playing'), 'true');
    assert.equal(harness.activeTimers().length, 0);
    harness.dispatchPointer(phaseCopy, 'pointerleave', 'mouse');
    assert.equal(root.dataset.executionPlayback, 'playing');
    assert.equal(root.dataset.executionTraceState, 'playing');
    assert.equal(harness.activeTimers().length, 1);

    harness.dispatchPointer(phaseCopy, 'pointerenter', 'touch');
    assert.equal(root.dataset.executionPlayback, 'playing', 'touch pointer hover does not create a reading hold');
    assert.equal(harness.activeTimers().length, 1);
    harness.dispatchPointer(phaseCopy, 'pointerleave', 'touch');
    phaseCopy.dispatchEvent(new harness.dom.window.Event('focusin', { bubbles: true }));
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(root.dataset.executionTraceState, 'playing');
    assert.equal(harness.activeTimers().length, 0);
    phaseCopy.dispatchEvent(new harness.dom.window.FocusEvent('focusout', { bubbles: true, relatedTarget: root }));
    assert.equal(root.dataset.executionPlayback, 'playing');
    assert.equal(harness.activeTimers().length, 1);
    harness.advanceTime(1_200);
    harness.runNextTimer(0);
    const visibleElapsed = Number(root.dataset.executionPhaseElapsed);
    harness.setViewport(false);
    assert.equal(root.dataset.executionPlayback, 'paused');
    harness.advanceTime(4_000);
    assert.equal(Number(root.dataset.executionPhaseElapsed), visibleElapsed, 'offscreen time does not consume phase time');
    harness.setViewport(true);
    assert.equal(root.dataset.executionPlayback, 'playing');
    assert.equal(harness.activeTimers().length, 1);

    assert.equal(inlineTab.getAttribute('aria-selected'), 'true');
    assert.equal(inlineTab.tabIndex, 0);
    assert.equal(deferredTab.getAttribute('aria-selected'), 'false');
    assert.equal(deferredTab.tabIndex, -1);
    harness.dispatchKey(inlineTab, 'ArrowRight');
    assert.equal(deferredTab.getAttribute('aria-selected'), 'true');
    assert.equal(deferredTab.tabIndex, 0);
    assert.equal(inlineTab.tabIndex, -1);
    assert.equal(panel.getAttribute('aria-labelledby'), deferredTab.id);
    assert.equal(root.querySelector('[data-execution-mode-description]').textContent, EXECUTION_MODE_DESCRIPTIONS.deferred);
    assert.equal(root.dataset.executionMode, 'deferred');
    assert.equal(root.dataset.executionPhase, 'request');

    harness.dispatchKey(deferredTab, 'Home');
    assert.equal(inlineTab.getAttribute('aria-selected'), 'true');
    assert.equal(inlineTab.tabIndex, 0);
    assert.equal(deferredTab.tabIndex, -1);
    assert.equal(panel.getAttribute('aria-labelledby'), inlineTab.id);
    assert.equal(root.querySelector('[data-execution-mode-description]').textContent, EXECUTION_MODE_DESCRIPTIONS.inline);
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.dataset.executionPhase, 'request');

    harness.dispatchKey(inlineTab, 'End');
    assert.equal(deferredTab.getAttribute('aria-selected'), 'true');
    assert.equal(root.dataset.executionMode, 'deferred');
    harness.dispatchKey(deferredTab, 'Home');
    assert.equal(inlineTab.getAttribute('aria-selected'), 'true');
    assert.equal(root.dataset.executionMode, 'inline');
    assert.equal(root.querySelector('[data-execution-phase-link]').getAttribute('href'), '/execution/http-and-deferred#inline-http');

    harness.setViewport(false);
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(root.dataset.executionTraceState, 'paused');
    assert.equal(root.querySelector('[data-execution-svg-shadow]').shadowRoot.querySelector('svg').getAttribute('data-execution-playing'), 'false');
    harness.setViewport(true);
    assert.equal(root.dataset.executionTraceState, 'playing');
    harness.setReducedMotion(true);
    assert.equal(root.dataset.executionPlayback, 'paused');
    assert.equal(root.dataset.executionTraceState, 'paused');
    assert.equal(root.querySelector('[data-execution-action="toggle"]').disabled, true);
  } finally {
    harness.dispose(cleanup);
  }
});

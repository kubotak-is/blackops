export const EXECUTION_PHASE_DURATION_MS = 5_000;
const EXECUTION_PROGRESS_TICK_MS = 50;
const INITIALIZED = 'executionWalkthroughInitialized';
const GLOBAL_BOOTSTRAPPED = 'blackopsExecutionWalkthroughBootstrapped';

export const EXECUTION_WALKTHROUGH_EVENT_HREFS = Object.freeze({
  'operation.received': '/concepts/journal#lifecycle-event',
  'operation.accepted': '/concepts/journal#lifecycle-event',
  'attempt.started': '/concepts/journal#lifecycle-event',
  'attempt.succeeded': '/concepts/journal#lifecycle-event',
  'operation.completed': '/concepts/journal#lifecycle-event',
});

export const EXECUTION_MODE_DESCRIPTIONS = Object.freeze({
  inline: 'HTTPリクエストを受けたプロセスで処理を実行し、完了した結果をレスポンスとして返す方式です。',
  deferred: '受付の保存が確定すると、HTTP 202とOperation IDを返します。処理の実行と完了は、別プロセスのWorkerが引き継ぎます。',
});

export const JOURNAL_DEFINITION = 'Operationの受付や実行結果を記録します。Operation IDで処理の経過を追えます。';

const commonSteps = [
  {
    id: 'request',
    mode: 'inline',
    label: 'HTTP Requestを受け取る',
    description: 'HTTP Requestを受け、Operationの入力処理を開始します。',
    guideHref: (mode) => mode === 'inline' ? '/execution/http-and-deferred#inline-http' : '/execution/http-and-deferred#deferred-http',
    activeNodeIds: ['http-entry'],
    activeEdgeIds: [],
    events: [],
  },
  {
    id: 'bind',
    mode: 'inline',
    label: '入力を型付きValueへ変換・検証する',
    description: 'OperationValueの型と制約に合わせて入力を組み立てます。',
    guideHref: '/operations/validation',
    activeNodeIds: ['value-binding'],
    activeEdgeIds: ['request-input'],
    events: [],
  },
  {
    id: 'strategy',
    mode: 'inline',
    label: '実行経路を選ぶ',
    description: 'OperationのMetadataに従って、InlineまたはDeferredへ進みます。',
    guideHref: '/execution/http-and-deferred',
    activeNodeIds: ['strategy'],
    activeEdgeIds: ['input-strategy'],
    events: [],
  },
];

const inlineSteps = [
  {
    id: 'receive',
    label: 'Operationの受付を記録する',
    description: '入力の変換と実行経路の判定を終えてから、受付の事実を記録します。',
    guideHref: '/concepts/journal#lifecycle-event',
    activeNodeIds: ['strategy'],
    activeEdgeIds: [],
    events: ['operation.received'],
  },
  {
    id: 'run',
    label: '同じHTTPプロセスでOperationを実行する',
    description: 'Attemptを開始し、Valueと必要なContextをhandle()へ渡します。',
    guideHref: '/execution/http-and-deferred#inline-http',
    activeNodeIds: ['operation'],
    activeEdgeIds: ['inline-dispatch'],
    events: ['attempt.started'],
  },
  {
    id: 'complete',
    label: 'Outcomeを返し、成功と完了を記録する',
    description: 'Operationが正常完了しました。InlineではOutcome Recordを保存しません。',
    guideHref: '/execution/http-and-deferred#inlineの正常完了',
    activeNodeIds: ['operation', 'outcome'],
    activeEdgeIds: ['operation-outcome'],
    events: ['attempt.succeeded', 'operation.completed'],
  },
  {
    id: 'response',
    label: 'OutcomeをHTTP Responseへ変換する',
    description: 'このHTTP Requestへの応答として結果を返します。',
    guideHref: '/execution/http-and-deferred#inline-http',
    activeNodeIds: ['outcome', 'http-entry'],
    activeEdgeIds: [],
    events: [],
  },
];

const deferredSteps = [
  {
    id: 'accept',
    label: 'Value・Contextと受付Journalを保存する',
    description: '同じ受付Transactionで保存を確定し、処理を受け付けます。',
    guideHref: '/execution/http-and-deferred#deferredの受付',
    activeNodeIds: ['strategy', 'durable-transport'],
    activeEdgeIds: ['deferred-accept'],
    events: ['operation.received', 'operation.accepted'],
  },
  {
    id: 'accepted-response',
    label: 'HTTP 202とOperation IDを返す',
    description: '202は受付済みを示す応答です。処理の完了は待ちません。',
    guideHref: '/execution/http-and-deferred#deferredの受付',
    activeNodeIds: ['http-entry', 'durable-transport'],
    activeEdgeIds: [],
    events: [],
  },
  {
    id: 'claim',
    label: '別プロセスのWorkerがClaimする',
    description: 'Workerは保存されたValueとContextを取得し、同じOperation IDで実行を引き継ぎます。',
    guideHref: '/execution/http-and-deferred#worker',
    activeNodeIds: ['durable-transport', 'worker'],
    activeEdgeIds: ['worker-claim'],
    events: [],
  },
  {
    id: 'run',
    label: 'WorkerでOperationを実行する',
    description: 'Attemptを開始してhandle()を呼び出します。',
    guideHref: '/execution/http-and-deferred#workerによる実行と完了',
    activeNodeIds: ['worker', 'operation'],
    activeEdgeIds: ['worker-dispatch'],
    events: ['attempt.started'],
  },
  {
    id: 'outcome',
    label: 'OperationがOutcomeを返す',
    description: '業務処理の結果が揃い、完了の確定へ進みます。',
    guideHref: '/concepts/core-concepts#outcome',
    activeNodeIds: ['operation', 'outcome'],
    activeEdgeIds: ['operation-outcome'],
    events: [],
  },
  {
    id: 'complete',
    label: 'Outcomeと成功・完了Journalを確定する',
    description: 'Outcomeの保存と成功・完了イベントを、同じTransactionで確定します。',
    guideHref: '/execution/http-and-deferred#workerによる実行と完了',
    activeNodeIds: ['worker', 'outcome'],
    activeEdgeIds: [],
    events: ['attempt.succeeded', 'operation.completed'],
  },
];

const withCumulativeEvents = (mode, path) => {
  let events = [];
  return [...commonSteps.map((step) => ({ ...step, mode })), ...path.map((step) => ({ ...step, mode }))].map((step) => {
    events = [...events, ...step.events];
    return {
      id: step.id,
      mode: step.mode,
      label: step.label,
      description: step.description,
      guideHref: typeof step.guideHref === 'function' ? step.guideHref(mode) : step.guideHref,
      activeNodeIds: step.activeNodeIds,
      activeEdgeIds: step.activeEdgeIds,
      journalEvents: [...events],
      newJournalEvents: [...step.events],
    };
  });
};

export const EXECUTION_WALKTHROUGH_STEPS = Object.freeze([
  ...withCumulativeEvents('inline', inlineSteps),
  ...withCumulativeEvents('deferred', deferredSteps),
]);

const modeLabel = (mode) => mode === 'inline' ? 'Inline' : 'Deferred';
const isLocalGuideHref = (href) => typeof href === 'string' && href.startsWith('/') && !href.startsWith('//');

const isElement = (value) => value instanceof Element;

const parseData = (root) => {
  const dataElement = root.querySelector('[data-execution-walkthrough-data]');
  if (!dataElement) return null;
  try {
    const data = JSON.parse(dataElement.textContent || '{}');
    if (!Array.isArray(data.steps) || data.steps.length === 0) return null;
    return data;
  } catch {
    return null;
  }
};

const localSvgUrl = (src) => {
  if (!src || src.startsWith('//') || !src.startsWith('/')) return null;
  try {
    const url = new URL(src, window.location.href);
    if (url.origin !== window.location.origin || !url.pathname.startsWith('/')) return null;
    return url;
  } catch {
    return null;
  }
};

const readLayout = (payload, root) => {
  const viewBox = payload?.meta?.viewBox;
  if (!Array.isArray(viewBox) || viewBox.length !== 2 || viewBox.some((value) => !Number.isFinite(value) || value <= 0)) return null;
  if (!payload || !Array.isArray(payload.components)) return null;

  const components = new Map();
  for (const component of payload.components) {
    const position = component?.pos;
    const size = component?.size;
    if (
      !component?.id
      || components.has(component.id)
      || !Array.isArray(position)
      || position.length !== 2
      || !Array.isArray(size)
      || size.length !== 2
      || [...position, ...size].some((value) => !Number.isFinite(value) || value < 0)
      || size.some((value) => value <= 0)
      || position[0] + size[0] > viewBox[0]
      || position[1] + size[1] > viewBox[1]
    ) return null;
    components.set(component.id, { x: position[0], y: position[1], width: size[0], height: size[1] });
  }

  const regionIds = [...root.querySelectorAll('[data-execution-region]')]
    .map((region) => region.getAttribute('data-execution-region'));
  if (regionIds.length === 0 || regionIds.some((id) => !id || !components.has(id))) return null;
  return { width: viewBox[0], height: viewBox[1], components };
};

const loadLayout = async (src, root, signal) => {
  const source = localSvgUrl(src);
  if (!source) return null;
  const response = await fetch(source.href, { credentials: 'same-origin', signal });
  if (!response.ok) throw new Error('Layout request failed');
  let payload;
  try {
    payload = JSON.parse(await response.text());
  } catch {
    throw new Error('Layout document invalid');
  }
  return readLayout(payload, root);
};

const applyLayout = (root, layout, name) => {
  const frame = root.querySelector('[data-execution-svg-frame]');
  if (!frame) return;
  frame.style.aspectRatio = `${layout.width} / ${layout.height}`;
  frame.dataset.executionLayout = name;
  root.dataset.executionLayout = name;
  for (const region of root.querySelectorAll('[data-execution-region]')) {
    const id = region.getAttribute('data-execution-region');
    const component = id ? layout.components.get(id) : null;
    if (!component) continue;
    region.style.left = `${(component.x / layout.width) * 100}%`;
    region.style.top = `${(component.y / layout.height) * 100}%`;
    region.style.width = `${(component.width / layout.width) * 100}%`;
    region.style.height = `${(component.height / layout.height) * 100}%`;
  }
};

const stageNumber = (steps, index) => {
  const mode = steps[index]?.mode;
  const modeIndexes = steps.flatMap((step, stepIndex) => step.mode === mode ? [stepIndex] : []);
  const position = Math.max(0, modeIndexes.indexOf(index)) + 1;
  return `${String(position).padStart(2, '0')} / ${String(modeIndexes.length || steps.length).padStart(2, '0')}`;
};

const updateStageBadge = (root, step, index) => {
  const badge = root.querySelector('[data-execution-stage-badge]');
  const phaseNumber = root.querySelector('[data-execution-phase-number]');
  const inspectionStage = root.querySelector('[data-execution-inspection-stage]');
  const graphStage = root.querySelector('[data-execution-graph-stage]');
  const inspecting = root.dataset.executionInspecting === 'true';
  const data = parseData(root);
  const number = stageNumber(data?.steps ?? [step], index);
  if (phaseNumber) phaseNumber.textContent = number;
  if (inspectionStage) inspectionStage.textContent = inspecting
    ? `停止中：${modeLabel(step.mode)}・段階 ${number}`
    : `現在の段階 ${number}`;
  if (graphStage) graphStage.textContent = `${modeLabel(step.mode)} ${number}${inspecting ? '・停止中' : ''}`;
  root.dataset.executionStageNumber = number;
  root.dataset.executionStagePosition = String(index);
  if (!badge) return;
  badge.textContent = number.split(' / ')[0];

  const activeNodeId = step.activeNodeIds.find((id) => [...root.querySelectorAll('[data-execution-region]')]
    .some((region) => region.getAttribute('data-execution-region') === id));
  const region = activeNodeId
    ? [...root.querySelectorAll('[data-execution-region]')].find((candidate) => candidate.getAttribute('data-execution-region') === activeNodeId)
    : null;
  if (!region || !region.style.left || !region.style.top) {
    delete root.dataset.executionStageNode;
    badge.hidden = true;
    return;
  }
  root.dataset.executionStageNode = activeNodeId;
  badge.style.left = region.style.left;
  badge.style.top = region.style.top;
  badge.hidden = false;
};

const updateInspectionButtons = (root, selectedId = '') => {
  for (const button of root.querySelectorAll('[data-execution-inspect]')) {
    button.setAttribute('aria-pressed', button.getAttribute('data-execution-region') === selectedId ? 'true' : 'false');
  }
};

const setRegionAvailability = (root, available) => {
  for (const button of root.querySelectorAll('[data-execution-inspect]')) {
    button.disabled = !available;
  }
};

const setActiveTargets = (root, step, index = Number(root.dataset.executionStagePosition)) => {
  const nodeIds = new Set(step.activeNodeIds);
  const edgeIds = new Set(step.activeEdgeIds);
  const inspectionId = root.dataset.executionInspectionId;
  const shadow = root.querySelector('[data-execution-svg-shadow]')?.shadowRoot;
  const target = shadow ?? root;
  const svg = shadow?.querySelector('svg');
  if (svg) svg.setAttribute('data-execution-playing', root.dataset.executionTraceState === 'playing' ? 'true' : 'false');
  for (const element of target.querySelectorAll('[data-node-id], [data-edge-id], [data-execution-trace]')) {
    const nodeId = element.getAttribute('data-node-id');
    const edgeId = element.getAttribute('data-edge-id');
    const traceId = element.getAttribute('data-execution-trace');
    const active = (nodeId && nodeIds.has(nodeId)) || (edgeId && edgeIds.has(edgeId)) || (traceId && edgeIds.has(traceId));
    const visualActive = inspectionId ? Boolean(nodeId && nodeId === inspectionId) : active;
    element.setAttribute('data-execution-active', visualActive ? 'true' : 'false');
    element.setAttribute('data-execution-inspection-active', nodeId && nodeId === inspectionId ? 'true' : 'false');
  }
  updateStageBadge(root, step, Number.isInteger(index) && index >= 0 ? index : 0);
};

const replaceJournal = (root, events, newEvents) => {
  const journal = root.querySelector('[data-execution-journal]');
  const status = root.querySelector('[data-execution-journal-status]');
  if (status) {
    status.hidden = false;
    status.textContent = JOURNAL_DEFINITION;
  }
  if (!journal) return;
  const remainingNewEvents = new Map();
  for (const event of newEvents) {
    remainingNewEvents.set(event, (remainingNewEvents.get(event) ?? 0) + 1);
  }
  journal.replaceChildren(...events.map((event) => {
    const item = document.createElement('li');
    item.dataset.executionJournalEvent = '';
    const isNew = (remainingNewEvents.get(event) ?? 0) > 0;
    item.dataset.executionNew = isNew ? 'true' : 'false';
    if (isNew) remainingNewEvents.set(event, remainingNewEvents.get(event) - 1);
    const href = EXECUTION_WALKTHROUGH_EVENT_HREFS[event];
    if (href) {
      const link = document.createElement('a');
      link.href = href;
      link.dataset.executionJournalLink = '';
      link.setAttribute('aria-label', `${event}の説明をJournalガイドで読む`);
      link.textContent = event;
      item.append(link);
    } else {
      item.textContent = event;
    }
    return item;
  }));
};

const updateModeButtons = (root, mode) => {
  const tabs = [...root.querySelectorAll('[data-execution-mode]')];
  const selectedTab = tabs.find((button) => button.getAttribute('data-execution-mode') === mode);
  for (const button of tabs) {
    const selected = button === selectedTab;
    button.setAttribute('aria-selected', selected ? 'true' : 'false');
    button.tabIndex = selected ? 0 : -1;
  }
  const panel = root.querySelector('[data-execution-mode-panel]');
  if (panel && selectedTab?.id) panel.setAttribute('aria-labelledby', selectedTab.id);
  const description = root.querySelector('[data-execution-mode-description]');
  if (description) description.textContent = EXECUTION_MODE_DESCRIPTIONS[mode] ?? '';
};

const updatePhaseButtons = (root, index) => {
  for (const button of root.querySelectorAll('[data-execution-step]')) {
    if (Number(button.getAttribute('data-execution-step')) === index) {
      button.setAttribute('aria-current', 'step');
    } else {
      button.removeAttribute('aria-current');
    }
  }
};

const modeRangeFor = (steps, mode) => {
  const indexes = steps.flatMap((step, index) => step.mode === mode ? [index] : []);
  if (indexes.length === 0) return null;
  return { start: indexes[0], end: indexes[indexes.length - 1] };
};

const loadCanonicalSvg = async (root, controller, svgSrc, onReady, isCurrent = () => true) => {
  if (!isCurrent()) return;
  const source = localSvgUrl(svgSrc ?? root.getAttribute('data-execution-svg-src'));
  const frame = root.querySelector('[data-execution-svg-frame]');
  const fallback = root.querySelector('[data-execution-fallback]');
  const mount = root.querySelector('[data-execution-svg-shadow]');
  const error = root.querySelector('[data-execution-svg-error]');
  if (!source || !frame || !fallback || !mount) return;

  fallback.hidden = false;
  if (error) error.hidden = true;
  root.dataset.executionSvgState = 'pending';

  try {
    const response = await fetch(source.href, { credentials: 'same-origin', signal: controller.signal });
    if (!response.ok) throw new Error('SVG request failed');
    const markup = await response.text();
    const parsed = new DOMParser().parseFromString(markup, 'image/svg+xml');
    const svg = parsed.documentElement;
    if (!svg || svg.localName !== 'svg' || parsed.querySelector('parsererror')) throw new Error('SVG document invalid');
    if (controller.signal.aborted || !isCurrent()) return;

    const instance = document.importNode(svg, true);
    for (const marker of instance.querySelectorAll('marker')) {
      // Keep arrowheads the same size when active paths receive a thicker stroke.
      marker.setAttribute('markerUnits', 'userSpaceOnUse');
    }
    for (const attribute of [...instance.attributes]) {
      if (attribute.name === 'tabindex' || attribute.name === 'role' || attribute.name.startsWith('aria-') || attribute.name.startsWith('on')) {
        instance.removeAttribute(attribute.name);
      }
    }
    instance.setAttribute('aria-hidden', 'true');
    instance.setAttribute('focusable', 'false');
    instance.classList.add('execution-walkthrough__svg');
    const theme = document.documentElement.getAttribute('data-theme');
    if (theme === 'light' || theme === 'dark') instance.setAttribute('data-theme', theme);
    for (const element of instance.querySelectorAll('*')) {
      for (const attribute of [...element.attributes]) {
        if (attribute.name === 'tabindex' || attribute.name === 'role' || attribute.name.startsWith('aria-') || attribute.name.startsWith('on')) {
          element.removeAttribute(attribute.name);
        }
      }
    }
    const traceGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    traceGroup.setAttribute('data-execution-traces', '');
    traceGroup.setAttribute('aria-hidden', 'true');
    for (const edge of instance.querySelectorAll('path[data-edge-id]')) {
      const edgeId = edge.getAttribute('data-edge-id');
      if (!edgeId) continue;
      const trace = edge.cloneNode(false);
      trace.removeAttribute('data-edge-id');
      trace.removeAttribute('marker-start');
      trace.removeAttribute('marker-end');
      trace.removeAttribute('data-edge-from');
      trace.removeAttribute('data-edge-to');
      trace.removeAttribute('data-edge-label');
      trace.removeAttribute('data-edge-key');
      trace.setAttribute('data-execution-trace', edgeId);
      trace.setAttribute('class', 'execution-walkthrough__trace');
      trace.setAttribute('fill', 'none');
      trace.setAttribute('pointer-events', 'none');
      traceGroup.append(trace);
    }
    const firstEdgeLabel = instance.querySelector('[data-edge-id]:not(path)');
    if (traceGroup.childElementCount > 0) {
      if (firstEdgeLabel?.parentNode) firstEdgeLabel.parentNode.insertBefore(traceGroup, firstEdgeLabel);
      else instance.append(traceGroup);
    }
    const shadow = mount.shadowRoot ?? mount.attachShadow({ mode: 'open' });
    const style = document.createElement('style');
    style.textContent = `
      :host { display: block; width: 100%; }
      svg.execution-walkthrough__svg { display: block; height: auto; max-width: 100%; width: 100%; }
      /* The canonical export keeps its own theme variables. Rebind this
         instance to the reader palette without changing the source SVG. */
      svg.execution-walkthrough__svg {
        --bg: var(--landing-raised, #ffffff) !important;
        --grid: var(--landing-line, #d9ded3) !important;
        --text: var(--landing-ink, #171b21) !important;
        --text-muted: var(--landing-muted, #59616e) !important;
        --text-dim: var(--landing-muted, #59616e) !important;
        --text-faint: var(--landing-muted, #59616e) !important;
        --panel: var(--landing-raised, #ffffff) !important;
        --panel-border: var(--landing-line, #d9ded3) !important;
        --lane-fill: color-mix(in srgb, var(--landing-surface, #f1f2ed) 72%, transparent) !important;
        --lane-stroke: var(--landing-line, #d9ded3) !important;
        --arrow: var(--landing-muted, #59616e) !important;
        --arrow-emphasis: var(--landing-accent, #385718) !important;
        --mask: var(--landing-raised, #ffffff) !important;
        --frontend-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 96%, var(--landing-line, #d9ded3)) !important;
        --frontend-stroke: var(--landing-line, #d9ded3) !important;
        --backend-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 96%, var(--landing-line, #d9ded3)) !important;
        --backend-stroke: var(--landing-line, #d9ded3) !important;
        --database-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 94%, var(--landing-line, #d9ded3)) !important;
        --database-stroke: var(--landing-line, #d9ded3) !important;
        --cloud-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 94%, var(--landing-line, #d9ded3)) !important;
        --cloud-stroke: var(--landing-line, #d9ded3) !important;
        --security-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 94%, var(--landing-line, #d9ded3)) !important;
        --security-stroke: var(--landing-line, #d9ded3) !important;
        --messagebus-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 94%, var(--landing-line, #d9ded3)) !important;
        --messagebus-stroke: var(--landing-line, #d9ded3) !important;
        --external-fill: color-mix(in srgb, var(--landing-raised, #ffffff) 92%, var(--landing-line, #d9ded3)) !important;
        --external-stroke: var(--landing-line, #d9ded3) !important;
        --toolbar-bg: var(--landing-raised, #ffffff) !important;
        --toolbar-border: var(--landing-line, #d9ded3) !important;
        --toolbar-text: var(--landing-ink, #171b21) !important;
        --toolbar-hover: var(--landing-surface, #f1f2ed) !important;
        --toolbar-menu-bg: var(--landing-raised, #ffffff) !important;
      }
      svg [data-node-id] { opacity: .92; transition: opacity .25s ease, filter .25s ease, stroke .25s ease; }
      svg path[data-edge-id] { opacity: .72; transition: opacity .25s ease, filter .25s ease, stroke .25s ease; }
      svg [data-edge-id]:not(path) { opacity: 1; transition: opacity .25s ease, filter .25s ease; }
      svg.execution-walkthrough__svg [data-node-id]:hover,
      svg.execution-walkthrough__svg [data-node-id]:focus-visible,
      svg.execution-walkthrough__svg [data-node-id][data-execution-active="true"] { filter: none !important; }
      svg [data-node-id][data-execution-active="true"] { opacity: 1; }
      svg [data-node-id][data-execution-active="true"] rect.c-frontend,
      svg [data-node-id][data-execution-active="true"] rect.c-backend,
      svg [data-node-id][data-execution-active="true"] rect.c-database {
        fill: color-mix(in srgb, var(--landing-action, #c9ed79) 24%, var(--landing-raised, #ffffff)) !important;
        stroke: var(--landing-accent, var(--bo-accent, #385718)) !important;
        stroke-width: 2 !important;
      }
      svg [data-node-id][data-execution-inspection-active="true"] { filter: none !important; opacity: 1 !important; }
      svg [data-node-id][data-execution-inspection-active="true"] rect.c-frontend,
      svg [data-node-id][data-execution-inspection-active="true"] rect.c-backend,
      svg [data-node-id][data-execution-inspection-active="true"] rect.c-database {
        fill: color-mix(in srgb, var(--landing-action, #c9ed79) 32%, var(--landing-raised, #ffffff)) !important;
        stroke: var(--landing-accent, var(--bo-accent, #385718)) !important;
        stroke-width: 2.4 !important;
      }
      svg [data-edge-id] text { fill: var(--landing-muted, var(--text-muted, #59616e)) !important; opacity: 1; stroke: none !important; }
      svg path[data-edge-id][data-execution-active="true"] { filter: none !important; opacity: 1; stroke: var(--landing-muted, var(--text-muted, #59616e)) !important; stroke-dasharray: none !important; stroke-width: 2.4 !important; }
      svg [data-edge-id]:not(path)[data-execution-active="true"] { filter: none !important; opacity: 1; stroke: none !important; }
      svg [data-edge-id]:not(path)[data-execution-active="true"] text { fill: var(--text-muted, var(--landing-muted, #59616e)) !important; stroke: none !important; }
      svg path[data-execution-trace] { fill: none !important; opacity: 0; pointer-events: none; stroke: var(--landing-accent, var(--bo-accent, #385718)) !important; stroke-dasharray: 6 24; stroke-linecap: round; stroke-width: 4 !important; }
      svg path[data-execution-trace][data-execution-active="true"] { opacity: 1; }
      svg[data-execution-playing="true"] path[data-execution-trace][data-execution-active="true"] { animation: execution-walkthrough-trace-flow 1.05s linear infinite; stroke-dasharray: 6 24; stroke-dashoffset: 30; }
      @keyframes execution-walkthrough-trace-flow { to { stroke-dashoffset: -30; } }
      @media (prefers-reduced-motion: reduce) {
        svg [data-node-id], svg path[data-edge-id], svg [data-edge-id]:not(path), svg path[data-execution-trace] { transition: none; }
        svg path[data-execution-trace][data-execution-active="true"] { animation: none; }
      }
    `;
    shadow.replaceChildren(instance, style);
    fallback.hidden = true;
    root.dataset.executionSvgState = 'ready';
    onReady?.(instance);
  } catch (errorValue) {
    if (errorValue?.name === 'AbortError' || !isCurrent()) return;
    root.dataset.executionSvgState = 'fallback';
    if (error) error.hidden = false;
  }
};

export function initExecutionWalkthrough(root) {
  if (!isElement(root) || root.dataset[INITIALIZED] === 'true') return () => {};

  const data = parseData(root);
  if (!data) {
    root.dataset.executionState = 'invalid';
    return () => {};
  }

  const steps = data.steps;
  const controller = new AbortController();
  const reducedMotionQuery = typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false, addEventListener() {}, removeEventListener() {} };
  const initialIndex = Number(root.getAttribute('data-execution-initial-step'));
  let currentIndex = Number.isInteger(initialIndex) && initialIndex >= 0 && initialIndex < steps.length ? initialIndex : 0;
  let timer = null;
  let phaseElapsedMs = 0;
  let phaseStartedAt = null;
  let autoRequested = !reducedMotionQuery.matches;
  let reducedMotion = reducedMotionQuery.matches;
  let inViewport = true;
  let readingHold = false;
  let cleanedUp = false;
  let themeObserver = null;
  const modeDescription = root.querySelector('[data-execution-mode-description]');
  const phaseCopy = root.querySelector('[data-execution-phase-copy]');
  const journalPanel = root.querySelector('[data-execution-journal-panel]');
  const inspectionPanel = root.querySelector('[data-execution-inspection-panel]');
  const progressIndicator = root.querySelector('[data-execution-progress]');
  const readingPanels = [modeDescription, phaseCopy, journalPanel, inspectionPanel].filter(Boolean);
  const mobileSvgSrc = root.getAttribute('data-execution-svg-src');
  const mobileDataSrc = root.getAttribute('data-execution-data-src');
  const desktopSvgSrc = root.getAttribute('data-execution-desktop-svg-src');
  const desktopDataSrc = root.getAttribute('data-execution-desktop-data-src');
  const layoutMedia = desktopSvgSrc && desktopDataSrc && typeof window.matchMedia === 'function'
    ? window.matchMedia('(min-width: 60rem)')
    : null;
  let layoutGeneration = 0;

  root.dataset[INITIALIZED] = 'true';

  const clearTimer = () => {
    if (timer !== null) window.clearTimeout(timer);
    timer = null;
  };

  const monotonicNow = () => {
    const now = typeof window.performance?.now === 'function' ? window.performance.now() : Date.now();
    return Number.isFinite(now) ? now : Date.now();
  };

  const elapsedPhaseTime = () => {
    if (phaseStartedAt === null) return Math.min(EXECUTION_PHASE_DURATION_MS, phaseElapsedMs);
    return Math.min(EXECUTION_PHASE_DURATION_MS, phaseElapsedMs + Math.max(0, monotonicNow() - phaseStartedAt));
  };

  const setProgress = () => {
    const elapsed = Math.max(0, Math.min(EXECUTION_PHASE_DURATION_MS, elapsedPhaseTime()));
    const remaining = Math.max(0, EXECUTION_PHASE_DURATION_MS - elapsed);
    const progress = EXECUTION_PHASE_DURATION_MS === 0 ? 1 : elapsed / EXECUTION_PHASE_DURATION_MS;
    root.dataset.executionPhaseDuration = String(EXECUTION_PHASE_DURATION_MS);
    root.dataset.executionPhaseElapsed = String(Math.round(elapsed));
    root.dataset.executionPhaseRemaining = String(Math.ceil(remaining));
    root.dataset.executionPhaseProgress = String(Number(progress.toFixed(4)));
    progressIndicator?.style.setProperty('--execution-progress', `${progress * 100}%`);
  };

  const freezePhaseClock = () => {
    if (phaseStartedAt !== null) {
      phaseElapsedMs = elapsedPhaseTime();
      phaseStartedAt = null;
    }
    setProgress();
  };

  const resetPhaseClock = () => {
    phaseElapsedMs = 0;
    phaseStartedAt = null;
    setProgress();
  };

  const startPhaseClock = () => {
    if (phaseStartedAt === null) phaseStartedAt = monotonicNow();
    setProgress();
  };

  const traceBlocked = () => cleanedUp || document.hidden || !inViewport || reducedMotion;
  const phaseBlocked = () => traceBlocked() || readingHold;

  const setInspectionVisibility = (inspecting) => {
    if (inspectionPanel) inspectionPanel.hidden = !inspecting;
    if (phaseCopy) phaseCopy.hidden = inspecting;
    if (journalPanel) journalPanel.hidden = inspecting;
  };

  const clearInspection = () => {
    const wasInspecting = root.dataset.executionInspecting === 'true' || Boolean(root.dataset.executionInspectionId);
    if (!wasInspecting) return false;
    delete root.dataset.executionInspectionId;
    root.dataset.executionInspecting = 'false';
    setInspectionVisibility(false);
    const selectionStatus = root.querySelector('[data-execution-selection-status]');
    if (selectionStatus) {
      selectionStatus.textContent = '';
      selectionStatus.hidden = true;
    }
    updateInspectionButtons(root);
    setActiveTargets(root, steps[currentIndex], currentIndex);
    return true;
  };

  const setSvgPlaybackState = () => {
    const phasePlaying = autoRequested && !phaseBlocked();
    const tracePlaying = !traceBlocked();
    root.dataset.executionPlayback = phasePlaying ? 'playing' : 'paused';
    root.dataset.executionTraceState = tracePlaying ? 'playing' : 'paused';
    const svg = root.querySelector('[data-execution-svg-shadow]')?.shadowRoot?.querySelector('svg');
    if (svg) {
      svg.setAttribute('data-execution-playing', tracePlaying ? 'true' : 'false');
      svg.setAttribute('data-execution-trace-state', tracePlaying ? 'playing' : 'paused');
    }
  };

  const setPlaybackLabel = () => {
    setSvgPlaybackState();
    setProgress();
    for (const control of root.querySelectorAll('[data-execution-enhance]')) {
      if (control instanceof HTMLButtonElement) control.disabled = false;
    }
    const toggle = root.querySelector('[data-execution-action="toggle"]');
    if (!(toggle instanceof HTMLButtonElement)) return;
    toggle.disabled = reducedMotion;
    toggle.setAttribute('aria-pressed', autoRequested && !reducedMotion ? 'true' : 'false');
    toggle.setAttribute('aria-label', reducedMotion
      ? '自動再生は停止中です。手動操作は利用できます。'
      : root.dataset.executionInspecting === 'true'
        ? '自動再生を開始して要素確認を終了'
      : autoRequested ? '自動再生を一時停止' : '自動再生を開始');
    const label = reducedMotion ? '停止中' : autoRequested ? '一時停止' : '再生';
    const labelElement = toggle.querySelector('[data-execution-toggle-label]');
    if (labelElement) labelElement.textContent = label;
    else toggle.textContent = label;
  };

  const loadVariant = async (desktop) => {
    const variantName = desktop ? 'landscape' : 'portrait';
    const svgSrc = desktop ? desktopSvgSrc : mobileSvgSrc;
    const dataSrc = desktop ? desktopDataSrc : mobileDataSrc;
    if (!svgSrc) return;
    const generation = ++layoutGeneration;
    const frame = root.querySelector('[data-execution-svg-frame]');
    const fallback = root.querySelector('[data-execution-fallback]');
    if (frame) frame.style.aspectRatio = '';
    if (fallback) fallback.hidden = false;
    root.dataset.executionLayout = `${variantName}-pending`;
    root.dataset.executionSvgState = 'pending';
    setRegionAvailability(root, false);

    let layout = null;
    if (dataSrc) {
      try {
        layout = await loadLayout(dataSrc, root, controller.signal);
      } catch (errorValue) {
        if (errorValue?.name === 'AbortError') return;
      }
    }
    if (generation !== layoutGeneration || controller.signal.aborted) return;
    if (layout) {
      applyLayout(root, layout, variantName);
      setRegionAvailability(root, true);
    } else if (variantName === 'portrait' && !dataSrc) {
      root.dataset.executionLayout = variantName;
      setRegionAvailability(root, true);
    } else {
      root.dataset.executionLayout = `${variantName}-error`;
    }
    await loadCanonicalSvg(root, controller, svgSrc, (instance) => {
      if (generation !== layoutGeneration) return;
      const applyTheme = () => {
        const theme = document.documentElement.getAttribute('data-theme');
        if (theme === 'light' || theme === 'dark') instance.setAttribute('data-theme', theme);
        else instance.removeAttribute('data-theme');
      };
      applyTheme();
      themeObserver?.disconnect();
      themeObserver = null;
      if (typeof MutationObserver === 'function') {
        themeObserver = new MutationObserver(applyTheme);
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
      }
      setActiveTargets(root, steps[currentIndex], currentIndex);
    }, () => generation === layoutGeneration);
  };

  const schedule = ({ updatePlayback = true } = {}) => {
    clearTimer();
    if (!autoRequested || phaseBlocked() || steps.length < 2) {
      freezePhaseClock();
      if (updatePlayback) setSvgPlaybackState();
      return;
    }
    startPhaseClock();
    if (updatePlayback) setSvgPlaybackState();
    const remaining = Math.max(0, EXECUTION_PHASE_DURATION_MS - elapsedPhaseTime());
    root.dataset.executionPhaseDelay = String(Math.ceil(remaining));
    if (remaining <= 0) {
      const range = modeRangeFor(steps, steps[currentIndex].mode);
      const nextIndex = range && currentIndex >= range.end ? range.start : currentIndex + 1;
      selectStep(nextIndex);
      schedule();
      return;
    }
    timer = window.setTimeout(() => {
      timer = null;
      if (phaseBlocked() || !autoRequested) {
        freezePhaseClock();
        setSvgPlaybackState();
        return;
      }
      setProgress();
      if (elapsedPhaseTime() < EXECUTION_PHASE_DURATION_MS) {
        schedule({ updatePlayback: false });
        return;
      }
      const range = modeRangeFor(steps, steps[currentIndex].mode);
      const nextIndex = range && currentIndex >= range.end ? range.start : currentIndex + 1;
      selectStep(nextIndex);
      schedule();
    }, Math.min(EXECUTION_PROGRESS_TICK_MS, Math.max(1, remaining)));
  };

  const announceStep = (step) => {
    const status = root.querySelector('[data-execution-status]');
    if (!status) return;
    const events = step.newJournalEvents.length > 0
      ? `。追加されたJournalイベント: ${step.newJournalEvents.join('、')}`
      : '';
    const number = root.dataset.executionStageNumber ?? '01';
    status.textContent = `${modeLabel(step.mode)} 段階 ${number}。${step.label}${events}`;
  };

  const announceInspection = (label, description) => {
    const status = root.querySelector('[data-execution-status]');
    if (!status) return;
    const number = root.dataset.executionStageNumber ?? '01';
    status.textContent = `段階 ${number}。${label}。${description}。自動再生を停止しました。詳しく見るリンクがあります。`;
  };

  const selectStep = (index, { announce = false } = {}) => {
    clearInspection();
    resetPhaseClock();
    currentIndex = (index + steps.length) % steps.length;
    const step = steps[currentIndex];
    root.dataset.executionPhase = step.id;
    root.dataset.executionMode = step.mode;
    root.dataset.executionState = 'ready';
    const modeElement = root.querySelector('[data-execution-phase-mode]');
    const labelElement = root.querySelector('[data-execution-phase-label]');
    const descriptionElement = root.querySelector('[data-execution-phase-description]');
    if (modeElement) modeElement.textContent = modeLabel(step.mode);
    if (labelElement) labelElement.textContent = step.label;
    if (descriptionElement) descriptionElement.textContent = step.description;
    const phaseLink = root.querySelector('[data-execution-phase-link]');
    if (phaseLink && isLocalGuideHref(step.guideHref)) {
      phaseLink.setAttribute('href', step.guideHref);
      phaseLink.setAttribute('aria-label', `${step.label}の詳しい説明を読む`);
    }
    updateModeButtons(root, step.mode);
    updatePhaseButtons(root, currentIndex);
    replaceJournal(root, step.journalEvents, step.newJournalEvents);
    root.dataset.executionPhaseDelay = String(EXECUTION_PHASE_DURATION_MS);
    setActiveTargets(root, step, currentIndex);
    if (announce) announceStep(step);
  };

  const moveWithinMode = (direction, announce = false) => {
    const range = modeRangeFor(steps, steps[currentIndex].mode);
    if (!range) return;
    const nextIndex = currentIndex + direction;
    if (nextIndex < range.start) {
      selectStep(range.end, { announce });
    } else if (nextIndex > range.end) {
      selectStep(range.start, { announce });
    } else {
      selectStep(nextIndex, { announce });
    }
    schedule();
  };

  const on = (selector, eventName, handler) => {
    for (const element of root.querySelectorAll(selector)) {
      element.addEventListener(eventName, handler, { signal: controller.signal });
    }
  };

  let pointerReading = false;
  let focusReading = false;
  const isReadingPanelTarget = (target) => Boolean(target) && readingPanels.some((panel) => panel.contains(target));
  const readingHoldChanged = () => {
    const next = pointerReading || focusReading;
    if (next === readingHold) return;
    readingHold = next;
    schedule();
  };
  for (const panel of readingPanels) {
    panel.addEventListener('pointerenter', (event) => {
      if (event.pointerType === 'touch') return;
      pointerReading = true;
      readingHoldChanged();
    }, { signal: controller.signal });
    panel.addEventListener('pointerleave', (event) => {
      if (event.pointerType === 'touch') return;
      pointerReading = false;
      readingHoldChanged();
    }, { signal: controller.signal });
    panel.addEventListener('focusin', () => {
      focusReading = true;
      readingHoldChanged();
    }, { signal: controller.signal });
    panel.addEventListener('focusout', (event) => {
      if (isReadingPanelTarget(event.relatedTarget)) return;
      focusReading = false;
      readingHoldChanged();
    }, { signal: controller.signal });
  }

  on('[data-execution-action="previous"]', 'click', () => {
    moveWithinMode(-1, true);
  });
  on('[data-execution-action="next"]', 'click', () => {
    moveWithinMode(1, true);
  });
  on('[data-execution-action="toggle"]', 'click', () => {
    if (reducedMotion) return;
    clearInspection();
    if (autoRequested) freezePhaseClock();
    autoRequested = !autoRequested;
    setPlaybackLabel();
    schedule();
  });
  on('[data-execution-inspect]', 'click', (event) => {
    const button = event.currentTarget;
    const id = button.getAttribute('data-execution-region');
    const label = button.getAttribute('data-execution-detail-label');
    const role = button.getAttribute('data-execution-detail-role');
    const description = button.getAttribute('data-execution-detail-description');
    const href = button.getAttribute('data-execution-detail-href');
    if (!id || !label || !role || !description || !href || href.startsWith('//') || !href.startsWith('/')) return;

    freezePhaseClock();
    clearTimer();
    autoRequested = false;
    root.dataset.executionInspecting = 'true';
    root.dataset.executionInspectionId = id;
    setInspectionVisibility(true);
    const inspectionLabel = root.querySelector('[data-execution-inspection-label]');
    const inspectionRole = root.querySelector('[data-execution-inspection-role]');
    const inspectionDescription = root.querySelector('[data-execution-inspection-description]');
    const inspectionLink = root.querySelector('[data-execution-inspection-link]');
    const selectionStatus = root.querySelector('[data-execution-selection-status]');
    if (inspectionLabel) inspectionLabel.textContent = label;
    if (inspectionRole) inspectionRole.textContent = role;
    if (inspectionDescription) inspectionDescription.textContent = description;
    if (inspectionLink) {
      inspectionLink.setAttribute('href', href);
      inspectionLink.hidden = false;
    }
    if (selectionStatus) {
      selectionStatus.textContent = `選択中：${label}`;
      selectionStatus.hidden = false;
    }
    updateInspectionButtons(root, id);
    setActiveTargets(root, steps[currentIndex], currentIndex);
    setPlaybackLabel();
    announceInspection(label, `${role}。${description}`);
    schedule();
  });
  on('[data-execution-inspection-resume]', 'click', () => {
    if (!clearInspection()) return;
    setPlaybackLabel();
    schedule();
  });
  const selectMode = (mode, announce = true) => {
    const index = steps.findIndex((step) => step.mode === mode);
    if (index < 0) return;
    selectStep(index, { announce });
    schedule();
  };
  on('[data-execution-mode]', 'click', (event) => {
    const mode = event.currentTarget.getAttribute('data-execution-mode');
    if (mode) selectMode(mode);
  });
  on('[data-execution-mode]', 'keydown', (event) => {
    const tabs = [...root.querySelectorAll('[data-execution-mode]')];
    const current = tabs.indexOf(event.currentTarget);
    if (current < 0) return;
    let next = current;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (current + 1) % tabs.length;
    else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (current - 1 + tabs.length) % tabs.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = tabs.length - 1;
    else return;
    event.preventDefault();
    const tab = tabs[next];
    tab.focus();
    const mode = tab.getAttribute('data-execution-mode');
    if (mode) selectMode(mode);
  });
  on('[data-execution-step]', 'click', (event) => {
    const index = Number(event.currentTarget.getAttribute('data-execution-step'));
    if (Number.isInteger(index) && index >= 0 && index < steps.length) {
      selectStep(index, { announce: true });
      schedule();
    }
  });

  const visibilityChanged = () => {
    schedule();
  };
  document.addEventListener('visibilitychange', visibilityChanged, { signal: controller.signal });

  let observer = null;
  if (typeof IntersectionObserver === 'function') {
    observer = new IntersectionObserver(([entry]) => {
      inViewport = Boolean(entry?.isIntersecting);
      visibilityChanged();
    }, { threshold: 0.1 });
    observer.observe(root.querySelector('[data-execution-svg-frame]') ?? root);
  }

  const motionChanged = (event) => {
    reducedMotion = Boolean(event.matches);
    if (reducedMotion) {
      autoRequested = false;
      clearTimer();
    }
    root.dataset.executionMotion = reducedMotion ? 'reduced' : 'full';
    setPlaybackLabel();
    schedule();
  };
  reducedMotionQuery.addEventListener?.('change', motionChanged, { signal: controller.signal });

  const cleanup = () => {
    layoutGeneration += 1;
    freezePhaseClock();
    cleanedUp = true;
    readingHold = false;
    autoRequested = false;
    clearTimer();
    setSvgPlaybackState();
    observer?.disconnect();
    themeObserver?.disconnect();
    layoutMedia?.removeEventListener?.('change', layoutChanged);
    controller.abort();
    root.dataset[INITIALIZED] = 'stopped';
  };
  document.addEventListener('astro:before-swap', cleanup, { once: true, signal: controller.signal });
  window.addEventListener('pagehide', cleanup, { once: true, signal: controller.signal });

  const layoutChanged = (event) => {
    void loadVariant(Boolean(event.matches));
  };
  layoutMedia?.addEventListener?.('change', layoutChanged, { signal: controller.signal });

  root.dataset.executionMotion = reducedMotion ? 'reduced' : 'full';
  selectStep(currentIndex);
  setPlaybackLabel();
  void loadVariant(Boolean(layoutMedia?.matches));
  schedule();

  return cleanup;
}

export function bootExecutionWalkthroughs() {
  if (typeof document === 'undefined') return;
  const boot = () => {
    for (const root of document.querySelectorAll('[data-execution-walkthrough]')) initExecutionWalkthrough(root);
  };
  boot();
  if (typeof window === 'undefined' || window[GLOBAL_BOOTSTRAPPED]) return;
  window[GLOBAL_BOOTSTRAPPED] = true;
  document.addEventListener('astro:page-load', boot);
  window.addEventListener('pageshow', boot);
}

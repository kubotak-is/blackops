import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let importFetchCalls = 0;
const originalFetch = globalThis.fetch;
globalThis.fetch = async () => {
  importFetchCalls += 1;
  throw new Error('Generated modules must not fetch during import.');
};

const { CreateOrder } = require('../.build/runtime/operations/order/create-order.js');
const { GenerateReport } = require('../.build/runtime/operations/report/generate-report.js');
const { fetchOperationStatus, waitForOperationStatus } = require('../.build/runtime/client.js');
assert.equal(importFetchCalls, 0);

const operationId = '01912345-6789-7abc-8def-0123456789ab';

function jsonResponse(status, payload, headers = {}) {
  const normalized = Object.fromEntries(
    Object.entries(headers).map(([name, value]) => [name.toLowerCase(), value]),
  );
  return {
    status,
    headers: {
      get(name) {
        const normalizedName = name.toLowerCase();
        if (normalizedName === 'content-type') {
          return normalized[normalizedName] ?? 'application/json; charset=utf-8';
        }
        return normalized[normalizedName] ?? null;
      },
    },
    async text() {
      return JSON.stringify(payload);
    },
  };
}

function statusPayload(operationType, state, fields = {}) {
  return {
    schemaVersion: 1,
    operationId,
    operationType,
    state,
    ...fields,
  };
}

function createWaitSignal(initiallyAborted = false) {
  let aborted = initiallyAborted;
  const listeners = new Set();
  let added = 0;
  let removed = 0;
  const signal = {
    get aborted() {
      return aborted;
    },
    reason: 'abort-reason-sensitive-value',
    addEventListener(type, listener) {
      assert.equal(type, 'abort');
      added += 1;
      listeners.add(listener);
    },
    removeEventListener(type, listener) {
      assert.equal(type, 'abort');
      removed += 1;
      listeners.delete(listener);
    },
  };

  return {
    signal,
    abort() {
      if (aborted) return;
      aborted = true;
      for (const listener of [...listeners]) listener();
    },
    get added() {
      return added;
    },
    get removed() {
      return removed;
    },
    get activeListeners() {
      return listeners.size;
    },
  };
}

function createWaitRuntime(start = 0, onSetTimeout = undefined) {
  let now = start;
  let nextHandle = 1;
  const active = new Map();
  const setCalls = [];
  const clearCalls = [];
  const clock = {
    nowMilliseconds() {
      return now;
    },
  };
  const timer = {
    setTimeout(callback, milliseconds) {
      setCalls.push(milliseconds);
      const handle = Object.freeze({ id: nextHandle++ });
      active.set(handle.id, { callback, milliseconds, handle });
      onSetTimeout?.(milliseconds, setCalls.length);
      return handle;
    },
    clearTimeout(handle) {
      clearCalls.push(handle);
      active.delete(handle.id);
    },
  };

  return {
    clock,
    timer,
    setCalls,
    clearCalls,
    get now() {
      return now;
    },
    get activeTimers() {
      return active.size;
    },
    fireNext() {
      const entry = active.values().next().value;
      assert.ok(entry, 'Expected an active wait timer.');
      active.delete(entry.handle.id);
      now += entry.milliseconds;
      entry.callback();
      return entry.milliseconds;
    },
  };
}

async function flushMicrotasks(turns = 12) {
  for (let turn = 0; turn < turns; turn += 1) await Promise.resolve();
}

function waitOptions(signal, runtime, fetch, maxWaitMilliseconds = 10_000) {
  return {
    signal,
    maxWaitMilliseconds,
    clock: runtime.clock,
    timer: runtime.timer,
    fetch,
  };
}

const terminalSignal = createWaitSignal();
const terminalRuntime = createWaitRuntime();
let terminalFetchCalls = 0;
const terminal = await GenerateReport.wait(
  operationId,
  waitOptions(terminalSignal.signal, terminalRuntime, async () => {
    terminalFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'completed', {
      outcome: { ready: true, reportName: 'monthly' },
    }));
  }),
);
assert.equal(terminalFetchCalls, 1);
assert.equal(terminal.kind, 'completed');
assert.equal(terminal.data.outcome.reportName, 'monthly');
assert.deepEqual(terminalRuntime.setCalls, [10_000]);
assert.equal(terminalRuntime.clearCalls.length, 1);
assert.equal(terminalRuntime.activeTimers, 0);
assert.equal(terminalSignal.activeListeners, 0);
assert.ok(Object.isFrozen(terminal));
assert.ok(Object.isFrozen(terminal.data));
assert.ok(Object.isFrozen(terminal.data.outcome));

const progressionSignal = createWaitSignal();
const progressionRuntime = createWaitRuntime();
const progressionResponses = [
  jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '2' }),
  jsonResponse(200, statusPayload('report.generate', 'running', { attempt: 1 }), { 'Retry-After': '1' }),
  jsonResponse(200, statusPayload('report.generate', 'retry_scheduled', {
    attempt: 1,
    retryAt: '2026-07-19T09:30:00.000000Z',
  }), { 'Retry-After': '2' }),
  jsonResponse(200, statusPayload('report.generate', 'completed', {
    outcome: { ready: true, reportName: 'annual' },
  })),
];
let progressionFetchCalls = 0;
const progressionPromise = GenerateReport.wait(
  operationId,
  waitOptions(progressionSignal.signal, progressionRuntime, async () => {
    const response = progressionResponses[progressionFetchCalls];
    progressionFetchCalls += 1;
    return response;
  }),
);
await flushMicrotasks();
assert.equal(progressionRuntime.fireNext(), 2_000);
await flushMicrotasks();
assert.equal(progressionRuntime.fireNext(), 1_000);
await flushMicrotasks();
assert.equal(progressionRuntime.fireNext(), 2_000);
const progression = await progressionPromise;
assert.equal(progressionFetchCalls, 4);
assert.equal(progression.kind, 'completed');
assert.equal(progression.data.outcome.reportName, 'annual');
assert.deepEqual(progressionRuntime.setCalls, [10_000, 2_000, 8_000, 1_000, 7_000, 2_000, 5_000]);
assert.equal(progressionRuntime.clearCalls.length, 7);
assert.equal(progressionRuntime.activeTimers, 0);
assert.equal(progressionSignal.activeListeners, 0);
assert.equal(progressionSignal.added, progressionSignal.removed);

for (const [state, fields] of [
  ['rejected', { error: { category: 'validation', code: 'validation_failed' } }],
  ['failed', { error: { code: 'operation_failed' } }],
  ['dead_lettered', { error: { code: 'operation_dead_lettered' } }],
]) {
  const signal = createWaitSignal();
  const runtime = createWaitRuntime();
  let calls = 0;
  const result = await GenerateReport.wait(operationId, waitOptions(signal.signal, runtime, async () => {
    calls += 1;
    return jsonResponse(200, statusPayload('report.generate', state, fields));
  }));
  assert.equal(result.kind, state);
  assert.equal(calls, 1);
  assert.equal(runtime.activeTimers, 0);
  assert.equal(signal.activeListeners, 0);
}

const timeoutSignal = createWaitSignal();
const timeoutRuntime = createWaitRuntime();
let timeoutFetchCalls = 0;
const timeoutPromise = GenerateReport.wait(
  operationId,
  waitOptions(timeoutSignal.signal, timeoutRuntime, async () => {
    timeoutFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '10' });
  }, 3_000),
);
await flushMicrotasks();
assert.equal(timeoutRuntime.fireNext(), 3_000);
const timeout = await timeoutPromise;
assert.deepEqual(timeout, {
  ok: false,
  kind: 'transport',
  status: null,
  error: { code: 'poll_timeout' },
});
assert.equal(timeoutFetchCalls, 1);
assert.equal(timeoutRuntime.activeTimers, 0);
assert.equal(timeoutSignal.activeListeners, 0);

const preAbortSignal = createWaitSignal(true);
const preAbortRuntime = createWaitRuntime();
let preAbortFetchCalls = 0;
const preAbort = await GenerateReport.wait(operationId, waitOptions(
  preAbortSignal.signal,
  preAbortRuntime,
  async () => {
    preAbortFetchCalls += 1;
    return jsonResponse(500, { status: 'error', code: 'internal_error' });
  },
));
assert.equal(preAbort.error.code, 'aborted');
assert.equal(preAbortFetchCalls, 0);
assert.equal(preAbortRuntime.setCalls.length, 0);
assert.equal(preAbortSignal.added, 0);

const sleepAbortSignal = createWaitSignal();
const sleepAbortRuntime = createWaitRuntime();
let sleepAbortFetchCalls = 0;
const sleepAbortPromise = GenerateReport.wait(operationId, waitOptions(
  sleepAbortSignal.signal,
  sleepAbortRuntime,
  async () => {
    sleepAbortFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '2' });
  },
));
await flushMicrotasks();
sleepAbortSignal.abort();
const sleepAbort = await sleepAbortPromise;
assert.equal(sleepAbort.error.code, 'aborted');
assert.equal(sleepAbortFetchCalls, 1);
assert.equal(sleepAbortRuntime.activeTimers, 0);
assert.equal(sleepAbortSignal.activeListeners, 0);

const inFlightAbortSignal = createWaitSignal();
const inFlightAbortRuntime = createWaitRuntime();
let inFlightRequestSignal;
const inFlightAbortPromise = GenerateReport.wait(operationId, waitOptions(
  inFlightAbortSignal.signal,
  inFlightAbortRuntime,
  async (_url, request) => {
    inFlightRequestSignal = request.signal;
    return new Promise(() => {});
  },
));
assert.equal(inFlightAbortRuntime.activeTimers, 1);
inFlightAbortSignal.abort();
const inFlightAbort = await inFlightAbortPromise;
assert.equal(inFlightAbort.error.code, 'aborted');
assert.equal(inFlightRequestSignal, inFlightAbortSignal.signal);
assert.equal(inFlightAbortRuntime.activeTimers, 0);
assert.equal(inFlightAbortSignal.activeListeners, 0);

const inFlightTimeoutSignal = createWaitSignal();
const inFlightTimeoutRuntime = createWaitRuntime();
let inFlightTimeoutFetchCalls = 0;
const inFlightTimeoutPromise = GenerateReport.wait(operationId, waitOptions(
  inFlightTimeoutSignal.signal,
  inFlightTimeoutRuntime,
  async () => {
    inFlightTimeoutFetchCalls += 1;
    return new Promise(() => {});
  },
  1_500,
));
assert.equal(inFlightTimeoutRuntime.fireNext(), 1_500);
const inFlightTimeout = await inFlightTimeoutPromise;
assert.equal(inFlightTimeout.error.code, 'poll_timeout');
assert.equal(inFlightTimeoutFetchCalls, 1);
assert.equal(inFlightTimeoutRuntime.activeTimers, 0);
assert.equal(inFlightTimeoutSignal.activeListeners, 0);

const immediateFailures = [
  [401, { status: 'error', category: 'unauthorized', code: 'auth.invalid' }, 'authentication'],
  [404, { status: 'error', code: 'operation_unavailable' }, 'unavailable'],
  [410, { status: 'error', code: 'operation_expired' }, 'expired'],
  [500, { status: 'error', code: 'internal_error' }, 'internal'],
];
for (const [status, payload, kind] of immediateFailures) {
  const signal = createWaitSignal();
  const runtime = createWaitRuntime();
  let calls = 0;
  const result = await GenerateReport.wait(operationId, waitOptions(signal.signal, runtime, async () => {
    calls += 1;
    return jsonResponse(status, payload);
  }));
  assert.equal(result.kind, kind);
  assert.equal(calls, 1);
  assert.equal(runtime.activeTimers, 0);
  assert.equal(signal.activeListeners, 0);
}

for (const expectedCode of ['network_error', 'unexpected_response']) {
  const signal = createWaitSignal();
  const runtime = createWaitRuntime();
  let calls = 0;
  const result = await GenerateReport.wait(operationId, waitOptions(signal.signal, runtime, async () => {
    calls += 1;
    if (expectedCode === 'network_error') {
      throw new Error('raw-body credential-secret');
    }
    return { status: 200, headers: {}, text: 'raw-body sensitive-value' };
  }));
  assert.equal(result.error.code, expectedCode);
  assert.equal(calls, 1);
  assert.equal(runtime.activeTimers, 0);
  assert.equal(signal.activeListeners, 0);
  assert.doesNotMatch(JSON.stringify(result), /raw-body|credential-secret|sensitive-value/);
}

for (const invalidOptions of [
  undefined,
  { signal: createWaitSignal().signal, maxWaitMilliseconds: 0 },
  { signal: createWaitSignal().signal, maxWaitMilliseconds: Number.POSITIVE_INFINITY },
  { signal: createWaitSignal().signal, maxWaitMilliseconds: 100, clock: { nowMilliseconds: () => -1 } },
]) {
  let calls = 0;
  const result = await GenerateReport.wait(operationId, {
    ...invalidOptions,
    fetch: async () => {
      calls += 1;
      return jsonResponse(500, { status: 'error', code: 'internal_error' });
    },
  });
  assert.equal(result.error.code, 'invalid_wait_options');
  assert.equal(calls, 0);
}

const invalidIdSignal = createWaitSignal();
const invalidIdRuntime = createWaitRuntime();
let invalidIdFetchCalls = 0;
const invalidId = await GenerateReport.wait('not-an-operation-id', waitOptions(
  invalidIdSignal.signal,
  invalidIdRuntime,
  async () => {
    invalidIdFetchCalls += 1;
    return jsonResponse(500, { status: 'error', code: 'internal_error' });
  },
));
assert.equal(invalidId.error.code, 'invalid_operation_id');
assert.equal(invalidIdFetchCalls, 0);
assert.equal(invalidIdRuntime.setCalls.length, 0);

const synchronousAbortSignal = createWaitSignal();
synchronousAbortSignal.signal.addEventListener = function addEventListener(_type, listener) {
  listener();
};
synchronousAbortSignal.signal.removeEventListener = function removeEventListener() {};
const synchronousAbortRuntime = createWaitRuntime();
let synchronousAbortFetchCalls = 0;
const synchronousAbort = await GenerateReport.wait(operationId, waitOptions(
  synchronousAbortSignal.signal,
  synchronousAbortRuntime,
  async () => {
    synchronousAbortFetchCalls += 1;
    return new Promise(() => {});
  },
));
assert.equal(synchronousAbort.error.code, 'aborted');
assert.equal(synchronousAbortFetchCalls, 0);
assert.equal(synchronousAbortRuntime.activeTimers, 0);

const abortDuringTimerSignal = createWaitSignal();
const abortDuringTimerRuntime = createWaitRuntime(0, () => abortDuringTimerSignal.abort());
let abortDuringTimerFetchCalls = 0;
const abortDuringTimer = await GenerateReport.wait(operationId, waitOptions(
  abortDuringTimerSignal.signal,
  abortDuringTimerRuntime,
  async () => {
    abortDuringTimerFetchCalls += 1;
    return new Promise(() => {});
  },
));
assert.equal(abortDuringTimer.error.code, 'aborted');
assert.equal(abortDuringTimerFetchCalls, 1);
assert.equal(abortDuringTimerRuntime.activeTimers, 0);
assert.equal(abortDuringTimerSignal.activeListeners, 0);

const abortDuringSleepTimerSignal = createWaitSignal();
const abortDuringSleepTimerRuntime = createWaitRuntime(0, (_milliseconds, call) => {
  if (call === 2) abortDuringSleepTimerSignal.abort();
});
let abortDuringSleepTimerFetchCalls = 0;
const abortDuringSleepTimer = await GenerateReport.wait(operationId, waitOptions(
  abortDuringSleepTimerSignal.signal,
  abortDuringSleepTimerRuntime,
  async () => {
    abortDuringSleepTimerFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '1' });
  },
));
assert.equal(abortDuringSleepTimer.error.code, 'aborted');
assert.equal(abortDuringSleepTimerFetchCalls, 1);
assert.equal(abortDuringSleepTimerRuntime.activeTimers, 0);
assert.equal(abortDuringSleepTimerSignal.activeListeners, 0);

let reverseClockRead = 0;
const reverseClockValues = [10, 10, 9];
const reverseClockSignal = createWaitSignal();
const reverseClockRuntime = createWaitRuntime();
let reverseClockFetchCalls = 0;
const reverseClock = await GenerateReport.wait(operationId, {
  signal: reverseClockSignal.signal,
  maxWaitMilliseconds: 1_000,
  clock: { nowMilliseconds: () => reverseClockValues[reverseClockRead++] },
  timer: reverseClockRuntime.timer,
  fetch: async () => {
    reverseClockFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '1' });
  },
});
assert.equal(reverseClock.error.code, 'invalid_wait_options');
assert.equal(reverseClockFetchCalls, 1);
assert.equal(reverseClockRuntime.activeTimers, 0);
assert.equal(reverseClockSignal.activeListeners, 0);

const throwingRemoveRuntime = createWaitRuntime();
let throwingRemoveFetchCalls = 0;
const throwingRemoveSignal = {
  aborted: false,
  addEventListener() {},
  removeEventListener() {
    throw new Error('raw cleanup detail');
  },
};
const throwingRemove = await GenerateReport.wait(operationId, waitOptions(
  throwingRemoveSignal,
  throwingRemoveRuntime,
  async () => {
    throwingRemoveFetchCalls += 1;
    return jsonResponse(200, statusPayload('report.generate', 'completed', {
      outcome: { ready: true, reportName: 'cleanup' },
    }));
  },
));
assert.equal(throwingRemove.error.code, 'invalid_wait_options');
assert.equal(throwingRemoveFetchCalls, 1);
assert.equal(throwingRemoveRuntime.activeTimers, 0);
assert.doesNotMatch(JSON.stringify(throwingRemove), /raw cleanup detail/);

const parallelSignalA = createWaitSignal();
const parallelSignalB = createWaitSignal();
const parallelRuntimeA = createWaitRuntime(100);
const parallelRuntimeB = createWaitRuntime(900);
let parallelFetchA = 0;
let parallelFetchB = 0;
const parallelA = GenerateReport.wait(operationId, waitOptions(
  parallelSignalA.signal,
  parallelRuntimeA,
  async () => {
    parallelFetchA += 1;
    return jsonResponse(200, statusPayload('report.generate', 'accepted'), { 'Retry-After': '1' });
  },
));
const parallelB = GenerateReport.wait(operationId, waitOptions(
  parallelSignalB.signal,
  parallelRuntimeB,
  async () => {
    parallelFetchB += 1;
    return parallelFetchB === 1
      ? jsonResponse(200, statusPayload('report.generate', 'running', { attempt: 1 }), { 'Retry-After': '2' })
      : jsonResponse(200, statusPayload('report.generate', 'completed', {
        outcome: { ready: true, reportName: 'parallel-b' },
      }));
  },
));
await flushMicrotasks();
parallelSignalA.abort();
assert.equal(parallelRuntimeB.fireNext(), 2_000);
const [parallelResultA, parallelResultB] = await Promise.all([parallelA, parallelB]);
assert.equal(parallelResultA.error.code, 'aborted');
assert.equal(parallelResultB.kind, 'completed');
assert.equal(parallelFetchA, 1);
assert.equal(parallelFetchB, 2);
assert.equal(parallelRuntimeA.activeTimers, 0);
assert.equal(parallelRuntimeB.activeTimers, 0);
assert.equal(parallelSignalA.activeListeners, 0);
assert.equal(parallelSignalB.activeListeners, 0);

const emptySignal = createWaitSignal();
const emptyRuntime = createWaitRuntime();
const empty = await waitForOperationStatus(
  operationId,
  waitOptions(emptySignal.signal, emptyRuntime, async () => jsonResponse(
    200,
    statusPayload('empty.operation', 'completed', { outcome: {} }),
  )),
  { operationType: 'empty.operation', outcomeMode: 'void', outcomeFields: [] },
);
assert.equal(empty.kind, 'completed');
assert.equal(empty.data.outcome, undefined);

const oneShotStates = [
  ['accepted', {}, '1'],
  ['running', { attempt: 1 }, '1'],
  ['retry_scheduled', { attempt: 1, retryAt: '2026-07-19T09:30:00.000000Z' }, '1'],
  ['completed', { outcome: { ready: true, reportName: 'one-shot' } }, undefined],
  ['rejected', { error: { category: 'validation', code: 'validation_failed' } }, undefined],
  ['failed', { error: { code: 'operation_failed' } }, undefined],
  ['dead_lettered', { error: { code: 'operation_dead_lettered' } }, undefined],
];
for (const [state, fields, retryAfter] of oneShotStates) {
  const result = await GenerateReport.status(operationId, {
    fetch: async () => jsonResponse(
      200,
      statusPayload('report.generate', state, fields),
      retryAfter === undefined ? {} : { 'Retry-After': retryAfter },
    ),
  });
  assert.equal(result.kind, state);
}

const fetchCalls = [];
await GenerateReport.fetch(
  { reportName: 'no-auto-status', recipientEmail: 'reader@example.test' },
  {
    fetch: async (url) => {
      fetchCalls.push(url);
      return jsonResponse(202, {
        status: 'accepted',
        operationId,
        acceptedAt: '2026-07-19T00:00:00+00:00',
      });
    },
  },
);
assert.deepEqual(fetchCalls, ['/reports']);

const defaultSignal = createWaitSignal();
const defaultRuntime = await CreateOrder.wait(operationId, {
  signal: defaultSignal.signal,
  maxWaitMilliseconds: 1_000,
  fetch: async () => jsonResponse(200, statusPayload('order.create', 'completed', {
    outcome: { active: true, orderId: 'default-runtime', sequence: 1, total: 1.5 },
  })),
});
assert.equal(defaultRuntime.kind, 'completed');
assert.equal(defaultSignal.activeListeners, 0);

if (originalFetch === undefined) {
  delete globalThis.fetch;
} else {
  globalThis.fetch = originalFetch;
}

process.stdout.write('Frontend status and finite wait runtime assertions passed.\n');

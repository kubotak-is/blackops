import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let importFetchCalls = 0;
const originalFetch = globalThis.fetch;
globalThis.fetch = async () => {
  importFetchCalls += 1;
  throw new Error('Generated root modules must not fetch during import.');
};

const { createBlackOpsClient, CreateOrder, GenerateReport, IssueCredential } = require('../.build/runtime/index.js');
const { buildOperationRequest } = require('../.build/runtime/client.js');
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

function orderOutcome(reference = 'order-42') {
  return Object.fromEntries([
    ['__proto__', {}],
    ['active', true],
    ['emptyMetadata', {}],
    ['emptyMetadataItems', []],
    ['lines', []],
    ['optionalEmptyMetadata', null],
    ['optionalOwner', null],
    ['orderId', reference],
    ['owner', { displayName: 'Alice', id: 'owner-1' }],
    ['sequence', 7],
    ['total', 12.5],
  ]);
}

function orderValue(reference = 'order-42') {
  return {
    accountId: 42,
    active: true,
    filter: 'open',
    amount: 12.5,
    reference,
    secretNote: 'runtime-sensitive-input',
    traceId: 'operation-owned-token',
  };
}

function waitSignal() {
  const listeners = new Set();
  return Object.freeze({
    aborted: false,
    addEventListener(_type, listener) {
      listeners.add(listener);
    },
    removeEventListener(_type, listener) {
      listeners.delete(listener);
    },
  });
}

const defaultHeaders = {
  Authorization: 'Bearer server-token',
  'X-Mode': 'default',
  'Content-Type': 'application-default',
};
const requests = [];
const clientOptions = {
  baseUrl: 'https://api.example.test/v1',
  headers: defaultHeaders,
  credentials: 'same-origin',
  fetch: async (url, request) => {
    requests.push({ url, request });
    if (request.method === 'GET') {
      return jsonResponse(200, {
        schemaVersion: 1,
        operationId,
        operationType: 'order.create',
        state: 'accepted',
      }, { 'Retry-After': '1' });
    }
    return jsonResponse(200, orderOutcome());
  },
};
const blackops = createBlackOpsClient(clientOptions);
defaultHeaders.Authorization = 'Bearer mutated-default';
clientOptions.baseUrl = 'https://mutated.example.test';
clientOptions.fetch = async () => {
  throw new Error('mutated fetch must not be used');
};

assert.ok(Object.isFrozen(blackops));
assert.ok(Object.isFrozen(blackops.CreateOrder));
assert.ok(Object.isFrozen(blackops.GenerateReport));
assert.ok(Object.isFrozen(blackops.IssueCredential));
assert.equal(blackops.CreateOrder.type, CreateOrder.type);
assert.equal(blackops.GenerateReport.type, GenerateReport.type);
assert.equal(blackops.IssueCredential.type, IssueCredential.type);
assert.equal(Object.hasOwn(blackops.IssueCredential, 'status'), false);
assert.equal(Object.hasOwn(blackops.IssueCredential, 'wait'), false);
assert.equal(
  blackops.CreateOrder.url({ accountId: 42, active: true, filter: 'open' }),
  'https://api.example.test/v1/accounts/42/orders?active=true&q=open',
);

const callHeaders = {
  authorization: 'Bearer call-token',
  'x-mode': 'call',
  'Content-Type': 'application-call',
  'X-Trace-ID': 'call-must-not-win',
};
const signal = Object.freeze({ aborted: false });
const pending = blackops.CreateOrder.fetch(orderValue(), {
  headers: callHeaders,
  credentials: 'include',
  signal,
  idempotencyKey: 'order-42',
});
callHeaders.authorization = 'Bearer mutated-call';
const completed = await pending;
assert.equal(completed.ok, true);
assert.doesNotMatch(JSON.stringify(completed), /server-token|call-token|api\.example\.test|runtime-sensitive-input/);
assert.equal(requests.length, 1);
const first = requests[0];
assert.equal(first.url, 'https://api.example.test/v1/accounts/42/orders?active=true&q=open');
assert.equal(first.request.credentials, 'include');
assert.equal(first.request.signal, signal);
assert.equal(first.request.headers.authorization, 'Bearer call-token');
assert.equal(first.request.headers.Authorization, undefined);
assert.equal(first.request.headers['x-mode'], 'call');
assert.equal(first.request.headers['X-Mode'], undefined);
assert.equal(first.request.headers['Content-Type'], 'application/json');
assert.equal(first.request.headers['X-Trace-ID'], 'operation-owned-token');
assert.equal(first.request.headers['Idempotency-Key'], 'order-42');
assert.ok(Object.isFrozen(first.request));
assert.ok(Object.isFrozen(first.request.headers));

const request = blackops.CreateOrder.toRequest(orderValue('order-request'), {
  idempotencyKey: 'order-request',
});
assert.equal(request.url, 'https://api.example.test/v1/accounts/42/orders?active=true&q=open');
assert.equal(request.headers['Idempotency-Key'], 'order-request');

const parallel = await Promise.all([
  blackops.CreateOrder.fetch(orderValue('parallel-a'), {
    headers: { 'X-Parallel': 'a' },
    idempotencyKey: 'parallel-a',
  }),
  blackops.CreateOrder.fetch(orderValue('parallel-b'), {
    headers: { 'X-Parallel': 'b' },
    idempotencyKey: 'parallel-b',
  }),
]);
assert.ok(parallel.every((result) => result.ok));
assert.equal(requests[1].request.headers['X-Parallel'], 'a');
assert.equal(requests[1].request.headers['Idempotency-Key'], 'parallel-a');
assert.equal(requests[2].request.headers['X-Parallel'], 'b');
assert.equal(requests[2].request.headers['Idempotency-Key'], 'parallel-b');

const current = await blackops.CreateOrder.status(operationId, { headers: { 'X-Status': 'one' } });
assert.equal(current.ok, true);
assert.equal(current.kind, 'accepted');
assert.equal(requests[3].url, `https://api.example.test/v1/operations/${operationId}`);
assert.equal(requests[3].request.headers['X-Status'], 'one');

let waitCalls = 0;
const waitClient = createBlackOpsClient({
  baseUrl: 'https://api.example.test',
  fetch: async () => {
    waitCalls += 1;
    return jsonResponse(200, {
      schemaVersion: 1,
      operationId,
      operationType: 'report.generate',
      state: 'completed',
      outcome: { ready: true, reportName: 'bound-report' },
    });
  },
});
const waited = await waitClient.GenerateReport.wait(operationId, {
  signal: waitSignal(),
  maxWaitMilliseconds: 1_000,
});
assert.equal(waitCalls, 1);
assert.equal(waited.ok, true);
assert.equal(waited.kind, 'completed');
assert.equal(waited.data.outcome.reportName, 'bound-report');

for (const [label, client, invoke] of [
  [
    'invalid base URL',
    createBlackOpsClient({ baseUrl: 'https://user:secret@example.test', fetch: async () => jsonResponse(200, {}) }),
    (candidate) => candidate.CreateOrder.fetch(orderValue()),
  ],
  [
    'missing fetch',
    createBlackOpsClient({ baseUrl: 'https://api.example.test' }),
    (candidate) => candidate.CreateOrder.fetch(orderValue()),
  ],
  [
    'invalid fetch',
    createBlackOpsClient({ baseUrl: 'https://api.example.test', fetch: 'not-a-function' }),
    (candidate) => candidate.CreateOrder.fetch(orderValue()),
  ],
  [
    'invalid default header',
    createBlackOpsClient({
      baseUrl: 'https://api.example.test',
      fetch: async () => jsonResponse(200, {}),
      headers: { 'Invalid Header': 'secret-header' },
    }),
    (candidate) => candidate.CreateOrder.fetch(orderValue()),
  ],
  [
    'raw idempotency header',
    createBlackOpsClient({
      baseUrl: 'https://api.example.test',
      fetch: async () => jsonResponse(200, {}),
      headers: { 'Idempotency-Key': 'raw-key' },
    }),
    (candidate) => candidate.CreateOrder.fetch(orderValue()),
  ],
]) {
  const result = await invoke(client);
  assert.deepEqual(result, {
    ok: false,
    kind: 'transport',
    status: null,
    error: { code: 'invalid_client_options' },
  }, label);
  assert.doesNotMatch(JSON.stringify(result), /secret|user:|raw-key/);
}

let invalidCalls = 0;
const validationClient = createBlackOpsClient({
  baseUrl: 'https://api.example.test',
  fetch: async () => {
    invalidCalls += 1;
    return jsonResponse(200, orderOutcome());
  },
});
for (const invoke of [
  () => validationClient.CreateOrder.fetch(orderValue(), { idempotencyKey: '' }),
  () => validationClient.CreateOrder.fetch(orderValue(), { idempotencyKey: 'contains space' }),
  () => validationClient.CreateOrder.fetch(orderValue(), { idempotencyKey: 'x'.repeat(256) }),
  () => validationClient.CreateOrder.fetch(orderValue(), { headers: { 'X-Test': 'line\nbreak' } }),
  () => validationClient.CreateOrder.fetch(orderValue(), { headers: { 'idempotency-key': 'raw-call-key' } }),
  () => validationClient.CreateOrder.fetch(orderValue(), { credentials: 'private-secret' }),
  () => validationClient.CreateOrder.fetch(orderValue(), { baseUrl: 'https://override.example.test' }),
  () => validationClient.CreateOrder.fetch(orderValue(), { fetch: async () => jsonResponse(200, {}) }),
  () => validationClient.CreateOrder.status(operationId, { idempotencyKey: 'get-key' }),
]) {
  const result = await invoke();
  assert.equal(result.error.code, 'invalid_client_options');
  assert.doesNotMatch(JSON.stringify(result), /contains space|line|private-secret|get-key/);
}
assert.equal(invalidCalls, 0);

for (const method of ['GET', 'HEAD']) {
  assert.throws(
    () => buildOperationRequest(method, '/readonly', [], {}, { idempotencyKey: 'read-key' }),
    (error) => error instanceof Error && error.message === 'BlackOps client options are invalid.',
  );
}

assert.throws(
  () => createBlackOpsClient({ baseUrl: 'https://user:secret@example.test' }).CreateOrder.url({
    accountId: 42,
    active: true,
  }),
  (error) => error instanceof Error
    && error.message === 'BlackOps client options are invalid.'
    && !error.message.includes('secret'),
);

if (originalFetch === undefined) {
  delete globalThis.fetch;
} else {
  globalThis.fetch = originalFetch;
}

process.stdout.write('Frontend bound client runtime assertions passed.\n');

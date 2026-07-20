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
assert.equal(importFetchCalls, 0);

function jsonResponse(status, payload) {
  return {
    status,
    headers: {
      get(name) {
        return name.toLowerCase() === 'content-type' ? 'application/json; charset=utf-8' : null;
      },
    },
    async text() {
      return JSON.stringify(payload);
    },
  };
}

function orderOutcome(overrides = {}) {
  return Object.fromEntries([
    ['__proto__', {}],
    ['active', true],
    ['emptyMetadata', {}],
    ['emptyMetadataItems', [{}, {}]],
    ['lines', [
      { owner: { displayName: 'Alice', id: 'owner-1' }, productId: 'product-1', quantity: 1 },
      { owner: null, productId: 'product-2', quantity: 2 },
    ]],
    ['optionalEmptyMetadata', {}],
    ['optionalOwner', null],
    ['orderId', 'order-42'],
    ['owner', { displayName: 'Alice', id: 'owner-1' }],
    ['sequence', 7],
    ['total', 12.5],
    ...Object.entries(overrides),
  ]);
}

const requests = [];
const inline = await CreateOrder.fetch(
  {
    accountId: 42,
    active: true,
    filter: 'open orders',
    traceId: 'trace-42',
    reference: 'order-42',
    amount: 12.5,
    secretNote: 'runtime-sensitive-input',
  },
  {
    baseUrl: 'https://api.example.test/v1',
    headers: { 'X-Application': 'frontend-fixture' },
    fetch: async (url, request) => {
      requests.push({ url, request });
      return jsonResponse(200, orderOutcome());
    },
  },
);

assert.deepEqual(inline, {
  ok: true,
  kind: 'completed',
  status: 200,
  data: orderOutcome(),
});
assert.equal(requests.length, 1);
assert.equal(requests[0].url, 'https://api.example.test/v1/accounts/42/orders?active=true&q=open%20orders');
assert.equal(requests[0].request.method, 'POST');
assert.equal(requests[0].request.headers['X-Trace-ID'], 'trace-42');
assert.equal(requests[0].request.headers['X-Application'], 'frontend-fixture');
assert.equal(requests[0].request.headers['Content-Type'], 'application/json');
assert.deepEqual(JSON.parse(requests[0].request.body), {
  reference: 'order-42',
  amount: 12.5,
  secretNote: 'runtime-sensitive-input',
});
assert.ok(Object.isFrozen(CreateOrder));
assert.ok(Object.isFrozen(inline));
assert.ok(Object.isFrozen(inline.data));
assert.ok(Object.isFrozen(inline.data.owner));
assert.ok(Object.isFrozen(inline.data.lines));
assert.ok(Object.isFrozen(inline.data.lines[0]));
assert.ok(Object.isFrozen(inline.data.lines[0].owner));
assert.ok(Object.isFrozen(inline.data.emptyMetadata));
assert.ok(Object.isFrozen(inline.data.emptyMetadataItems));
assert.ok(Object.isFrozen(inline.data.emptyMetadataItems[0]));
assert.ok(Object.isFrozen(inline.data.optionalEmptyMetadata));
assert.equal(Object.hasOwn(inline.data, '__proto__'), true);
assert.deepEqual(inline.data.__proto__, {});
assert.ok(Object.isFrozen(inline.data.__proto__));
assert.equal(Object.getPrototypeOf(inline.data), Object.prototype);
assert.equal(Object.prototype.polluted, undefined);

async function decodeOrderOutcome(payload) {
  return CreateOrder.fetch(
    {
      accountId: 42,
      active: true,
      traceId: 'trace-42',
      reference: 'order-42',
      amount: 12.5,
      secretNote: 'runtime-sensitive-input',
    },
    { fetch: async () => jsonResponse(200, payload) },
  );
}

const emptyList = await decodeOrderOutcome(orderOutcome({ lines: [] }));
assert.equal(emptyList.ok, true);
assert.deepEqual(emptyList.data.lines, []);
assert.ok(Object.isFrozen(emptyList.data.lines));

const invalidOutcomes = [
  { label: 'wrong list shape', value: orderOutcome({ lines: { 0: orderOutcome().lines[0] } }) },
  { label: 'wrong element', value: orderOutcome({ lines: ['invalid'] }) },
  { label: 'wrong nested scalar', value: orderOutcome({ owner: { displayName: 42, id: 'owner-1' } }) },
  { label: 'missing nested field', value: orderOutcome({ owner: { id: 'owner-1' } }) },
  { label: 'unknown nested field', value: orderOutcome({ owner: { displayName: 'Alice', id: 'owner-1', role: 'admin' } }) },
  { label: 'missing root field', value: (() => { const value = orderOutcome(); delete value.owner; return value; })() },
  { label: 'unknown root field', value: orderOutcome({ credential: 'must-not-survive' }) },
  { label: 'sparse list', value: orderOutcome({ lines: [orderOutcome().lines[0], , orderOutcome().lines[1]] }) },
];
for (const invalid of invalidOutcomes) {
  const result = await decodeOrderOutcome(invalid.value);
  assert.deepEqual(result, {
    ok: false,
    kind: 'transport',
    status: null,
    error: { code: 'unexpected_response' },
  }, invalid.label);
  assert.doesNotMatch(JSON.stringify(result), /credential|admin/);
}

const deferred = await GenerateReport.fetch(
  { reportName: 'monthly', recipientEmail: 'reader@example.test' },
  {
    fetch: async () => jsonResponse(202, {
      status: 'accepted',
      operationId: '01912345-6789-7abc-8def-0123456789ab',
      acceptedAt: '2026-07-19T00:00:00+00:00',
    }),
  },
);
assert.deepEqual(deferred, {
  ok: true,
  kind: 'accepted',
  status: 202,
  data: {
    operationId: '01912345-6789-7abc-8def-0123456789ab',
    acceptedAt: '2026-07-19T00:00:00+00:00',
  },
});
assert.ok(Object.isFrozen(GenerateReport));
assert.ok(Object.isFrozen(deferred));

const validation = await CreateOrder.fetch(
  {
    accountId: 42,
    active: true,
    traceId: 'trace-42',
    reference: '',
    amount: 12.5,
    secretNote: 'runtime-sensitive-input',
  },
  {
    fetch: async () => jsonResponse(422, {
      status: 'rejected',
      category: 'validation',
      code: 'validation.failed',
      operationId: '01912345-6789-7abc-8def-0123456789ab',
      violations: [{ field: 'reference', rule: 'NotBlank', code: 'value.not_blank' }],
    }),
  },
);
assert.equal(validation.ok, false);
assert.equal(validation.kind, 'validation');
assert.equal(validation.error.violations[0].field, 'reference');

const network = await CreateOrder.fetch(
  {
    accountId: 42,
    active: true,
    traceId: 'trace-42',
    reference: 'order-42',
    amount: 12.5,
    secretNote: 'runtime-sensitive-input',
  },
  { fetch: async () => { throw new Error('raw-body credential-secret'); } },
);
assert.deepEqual(network, {
  ok: false,
  kind: 'transport',
  status: null,
  error: { code: 'network_error' },
});
assert.doesNotMatch(JSON.stringify(network), /raw-body|credential-secret|runtime-sensitive-input/);

if (originalFetch === undefined) {
  delete globalThis.fetch;
} else {
  globalThis.fetch = originalFetch;
}

process.stdout.write('Frontend injected fetch runtime assertions passed.\n');

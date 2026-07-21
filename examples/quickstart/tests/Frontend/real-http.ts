import {
  CreateOrder,
  GenerateReport,
  ShowWelcome,
  TriggerFailure,
  operationOptions,
} from '../../resources/js/application/operations';
import { testOperationWaitController } from './wait-signal';
import { createBlackOpsClient } from '../../resources/js/blackops';
import type {
  OperationFetch,
  OperationFetchRequest,
  OperationFetchResponse,
} from '../../resources/js/blackops/types';

type RuntimeProcess = {
  env: Readonly<Record<string, string | undefined>>;
  stdout: Readonly<{ write(value: string): void }>;
  stderr: Readonly<{ write(value: string): void }>;
  exitCode?: number;
};

type NativeFetch = (
  url: string,
  request: Readonly<Record<string, unknown>>,
) => Promise<OperationFetchResponse>;

const runtimeCandidate = (globalThis as unknown as { process?: RuntimeProcess }).process;
if (runtimeCandidate === undefined) {
  throw new Error('Node process is required.');
}
const runtime: RuntimeProcess = runtimeCandidate;

const nativeFetchCandidate = (globalThis as unknown as { fetch?: unknown }).fetch;
if (typeof nativeFetchCandidate !== 'function') {
  throw new Error('Node fetch is required.');
}
const nativeFetch = nativeFetchCandidate as NativeFetch;

class SafeAssertionError extends Error {}

function requiredEnvironment(name: string): string {
  const value = runtime.env[name];
  if (value === undefined || value === '') {
    throw new Error(`Required environment is missing: ${name}`);
  }

  return value;
}

function assert(condition: boolean, message: string): asserts condition {
  if (!condition) {
    throw new SafeAssertionError(message);
  }
}

function assertEqual<T>(actual: T, expected: T, message: string): void {
  assert(Object.is(actual, expected), `${message}: expected ${String(expected)}, received ${String(actual)}`);
}

function assertAbsent(value: unknown, forbidden: readonly string[], message: string): void {
  const encoded = JSON.stringify(value);
  for (const candidate of forbidden) {
    assert(!encoded.includes(candidate), message);
  }
}

function realHttpFetch(calls: string[]): OperationFetch {
  return async (url: string, request: OperationFetchRequest): Promise<OperationFetchResponse> => {
    calls.push(url);
    const nativeRequest: Record<string, unknown> = {
      method: request.method,
      headers: request.headers,
    };
    if (request.body !== undefined) {
      nativeRequest.body = request.body;
    }
    if (request.credentials !== undefined) {
      nativeRequest.credentials = request.credentials;
    }

    return nativeFetch(url, Object.freeze(nativeRequest));
  };
}

async function main(): Promise<void> {
  const baseUrl = requiredEnvironment('BLACKOPS_FRONTEND_BASE_URL');
  const sampleToken = requiredEnvironment('BLACKOPS_FRONTEND_SAMPLE_TOKEN');
  const reportSecret = requiredEnvironment('BLACKOPS_FRONTEND_REPORT_SECRET');
  const failureSecret = requiredEnvironment('BLACKOPS_FRONTEND_FAILURE_SECRET');
  const rawTransportError = requiredEnvironment('BLACKOPS_FRONTEND_RAW_ERROR');
  const options = operationOptions(sampleToken, baseUrl);
  const blackops = createBlackOpsClient({
    baseUrl,
    fetch: nativeFetch,
    headers: { 'X-Sample-Token': sampleToken },
  });

  assertEqual(ShowWelcome.type, 'welcome.show', 'Welcome type metadata');
  assertEqual(ShowWelcome.method, 'GET', 'Welcome method metadata');
  assertEqual(ShowWelcome.path, '/welcome', 'Welcome path metadata');
  assertEqual(ShowWelcome.strategy, 'inline', 'Welcome strategy metadata');
  assertEqual(GenerateReport.strategy, 'deferred', 'Report strategy metadata');
  assertEqual(CreateOrder.strategy, 'inline', 'Order strategy metadata');
  assertEqual(TriggerFailure.strategy, 'inline', 'Diagnostics strategy metadata');
  assert(Object.isFrozen(ShowWelcome), 'Operation objects must be frozen.');

  assertEqual(ShowWelcome.url(), '/welcome', 'Welcome relative URL');
  assertEqual(GenerateReport.url(), '/reports', 'Report relative URL');
  assertEqual(CreateOrder.url(), '/orders', 'Order relative URL');
  assertEqual(TriggerFailure.url(), '/failures', 'Diagnostics relative URL');

  const reportInput = {
    reportName: `frontend-${Date.now()}`,
    recipientEmail: reportSecret,
  };
  const reportRequest = GenerateReport.toRequest(reportInput, options);
  assertEqual(reportRequest.method, 'POST', 'Report request method');
  assertEqual(reportRequest.url, `${baseUrl}/reports`, 'Report absolute URL');
  assertEqual(reportRequest.headers['X-Sample-Token'], sampleToken, 'Application credential header');
  assertEqual(reportRequest.headers['Content-Type'], 'application/json', 'Report content type');
  assertEqual(JSON.parse(reportRequest.body ?? '{}').recipientEmail, reportSecret, 'Report write-only input');

  const welcome = await blackops.ShowWelcome.fetch({});
  assert(welcome.ok && welcome.kind === 'completed', 'Welcome must complete.');
  assertEqual(welcome.status, 200, 'Welcome status');
  assertEqual(welcome.data.message, 'Welcome to BlackOps', 'Welcome outcome');

  const orderReference = `frontend-order-${Date.now()}`;
  const order = await CreateOrder.fetch({ reference: orderReference }, options);
  assert(order.ok && order.kind === 'completed', 'Order must complete.');
  assertEqual(order.status, 200, 'Order status');
  assertEqual(order.data.reference, orderReference, 'Order reference');
  assertEqual(order.data.status, 'created', 'Order state');

  const validation = await GenerateReport.fetch(
    { reportName: '', recipientEmail: reportSecret },
    options,
  );
  assert(!validation.ok && validation.kind === 'validation', 'Invalid report must be validation failure.');
  assertEqual(validation.status, 422, 'Validation status');
  assertEqual(validation.error.violations[0]?.field, 'reportName', 'Validation field');

  const internal = await TriggerFailure.fetch(
    { reference: `frontend-failure-${Date.now()}`, sensitiveNote: failureSecret },
    options,
  );
  assert(!internal.ok && internal.kind === 'internal', 'Diagnostics failure must be internal.');
  assertEqual(internal.status, 500, 'Internal status');
  assertEqual(internal.error.code, 'internal_error', 'Internal code');

  const transport = await ShowWelcome.fetch({}, {
    ...options,
    fetch: async () => {
      throw new Error(rawTransportError);
    },
  });
  assert(!transport.ok && transport.kind === 'transport', 'Fetch throw must be transport failure.');
  assertEqual(transport.status, null, 'Transport status');
  assertEqual(transport.error.code, 'network_error', 'Transport code');

  const reportCalls: string[] = [];
  const reportOptions = Object.freeze({
    ...options,
    fetch: realHttpFetch(reportCalls),
  });
  const deferred = await GenerateReport.fetch(reportInput, reportOptions);
  assert(deferred.ok && deferred.kind === 'accepted', 'Report must be accepted.');
  assertEqual(deferred.status, 202, 'Report status');
  assertEqual(reportCalls.length, 1, 'Deferred fetch must perform exactly one request.');
  assertEqual(reportCalls[0], `${baseUrl}/reports`, 'Deferred fetch URL');

  const operationId = deferred.data.operationId;
  const current = await GenerateReport.status(operationId, reportOptions);
  assert(current.ok && current.kind === 'accepted', 'Immediate report status must be accepted.');
  assertEqual(current.status, 200, 'Immediate status HTTP code');
  assertEqual(current.data.operationId, operationId, 'Immediate status operation ID');
  assertEqual(current.data.operationType, 'report.generate', 'Immediate status operation type');
  assert(Number.isSafeInteger(current.retryAfterSeconds) && current.retryAfterSeconds > 0, 'Retry hint must be positive.');
  assertEqual(reportCalls.length, 2, 'One-shot status must perform exactly one additional request.');
  assertEqual(reportCalls[1], `${baseUrl}/operations/${operationId}`, 'Status URL');

  const missingToken = await GenerateReport.status(operationId, {
    baseUrl,
    fetch: realHttpFetch([]),
  });
  assert(!missingToken.ok && missingToken.kind === 'unavailable', 'Missing status token must be unavailable.');
  assertEqual(missingToken.status, 404, 'Missing status token HTTP code');

  const invalidToken = await GenerateReport.status(
    operationId,
    {
      ...operationOptions('invalid-frontend-token', baseUrl),
      fetch: realHttpFetch([]),
    },
  );
  assert(!invalidToken.ok && invalidToken.kind === 'authentication', 'Invalid status token must be authentication failure.');
  assertEqual(invalidToken.status, 401, 'Invalid status token HTTP code');

  const unknown = await GenerateReport.status(
    '018f22e2-7a13-7c90-8f3a-7d91b625eca9',
    reportOptions,
  );
  assert(!unknown.ok && unknown.kind === 'unavailable', 'Unknown operation must be unavailable.');
  assertEqual(unknown.status, 404, 'Unknown operation HTTP code');

  runtime.stdout.write(`BLACKOPS_WAIT_STARTED:${operationId}\n`);
  const waitController = testOperationWaitController();
  const terminal = await GenerateReport.wait(operationId, {
    ...reportOptions,
    signal: waitController.signal,
    maxWaitMilliseconds: 15_000,
  });
  assert(
    terminal.ok && terminal.kind === 'completed',
    `Report wait must complete; received ${terminal.kind}${terminal.ok ? '' : `:${terminal.error.code}`}.`,
  );
  assertEqual(terminal.status, 200, 'Completed status HTTP code');
  assertEqual(terminal.data.operationId, operationId, 'Completed operation ID');
  assertEqual(terminal.data.outcome.reportName, reportInput.reportName, 'Completed report name');
  assertEqual(
    terminal.data.outcome.location,
    `/reports/generated/${reportInput.reportName}.json`,
    'Completed report location',
  );

  const timeoutCalls: string[] = [];
  const timeoutOptions = Object.freeze({
    ...options,
    fetch: realHttpFetch(timeoutCalls),
  });
  const timeoutInput = {
    reportName: `frontend-timeout-${Date.now()}`,
    recipientEmail: reportSecret,
  };
  const timeoutAccepted = await GenerateReport.fetch(timeoutInput, timeoutOptions);
  assert(timeoutAccepted.ok && timeoutAccepted.kind === 'accepted', 'Timeout report must be accepted.');
  const timeoutController = testOperationWaitController();
  const timedOut = await GenerateReport.wait(timeoutAccepted.data.operationId, {
    ...timeoutOptions,
    signal: timeoutController.signal,
    maxWaitMilliseconds: 150,
  });
  assert(!timedOut.ok && timedOut.kind === 'transport', 'Unprocessed report wait must fail by deadline.');
  assertEqual(timedOut.status, null, 'Poll timeout status');
  assertEqual(timedOut.error.code, 'poll_timeout', 'Poll timeout code');

  const forbidden = [
    sampleToken,
    reportSecret,
    failureSecret,
    rawTransportError,
    'quickstart-user',
    'quickstart-worker-1',
  ];
  assertAbsent(welcome, forbidden, 'Welcome result exposed runtime input.');
  assertAbsent(order, forbidden, 'Order result exposed runtime input.');
  assertAbsent(deferred, forbidden, 'Deferred result exposed runtime input.');
  assertAbsent(current, forbidden, 'Status result exposed runtime input.');
  assertAbsent(terminal, forbidden, 'Wait result exposed runtime input.');
  assertAbsent(timedOut, forbidden, 'Timeout result exposed runtime input.');
  assertAbsent(validation, forbidden, 'Validation result exposed runtime input.');
  assertAbsent(internal, forbidden, 'Internal result exposed runtime input.');
  assertAbsent(transport, forbidden, 'Transport result exposed runtime input.');

  runtime.stdout.write(`${JSON.stringify({
    welcome: { input: {}, output: { ok: welcome.ok, kind: welcome.kind, status: welcome.status } },
    report: {
      input: { reportName: reportInput.reportName },
      output: { ok: deferred.ok, kind: deferred.kind, status: deferred.status, operationId },
    },
    status: {
      input: { operationId },
      output: { ok: current.ok, kind: current.kind, status: current.status },
    },
    wait: {
      input: { operationId, maxWaitMilliseconds: 15_000 },
      output: { ok: terminal.ok, kind: terminal.kind, status: terminal.status },
    },
    timeout: {
      input: { operationId: timeoutAccepted.data.operationId, maxWaitMilliseconds: 150 },
      output: { ok: timedOut.ok, kind: timedOut.kind, status: timedOut.status, code: timedOut.error.code },
    },
    order: { input: { reference: orderReference }, output: { ok: order.ok, kind: order.kind, status: order.status } },
    validation: { input: { reportName: '' }, output: { ok: validation.ok, kind: validation.kind, status: validation.status } },
    internal: { input: { reference: 'generated-at-runtime' }, output: { ok: internal.ok, kind: internal.kind, status: internal.status } },
    transport: { input: { baseUrl }, output: { ok: transport.ok, kind: transport.kind, status: transport.status, code: transport.error.code } },
  })}\n`);
}

void main().catch((error: unknown) => {
  const detail = error instanceof SafeAssertionError ? ` ${error.message}` : '';
  runtime.stderr.write(`Frontend real HTTP assertions failed.${detail}\n`);
  runtime.exitCode = 1;
});

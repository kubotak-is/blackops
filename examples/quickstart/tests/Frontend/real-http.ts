import {
  CreateOrder,
  GenerateReport,
  ShowWelcome,
  TriggerFailure,
  operationOptions,
} from '../../resources/js/application/operations';

type RuntimeProcess = {
  env: Readonly<Record<string, string | undefined>>;
  stdout: Readonly<{ write(value: string): void }>;
  stderr: Readonly<{ write(value: string): void }>;
  exitCode?: number;
};

const runtimeCandidate = (globalThis as unknown as { process?: RuntimeProcess }).process;
if (runtimeCandidate === undefined) {
  throw new Error('Node process is required.');
}
const runtime: RuntimeProcess = runtimeCandidate;

function requiredEnvironment(name: string): string {
  const value = runtime.env[name];
  if (value === undefined || value === '') {
    throw new Error(`Required environment is missing: ${name}`);
  }

  return value;
}

function assert(condition: boolean, message: string): asserts condition {
  if (!condition) {
    throw new Error(message);
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

async function main(): Promise<void> {
  const baseUrl = requiredEnvironment('BLACKOPS_FRONTEND_BASE_URL');
  const sampleToken = requiredEnvironment('BLACKOPS_FRONTEND_SAMPLE_TOKEN');
  const reportSecret = requiredEnvironment('BLACKOPS_FRONTEND_REPORT_SECRET');
  const failureSecret = requiredEnvironment('BLACKOPS_FRONTEND_FAILURE_SECRET');
  const rawTransportError = requiredEnvironment('BLACKOPS_FRONTEND_RAW_ERROR');
  const options = operationOptions(sampleToken, baseUrl);

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

  const welcome = await ShowWelcome.fetch({}, options);
  assert(welcome.ok && welcome.kind === 'completed', 'Welcome must complete.');
  assertEqual(welcome.status, 200, 'Welcome status');
  assertEqual(welcome.data.message, 'Welcome to BlackOps', 'Welcome outcome');

  const orderReference = `frontend-order-${Date.now()}`;
  const order = await CreateOrder.fetch({ reference: orderReference }, options);
  assert(order.ok && order.kind === 'completed', 'Order must complete.');
  assertEqual(order.status, 200, 'Order status');
  assertEqual(order.data.reference, orderReference, 'Order reference');
  assertEqual(order.data.status, 'created', 'Order state');

  const deferred = await GenerateReport.fetch(reportInput, options);
  assert(deferred.ok && deferred.kind === 'accepted', 'Report must be accepted.');
  assertEqual(deferred.status, 202, 'Report status');
  assert(deferred.data.operationId.length > 0, 'Report operation ID is required.');

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

  const forbidden = [sampleToken, reportSecret, failureSecret, rawTransportError];
  assertAbsent(welcome, forbidden, 'Welcome result exposed runtime input.');
  assertAbsent(order, forbidden, 'Order result exposed runtime input.');
  assertAbsent(deferred, forbidden, 'Deferred result exposed runtime input.');
  assertAbsent(validation, forbidden, 'Validation result exposed runtime input.');
  assertAbsent(internal, forbidden, 'Internal result exposed runtime input.');
  assertAbsent(transport, forbidden, 'Transport result exposed runtime input.');

  runtime.stdout.write(`${JSON.stringify({
    welcome: { input: {}, output: { ok: welcome.ok, kind: welcome.kind, status: welcome.status } },
    report: { input: { reportName: reportInput.reportName }, output: { ok: deferred.ok, kind: deferred.kind, status: deferred.status } },
    order: { input: { reference: orderReference }, output: { ok: order.ok, kind: order.kind, status: order.status } },
    validation: { input: { reportName: '' }, output: { ok: validation.ok, kind: validation.kind, status: validation.status } },
    internal: { input: { reference: 'generated-at-runtime' }, output: { ok: internal.ok, kind: internal.kind, status: internal.status } },
    transport: { input: { baseUrl }, output: { ok: transport.ok, kind: transport.kind, status: transport.status, code: transport.error.code } },
  })}\n`);
}

void main().catch(() => {
  runtime.stderr.write('Frontend real HTTP assertions failed.\n');
  runtime.exitCode = 1;
});

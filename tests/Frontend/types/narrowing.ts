import {
  CreateOrder,
  type CreateOrderResult,
  type CreateOrderStatusResult,
} from '../fixture/resources/js/blackops/operations/order/create-order';
import {
  GenerateReport,
  type GenerateReportResult,
  type GenerateReportWaitResult,
} from '../fixture/resources/js/blackops/operations/report/generate-report';
import {
  IssueCredential,
  type IssueCredentialResult,
} from '../fixture/resources/js/blackops/operations/identity/credential/issue-credential';
import {
  createBlackOpsClient,
  CreateOrder as RootCreateOrder,
  type OperationFetchResponse,
} from '../fixture/resources/js/blackops';
import type {
  OperationStatusResult,
  OperationWaitResult,
  OperationWaitClock,
  OperationWaitSignal,
  OperationWaitTimer,
} from '../fixture/resources/js/blackops/types';

function acceptInline(result: CreateOrderResult): string {
  if (result.ok) {
    const status: 200 = result.status;
    const kind: 'completed' = result.kind;
    const ownerId: string = result.data.owner.id;
    const lines: ReadonlyArray<import('../fixture/resources/js/blackops/operations/order/create-order').OrderLine> = result.data.lines;
    return `${kind}:${status}:${result.data.orderId}:${ownerId}:${lines.length}`;
  }

  if (result.kind === 'validation') {
    const status: 422 = result.status;
    return `${status}:${result.error.violations[0]?.field ?? 'none'}`;
  }

  if (result.kind === 'transport') {
    const status: null = result.status;
    return `${status}:${result.error.code}`;
  }

  return `${result.kind}:${result.status}`;
}

function acceptDeferred(result: GenerateReportResult): string {
  if (result.ok) {
    const status: 202 = result.status;
    const kind: 'accepted' = result.kind;
    return `${kind}:${status}:${result.data.operationId}`;
  }

  return `${result.kind}:${result.status}`;
}

function acceptEphemeral(result: IssueCredentialResult): string {
  if (result.ok) {
    const status: 200 = result.status;
    const token: string = result.data.token;
    return `${status}:${token}:${result.data.expiresAt}`;
  }
  return `${result.kind}:${result.status}`;
}

function acceptStatus(result: CreateOrderStatusResult): string {
  if (!result.ok) {
    if (result.kind === 'authentication') {
      const status: 401 = result.status;
      return `${status}:${result.error.code}`;
    }
    if (result.kind === 'unavailable') {
      const code: 'operation_unavailable' = result.error.code;
      return code;
    }
    if (result.kind === 'expired') {
      const code: 'operation_expired' = result.error.code;
      return code;
    }
    if (result.kind === 'internal') {
      const code: 'internal_error' = result.error.code;
      return code;
    }
    const status: null = result.status;
    return `${status}:${result.error.code}`;
  }

  const operationType: 'order.create' = result.data.operationType;
  const schemaVersion: 1 = result.data.schemaVersion;
  if (result.kind === 'accepted') {
    const state: 'accepted' = result.data.state;
    return `${operationType}:${schemaVersion}:${state}:${result.retryAfterSeconds}`;
  }
  if (result.kind === 'running') {
    const state: 'running' = result.data.state;
    return `${state}:${result.data.attempt}:${result.retryAfterSeconds}`;
  }
  if (result.kind === 'retry_scheduled') {
    const state: 'retry_scheduled' = result.data.state;
    return `${state}:${result.data.retryAt}:${result.retryAfterSeconds}`;
  }
  if (result.kind === 'completed') {
    const state: 'completed' = result.data.state;
    return `${state}:${result.data.outcome.orderId}:${result.data.outcome.owner.displayName}`;
  }
  if (result.kind === 'rejected') {
    const state: 'rejected' = result.data.state;
    return `${state}:${result.data.error.category}:${result.data.error.code}`;
  }
  if (result.kind === 'failed') {
    const code: 'operation_failed' = result.data.error.code;
    return code;
  }
  const code: 'operation_dead_lettered' = result.data.error.code;
  return code;
}

function acceptWait(result: GenerateReportWaitResult): string {
  if (!result.ok) {
    return `${result.kind}:${result.status}:${result.error.code}`;
  }
  if (result.kind === 'completed') {
    return `${result.data.outcome.reportName}:${result.data.outcome.ready}`;
  }
  if (result.kind === 'rejected') {
    return `${result.kind}:${result.data.error.category}`;
  }
  return `${result.kind}:${result.data.error.code}`;
}

function acceptEmptyOutcome(result: OperationStatusResult<'empty.operation', undefined>): undefined | string {
  if (result.ok && result.kind === 'completed') {
    const outcome: undefined = result.data.outcome;
    return outcome;
  }
  return result.kind;
}

function acceptEmptyWait(result: OperationWaitResult<'empty.operation', undefined>): undefined | string {
  if (result.ok && result.kind === 'completed') {
    const outcome: undefined = result.data.outcome;
    return outcome;
  }
  return result.kind;
}

type StatusTransportCode = Extract<CreateOrderStatusResult, { kind: 'transport' }>['error']['code'];
type FetchTransportCode = Extract<CreateOrderResult, { kind: 'transport' }>['error']['code'];
type WaitKind = GenerateReportWaitResult['kind'];
const statusCode: StatusTransportCode = 'unexpected_response';
const fetchCode: FetchTransportCode = 'network_error';
const terminalWaitKind: WaitKind = 'completed';
// @ts-expect-error wait-only timeout is not part of one-shot status
const invalidStatusCode: StatusTransportCode = 'poll_timeout';
// @ts-expect-error wait-only option failure is not part of fetch
const invalidFetchCode: FetchTransportCode = 'invalid_wait_options';
// @ts-expect-error wait resolves only terminal or failure results
const invalidWaitKind: WaitKind = 'accepted';

const waitSignal: OperationWaitSignal = {
  aborted: false,
  addEventListener(_type, _listener, _options) {},
  removeEventListener(_type, _listener) {},
};
const waitClock: OperationWaitClock = { nowMilliseconds: () => 0 };
const waitTimer: OperationWaitTimer = {
  setTimeout: (_callback, _milliseconds) => Object.freeze({}),
  clearTimeout: (_handle) => {},
};

void GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab', {
  signal: waitSignal,
  maxWaitMilliseconds: 30_000,
  clock: waitClock,
  timer: waitTimer,
});

// @ts-expect-error wait requires options
void GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab');
// @ts-expect-error wait requires maxWaitMilliseconds
void GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab', { signal: waitSignal });
// @ts-expect-error wait requires signal
void GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab', { maxWaitMilliseconds: 30_000 });

type SvelteKitServerRequest = Readonly<{
  method?: string;
  headers?: Readonly<Record<string, string>>;
  body?: string;
  credentials?: 'omit' | 'same-origin' | 'include';
  signal?: Readonly<{
    aborted: boolean;
    addEventListener(type: 'abort', listener: () => void): void;
  }>;
}>;

type SvelteKitServerFetch = (
  input: string | Readonly<{ url: string }>,
  request?: SvelteKitServerRequest,
) => Promise<OperationFetchResponse>;

declare const eventFetch: SvelteKitServerFetch;
const blackops = createBlackOpsClient({
  baseUrl: 'https://api.example.test',
  fetch: eventFetch,
  headers: { Authorization: 'Bearer type-only-token' },
  credentials: 'same-origin',
});
const orderValue = {
  accountId: 42,
  active: true,
  amount: 12.5,
  reference: 'order-42',
  secretNote: 'type-only-sensitive-input',
  traceId: 'trace-42',
};
void blackops.CreateOrder.fetch(orderValue, { idempotencyKey: 'order-42' });
void blackops.CreateOrder.toRequest(orderValue, { idempotencyKey: 'order-42' });
void blackops.CreateOrder.status('01912345-6789-7abc-8def-0123456789ab');
void blackops.GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab', {
  signal: waitSignal,
  maxWaitMilliseconds: 30_000,
});
void blackops.IssueCredential.fetch({ identity: 'user-1', password: 'type-only-secret' });
// @ts-expect-error ephemeral bound operations do not expose status
void blackops.IssueCredential.status('01912345-6789-7abc-8def-0123456789ab');
// @ts-expect-error ephemeral bound operations do not expose wait
void blackops.IssueCredential.wait('01912345-6789-7abc-8def-0123456789ab', {
  signal: waitSignal,
  maxWaitMilliseconds: 30_000,
});
const rootType: 'order.create' = RootCreateOrder.type;
// @ts-expect-error bound calls cannot replace the factory fetch
void blackops.CreateOrder.fetch(orderValue, { fetch: eventFetch });
// @ts-expect-error bound calls cannot replace the factory base URL
void blackops.CreateOrder.fetch(orderValue, { baseUrl: 'https://other.example.test' });
// @ts-expect-error status GET does not accept an idempotency key
void blackops.CreateOrder.status('01912345-6789-7abc-8def-0123456789ab', { idempotencyKey: 'invalid' });
void blackops.GenerateReport.wait('01912345-6789-7abc-8def-0123456789ab', {
  signal: waitSignal,
  maxWaitMilliseconds: 30_000,
  // @ts-expect-error wait GET does not accept an idempotency key
  idempotencyKey: 'invalid',
});

void CreateOrder.fetch;
void CreateOrder.status;
void CreateOrder.wait;
void GenerateReport.fetch;
void GenerateReport.status;
void GenerateReport.wait;
void IssueCredential.fetch;
// @ts-expect-error ephemeral operations do not expose status
void IssueCredential.status;
// @ts-expect-error ephemeral operations do not expose wait
void IssueCredential.wait;
void acceptInline;
void acceptDeferred;
void acceptEphemeral;
void acceptStatus;
void acceptWait;
void acceptEmptyOutcome;
void acceptEmptyWait;
void statusCode;
void fetchCode;
void terminalWaitKind;
void invalidStatusCode;
void invalidFetchCode;
void invalidWaitKind;
void rootType;

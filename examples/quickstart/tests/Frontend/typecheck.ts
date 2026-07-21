import {
  CreateOrder,
  GenerateReport,
  ShowWelcome,
  TriggerFailure,
  operationOptions,
} from '../../resources/js/application/operations';
import { testOperationWaitController } from './wait-signal';
import { createBlackOpsClient } from '../../resources/js/blackops';
import type { CreateOrderResult } from '../../resources/js/blackops/operations/order/create-order';
import type { GenerateReportResult } from '../../resources/js/blackops/operations/report/generate-report';
import type {
  GenerateReportStatusResult,
  GenerateReportWaitResult,
} from '../../resources/js/blackops/operations/report/generate-report';
import type { ShowWelcomeResult } from '../../resources/js/blackops/operations/welcome/show-welcome';
import type { TriggerFailureResult } from '../../resources/js/blackops/operations/diagnostics/failure/trigger-failure';

function welcomeMessage(result: ShowWelcomeResult): string {
  if (result.ok) {
    const status: 200 = result.status;
    const kind: 'completed' = result.kind;
    return `${kind}:${status}:${result.data.message}`;
  }

  return `${result.kind}:${result.status}`;
}

function reportState(result: GenerateReportResult): string {
  if (result.ok) {
    const status: 202 = result.status;
    const kind: 'accepted' = result.kind;
    return `${kind}:${status}:${result.data.operationId}`;
  }

  return `${result.kind}:${result.status}`;
}

function reportStatusState(result: GenerateReportStatusResult): string {
  if (!result.ok) {
    switch (result.kind) {
      case 'authentication':
      case 'unavailable':
      case 'expired':
      case 'internal':
        return `${result.kind}:${result.status}:${result.error.code}`;
      case 'transport':
        return `${result.kind}:${result.status}:${result.error.code}`;
    }
  }

  switch (result.kind) {
    case 'accepted':
    case 'running':
    case 'retry_scheduled':
      return `${result.data.state}:${result.retryAfterSeconds}`;
    case 'completed':
      return `${result.data.outcome.reportName}:${result.data.outcome.location}`;
    case 'rejected':
      return `${result.data.error.category}:${result.data.error.code}`;
    case 'failed':
    case 'dead_lettered':
      return result.data.error.code;
  }
}

function reportWaitState(result: GenerateReportWaitResult): string {
  if (!result.ok) {
    return `${result.kind}:${result.status}:${result.error.code}`;
  }

  switch (result.kind) {
    case 'completed':
      return `${result.data.outcome.reportName}:${result.data.outcome.location}`;
    case 'rejected':
      return `${result.data.error.category}:${result.data.error.code}`;
    case 'failed':
    case 'dead_lettered':
      return result.data.error.code;
  }
}

function orderState(result: CreateOrderResult): string {
  if (result.ok) {
    const status: 200 = result.status;
    return `${status}:${result.data.reference}:${result.data.status}`;
  }

  return `${result.kind}:${result.status}`;
}

function failureState(result: TriggerFailureResult): string {
  if (!result.ok && result.kind === 'internal') {
    const status: 500 = result.status;
    return `${status}:${result.error.code}`;
  }

  return `${result.kind}:${result.status}`;
}

const welcomeType: 'welcome.show' = ShowWelcome.type;
const welcomeMethod: 'GET' = ShowWelcome.method;
const reportStrategy: 'deferred' = GenerateReport.strategy;
const orderPath: '/orders' = CreateOrder.path;
const failureStrategy: 'inline' = TriggerFailure.strategy;
declare const serverFetch: (...arguments_: never[]) => unknown;
const blackops = createBlackOpsClient({
  baseUrl: 'http://127.0.0.1:8080',
  fetch: serverFetch,
  headers: { 'X-Sample-Token': 'type-only-token' },
});

void ShowWelcome.url();
void GenerateReport.toRequest(
  { reportName: 'weekly', recipientEmail: 'write-only@example.test' },
  operationOptions('runtime-token'),
);
void GenerateReport.status('018f22e2-7a13-7c90-8f3a-7d91b625eca9');
const waitController = testOperationWaitController();
void GenerateReport.wait('018f22e2-7a13-7c90-8f3a-7d91b625eca9', {
  ...operationOptions('runtime-token'),
  signal: waitController.signal,
  maxWaitMilliseconds: 15_000,
});
waitController.abort();
void CreateOrder.fetch;
void blackops.ShowWelcome.fetch({});
void TriggerFailure.fetch;
void welcomeType;
void welcomeMethod;
void reportStrategy;
void orderPath;
void failureStrategy;
void welcomeMessage;
void reportState;
void reportStatusState;
void reportWaitState;
void orderState;
void failureState;

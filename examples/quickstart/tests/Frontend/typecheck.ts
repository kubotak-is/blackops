import {
  CreateOrder,
  GenerateReport,
  ShowWelcome,
  TriggerFailure,
  operationOptions,
} from '../../resources/js/application/operations';
import type { CreateOrderResult } from '../../resources/js/blackops/operations/order/create-order';
import type { GenerateReportResult } from '../../resources/js/blackops/operations/report/generate-report';
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

void ShowWelcome.url();
void GenerateReport.toRequest(
  { reportName: 'weekly', recipientEmail: 'write-only@example.test' },
  operationOptions('runtime-token'),
);
void CreateOrder.fetch;
void TriggerFailure.fetch;
void welcomeType;
void welcomeMethod;
void reportStrategy;
void orderPath;
void failureStrategy;
void welcomeMessage;
void reportState;
void orderState;
void failureState;

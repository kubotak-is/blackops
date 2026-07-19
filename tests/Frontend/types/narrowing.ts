import {
  CreateOrder,
  type CreateOrderResult,
} from '../fixture/resources/js/blackops/operations/order/create-order';
import {
  GenerateReport,
  type GenerateReportResult,
} from '../fixture/resources/js/blackops/operations/report/generate-report';

function acceptInline(result: CreateOrderResult): string {
  if (result.ok) {
    const status: 200 = result.status;
    const kind: 'completed' = result.kind;
    return `${kind}:${status}:${result.data.orderId}`;
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

void CreateOrder.fetch;
void GenerateReport.fetch;
void acceptInline;
void acceptDeferred;

import type { OperationCallOptions } from '../blackops/types';

export { ShowWelcome } from '../blackops/operations/welcome/show-welcome';
export { GenerateReport } from '../blackops/operations/report/generate-report';
export { CreateOrder } from '../blackops/operations/order/create-order';
export { TriggerFailure } from '../blackops/operations/diagnostics/failure/trigger-failure';

export function operationOptions(
  sampleToken: string,
  baseUrl?: string,
): OperationCallOptions {
  return Object.freeze({
    ...(baseUrl === undefined ? {} : { baseUrl }),
    headers: Object.freeze({ 'X-Sample-Token': sampleToken }),
  });
}

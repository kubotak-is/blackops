import type { OperationWaitSignal } from '../../resources/js/blackops/types';

export type TestOperationWaitController = Readonly<{
  signal: OperationWaitSignal;
  abort(reason?: unknown): void;
}>;

export function testOperationWaitController(): TestOperationWaitController {
  const listeners = new Set<() => void>();
  let aborted = false;
  let reason: unknown;

  const signal: OperationWaitSignal = Object.freeze({
    get aborted(): boolean {
      return aborted;
    },
    get reason(): unknown {
      return reason;
    },
    addEventListener(type: 'abort', listener: () => void): void {
      if (type !== 'abort') {
        return;
      }

      if (aborted) {
        listener();
        return;
      }

      listeners.add(listener);
    },
    removeEventListener(type: 'abort', listener: () => void): void {
      if (type === 'abort') {
        listeners.delete(listener);
      }
    },
  });

  return Object.freeze({
    signal,
    abort(abortReason?: unknown): void {
      if (aborted) {
        return;
      }

      aborted = true;
      reason = abortReason;

      for (const listener of [...listeners]) {
        listener();
      }
      listeners.clear();
    },
  });
}

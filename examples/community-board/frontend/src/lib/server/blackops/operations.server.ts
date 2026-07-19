import { env } from '$env/dynamic/private';
import { ShowBoardWelcome } from './generated/operations/board/welcome/show-board-welcome';
import type { OperationAbortSignal, OperationFetch } from './generated/types';

export type ServerFetch = typeof globalThis.fetch;

export type BoardWelcomeView =
  | Readonly<{
      available: true;
      message: string;
      summary: string;
    }>
  | Readonly<{
      available: false;
      message: string;
    }>;

const unavailableWelcome = Object.freeze({
  available: false as const,
  message: 'The board service is temporarily unavailable.',
});

function isNativeAbortSignal(signal: OperationAbortSignal | undefined): signal is AbortSignal {
  return signal !== undefined && 'addEventListener' in signal;
}

function operationFetch(serverFetch: ServerFetch): OperationFetch {
  return async (url, request) =>
    serverFetch(url, {
      method: request.method,
      headers: request.headers,
      body: request.body,
      credentials: request.credentials,
      signal: isNativeAbortSignal(request.signal) ? request.signal : undefined,
    });
}

export async function loadBoardWelcome(
  serverFetch: ServerFetch,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<BoardWelcomeView> {
  if (baseUrl === undefined || baseUrl.trim() === '') {
    return unavailableWelcome;
  }

  try {
    const result = await ShowBoardWelcome.fetch({}, {
      baseUrl,
      fetch: operationFetch(serverFetch),
    });

    if (!result.ok || result.kind !== 'completed') {
      return unavailableWelcome;
    }

    return Object.freeze({
      available: true as const,
      message: result.data.message,
      summary: result.data.summary,
    });
  } catch {
    return unavailableWelcome;
  }
}

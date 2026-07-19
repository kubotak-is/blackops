import { env } from '$env/dynamic/private';
import { ShowCurrentUser } from './generated/operations/board/identity/current/user/show-current-user';
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

export type CurrentUserView = Readonly<{
  id: string;
  email: string;
  displayName: string;
}>;

export type CurrentSessionView =
  | Readonly<{ state: 'authenticated'; user: CurrentUserView }>
  | Readonly<{ state: 'invalid' }>
  | Readonly<{ state: 'unavailable' }>;

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

export async function loadCurrentUser(
  serverFetch: ServerFetch,
  rawToken: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<CurrentSessionView> {
  if (baseUrl === undefined || baseUrl.trim() === '') {
    return Object.freeze({ state: 'unavailable' });
  }

  try {
    const result = await ShowCurrentUser.fetch(
      {},
      {
        baseUrl,
        fetch: operationFetch(serverFetch),
        headers: { Authorization: `Bearer ${rawToken}` },
      },
    );

    if (!result.ok) {
      return Object.freeze({ state: result.status === 401 ? 'invalid' : 'unavailable' });
    }

    if (result.kind !== 'completed') {
      return Object.freeze({ state: 'unavailable' });
    }

    return Object.freeze({
      state: 'authenticated' as const,
      user: Object.freeze({
        id: result.data.id,
        email: result.data.email,
        displayName: result.data.displayName,
      }),
    });
  } catch {
    return Object.freeze({ state: 'unavailable' });
  }
}

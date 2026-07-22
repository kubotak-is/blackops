import { env } from '$env/dynamic/private';
import { createServerBlackOpsClient } from './client.server';
import type { ServerFetch } from './client.server';

export type { ServerFetch } from './client.server';

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

export async function loadBoardWelcome(
  serverFetch: ServerFetch,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<BoardWelcomeView> {
  const blackops = createServerBlackOpsClient(serverFetch, null, baseUrl);
  if (blackops === null) {
    return unavailableWelcome;
  }

  try {
    const result = await blackops.ShowBoardWelcome.fetch({});

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
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) {
    return Object.freeze({ state: 'unavailable' });
  }

  try {
    const result = await blackops.ShowCurrentUser.fetch({});

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

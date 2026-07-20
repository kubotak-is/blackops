import { clearSessionCookie, SESSION_COOKIE_NAME } from '$lib/server/auth/session.server';
import type { BoardFailure } from '$lib/server/blackops/board.server';
import { error, redirect, type Cookies } from '@sveltejs/kit';

type LayoutIdentity = Readonly<{
  currentUser: Readonly<{ id: string; email: string; displayName: string }> | null;
  identityAvailable: boolean;
}>;

export async function requireBoardSession(
  cookies: Cookies,
  parent: () => Promise<LayoutIdentity>,
): Promise<Readonly<{ rawToken: string; user: NonNullable<LayoutIdentity['currentUser']> }>> {
  const rawToken = cookies.get(SESSION_COOKIE_NAME);
  if (rawToken === undefined) {
    redirect(303, '/login');
  }

  const identity = await parent();
  if (identity.currentUser === null) {
    if (!identity.identityAvailable) {
      error(503, 'The board service is temporarily unavailable. Please try again.');
    }

    redirect(303, '/login');
  }

  return Object.freeze({ rawToken, user: identity.currentUser });
}

export async function requireBoardActionSession(
  cookies: Cookies,
): Promise<Readonly<{ rawToken: string }>> {
  const rawToken = cookies.get(SESSION_COOKIE_NAME);
  if (rawToken === undefined) {
    redirect(303, '/login');
  }

  return Object.freeze({ rawToken });
}

export function handleBoardLoadFailure(failure: BoardFailure, cookies: Cookies): never {
  if (failure.kind === 'authentication') {
    clearSessionCookie(cookies);
    redirect(303, '/login');
  }

  error(failure.status, failure.message);
}

export function handleBoardActionAuthentication(failure: BoardFailure, cookies: Cookies): void {
  if (failure.kind === 'authentication') {
    clearSessionCookie(cookies);
    redirect(303, '/login');
  }
}

export function formString(form: FormData, field: string): string {
  const value = form.get(field);
  return typeof value === 'string' ? value : '';
}

import { loadCurrentUser } from '$lib/server/blackops/operations.server';
import {
  clearSessionCookie,
  SESSION_COOKIE_NAME,
} from '$lib/server/auth/session.server';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ cookies, fetch }) => {
  const rawToken = cookies.get(SESSION_COOKIE_NAME);
  if (rawToken === undefined) {
    return { currentUser: null, identityAvailable: true };
  }

  const session = await loadCurrentUser(fetch, rawToken);
  if (session.state === 'invalid') {
    clearSessionCookie(cookies);
    return { currentUser: null, identityAvailable: true };
  }

  if (session.state === 'unavailable') {
    return { currentUser: null, identityAvailable: false };
  }

  return { currentUser: session.user, identityAvailable: true };
};

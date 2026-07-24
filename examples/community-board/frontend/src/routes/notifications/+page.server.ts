import { clearSessionCookie } from '$lib/server/auth/session.server';
import { requireBoardSession } from '$lib/server/board-route.server';
import { loadNotifications } from '$lib/server/blackops/notification.server';
import { redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, fetch, parent }) => {
  const session = await requireBoardSession(cookies, parent);
  const result = await loadNotifications(fetch, session.rawToken);
  if (!result.ok && result.kind === 'authentication') {
    clearSessionCookie(cookies);
    redirect(303, '/login');
  }

  return result;
};

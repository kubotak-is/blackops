import { clearSessionCookie } from '$lib/server/auth/session.server';
import { requireBoardSession } from '$lib/server/board-route.server';
import { loadDigest } from '$lib/server/blackops/digest.server';
import { error, redirect } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, fetch, params, parent }) => {
  const session = await requireBoardSession(cookies, parent);
  const result = await loadDigest(fetch, session.rawToken, params.digestId);
  if (!result.ok) {
    if (result.kind === 'authentication') {
      clearSessionCookie(cookies);
      redirect(303, '/login');
    }
    error(result.status, result.message);
  }

  return { digest: result.data };
};

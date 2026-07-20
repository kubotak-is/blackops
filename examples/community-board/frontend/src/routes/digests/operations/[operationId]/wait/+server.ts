import { clearSessionCookie, SESSION_COOKIE_NAME } from '$lib/server/auth/session.server';
import { waitForDigest } from '$lib/server/blackops/digest.server';
import { json, redirect } from '@sveltejs/kit';
import type { RequestHandler } from './$types';

const headers = { 'Cache-Control': 'private, no-store' };

export const GET: RequestHandler = async ({ cookies, fetch, params, request }) => {
  const rawToken = cookies.get(SESSION_COOKIE_NAME);
  if (rawToken === undefined) redirect(303, '/login');

  const status = await waitForDigest(fetch, rawToken, params.operationId, request.signal, 2_500);
  if (!status.ok) {
    if (status.kind === 'authentication') {
      clearSessionCookie(cookies);
      redirect(303, '/login');
    }
    return json(
      { ok: false, kind: status.kind, message: status.message },
      { status: status.status, headers },
    );
  }

  return json(status, { headers });
};

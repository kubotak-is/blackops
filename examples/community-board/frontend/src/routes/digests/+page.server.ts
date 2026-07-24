import { clearSessionCookie } from '$lib/server/auth/session.server';
import { formString, requireBoardActionSession, requireBoardSession } from '$lib/server/board-route.server';
import {
  currentUtcIsoWeek,
  digestOperationLocation,
  startWeeklyDigest,
} from '$lib/server/blackops/digest.server';
import { fail, redirect } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, parent }) => {
  await requireBoardSession(cookies, parent);
  const bytes = new Uint8Array(24);
  globalThis.crypto.getRandomValues(bytes);
  return {
    week: currentUtcIsoWeek(),
    idempotencyKey: Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(''),
  };
};

export const actions: Actions = {
  default: async ({ cookies, fetch, request }) => {
    const session = await requireBoardActionSession(cookies);
    const form = await request.formData();
    const week = formString(form, 'week');
    const idempotencyKey = formString(form, 'idempotencyKey');
    const result = await startWeeklyDigest(fetch, session.rawToken, week, idempotencyKey);
    if (!result.ok) {
      if (result.kind === 'authentication') {
        clearSessionCookie(cookies);
        redirect(303, '/login');
      }
      return fail(result.status, {
        success: false as const,
        message: result.message,
        fieldErrors: result.fieldErrors,
        values: { week: week.slice(0, 8), idempotencyKey },
      });
    }

    redirect(303, digestOperationLocation(result.operationId));
  },
};

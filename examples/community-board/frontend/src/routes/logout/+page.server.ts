import { logoutIdentity } from '$lib/server/auth/auth-client.server';
import {
  clearSessionCookie,
  resolveSessionToken,
} from '$lib/server/auth/session.server';
import { redirect } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = () => {
  redirect(303, '/');
};

export const actions: Actions = {
  default: async ({ cookies, fetch }) => {
    await logoutIdentity(fetch, resolveSessionToken(cookies));
    clearSessionCookie(cookies);
    redirect(303, '/login');
  },
};

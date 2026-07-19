import { loginIdentity } from '$lib/server/auth/auth-client.server';
import { loginFailureData } from '$lib/server/auth/form-errors.server';
import {
  SESSION_COOKIE_NAME,
  setSessionCookie,
} from '$lib/server/auth/session.server';
import { fail, redirect } from '@sveltejs/kit';
import type { Actions } from './$types';

function stringField(form: FormData, name: string): string {
  const value = form.get(name);
  return typeof value === 'string' ? value : '';
}

export const actions: Actions = {
  default: async ({ cookies, fetch, request }) => {
    const form = await request.formData();
    const email = stringField(form, 'email');
    const password = stringField(form, 'password');
    const currentToken = cookies.get(SESSION_COOKIE_NAME) ?? null;
    const result = await loginIdentity(fetch, { email, password }, currentToken);

    if (!result.ok) {
      return fail(result.status, loginFailureData({ email }, result));
    }

    setSessionCookie(cookies, result.sessionToken);
    redirect(303, '/me');
  },
};

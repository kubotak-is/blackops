import { registerIdentity } from '$lib/server/auth/auth-client.server';
import { registrationFailureData } from '$lib/server/auth/form-errors.server';
import { setSessionCookie } from '$lib/server/auth/session.server';
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
    const displayName = stringField(form, 'displayName');
    const password = stringField(form, 'password');
    const result = await registerIdentity(fetch, { email, displayName, password });

    if (!result.ok) {
      return fail(result.status, registrationFailureData({ email, displayName }, result));
    }

    setSessionCookie(cookies, result.sessionToken);
    redirect(303, '/me');
  },
};

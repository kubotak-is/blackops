import {
  formString,
  handleBoardActionAuthentication,
  requireBoardActionSession,
  requireBoardSession,
} from '$lib/server/board-route.server';
import { createPost, fixedPostLocation, replayFormValue } from '$lib/server/blackops/board.server';
import { fail, redirect } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, parent }) => {
  await requireBoardSession(cookies, parent);
  return {};
};

export const actions: Actions = {
  default: async ({ cookies, fetch, request }) => {
    const session = await requireBoardActionSession(cookies);
    const form = await request.formData();
    const values = { title: formString(form, 'title'), body: formString(form, 'body') };
    const result = await createPost(fetch, session.rawToken, values);

    if (!result.ok) {
      handleBoardActionAuthentication(result, cookies);
      return fail(result.status, {
        success: false as const,
        message: result.message,
        fieldErrors: result.fieldErrors,
        values: {
          title: replayFormValue(values.title),
          body: replayFormValue(values.body),
        },
      });
    }

    redirect(303, fixedPostLocation(result.postId));
  },
};

import {
  formString,
  handleBoardActionAuthentication,
  handleBoardLoadFailure,
  requireBoardActionSession,
  requireBoardSession,
} from '$lib/server/board-route.server';
import {
  fixedPostLocation,
  loadPostDetail,
  replayFormValue,
  updatePost,
} from '$lib/server/blackops/board.server';
import { error, fail, redirect } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, fetch, params, parent }) => {
  const session = await requireBoardSession(cookies, parent);
  const result = await loadPostDetail(fetch, session.rawToken, params.postId, session.user.id);
  if (!result.ok) handleBoardLoadFailure(result, cookies);
  if (!result.data.owner) error(404, 'This post could not be found.');

  return { post: { postId: result.data.id, title: result.data.title, body: result.data.body } };
};

export const actions: Actions = {
  default: async ({ cookies, fetch, params, request }) => {
    const session = await requireBoardActionSession(cookies);
    const form = await request.formData();
    const values = {
      postId: params.postId,
      title: formString(form, 'title'),
      body: formString(form, 'body'),
    };
    const result = await updatePost(fetch, session.rawToken, values);

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

    redirect(303, fixedPostLocation(params.postId));
  },
};

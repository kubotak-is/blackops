import {
  formString,
  handleBoardActionAuthentication,
  handleBoardLoadFailure,
  requireBoardActionSession,
  requireBoardSession,
} from '$lib/server/board-route.server';
import {
  addComment,
  deletePost,
  fixedPostLocation,
  loadPostDetail,
  replayFormValue,
} from '$lib/server/blackops/board.server';
import { fail, redirect } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, fetch, params, parent }) => {
  const session = await requireBoardSession(cookies, parent);
  const result = await loadPostDetail(fetch, session.rawToken, params.postId, session.user.id);
  if (!result.ok) handleBoardLoadFailure(result, cookies);

  return { post: result.data };
};

export const actions: Actions = {
  comment: async ({ cookies, fetch, params, request }) => {
    const session = await requireBoardActionSession(cookies);
    const form = await request.formData();
    const body = formString(form, 'body');
    const result = await addComment(fetch, session.rawToken, { postId: params.postId, body });

    if (!result.ok) {
      handleBoardActionAuthentication(result, cookies);
      return fail(result.status, {
        success: false as const,
        message: result.message,
        fieldErrors: result.fieldErrors,
        values: { body: replayFormValue(body) },
      });
    }

    redirect(303, fixedPostLocation(params.postId));
  },
  delete: async ({ cookies, fetch, params }) => {
    const session = await requireBoardActionSession(cookies);
    const result = await deletePost(fetch, session.rawToken, params.postId);

    if (!result.ok) {
      handleBoardActionAuthentication(result, cookies);
      return fail(result.status, {
        success: false as const,
        message: result.message,
        fieldErrors: result.fieldErrors,
        values: { body: '' },
      });
    }

    redirect(303, '/posts');
  },
};

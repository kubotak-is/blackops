import { handleBoardLoadFailure, requireBoardSession } from '$lib/server/board-route.server';
import { loadPostFeed, normalizePage } from '$lib/server/blackops/board.server';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ cookies, fetch, parent, url }) => {
  const session = await requireBoardSession(cookies, parent);
  const result = await loadPostFeed(fetch, session.rawToken, normalizePage(url.searchParams.get('page')));
  if (!result.ok) handleBoardLoadFailure(result, cookies);

  return { feed: result.data };
};

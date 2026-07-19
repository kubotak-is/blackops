import { loadBoardWelcome } from '$lib/server/blackops/operations.server';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ fetch }) => ({
  welcome: await loadBoardWelcome(fetch),
});

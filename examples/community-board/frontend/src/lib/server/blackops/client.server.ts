import { env } from '$env/dynamic/private';
import { createBlackOpsClient } from './generated';
import type { BlackOpsClient } from './generated';

export type ServerFetch = typeof globalThis.fetch;

export function createServerBlackOpsClient(
  serverFetch: ServerFetch,
  rawToken: string | null = null,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): BlackOpsClient | null {
  if (baseUrl === undefined || baseUrl.trim() === '') {
    return null;
  }

  return createBlackOpsClient({
    baseUrl,
    fetch: serverFetch,
    headers: rawToken === null ? undefined : { Authorization: `Bearer ${rawToken}` },
  });
}

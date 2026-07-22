import { env } from '$env/dynamic/private';
import type { Cookies } from '@sveltejs/kit';

export const SESSION_COOKIE_NAME = 'community_board_session';
const SESSION_TOKEN_PATTERN = /^[A-Za-z0-9_-]{43}$/;

type CookieOptions = Parameters<Cookies['set']>[2];

export function sessionCookieOptions(
  environment: Readonly<Record<string, string | undefined>> = env,
): CookieOptions {
  const secureValue = environment.SESSION_COOKIE_SECURE;
  if (secureValue !== undefined && secureValue !== 'true' && secureValue !== 'false') {
    throw new Error('SESSION_COOKIE_SECURE must be true or false.');
  }

  const ttlValue = environment.SESSION_TTL_SECONDS ?? '28800';
  if (!/^[1-9][0-9]*$/.test(ttlValue)) {
    throw new Error('SESSION_TTL_SECONDS must be a positive integer.');
  }

  const maxAge = Number(ttlValue);
  if (!Number.isSafeInteger(maxAge) || maxAge <= 0) {
    throw new Error('SESSION_TTL_SECONDS is outside the supported range.');
  }

  return Object.freeze({
    httpOnly: true,
    sameSite: 'strict' as const,
    path: '/',
    secure: secureValue !== 'false',
    maxAge,
  });
}

export function setSessionCookie(cookies: Cookies, rawToken: string): void {
  cookies.set(SESSION_COOKIE_NAME, rawToken, sessionCookieOptions());
}

export function normalizeSessionToken(rawToken: string | undefined): string | null {
  return rawToken !== undefined && SESSION_TOKEN_PATTERN.test(rawToken) ? rawToken : null;
}

export function resolveSessionToken(cookies: Cookies): string | null {
  const storedToken = cookies.get(SESSION_COOKIE_NAME);
  const canonicalToken = normalizeSessionToken(storedToken);

  if (storedToken !== undefined && canonicalToken === null) {
    clearSessionCookie(cookies);
  }

  return canonicalToken;
}

export function clearSessionCookie(cookies: Cookies): void {
  cookies.delete(SESSION_COOKIE_NAME, {
    path: '/',
    httpOnly: true,
    sameSite: 'strict',
    secure: sessionCookieOptions().secure,
  });
}

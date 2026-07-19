import { describe, expect, it } from 'vitest';
import { sessionCookieOptions } from './session.server';

describe('session cookie settings', () => {
  it('defaults to a production-safe secure cookie', () => {
    expect(sessionCookieOptions({})).toEqual({
      httpOnly: true,
      sameSite: 'strict',
      path: '/',
      secure: true,
      maxAge: 28_800,
    });
  });

  it('allows only explicit local HTTP opt-out and bounds max age to backend TTL', () => {
    expect(
      sessionCookieOptions({ SESSION_COOKIE_SECURE: 'false', SESSION_TTL_SECONDS: '600' }),
    ).toMatchObject({ secure: false, maxAge: 600 });
  });

  it('rejects unsafe cookie configuration', () => {
    expect(() => sessionCookieOptions({ SESSION_COOKIE_SECURE: '0' })).toThrow();
    expect(() => sessionCookieOptions({ SESSION_TTL_SECONDS: '0' })).toThrow();
    expect(() => sessionCookieOptions({ SESSION_TTL_SECONDS: '1.5' })).toThrow();
  });
});

import type { Cookies } from '@sveltejs/kit';
import { describe, expect, it, vi } from 'vitest';
import {
  normalizeSessionToken,
  resolveSessionToken,
  SESSION_COOKIE_NAME,
  sessionCookieOptions,
} from './session.server';

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

  it('accepts only canonical opaque session tokens from browser cookies', () => {
    const token = 'A'.repeat(43);

    expect(normalizeSessionToken(token)).toBe(token);
    expect(normalizeSessionToken(undefined)).toBeNull();
    expect(normalizeSessionToken('')).toBeNull();
    expect(normalizeSessionToken('deleted')).toBeNull();
    expect(normalizeSessionToken('A'.repeat(42))).toBeNull();
    expect(normalizeSessionToken(`${'A'.repeat(42)}+`)).toBeNull();
  });

  it('removes a noncanonical browser cookie at the shared session boundary', () => {
    const deleteCookie = vi.fn();
    const cookies = {
      get: vi.fn(() => 'invalid-session-marker'),
      delete: deleteCookie,
    } as unknown as Cookies;

    expect(resolveSessionToken(cookies)).toBeNull();
    expect(deleteCookie).toHaveBeenCalledOnce();
    expect(deleteCookie).toHaveBeenCalledWith(
      SESSION_COOKIE_NAME,
      expect.objectContaining({ path: '/', httpOnly: true, sameSite: 'strict' }),
    );
  });
});

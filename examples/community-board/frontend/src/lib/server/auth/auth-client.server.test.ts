import { describe, expect, it } from 'vitest';
import {
  loginIdentity,
  logoutIdentity,
  registerIdentity,
  type ServerFetch,
} from './auth-client.server';

const token = 'A'.repeat(43);

describe('server-only authentication client', () => {
  it('decodes a safe registration session without exposing backend details', async () => {
    const calls: Array<{ url: string; authorization: string | null }> = [];
    const serverFetch: ServerFetch = async (input, init) => {
      calls.push({
        url: String(input),
        authorization: new Headers(init?.headers).get('authorization'),
      });

      return Response.json(
        {
          user: { id: 'user-id', email: 'person@example.com', displayName: 'Person' },
          sessionToken: token,
        },
        { status: 201 },
      );
    };

    const result = await registerIdentity(
      serverFetch,
      {
        email: 'person@example.com',
        displayName: 'Person',
        password: 'password-marker-not-returned',
      },
      'http://blackops.test',
    );

    expect(result).toEqual({
      ok: true,
      user: { id: 'user-id', email: 'person@example.com', displayName: 'Person' },
      sessionToken: token,
    });
    expect(calls).toEqual([
      { url: 'http://blackops.test/auth/users', authorization: null },
    ]);
    expect(JSON.stringify(result)).not.toContain('password-marker-not-returned');
  });

  it('injects the current bearer only for login rotation', async () => {
    let authorization: string | null = null;
    const serverFetch: ServerFetch = async (_input, init) => {
      authorization = new Headers(init?.headers).get('authorization');
      return Response.json({
        user: { id: 'user-id', email: 'person@example.com', displayName: 'Person' },
        sessionToken: token,
      });
    };

    await loginIdentity(
      serverFetch,
      { email: 'person@example.com', password: 'a sufficiently long password' },
      'B'.repeat(43),
      'http://blackops.test',
    );

    expect(authorization).toBe(`Bearer ${'B'.repeat(43)}`);
  });

  it('projects validation fields but never rejected values or raw details', async () => {
    const password = 'short-password-marker';
    const serverFetch: ServerFetch = async () =>
      Response.json(
        {
          status: 'error',
          code: 'identity.validation_failed',
          violations: [
            { field: 'password', code: 'identity.password.too_short', rejected: password },
            { field: 'internal', code: 'raw-sql-detail' },
          ],
          detail: 'postgresql://private-host/absolute/path',
        },
        { status: 422 },
      );

    const result = await registerIdentity(
      serverFetch,
      { email: 'person@example.com', displayName: 'Person', password },
      'http://blackops.test',
    );
    const serialized = JSON.stringify(result);

    expect(result).toEqual({
      ok: false,
      status: 422,
      code: 'identity.validation_failed',
      fieldErrors: { password: 'identity.password.too_short' },
    });
    expect(serialized).not.toContain(password);
    expect(serialized).not.toContain('private-host');
    expect(serialized).not.toContain('raw-sql-detail');
  });

  it('fails closed for malformed success and transport errors', async () => {
    const malformed: ServerFetch = async () => Response.json({ sessionToken: token });
    const failed: ServerFetch = async () => {
      throw new Error('ECONNREFUSED http://private-backend');
    };

    await expect(
      registerIdentity(
        malformed,
        { email: '', displayName: '', password: '' },
        'http://blackops.test',
      ),
    ).resolves.toMatchObject({ ok: false, code: 'identity.unavailable' });
    await expect(
      registerIdentity(failed, { email: '', displayName: '', password: '' }, 'http://private-backend'),
    ).resolves.toEqual({
      ok: false,
      status: 503,
      code: 'identity.unavailable',
      fieldErrors: {},
    });
  });

  it('uses DELETE for server-side logout and swallows backend failures', async () => {
    let method: string | undefined;
    let authorization: string | null = null;
    const serverFetch: ServerFetch = async (_input, init) => {
      method = init?.method;
      authorization = new Headers(init?.headers).get('authorization');
      throw new Error('backend unavailable');
    };

    await expect(
      logoutIdentity(serverFetch, token, 'http://blackops.test'),
    ).resolves.toBeUndefined();
    expect(method).toBe('DELETE');
    expect(authorization).toBe(`Bearer ${token}`);
  });
});

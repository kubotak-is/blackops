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
          token,
          issuedAt: '2026-07-22T00:00:00+00:00',
          expiresAt: '2026-07-22T08:00:00+00:00',
        },
        { status: 200 },
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
      sessionToken: token,
    });
    expect(calls).toEqual([
      { url: 'http://blackops.test/auth/register', authorization: null },
    ]);
    expect(JSON.stringify(result)).not.toContain('password-marker-not-returned');
  });

  it('injects the current bearer only for login rotation', async () => {
    const requests: Array<{ authorization: string | null; body: unknown }> = [];
    const serverFetch: ServerFetch = async (_input, init) => {
      requests.push({
        authorization: new Headers(init?.headers).get('authorization'),
        body: JSON.parse(String(init?.body)),
      });
      return Response.json({
        token,
        issuedAt: '2026-07-22T00:00:00+00:00',
        expiresAt: '2026-07-22T08:00:00+00:00',
      });
    };

    await loginIdentity(
      serverFetch,
      { email: 'person@example.com', password: 'a sufficiently long password' },
      'B'.repeat(43),
      'http://blackops.test',
    );
    await loginIdentity(
      serverFetch,
      { email: 'person@example.com', password: 'a sufficiently long password' },
      null,
      'http://blackops.test',
    );

    expect(requests).toEqual([
      {
        authorization: null,
        body: {
          currentToken: 'B'.repeat(43),
          email: 'person@example.com',
          password: 'a sufficiently long password',
        },
      },
      {
        authorization: null,
        body: {
          currentToken: null,
          email: 'person@example.com',
          password: 'a sufficiently long password',
        },
      },
    ]);
  });

  it('projects only generated validation fields without rejected values', async () => {
    const password = 'short-password-marker';
    const serverFetch: ServerFetch = async () =>
      Response.json(
        {
          status: 'rejected',
          operationId: '019f32ab-2be0-7b38-a0a7-1ab2f9687698',
          category: 'validation',
          code: 'validation.failed',
          violations: [
            { field: 'password', rule: 'Length', code: 'validation.length' },
          ],
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
      fieldErrors: { password: 'validation.length' },
    });
    expect(serialized).not.toContain(password);
    expect(serialized).not.toContain('private-host');
  });

  it('fails closed for malformed success and transport errors', async () => {
    const malformed: ServerFetch = async () => Response.json({ token });
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

  it('uses the generated logout operation and swallows backend failures', async () => {
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
    expect(method).toBe('POST');
    expect(authorization).toBe(`Bearer ${token}`);
  });
});

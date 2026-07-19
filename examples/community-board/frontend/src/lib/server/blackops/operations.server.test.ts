import { describe, expect, it } from 'vitest';
import { loadBoardWelcome, loadCurrentUser, type ServerFetch } from './operations.server';

describe('loadBoardWelcome', () => {
  it('maps the typed BlackOps outcome to a safe landing view', async () => {
    const calls: string[] = [];
    const serverFetch: ServerFetch = async (input) => {
      calls.push(String(input));

      return new Response(
        JSON.stringify({
          message: 'Welcome to BlackOps Board',
          summary: 'A server-rendered reference application powered by BlackOps Operations.',
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      );
    };

    const result = await loadBoardWelcome(serverFetch, 'http://blackops.test');

    expect(calls).toEqual(['http://blackops.test/welcome']);
    expect(result).toEqual({
      available: true,
      message: 'Welcome to BlackOps Board',
      summary: 'A server-rendered reference application powered by BlackOps Operations.',
    });
  });

  it('does not expose transport details to the landing view', async () => {
    const rawFailure = 'connect ECONNREFUSED http://private-backend:80';
    const serverFetch: ServerFetch = async () => {
      throw new Error(rawFailure);
    };

    const result = await loadBoardWelcome(serverFetch, 'http://private-backend:80');
    const serialized = JSON.stringify(result);

    expect(result).toEqual({
      available: false,
      message: 'The board service is temporarily unavailable.',
    });
    expect(serialized).not.toContain(rawFailure);
    expect(serialized).not.toContain('private-backend');
  });

  it('does not expose a backend error body to the landing view', async () => {
    const rawFailure = 'database failed at http://private-backend:80 with credential-secret';
    const serverFetch: ServerFetch = async () =>
      new Response(
        JSON.stringify({
          status: 'error',
          code: 'internal_error',
          detail: rawFailure,
        }),
        { status: 500, headers: { 'Content-Type': 'application/json' } },
      );

    const result = await loadBoardWelcome(serverFetch, 'http://private-backend:80');
    const serialized = JSON.stringify(result);

    expect(result).toEqual({
      available: false,
      message: 'The board service is temporarily unavailable.',
    });
    expect(serialized).not.toContain(rawFailure);
    expect(serialized).not.toContain('credential-secret');
  });

  it('fails closed when the private base URL is missing', async () => {
    const serverFetch: ServerFetch = async () => {
      throw new Error('must not be called');
    };

    await expect(loadBoardWelcome(serverFetch, '   ')).resolves.toEqual({
      available: false,
      message: 'The board service is temporarily unavailable.',
    });
  });
});

describe('loadCurrentUser', () => {
  it('injects bearer per request and returns only a safe current-user view', async () => {
    let authorization: string | null = null;
    const serverFetch: ServerFetch = async (_input, init) => {
      authorization = new Headers(init?.headers).get('authorization');
      return Response.json({
        id: 'user-id',
        email: 'person@example.com',
        displayName: 'Person',
      });
    };

    const result = await loadCurrentUser(serverFetch, 'raw-session-token', 'http://blackops.test');

    expect(authorization).toBe('Bearer raw-session-token');
    expect(result).toEqual({
      state: 'authenticated',
      user: { id: 'user-id', email: 'person@example.com', displayName: 'Person' },
    });
    expect(JSON.stringify(result)).not.toContain('raw-session-token');
  });

  it('marks an invalid session for cookie removal without exposing backend errors', async () => {
    const serverFetch: ServerFetch = async () =>
      Response.json(
        {
          status: 'error',
          category: 'unauthorized',
          code: 'authentication.invalid_session',
        },
        { status: 401 },
      );

    const result = await loadCurrentUser(serverFetch, 'expired-session', 'http://blackops.test');

    expect(result).toEqual({ state: 'invalid' });
    expect(JSON.stringify(result)).not.toContain('expired-session');
  });
});

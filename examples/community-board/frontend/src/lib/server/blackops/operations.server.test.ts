import { describe, expect, it } from 'vitest';
import { loadBoardWelcome, type ServerFetch } from './operations.server';

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

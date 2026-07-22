import { describe, expect, it } from 'vitest';
import { createServerBlackOpsClient, type ServerFetch } from './client.server';

describe('request-scoped BlackOps client', () => {
  it('isolates fetch, credentials, headers, and abort signals across concurrent requests', async () => {
    const calls: Array<{
      owner: string;
      authorization: string | null;
      signal: AbortSignal | null;
    }> = [];
    const fetchFor = (owner: string): ServerFetch => async (_input, init) => {
      calls.push({
        owner,
        authorization: new Headers(init?.headers).get('authorization'),
        signal: init?.signal instanceof AbortSignal ? init.signal : null,
      });
      return Response.json({
        message: `Welcome ${owner}`,
        summary: 'isolated',
      });
    };
    const aliceSignal = new AbortController().signal;
    const bobSignal = new AbortController().signal;
    const alice = createServerBlackOpsClient(fetchFor('alice'), 'alice-token', 'http://alice.test');
    const bob = createServerBlackOpsClient(fetchFor('bob'), 'bob-token', 'http://bob.test');

    expect(alice).not.toBeNull();
    expect(bob).not.toBeNull();
    await Promise.all([
      alice!.ShowBoardWelcome.fetch({}, { signal: aliceSignal }),
      bob!.ShowBoardWelcome.fetch({}, { signal: bobSignal }),
    ]);

    expect(calls).toEqual([
      { owner: 'alice', authorization: 'Bearer alice-token', signal: aliceSignal },
      { owner: 'bob', authorization: 'Bearer bob-token', signal: bobSignal },
    ]);
    expect(Object.isFrozen(alice)).toBe(true);
    expect(Object.isFrozen(bob)).toBe(true);
  });
});

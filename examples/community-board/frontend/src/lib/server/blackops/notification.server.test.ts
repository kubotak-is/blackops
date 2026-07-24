import { describe, expect, it } from 'vitest';
import { loadNotifications } from './notification.server';
import type { ServerFetch } from './client.server';

const json = (body: unknown, status = 200): Response => Response.json(body, { status });

describe('notification server boundary', () => {
  it('loads only safe notification fields through the authenticated generated client', async () => {
    let authorization: string | null = null;
    const fetch: ServerFetch = async (_input, init) => {
      authorization = new Headers(init?.headers).get('authorization');
      return json({ notifications: [{ id: 'n1', sourcePostId: 'p1', sourceCommentId: 'c1', message: 'Someone commented on your post.', createdAt: '2026-07-24T00:00:00.000000Z' }] });
    };

    const loaded = await loadNotifications(fetch, 'alice-token', 'http://blackops.test');
    await expect(Promise.resolve(loaded)).resolves.toEqual({
      ok: true,
      notifications: [{ id: 'n1', sourcePostId: 'p1', sourceCommentId: 'c1', message: 'Someone commented on your post.', createdAt: '2026-07-24T00:00:00.000000Z' }],
    });
    expect(authorization).toBe('Bearer alice-token');
  });

  it('conceals auth, transport, and private configuration failures', async () => {
    await expect(loadNotifications(async () => json({ status: 'error', category: 'unauthorized', code: 'auth.required' }, 401), 'token', 'http://private')).resolves.toMatchObject({ ok: false, kind: 'authentication', status: 401 });
    await expect(loadNotifications(async () => { throw new Error('SQLSTATE private'); }, 'token', 'http://private')).resolves.toMatchObject({ ok: false, kind: 'unavailable', status: 503 });
    await expect(loadNotifications(async () => { throw new Error('must not call'); }, 'token', ' ')).resolves.toMatchObject({ ok: false, kind: 'unavailable', status: 503 });
  });
});

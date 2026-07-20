import { describe, expect, it } from 'vitest';
import {
  addComment,
  createPost,
  deletePost,
  fixedPostLocation,
  loadPostDetail,
  loadPostFeed,
  normalizePage,
  replayFormValue,
  updatePost,
  type BoardFailure,
} from './board.server';
import type { ServerFetch } from './operations.server';

const json = (body: unknown, status = 200): Response => Response.json(body, { status });

describe('board operation boundary', () => {
  it('uses all six generated methods, paths, values, and per-request bearer injection', async () => {
    const calls: Array<{ url: string; method: string; authorization: string | null; body: unknown }> = [];
    const responses = [
      json({ page: 2, perPage: 20, total: 21, posts: [] }),
      json({
        post: {
          id: 'post-1', authorId: 'user-1', authorDisplayName: 'Alice', title: 'First', body: 'Body',
          createdAt: '2026-07-20T00:00:00+00:00', updatedAt: '2026-07-20T00:00:00+00:00',
        },
        comments: [],
      }),
      json({ postId: 'post-2', createdAt: '2026-07-20T00:00:00+00:00' }),
      json({ postId: 'post-1', updatedAt: '2026-07-20T01:00:00+00:00' }),
      json({ postId: 'post-1', commentId: 'comment-1', createdAt: '2026-07-20T01:00:00+00:00' }),
      new Response(null, { status: 204 }),
    ];
    const serverFetch: ServerFetch = async (input, init) => {
      calls.push({
        url: String(input),
        method: init?.method ?? 'GET',
        authorization: new Headers(init?.headers).get('authorization'),
        body: init?.body === undefined ? undefined : JSON.parse(String(init.body)),
      });
      return responses.shift()!;
    };

    await loadPostFeed(serverFetch, 'secret-token', 2, 'http://blackops.test');
    await loadPostDetail(serverFetch, 'secret-token', 'post-1', 'user-1', 'http://blackops.test');
    await createPost(serverFetch, 'secret-token', { title: 'Second', body: 'New' }, 'http://blackops.test');
    await updatePost(serverFetch, 'secret-token', { postId: 'post-1', title: 'Changed', body: 'Edit' }, 'http://blackops.test');
    await addComment(serverFetch, 'secret-token', { postId: 'post-1', body: 'Reply' }, 'http://blackops.test');
    await deletePost(serverFetch, 'secret-token', 'post-1', 'http://blackops.test');

    expect(calls).toEqual([
      { url: 'http://blackops.test/posts?page=2&perPage=20', method: 'GET', authorization: 'Bearer secret-token', body: undefined },
      { url: 'http://blackops.test/posts/post-1', method: 'GET', authorization: 'Bearer secret-token', body: undefined },
      { url: 'http://blackops.test/posts', method: 'POST', authorization: 'Bearer secret-token', body: { body: 'New', title: 'Second' } },
      { url: 'http://blackops.test/posts/post-1', method: 'PUT', authorization: 'Bearer secret-token', body: { body: 'Edit', title: 'Changed' } },
      { url: 'http://blackops.test/posts/post-1/comments', method: 'POST', authorization: 'Bearer secret-token', body: { body: 'Reply' } },
      { url: 'http://blackops.test/posts/post-1', method: 'DELETE', authorization: 'Bearer secret-token', body: undefined },
    ]);
    expect(JSON.stringify(await createPost(async () => { throw new Error('offline'); }, 'secret-token', { title: '', body: '' }, 'http://internal:8080'))).not.toContain('secret-token');
  });

  it('projects nested feed and detail data without author identifiers', async () => {
    const feed = await loadPostFeed(async () => json({
      page: 1,
      perPage: 20,
      total: 1,
      posts: [{
        id: 'post-1', authorId: 'private-author', authorDisplayName: 'Alice', title: 'Hello', bodyPreview: 'Preview',
        createdAt: '2026-07-20T00:00:00+00:00', updatedAt: '2026-07-20T01:00:00+00:00', commentCount: 1,
      }],
    }), 'token', 1, 'http://blackops.test');
    const detail = await loadPostDetail(async () => json({
      post: {
        id: 'post-1', authorId: 'private-author', authorDisplayName: 'Alice', title: 'Hello', body: 'Full body',
        createdAt: '2026-07-20T00:00:00+00:00', updatedAt: '2026-07-20T01:00:00+00:00',
      },
      comments: [{
        id: 'comment-1', postId: 'post-1', authorId: 'comment-author', authorDisplayName: 'Bob', body: 'Reply',
        createdAt: '2026-07-20T02:00:00+00:00',
      }],
    }), 'token', 'post-1', 'private-author', 'http://blackops.test');

    expect(feed.ok && feed.data).toMatchObject({ hasPrevious: false, hasNext: false, posts: [{ id: 'post-1' }] });
    expect(detail.ok && detail.data).toMatchObject({ owner: true, commentCount: 1, comments: [{ id: 'comment-1', body: 'Reply' }] });
    expect(JSON.stringify({ feed, detail })).not.toContain('private-author');
    expect(JSON.stringify({ feed, detail })).not.toContain('comment-author');
  });

  it('computes non-owner views server-side', async () => {
    const result = await loadPostDetail(async () => json({
      post: {
        id: 'post-1', authorId: 'alice', authorDisplayName: 'Alice', title: 'Hello', body: 'Body',
        createdAt: '2026-07-20T00:00:00+00:00', updatedAt: '2026-07-20T00:00:00+00:00',
      },
      comments: [],
    }), 'token', 'post-1', 'bob', 'http://blackops.test');

    expect(result.ok && result.data.owner).toBe(false);
  });

  it('maps validation fields and drops backend identifiers, rules, and codes', async () => {
    const rawOperationId = '01999999-9999-7999-8999-999999999999';
    const result = await createPost(async () => json({
      status: 'rejected', operationId: rawOperationId, category: 'validation', code: 'validation.failed',
      violations: [
        { field: 'title', rule: 'Length', code: 'validation.length' },
        { field: 'body', rule: 'NotBlank', code: 'validation.not_blank' },
      ],
    }, 422), 'raw-credential', { title: '', body: '' }, 'http://private-backend:8080');

    expect(result).toEqual({
      ok: false,
      kind: 'validation',
      status: 422,
      message: 'Please correct the highlighted fields.',
      fieldErrors: { title: 'Please check title.', body: 'Please check body.' },
    });
    const serialized = JSON.stringify(result);
    expect(serialized).not.toContain(rawOperationId);
    expect(serialized).not.toContain('validation.length');
    expect(serialized).not.toContain('raw-credential');
    expect(serialized).not.toContain('private-backend');
  });

  it.each([
    [401, { status: 'error', category: 'unauthorized', code: 'authentication.invalid_session' }, 'authentication', 401],
    [404, { status: 'rejected', operationId: 'op-secret', category: 'not_found', code: 'board.post.not_found' }, 'not_found', 404],
    [409, { status: 'rejected', operationId: 'op-secret', category: 'conflict', code: 'board.post.conflict' }, 'conflict', 409],
    [500, { status: 'error', operationId: 'op-secret', code: 'internal_error' }, 'unavailable', 503],
  ] as const)('maps backend %s to a safe failure', async (status, payload, kind, safeStatus) => {
    const result = await deletePost(async () => json(payload, status), 'token', 'post-1', 'http://internal-host');
    expect(result.ok).toBe(false);
    expect((result as BoardFailure).kind).toBe(kind);
    expect((result as BoardFailure).status).toBe(safeStatus);
    expect(JSON.stringify(result)).not.toMatch(/op-secret|internal-host|board\.post/);
  });

  it('fails closed on malformed responses, thrown transport errors, and missing configuration', async () => {
    const marker = 'SQLSTATE at /srv/private http://internal:8080';
    const malformed = await loadPostFeed(async () => new Response(marker, { status: 500 }), 'token', 1, 'http://internal:8080');
    const transport = await loadPostFeed(async () => { throw new Error(marker); }, 'token', 1, 'http://internal:8080');
    const missing = await loadPostFeed(async () => { throw new Error('must not call'); }, 'token', 1, '   ');

    for (const result of [malformed, transport, missing]) {
      expect(result).toMatchObject({ ok: false, kind: 'unavailable', status: 503 });
      expect(JSON.stringify(result)).not.toMatch(/SQLSTATE|\/srv\/private|internal:8080|token/);
    }
  });

  it('normalizes pagination, clamps replay data by Unicode characters, and builds fixed redirects', () => {
    expect([null, '', '0', '-1', '1.5', 'abc', '9007199254740992'].map(normalizePage)).toEqual([1, 1, 1, 1, 1, 1, 1]);
    expect(normalizePage('23')).toBe(23);
    expect(Array.from(replayFormValue('🙂'.repeat(150)))).toHaveLength(100);
    expect(fixedPostLocation('post/unsafe?next=/admin')).toBe('/posts/post%2Funsafe%3Fnext%3D%2Fadmin');
  });
});

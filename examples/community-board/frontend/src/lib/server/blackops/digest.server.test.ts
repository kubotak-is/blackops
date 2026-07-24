import { describe, expect, it } from 'vitest';
import {
  currentUtcIsoWeek,
  digestLocation,
  digestOperationLocation,
  loadDigest,
  loadDigestStatus,
  startWeeklyDigest,
  waitForDigest,
} from './digest.server';
import type { ServerFetch } from './operations.server';

const operationId = '019b4000-0000-7000-8000-000000000001';
const digestId = '019b5000-0000-7000-8000-000000000001';

const json = (body: unknown, status = 200, headers: Record<string, string> = {}): Response =>
  Response.json(body, { status, headers });

describe('digest operation boundary', () => {
  it('starts through the generated deferred fetch with per-call bearer injection', async () => {
    let call: { url: string; authorization: string | null; idempotencyKey: string | null; body: unknown } | undefined;
    const fetch: ServerFetch = async (input, init) => {
      call = {
        url: String(input),
        authorization: new Headers(init?.headers).get('authorization'),
        idempotencyKey: new Headers(init?.headers).get('idempotency-key'),
        body: JSON.parse(String(init?.body)),
      };
      return json({ status: 'accepted', operationId, acceptedAt: '2026-07-21T00:00:00.000000Z' }, 202);
    };

    await expect(startWeeklyDigest(fetch, 'secret-token', '2026-W30', 'digest-key', 'http://blackops.test'))
      .resolves.toEqual({ ok: true, operationId });
    expect(call).toEqual({
      url: 'http://blackops.test/digests',
      authorization: 'Bearer secret-token',
      idempotencyKey: 'digest-key',
      body: { week: '2026-W30' },
    });
  });

  it('maps validation to an explicit week error and strips backend metadata', async () => {
    const result = await startWeeklyDigest(async () => json({
      status: 'rejected',
      operationId,
      category: 'validation',
      code: 'validation.failed',
      violations: [],
    }, 422), 'credential', '2021-W53', 'digest-key', 'http://private-backend');

    expect(result).toEqual({
      ok: false,
      kind: 'validation',
      status: 422,
      message: 'Please enter a valid ISO week.',
      fieldErrors: { week: 'Please enter a valid ISO week.' },
    });
    expect(JSON.stringify(result)).not.toMatch(/credential|private-backend|019b4000/);
  });

  it('rejects a tampered key before transport without returning the raw value', async () => {
    const result = await startWeeklyDigest(async () => { throw new Error('must not call'); }, 'token', '2026-W30', 'bad key', 'http://private');
    expect(result).toMatchObject({ ok: false, kind: 'validation', status: 422 });
    expect(JSON.stringify(result)).not.toContain('bad key');
  });

  it.each([
    ['accepted', {}, 9, { state: 'accepted', retryAfterSeconds: 5 }],
    ['running', { attempt: 1 }, 2, { state: 'running', retryAfterSeconds: 2 }],
    ['retry_scheduled', { attempt: 1, retryAt: '2026-07-21T00:00:01.000000Z' }, 3, { state: 'retry_scheduled', retryAfterSeconds: 3 }],
  ] as const)('maps %s and clamps retry hints', async (state, extra, hint, expected) => {
    const result = await loadDigestStatus(async () => json({
      schemaVersion: 1,
      operationId,
      operationType: 'board.digest.weekly.generate',
      state,
      ...extra,
    }, 200, { 'retry-after': String(hint) }), 'token', operationId, 'http://blackops.test');

    expect(result).toMatchObject({ ok: true, operationId, ...expected });
  });

  it('maps completed to only the digest navigation identity', async () => {
    const result = await loadDigestStatus(async () => json({
      schemaVersion: 1,
      operationId,
      operationType: 'board.digest.weekly.generate',
      state: 'completed',
      outcome: {
        digestId,
        week: '2026-W30',
        postCount: 1,
        commentCount: 1,
        createdAt: '2026-07-21T00:00:00.000000Z',
      },
    }), 'token', operationId, 'http://blackops.test');

    expect(result).toEqual({ ok: true, operationId, state: 'completed', digestId });
    expect(JSON.stringify(result)).not.toMatch(/postCount|commentCount|createdAt/);
  });

  it('conceals an expired operation behind the safe not-found result', async () => {
    const result = await loadDigestStatus(async () => json({
      status: 'error',
      code: 'operation_expired',
    }, 410), 'token', operationId, 'http://blackops.test');

    expect(result).toEqual({
      ok: false,
      kind: 'not_found',
      status: 404,
      message: 'This digest operation could not be found.',
      fieldErrors: {},
    });
  });

  it.each(['rejected', 'failed', 'dead_lettered'] as const)('shrinks %s to the same safe failure', async (state) => {
    const error = state === 'rejected'
      ? { category: 'validation', code: 'board.digest.invalid_week' }
      : { code: state === 'failed' ? 'operation_failed' : 'operation_dead_lettered' };
    const result = await loadDigestStatus(async () => json({
      schemaVersion: 1,
      operationId,
      operationType: 'board.digest.weekly.generate',
      state,
      error,
    }), 'token', operationId, 'http://blackops.test');

    expect(result).toEqual({ ok: true, operationId, state: 'failed', message: 'Digest generation could not be completed.' });
    expect(JSON.stringify(result)).not.toMatch(/invalid_week|operation_failed|dead_lettered|attempt/);
  });

  it('falls back to one status request after a finite wait timeout', async () => {
    let calls = 0;
    const fetch: ServerFetch = async () => {
      calls += 1;
      return json({
        schemaVersion: 1,
        operationId,
        operationType: 'board.digest.weekly.generate',
        state: 'accepted',
      }, 200, { 'retry-after': '1' });
    };

    const result = await waitForDigest(fetch, 'token', operationId, new AbortController().signal, 1, 'http://blackops.test');
    expect(result).toMatchObject({ ok: true, state: 'accepted' });
    expect(calls).toBe(2);
  });

  it('maps malformed identifiers, abort, transport, and private configuration safely', async () => {
    const malformed = await loadDigestStatus(async () => { throw new Error('must not call'); }, 'token', 'bad', 'http://private');
    const controller = new AbortController();
    controller.abort();
    const aborted = await waitForDigest(async () => { throw new Error('must not call'); }, 'token', operationId, controller.signal, 10, 'http://private');
    const transport = await loadDigest(async () => { throw new Error('SQLSTATE /private'); }, 'token', digestId, 'http://private');
    const missing = await loadDigest(async () => { throw new Error('must not call'); }, 'token', digestId, ' ');

    expect(malformed).toMatchObject({ ok: false, kind: 'not_found', status: 404 });
    for (const result of [aborted, transport, missing]) {
      expect(result).toMatchObject({ ok: false, kind: 'unavailable', status: 503 });
      expect(JSON.stringify(result)).not.toMatch(/token|private|SQLSTATE/);
    }
  });

  it('loads only safe digest detail fields', async () => {
    const result = await loadDigest(async () => json({
      digestId,
      week: '2026-W30',
      content: 'Weekly digest for 2026-W30: 1 post and 1 comment.',
      postCount: 1,
      commentCount: 1,
      createdAt: '2026-07-21T00:00:00.000000Z',
    }), 'token', digestId, 'http://blackops.test');

    expect(result.ok && result.data).toMatchObject({ digestId, postCount: 1, commentCount: 1 });
  });

  it('calculates UTC ISO weeks and builds fixed encoded routes', () => {
    expect(currentUtcIsoWeek(new Date('2021-01-01T23:59:59-10:00'))).toBe('2020-W53');
    expect(currentUtcIsoWeek(new Date('2021-01-04T00:00:00Z'))).toBe('2021-W01');
    expect(digestOperationLocation('id/?next=/admin')).toBe('/digests/operations/id%2F%3Fnext%3D%2Fadmin');
    expect(digestLocation('id/?next=/admin')).toBe('/digests/id%2F%3Fnext%3D%2Fadmin');
  });
});

import { env } from '$env/dynamic/private';
import { GenerateWeeklyDigest } from './generated/operations/board/digest/weekly/generate-weekly-digest';
import { createServerBlackOpsClient } from './client.server';
import type { ServerFetch } from './client.server';

const unavailableMessage = 'The digest service is temporarily unavailable. Please try again.';
const failedMessage = 'Digest generation could not be completed.';

export type DigestFailure = Readonly<{
  ok: false;
  kind: 'authentication' | 'not_found' | 'validation' | 'unavailable';
  status: 401 | 404 | 422 | 503;
  message: string;
  fieldErrors: Readonly<Record<string, string>>;
}>;

export type DigestStatusView =
  | Readonly<{ ok: true; operationId: string; state: 'accepted' | 'running' | 'retry_scheduled'; retryAfterSeconds: number }>
  | Readonly<{ ok: true; operationId: string; state: 'completed'; digestId: string }>
  | Readonly<{ ok: true; operationId: string; state: 'failed'; message: string }>
  | DigestFailure;

export type DigestDetailView = Readonly<{
  digestId: string;
  week: string;
  content: string;
  postCount: number;
  commentCount: number;
  createdAt: string;
}>;

export type StartDigestResult = Readonly<{ ok: true; operationId: string }> | DigestFailure;
export type LoadDigestResult = Readonly<{ ok: true; data: DigestDetailView }> | DigestFailure;

function emptyFieldErrors(): Readonly<Record<string, string>> {
  return Object.freeze({});
}

function failure(
  kind: DigestFailure['kind'],
  status: DigestFailure['status'],
  message: string,
  fieldErrors: Readonly<Record<string, string>> = emptyFieldErrors(),
): DigestFailure {
  return Object.freeze({ ok: false as const, kind, status, message, fieldErrors });
}

function unavailable(): DigestFailure {
  return failure('unavailable', 503, unavailableMessage);
}

function mapBoundaryFailure(result: Readonly<{
  status: number | null;
  kind: string;
  error?: Readonly<{ code?: string }>;
}>): DigestFailure {
  if (result.status === 401) return failure('authentication', 401, 'Please log in to continue.');
  if (result.status === 404 || result.status === 410 || result.error?.code === 'invalid_operation_id') {
    return failure('not_found', 404, 'This digest operation could not be found.');
  }
  return unavailable();
}

function retryAfter(value: unknown): number {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0 ? Math.min(value, 5) : 1;
}

function mapStatus(result: Awaited<ReturnType<typeof GenerateWeeklyDigest.status>>): DigestStatusView {
  if (!result.ok) return mapBoundaryFailure(result);
  if (result.kind === 'completed') {
    return Object.freeze({
      ok: true as const,
      operationId: result.data.operationId,
      state: 'completed' as const,
      digestId: result.data.outcome.digestId,
    });
  }
  if (result.kind === 'rejected' || result.kind === 'failed' || result.kind === 'dead_lettered') {
    return Object.freeze({
      ok: true as const,
      operationId: result.data.operationId,
      state: 'failed' as const,
      message: failedMessage,
    });
  }

  return Object.freeze({
    ok: true as const,
    operationId: result.data.operationId,
    state: result.kind,
    retryAfterSeconds: retryAfter(result.retryAfterSeconds),
  });
}

function mapWait(result: Awaited<ReturnType<typeof GenerateWeeklyDigest.wait>>): DigestStatusView | 'timeout' {
  if (!result.ok) {
    if (result.kind === 'transport' && result.error.code === 'poll_timeout') return 'timeout';
    return mapBoundaryFailure(result);
  }
  if (result.kind === 'completed') {
    return Object.freeze({
      ok: true as const,
      operationId: result.data.operationId,
      state: 'completed' as const,
      digestId: result.data.outcome.digestId,
    });
  }

  return Object.freeze({
    ok: true as const,
    operationId: result.data.operationId,
    state: 'failed' as const,
    message: failedMessage,
  });
}

export function currentUtcIsoWeek(now: Date = new Date()): string {
  const date = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
  const day = date.getUTCDay() || 7;
  date.setUTCDate(date.getUTCDate() + 4 - day);
  const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
  const week = Math.ceil((((date.getTime() - yearStart.getTime()) / 86_400_000) + 1) / 7);
  return `${date.getUTCFullYear()}-W${String(week).padStart(2, '0')}`;
}

export function digestOperationLocation(operationId: string): string {
  return `/digests/operations/${encodeURIComponent(operationId)}`;
}

export function digestLocation(digestId: string): string {
  return `/digests/${encodeURIComponent(digestId)}`;
}

export async function startWeeklyDigest(
  serverFetch: ServerFetch,
  rawToken: string,
  week: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<StartDigestResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();
  try {
    const result = await blackops.GenerateWeeklyDigest.fetch({ week });
    if (result.ok) return Object.freeze({ ok: true as const, operationId: result.data.operationId });
    if (result.kind === 'validation') {
      return failure('validation', 422, 'Please enter a valid ISO week.', Object.freeze({ week: 'Please enter a valid ISO week.' }));
    }
    return mapBoundaryFailure(result);
  } catch {
    return unavailable();
  }
}

export async function loadDigestStatus(
  serverFetch: ServerFetch,
  rawToken: string,
  operationId: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<DigestStatusView> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();
  try {
    return mapStatus(await blackops.GenerateWeeklyDigest.status(operationId));
  } catch {
    return unavailable();
  }
}

export async function waitForDigest(
  serverFetch: ServerFetch,
  rawToken: string,
  operationId: string,
  signal: AbortSignal,
  maxWaitMilliseconds: number,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<DigestStatusView> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();
  try {
    const result = mapWait(await blackops.GenerateWeeklyDigest.wait(operationId, { signal, maxWaitMilliseconds }));
    return result === 'timeout'
      ? loadDigestStatus(serverFetch, rawToken, operationId, baseUrl)
      : result;
  } catch {
    return unavailable();
  }
}

export async function loadDigest(
  serverFetch: ServerFetch,
  rawToken: string,
  digestId: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<LoadDigestResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();
  try {
    const result = await blackops.ShowDigest.fetch({ digestId });
    if (!result.ok) return mapBoundaryFailure(result);
    return Object.freeze({
      ok: true as const,
      data: Object.freeze({
        digestId: result.data.digestId,
        week: result.data.week,
        content: result.data.content,
        postCount: result.data.postCount,
        commentCount: result.data.commentCount,
        createdAt: result.data.createdAt,
      }),
    });
  } catch {
    return unavailable();
  }
}

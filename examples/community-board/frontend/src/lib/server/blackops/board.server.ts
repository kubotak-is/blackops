import { env } from '$env/dynamic/private';
import type {
  OperationFailureResult,
  ValidationViolation,
} from './generated/types';
import { createServerBlackOpsClient } from './client.server';
import type { ServerFetch } from './client.server';

export const POSTS_PER_PAGE = 20;

const FORM_REPLAY_CHARACTER_BUDGET = 100;
const unavailableMessage = 'The board service is temporarily unavailable. Please try again.';

export type BoardFailureKind =
  | 'authentication'
  | 'not_found'
  | 'conflict'
  | 'validation'
  | 'unavailable';

export type BoardFailure = Readonly<{
  ok: false;
  kind: BoardFailureKind;
  status: 401 | 404 | 409 | 422 | 503;
  message: string;
  fieldErrors: Readonly<Record<string, string>>;
}>;

export type FeedItemView = Readonly<{
  id: string;
  authorDisplayName: string;
  title: string;
  bodyPreview: string;
  createdAt: string;
  updatedAt: string;
  commentCount: number;
}>;

export type FeedPageView = Readonly<{
  posts: ReadonlyArray<FeedItemView>;
  page: number;
  perPage: number;
  total: number;
  hasPrevious: boolean;
  hasNext: boolean;
}>;

export type CommentView = Readonly<{
  id: string;
  authorDisplayName: string;
  body: string;
  createdAt: string;
}>;

export type PostDetailView = Readonly<{
  id: string;
  authorDisplayName: string;
  title: string;
  bodyPreview: string;
  body: string;
  createdAt: string;
  updatedAt: string;
  commentCount: number;
  owner: boolean;
  comments: ReadonlyArray<CommentView>;
}>;

export type BoardResult<T> = Readonly<{ ok: true; data: T }> | BoardFailure;

type MutationSuccess = Readonly<{ ok: true }>;
export type MutationResult = MutationSuccess | BoardFailure;
export type CreatePostResult = Readonly<{ ok: true; postId: string }> | BoardFailure;

function emptyFieldErrors(): Readonly<Record<string, string>> {
  return Object.freeze({});
}

function safeValidationErrors<TField extends string>(
  violations: readonly ValidationViolation<TField>[],
  allowedFields: ReadonlySet<string>,
): Readonly<Record<string, string>> {
  const errors: Record<string, string> = {};
  for (const violation of violations) {
    if (allowedFields.has(violation.field) && errors[violation.field] === undefined) {
      errors[violation.field] = `Please check ${violation.field}.`;
    }
  }

  return Object.freeze(errors);
}

function mapFailure<TField extends string>(
  result: OperationFailureResult<TField>,
  allowedFields: ReadonlySet<string> = new Set(),
): BoardFailure {
  if (result.kind === 'validation') {
    return Object.freeze({
      ok: false as const,
      kind: 'validation' as const,
      status: 422 as const,
      message: 'Please correct the highlighted fields.',
      fieldErrors: safeValidationErrors(result.error.violations, allowedFields),
    });
  }

  if (result.status === 401) {
    return Object.freeze({
      ok: false as const,
      kind: 'authentication' as const,
      status: 401 as const,
      message: 'Please log in to continue.',
      fieldErrors: emptyFieldErrors(),
    });
  }

  if (result.status === 404 || result.status === 403) {
    return Object.freeze({
      ok: false as const,
      kind: 'not_found' as const,
      status: 404 as const,
      message: 'This post could not be found.',
      fieldErrors: emptyFieldErrors(),
    });
  }

  if (result.status === 409) {
    return Object.freeze({
      ok: false as const,
      kind: 'conflict' as const,
      status: 409 as const,
      message: 'The post changed while you were working. Please try again.',
      fieldErrors: emptyFieldErrors(),
    });
  }

  return Object.freeze({
    ok: false as const,
    kind: 'unavailable' as const,
    status: 503 as const,
    message: unavailableMessage,
    fieldErrors: emptyFieldErrors(),
  });
}

function unavailable(): BoardFailure {
  return Object.freeze({
    ok: false as const,
    kind: 'unavailable' as const,
    status: 503 as const,
    message: unavailableMessage,
    fieldErrors: emptyFieldErrors(),
  });
}

export function normalizePage(rawPage: string | null): number {
  if (rawPage === null || !/^[1-9][0-9]*$/.test(rawPage)) {
    return 1;
  }

  const page = Number(rawPage);
  return Number.isSafeInteger(page) ? page : 1;
}

export function replayFormValue(value: string): string {
  return Array.from(value).slice(0, FORM_REPLAY_CHARACTER_BUDGET).join('');
}

export function fixedPostLocation(postId: string): string {
  return `/posts/${encodeURIComponent(postId)}`;
}

export async function loadPostFeed(
  serverFetch: ServerFetch,
  rawToken: string,
  page: number,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<BoardResult<FeedPageView>> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.ListPosts.fetch({ page, perPage: POSTS_PER_PAGE });
    if (!result.ok) return mapFailure(result);
    if (result.kind !== 'completed') return unavailable();

    const posts = Object.freeze(result.data.posts.map((post) => Object.freeze({
      id: post.id,
      authorDisplayName: post.authorDisplayName,
      title: post.title,
      bodyPreview: post.bodyPreview,
      createdAt: post.createdAt,
      updatedAt: post.updatedAt,
      commentCount: post.commentCount,
    })));
    const totalPages = Math.ceil(result.data.total / result.data.perPage);

    return Object.freeze({
      ok: true as const,
      data: Object.freeze({
        posts,
        page: result.data.page,
        perPage: result.data.perPage,
        total: result.data.total,
        hasPrevious: result.data.page > 1,
        hasNext: result.data.page < totalPages,
      }),
    });
  } catch {
    return unavailable();
  }
}

export async function loadPostDetail(
  serverFetch: ServerFetch,
  rawToken: string,
  postId: string,
  currentUserId: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<BoardResult<PostDetailView>> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.ShowPost.fetch({ postId });
    if (!result.ok) return mapFailure(result);
    if (result.kind !== 'completed') return unavailable();

    const comments = Object.freeze(result.data.comments.map((comment) => Object.freeze({
      id: comment.id,
      authorDisplayName: comment.authorDisplayName,
      body: comment.body,
      createdAt: comment.createdAt,
    })));
    const post = result.data.post;

    return Object.freeze({
      ok: true as const,
      data: Object.freeze({
        id: post.id,
        authorDisplayName: post.authorDisplayName,
        title: post.title,
        bodyPreview: post.body,
        body: post.body,
        createdAt: post.createdAt,
        updatedAt: post.updatedAt,
        commentCount: comments.length,
        owner: post.authorId === currentUserId,
        comments,
      }),
    });
  } catch {
    return unavailable();
  }
}

export async function createPost(
  serverFetch: ServerFetch,
  rawToken: string,
  values: Readonly<{ title: string; body: string }>,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<CreatePostResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.CreatePost.fetch(values);
    if (!result.ok) return mapFailure(result, new Set(['title', 'body']));
    if (result.kind !== 'completed') return unavailable();
    return Object.freeze({ ok: true as const, postId: result.data.postId });
  } catch {
    return unavailable();
  }
}

export async function updatePost(
  serverFetch: ServerFetch,
  rawToken: string,
  values: Readonly<{ postId: string; title: string; body: string }>,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<MutationResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.UpdatePost.fetch(values);
    if (!result.ok) return mapFailure(result, new Set(['title', 'body']));
    return Object.freeze({ ok: true as const });
  } catch {
    return unavailable();
  }
}

export async function deletePost(
  serverFetch: ServerFetch,
  rawToken: string,
  postId: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<MutationResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.DeletePost.fetch({ postId });
    if (!result.ok) return mapFailure(result);
    return Object.freeze({ ok: true as const });
  } catch {
    return unavailable();
  }
}

export async function addComment(
  serverFetch: ServerFetch,
  rawToken: string,
  values: Readonly<{ postId: string; body: string }>,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<MutationResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.AddComment.fetch(values);
    if (!result.ok) return mapFailure(result, new Set(['body']));
    return Object.freeze({ ok: true as const });
  } catch {
    return unavailable();
  }
}

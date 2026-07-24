import { env } from '$env/dynamic/private';
import { createServerBlackOpsClient } from './client.server';
import type { ServerFetch } from './client.server';

export type NotificationFailure = Readonly<{
  ok: false;
  status: 401 | 503;
  kind: 'authentication' | 'unavailable';
  message: string;
}>;

export type NotificationView = Readonly<{
  id: string;
  sourcePostId: string;
  sourceCommentId: string;
  message: string;
  createdAt: string;
}>;

export type NotificationResult = Readonly<{
  ok: true;
  notifications: ReadonlyArray<NotificationView>;
}> | NotificationFailure;

export async function loadNotifications(
  serverFetch: ServerFetch,
  rawToken: string,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<NotificationResult> {
  const blackops = createServerBlackOpsClient(serverFetch, rawToken, baseUrl);
  if (blackops === null) return unavailable();

  try {
    const result = await blackops.ListNotifications.fetch({ limit: 50 });
    if (!result.ok) {
      if (result.status === 401) {
        return Object.freeze({ ok: false as const, kind: 'authentication' as const, status: 401 as const, message: 'Please log in to continue.' });
      }

      return unavailable();
    }

    return Object.freeze({
      ok: true as const,
      notifications: Object.freeze(result.data.notifications.map((item) => Object.freeze({
        id: item.id,
        sourcePostId: item.sourcePostId,
        sourceCommentId: item.sourceCommentId,
        message: item.message,
        createdAt: item.createdAt,
      }))),
    });
  } catch {
    return unavailable();
  }
}

function unavailable(): NotificationFailure {
  return Object.freeze({
    ok: false as const,
    kind: 'unavailable' as const,
    status: 503 as const,
    message: 'Notifications are temporarily unavailable.',
  });
}

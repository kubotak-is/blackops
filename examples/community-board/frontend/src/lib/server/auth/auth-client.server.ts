import { env } from '$env/dynamic/private';

export type ServerFetch = typeof globalThis.fetch;

export type SafeUser = Readonly<{
  id: string;
  email: string;
  displayName: string;
}>;

export type AuthField = 'email' | 'displayName' | 'password';

export type AuthSuccess = Readonly<{
  ok: true;
  user: SafeUser;
  sessionToken: string;
}>;

export type AuthFailure = Readonly<{
  ok: false;
  status: number;
  code: string;
  fieldErrors: Readonly<Partial<Record<AuthField, string>>>;
}>;

export type AuthResult = AuthSuccess | AuthFailure;

type RegisterInput = Readonly<{
  email: string;
  displayName: string;
  password: string;
}>;

type LoginInput = Readonly<{
  email: string;
  password: string;
}>;

const unavailable: AuthFailure = Object.freeze({
  ok: false,
  status: 503,
  code: 'identity.unavailable',
  fieldErrors: Object.freeze({}),
});

const safeFailureCodes = new Set([
  'identity.validation_failed',
  'identity.email_unavailable',
  'identity.invalid_request',
  'identity.unsupported_media_type',
  'authentication.invalid_credentials',
]);

const safeFieldCodes = new Set([
  'identity.email.invalid',
  'identity.display_name.required',
  'identity.display_name.too_long',
  'identity.password.invalid',
  'identity.password.too_short',
  'identity.password.too_long',
]);

function endpoint(path: string, baseUrl: string | undefined): string | null {
  if (baseUrl === undefined || baseUrl.trim() === '') {
    return null;
  }

  try {
    const parsed = new URL(baseUrl);
    if (
      (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') ||
      parsed.username !== '' ||
      parsed.password !== '' ||
      parsed.search !== '' ||
      parsed.hash !== ''
    ) {
      return null;
    }

    const prefix = parsed.pathname.replace(/\/$/, '');
    parsed.pathname = `${prefix}${path}`;

    return parsed.toString();
  } catch {
    return null;
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function safeUser(value: unknown): SafeUser | null {
  if (!isRecord(value)) {
    return null;
  }

  const { id, email, displayName } = value;
  if (typeof id !== 'string' || typeof email !== 'string' || typeof displayName !== 'string') {
    return null;
  }

  return Object.freeze({ id, email, displayName });
}

function failure(status: number, payload: unknown): AuthFailure {
  if (
    !isRecord(payload) ||
    typeof payload.code !== 'string' ||
    !safeFailureCodes.has(payload.code)
  ) {
    return unavailable;
  }

  const fieldErrors: Partial<Record<AuthField, string>> = {};
  if (payload.code === 'identity.validation_failed' && Array.isArray(payload.violations)) {
    for (const violation of payload.violations) {
      if (
        !isRecord(violation) ||
        typeof violation.code !== 'string' ||
        !safeFieldCodes.has(violation.code)
      ) {
        continue;
      }

      if (
        violation.field === 'email' ||
        violation.field === 'displayName' ||
        violation.field === 'password'
      ) {
        fieldErrors[violation.field] = violation.code;
      }
    }
  }

  return Object.freeze({
    ok: false as const,
    status,
    code: payload.code,
    fieldErrors: Object.freeze(fieldErrors),
  });
}

async function requestSession(
  serverFetch: ServerFetch,
  path: string,
  body: RegisterInput | LoginInput,
  currentToken: string | null,
  baseUrl: string | undefined,
): Promise<AuthResult> {
  const url = endpoint(path, baseUrl);
  if (url === null) {
    return unavailable;
  }

  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  if (currentToken !== null) {
    headers.Authorization = `Bearer ${currentToken}`;
  }

  try {
    const response = await serverFetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
    });
    const contentType = response.headers.get('content-type') ?? '';
    if (!contentType.toLowerCase().startsWith('application/json')) {
      return unavailable;
    }

    const payload: unknown = await response.json();
    if (!response.ok) {
      return failure(response.status, payload);
    }

    if (!isRecord(payload)) {
      return unavailable;
    }

    const user = safeUser(payload.user);
    if (
      user === null ||
      typeof payload.sessionToken !== 'string' ||
      !/^[A-Za-z0-9_-]{43}$/.test(payload.sessionToken)
    ) {
      return unavailable;
    }

    return Object.freeze({
      ok: true as const,
      user,
      sessionToken: payload.sessionToken,
    });
  } catch {
    return unavailable;
  }
}

export function registerIdentity(
  serverFetch: ServerFetch,
  input: RegisterInput,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<AuthResult> {
  return requestSession(serverFetch, '/auth/users', input, null, baseUrl);
}

export function loginIdentity(
  serverFetch: ServerFetch,
  input: LoginInput,
  currentToken: string | null,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<AuthResult> {
  return requestSession(serverFetch, '/auth/sessions', input, currentToken, baseUrl);
}

export async function logoutIdentity(
  serverFetch: ServerFetch,
  currentToken: string | null,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<void> {
  const url = endpoint('/auth/sessions/current', baseUrl);
  if (url === null) {
    return;
  }

  const headers: Record<string, string> = {};
  if (currentToken !== null) {
    headers.Authorization = `Bearer ${currentToken}`;
  }

  try {
    await serverFetch(url, { method: 'DELETE', headers });
  } catch {
    // The browser cookie is still removed; backend errors are not exposed to the action response.
  }
}

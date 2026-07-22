import { env } from '$env/dynamic/private';
import { createServerBlackOpsClient } from '$lib/server/blackops/client.server';
import type {
  LoginResult,
  RegisterResult,
  ValidationViolation,
} from '$lib/server/blackops/generated';

export type ServerFetch = typeof globalThis.fetch;

export type AuthField = 'email' | 'displayName' | 'password';

export type AuthSuccess = Readonly<{
  ok: true;
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

function fieldErrors(
  violations: readonly ValidationViolation<string>[],
): Readonly<Partial<Record<AuthField, string>>> {
  const errors: Partial<Record<AuthField, string>> = {};
  for (const violation of violations) {
    if (
      violation.field === 'email' ||
      violation.field === 'displayName' ||
      violation.field === 'password'
    ) {
      errors[violation.field] = violation.code;
    }
  }

  return Object.freeze(errors);
}

function failure(result: RegisterResult | LoginResult): AuthFailure {
  if (result.ok) {
    return unavailable;
  }

  if (result.kind === 'validation') {
    return Object.freeze({
      ok: false as const,
      status: 422,
      code: 'identity.validation_failed',
      fieldErrors: fieldErrors(result.error.violations),
    });
  }

  if (result.kind === 'rejected') {
    const code = result.status === 409
      ? 'identity.email_unavailable'
      : result.status === 401
        ? 'authentication.invalid_credentials'
        : result.status === 403
          ? 'identity.registration_disabled'
          : 'identity.invalid_request';

    return Object.freeze({
      ok: false as const,
      status: result.status,
      code,
      fieldErrors: Object.freeze({}),
    });
  }

  if (result.kind === 'protocol') {
    return Object.freeze({
      ok: false as const,
      status: 400,
      code: 'identity.invalid_request',
      fieldErrors: Object.freeze({}),
    });
  }

  return unavailable;
}

function success(token: string): AuthResult {
  if (!/^[A-Za-z0-9_-]{43}$/.test(token)) {
    return unavailable;
  }

  return Object.freeze({ ok: true as const, sessionToken: token });
}

export async function registerIdentity(
  serverFetch: ServerFetch,
  input: RegisterInput,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<AuthResult> {
  const blackops = createServerBlackOpsClient(serverFetch, null, baseUrl);
  if (blackops === null) {
    return unavailable;
  }

  const result = await blackops.Register.fetch(input);

  return result.ok ? success(result.data.token) : failure(result);
}

export async function loginIdentity(
  serverFetch: ServerFetch,
  input: LoginInput,
  currentToken: string | null,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<AuthResult> {
  const blackops = createServerBlackOpsClient(serverFetch, null, baseUrl);
  if (blackops === null) {
    return unavailable;
  }

  const result = await blackops.Login.fetch({ ...input, currentToken });

  return result.ok ? success(result.data.token) : failure(result);
}

export async function logoutIdentity(
  serverFetch: ServerFetch,
  currentToken: string | null,
  baseUrl: string | undefined = env.BLACKOPS_BASE_URL,
): Promise<void> {
  if (currentToken === null) {
    return;
  }

  const blackops = createServerBlackOpsClient(serverFetch, currentToken, baseUrl);
  if (blackops === null) {
    return;
  }

  try {
    await blackops.Logout.fetch({ token: currentToken });
  } catch {
    // Browser session cleanup must not depend on backend availability.
  }
}

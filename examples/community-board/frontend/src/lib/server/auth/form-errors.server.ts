import type { AuthFailure } from './auth-client.server';

export type RegistrationFormValues = Readonly<{
  email: string;
  displayName: string;
}>;

export type LoginFormValues = Readonly<{
  email: string;
}>;

export function registrationFailureData(
  values: RegistrationFormValues,
  failure: AuthFailure,
) {
  return Object.freeze({
    success: false as const,
    code: failure.code,
    fieldErrors: failure.fieldErrors,
    values: Object.freeze({ email: values.email, displayName: values.displayName }),
  });
}

export function loginFailureData(values: LoginFormValues, failure: AuthFailure) {
  return Object.freeze({
    success: false as const,
    code: failure.code,
    fieldErrors: failure.fieldErrors,
    values: Object.freeze({ email: values.email }),
  });
}

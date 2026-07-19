import { describe, expect, it } from 'vitest';
import { loginFailureData, registrationFailureData } from './form-errors.server';

describe('form error projection', () => {
  it('returns only safe registration values and never a password', () => {
    const data = registrationFailureData(
      { email: 'person@example.com', displayName: 'Person' },
      {
        ok: false,
        status: 422,
        code: 'identity.validation_failed',
        fieldErrors: { password: 'identity.password.too_short' },
      },
    );

    expect(data.values).toEqual({ email: 'person@example.com', displayName: 'Person' });
    expect(data).not.toHaveProperty('password');
    expect(JSON.stringify(data)).not.toContain('password-marker');
  });

  it('returns only the email for login failure', () => {
    const data = loginFailureData(
      { email: 'person@example.com' },
      {
        ok: false,
        status: 401,
        code: 'authentication.invalid_credentials',
        fieldErrors: {},
      },
    );

    expect(data.values).toEqual({ email: 'person@example.com' });
    expect(Object.keys(data.values)).toEqual(['email']);
  });
});

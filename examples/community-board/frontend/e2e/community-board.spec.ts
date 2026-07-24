import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { access, mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

const baseURL = process.env.COMMUNITY_BOARD_BASE_URL ?? 'http://localhost:5173';
const syncDirectory = process.env.COMMUNITY_BOARD_SYNC_DIRECTORY;
const screenshotPath = resolve(
  process.cwd(),
  process.env.COMMUNITY_BOARD_SCREENSHOT_PATH ?? '../../../docs/guide/assets/community-board/blackops-board.png'
);

async function expectAccessible(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page }).analyze();
  const blocking = results.violations.filter(({ impact }) => impact === 'critical' || impact === 'serious');
  expect(blocking, blocking.map(({ id, help }) => `${id}: ${help}`).join('\n')).toEqual([]);
}

async function signal(name: string): Promise<void> {
  if (syncDirectory === undefined) throw new Error('COMMUNITY_BOARD_SYNC_DIRECTORY is required.');
  await writeFile(resolve(syncDirectory, name), 'ready\n', 'utf8');
}

async function waitForSignal(name: string): Promise<void> {
  if (syncDirectory === undefined) throw new Error('COMMUNITY_BOARD_SYNC_DIRECTORY is required.');
  for (let attempt = 0; attempt < 300; attempt += 1) {
    try { await access(resolve(syncDirectory, name)); return; } catch { await new Promise((resolvePromise) => setTimeout(resolvePromise, 100)); }
  }
  throw new Error(`Timed out waiting for ${name}`);
}

test('real browser journey remains accessible and credential-free', async ({ page }) => {
  const unique = `${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
  const email = `browser-${unique}@example.test`;
  const password = `browser-password-${unique}-long`;
  const title = `Browser journey ${unique}`;
  const editedTitle = `${title} edited`;
  const body = `A real browser created this post for the accessibility journey ${unique}.`;
  const comment = `Keyboard and form behavior verified ${unique}.`;
  const unexpectedOrigins = new Set<string>();

  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.protocol !== 'data:' && url.protocol !== 'blob:' && url.origin !== new URL(baseURL).origin) {
      unexpectedOrigins.add(url.origin);
    }
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('working board');
  await expect(page.getByRole('link', { name: 'Create an account' })).toBeInViewport();
  await expectAccessible(page);

  const lightCanvas = await page.locator('body').evaluate((element) => getComputedStyle(element).backgroundColor);
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.reload();
  const darkCanvas = await page.locator('body').evaluate((element) => getComputedStyle(element).backgroundColor);
  expect(darkCanvas).not.toBe(lightCanvas);
  await expectAccessible(page);

  await page.emulateMedia({ colorScheme: 'light', reducedMotion: 'reduce' });
  await page.reload();
  const animationDuration = await page.locator('main').evaluate((element) => getComputedStyle(element).animationDuration);
  expect(animationDuration).toMatch(/^(0\.01ms|1e-05s)$/);

  await page.setViewportSize({ width: 320, height: 720 });
  await page.reload();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  await expect(page.getByRole('navigation', { name: 'Primary' })).toBeInViewport();
  await expect(page.getByRole('link', { name: 'Create an account' })).toBeInViewport();

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.reload();
  await mkdir(dirname(screenshotPath), { recursive: true });
  await page.screenshot({ path: screenshotPath, fullPage: true });
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
  const focusOutline = await page.getByRole('link', { name: 'Skip to content' }).evaluate(
    (element) => getComputedStyle(element).outlineStyle
  );
  expect(focusOutline).not.toBe('none');

  await page.getByRole('link', { name: 'Create an account' }).click();
  await expect(page).toHaveURL(/\/register$/);
  await expectAccessible(page);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Display name').fill('Browser Operator');
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Register' }).click();
  await expect(page).toHaveURL(/\/me$/);
  await expect(page.getByTestId('current-user')).toContainText(email);

  await page.getByRole('button', { name: 'Log out' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page).toHaveURL(/\/me$/);

  await page.getByRole('link', { name: 'Posts', exact: true }).click();
  await expect(page).toHaveURL(/\/posts$/);
  await expectAccessible(page);
  await page.getByRole('link', { name: 'Write a post' }).first().click();
  await page.getByRole('button', { name: 'Publish post' }).click();
  await expect(page.getByRole('alert')).toHaveCount(3);
  await expect(page.getByLabel('Title')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByLabel('Title')).toHaveAttribute('aria-describedby', 'title-error');
  await page.getByLabel('Title').fill(title);
  await page.getByLabel('Body').fill(body);
  await page.getByRole('button', { name: 'Publish post' }).click();
  await expect(page).toHaveURL(/\/posts\/[0-9a-f-]{36}$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
  await expectAccessible(page);

  await page.getByLabel('Comment').fill(comment);
  await page.getByRole('button', { name: 'Add comment' }).click();
  await expect(page.getByText(comment)).toBeVisible();
  await page.getByRole('link', { name: 'Edit post' }).click();
  await page.getByLabel('Title').fill(editedTitle);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(editedTitle);
  await page.getByRole('link', { name: 'Posts', exact: true }).click();
  await expect(page.getByRole('link', { name: editedTitle })).toBeVisible();

  await page.getByRole('link', { name: 'Digests' }).click();
  await expectAccessible(page);
  const digestForm = page.locator('form.digest-form');
  const weekInput = page.getByLabel('ISO week');
  const idempotencyInput = digestForm.locator('input[name="idempotencyKey"]');
  const firstKey = await idempotencyInput.inputValue();
  expect(firstKey).toMatch(/^[0-9a-f]{48}$/);
  await digestForm.evaluate((form: HTMLFormElement) => { form.noValidate = true; });
  await weekInput.fill('invalid');
  await page.getByRole('button', { name: 'Generate digest' }).click();
  await expect(page.locator('#week-error')).toContainText('valid ISO week');
  await expect(weekInput).toHaveAttribute('aria-invalid', 'true');
  await expect(weekInput).toHaveAttribute('aria-describedby', /week-error/);
  await weekInput.fill('2026-W30');
  expect(await weekInput.evaluate((input: HTMLInputElement) => input.checkValidity())).toBe(true);
  const firstResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/digests') && response.request().method() === 'POST'
  ));
  await page.getByRole('button', { name: 'Generate digest' }).click();
  const firstResponse = await firstResponsePromise;
  expect(firstResponse.status()).toBe(303);
  expect(firstResponse.headers().location).toMatch(/^\/digests\/operations\/[0-9a-f-]{36}$/);
  if (page.url().endsWith('/digests')) {
    throw new Error(`Digest submission did not redirect: ${(await page.getByRole('alert').allInnerTexts()).join(' | ')}`);
  }
  await expect(page).toHaveURL(/\/digests\/operations\/[0-9a-f-]{36}$/);
  const firstOperationLocation = new URL(page.url()).pathname;
  await expect(page.locator('[data-state="accepted"]')).toBeVisible();
  await signal('digest-requested');
  await expect(page.getByRole('heading', { name: 'Retry scheduled' })).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('[data-state="retry_scheduled"]')).toBeVisible();
  await expectAccessible(page);
  await signal('retry-observed');
  await expect(page).toHaveURL(/\/digests\/[0-9a-f-]{36}$/, { timeout: 30_000 });
  await expect(page.getByText('Completed')).toBeVisible();
  await expectAccessible(page);
  const firstDigestLocation = new URL(page.url()).pathname;

  await page.goto('/digests');
  const replayForm = page.locator('form.digest-form');
  await replayForm.locator('input[name="idempotencyKey"]').evaluate((element, key) => {
    (element as HTMLInputElement).value = key;
  }, firstKey);
  await page.getByLabel('ISO week').fill('2026-W30');
  const replayResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/digests') && response.request().method() === 'POST'
  ));
  await page.getByRole('button', { name: 'Generate digest' }).click();
  const replayResponse = await replayResponsePromise;
  expect(replayResponse.status()).toBe(303);
  expect(replayResponse.headers().location).toBe(firstOperationLocation);
  await expect(page).toHaveURL(firstDigestLocation);
  await expect(page.getByText('Completed')).toBeVisible();

  const freshResponse = await page.goto('/digests');
  expect(freshResponse?.status()).toBe(200);
  const secondKey = await page.locator('form.digest-form input[name="idempotencyKey"]').inputValue();
  expect(secondKey).toMatch(/^[0-9a-f]{48}$/);
  expect(secondKey).not.toBe(firstKey);
  const secondResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/digests') && response.request().method() === 'POST'
  ));
  await page.getByLabel('ISO week').fill('2026-W30');
  await page.getByRole('button', { name: 'Generate digest' }).click();
  const secondResponse = await secondResponsePromise;
  expect(secondResponse.status()).toBe(303);
  const secondLocation = secondResponse.headers().location;
  expect(secondLocation).toMatch(/^\/digests\/operations\/[0-9a-f-]{36}$/);
  expect(secondLocation).not.toBe(firstOperationLocation);
  await expect(page).toHaveURL(/\/digests\/operations\/[0-9a-f-]{36}$/);
  await expect(page.locator('[data-state="accepted"]')).toBeVisible();
  await signal('digest-second-requested');
  await expect(page.getByRole('heading', { name: 'Retry scheduled' })).toBeVisible({ timeout: 30_000 });
  await signal('second-retry-observed');
  await expect(page).toHaveURL(/\/digests\/[0-9a-f-]{36}$/, { timeout: 30_000 });
  await expect(page.getByText('Completed')).toBeVisible();
  expect(new URL(page.url()).pathname).not.toBe(firstDigestLocation);
  await expectAccessible(page);

  await page.getByRole('link', { name: 'Posts', exact: true }).click();
  const renderedText = await page.locator('body').innerText();
  for (const forbidden of [password, 'community_board_session', 'http://http', '/workspace/', 'SQLSTATE']) {
    expect(renderedText).not.toContain(forbidden);
  }
  expect(unexpectedOrigins).toEqual(new Set());

  await page.getByRole('button', { name: 'Log out' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('link', { name: 'Register' })).toBeVisible();
});

test('independent Alice and Bob sessions deliver one notification after relay and duplicate delivery', async ({ page, browser }) => {
  const unique = `${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
  const password = `notification-password-${unique}-long`;
  const bob = await browser.newPage();
  const register = async (target: Page, name: string): Promise<void> => {
    await target.goto('/register');
    await target.getByLabel('Email').fill(`${name}-${unique}@example.test`);
    await target.getByLabel('Display name').fill(name);
    await target.getByLabel('Password').fill(password);
    await target.getByRole('button', { name: 'Register' }).click();
    await expect(target).toHaveURL(/\/me$/);
  };

  await register(page, 'Alice');
  await register(bob, 'Bob');
  await page.goto('/posts/new');
  await page.getByLabel('Title').fill(`Notification post ${unique}`);
  await page.getByLabel('Body').fill('Notification body');
  await page.getByRole('button', { name: 'Publish post' }).click();
  await expect(page).toHaveURL(/\/posts\/[0-9a-f-]{36}$/);
  const postUrl = page.url();
  await bob.goto(postUrl);
  await bob.getByLabel('Comment').fill('Bob notification comment');
  await bob.getByRole('button', { name: 'Add comment' }).click();
  await expect(bob).toHaveURL(postUrl);
  await expect(bob.getByText('Bob notification comment')).toBeVisible();
  await page.goto('/notifications');
  await expect(page.getByText('No notifications yet.')).toBeVisible();
  await signal('notification-requested');
  await waitForSignal('notification-delivery-complete');
  await expect.poll(async () => {
    await page.reload();
    return await page.getByText('Someone commented on your post.').count();
  }, { timeout: 30_000 }).toBe(1);
  await bob.goto('/notifications');
  await expect(bob.getByText('No notifications yet.')).toBeVisible();
  await signal('notification-replay-requested');
  await waitForSignal('notification-replayed');
  await expect.poll(async () => {
    await page.reload();
    return await page.getByText('Someone commented on your post.').count();
  }, { timeout: 30_000 }).toBe(1);
  await bob.close();
});

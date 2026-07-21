<script lang="ts">
  import AlertTriangle from 'reicon-svelte/icons/AlertTriangle';
  import Login from 'reicon-svelte/icons/Login';
  import type { ActionData } from './$types';

  let { form }: { form: ActionData } = $props();
</script>

<svelte:head>
  <title>Log in | BlackOps Board</title>
</svelte:head>

<main id="main-content" class="page page--reading auth-page">
  <div class="page-heading"><h1>Log in</h1><p>Continue to posts, comments, and weekly digests.</p></div>
  <section class="panel" aria-label="Login form">
    {#if form?.code && form.code !== 'identity.validation_failed'}
      <p class="notice notice--error" role="alert"><AlertTriangle size={20} weight="Outline" aria-hidden="true" /> Your email or password could not be verified.</p>
    {/if}
    <form class="form-stack" method="POST">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value={form?.values?.email ?? ''} aria-invalid={form?.fieldErrors?.email ? 'true' : undefined} aria-describedby={form?.fieldErrors?.email ? 'email-error' : undefined} required />
        {#if form?.fieldErrors?.email}<p id="email-error" class="field-error" role="alert">Enter a valid email address.</p>{/if}
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" minlength="12" maxlength="128" aria-invalid={form?.fieldErrors?.password ? 'true' : undefined} aria-describedby={form?.fieldErrors?.password ? 'password-helper password-error' : 'password-helper'} required />
        <p id="password-helper" class="helper">Use the 12 to 128 character password for your account.</p>
        {#if form?.fieldErrors?.password}<p id="password-error" class="field-error" role="alert">Enter a valid password.</p>{/if}
      </div>
      <button type="submit"><Login size={20} weight="Outline" aria-hidden="true" /> Log in</button>
    </form>
  </section>
</main>

<style>
  .auth-page { max-width: 36rem; }
</style>

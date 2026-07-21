<script lang="ts">
  import AlertTriangle from 'reicon-svelte/icons/AlertTriangle';
  import UserAdd from 'reicon-svelte/icons/UserAdd';
  import type { ActionData } from './$types';

  let { form }: { form: ActionData } = $props();
</script>

<svelte:head>
  <title>Register | BlackOps Board</title>
</svelte:head>

<main id="main-content" class="page page--reading auth-page">
  <div class="page-heading"><h1>Create your account</h1><p>Join the board with a private, server-managed session.</p></div>
  <section class="panel" aria-label="Registration form">
  {#if form?.code && form.code !== 'identity.validation_failed'}
    <p class="notice notice--error" role="alert"><AlertTriangle size={20} weight="Outline" aria-hidden="true" /> Your account could not be created.</p>
  {/if}
  <form class="form-stack" method="POST">
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" value={form?.values?.email ?? ''} aria-invalid={form?.fieldErrors?.email ? 'true' : undefined} aria-describedby={form?.fieldErrors?.email ? 'email-error' : undefined} required />{#if form?.fieldErrors?.email}<p id="email-error" class="field-error" role="alert">Enter a valid email address.</p>{/if}</div>
    <div class="field"><label for="display-name">Display name</label><input id="display-name" name="displayName" autocomplete="name" value={form?.values?.displayName ?? ''} aria-invalid={form?.fieldErrors?.displayName ? 'true' : undefined} aria-describedby={form?.fieldErrors?.displayName ? 'display-name-error' : undefined} required />{#if form?.fieldErrors?.displayName}<p id="display-name-error" class="field-error" role="alert">Enter a display name of at most 80 characters.</p>{/if}</div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" aria-invalid={form?.fieldErrors?.password ? 'true' : undefined} aria-describedby={form?.fieldErrors?.password ? 'password-helper password-error' : 'password-helper'} required /><p id="password-helper" class="helper">Use 12 to 128 characters. This value is never shown again.</p>{#if form?.fieldErrors?.password}<p id="password-error" class="field-error" role="alert">Use a password between 12 and 128 characters.</p>{/if}</div>
    <button type="submit"><UserAdd size={20} weight="Outline" aria-hidden="true" /> Register</button>
  </form>
  </section>
</main>

<style>
  .auth-page { max-width: 36rem; }
</style>

<script lang="ts">
  import type { ActionData } from './$types';

  let { form }: { form: ActionData } = $props();
</script>

<svelte:head>
  <title>Register — BlackOps Board</title>
</svelte:head>

<main>
  <h1>Create your account</h1>
  {#if form?.code && form.code !== 'identity.validation_failed'}
    <p role="alert">Your account could not be created.</p>
  {/if}
  <form method="POST">
    <label>
      Email
      <input name="email" type="email" autocomplete="email" value={form?.values?.email ?? ''} required />
    </label>
    {#if form?.fieldErrors?.email}<p role="alert">Enter a valid email address.</p>{/if}

    <label>
      Display name
      <input name="displayName" autocomplete="name" value={form?.values?.displayName ?? ''} required />
    </label>
    {#if form?.fieldErrors?.displayName}<p role="alert">Enter a display name of at most 80 characters.</p>{/if}

    <label>
      Password
      <input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required />
    </label>
    {#if form?.fieldErrors?.password}<p role="alert">Use a password between 12 and 128 characters.</p>{/if}

    <button type="submit">Register</button>
  </form>
</main>

<style>
  main { max-width: 32rem; margin: 0 auto; padding: 3rem 1.5rem; }
  form, label { display: grid; gap: 0.5rem; }
  form { gap: 1.25rem; }
  input, button { font: inherit; padding: 0.75rem; }
  p[role='alert'] { color: #9f1239; }
</style>

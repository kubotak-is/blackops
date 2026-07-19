<script lang="ts">
  import type { ActionData } from './$types';

  let { form }: { form: ActionData } = $props();
</script>

<svelte:head>
  <title>Log in — BlackOps Board</title>
</svelte:head>

<main>
  <h1>Log in</h1>
  {#if form?.code && form.code !== 'identity.validation_failed'}
    <p role="alert">Your email or password could not be verified.</p>
  {/if}
  <form method="POST">
    <label>
      Email
      <input name="email" type="email" autocomplete="email" value={form?.values?.email ?? ''} required />
    </label>
    {#if form?.fieldErrors?.email}<p role="alert">Enter a valid email address.</p>{/if}

    <label>
      Password
      <input name="password" type="password" autocomplete="current-password" minlength="12" maxlength="128" required />
    </label>
    {#if form?.fieldErrors?.password}<p role="alert">Enter a valid password.</p>{/if}

    <button type="submit">Log in</button>
  </form>
</main>

<style>
  main { max-width: 32rem; margin: 0 auto; padding: 3rem 1.5rem; }
  form, label { display: grid; gap: 0.5rem; }
  form { gap: 1.25rem; }
  input, button { font: inherit; padding: 0.75rem; }
  p[role='alert'] { color: #9f1239; }
</style>

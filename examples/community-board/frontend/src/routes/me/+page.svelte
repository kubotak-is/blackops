<script lang="ts">
  import type { PageData } from './$types';

  let { data }: { data: PageData } = $props();
</script>

<svelte:head>
  <title>Your account — BlackOps Board</title>
</svelte:head>

<main>
  <h1>Your account</h1>
  {#if data.currentUser}
    <dl data-testid="current-user">
      <dt>Display name</dt>
      <dd>{data.currentUser.displayName}</dd>
      <dt>Email</dt>
      <dd>{data.currentUser.email}</dd>
    </dl>
  {:else if data.identityAvailable}
    <p>You are not logged in. <a href="/login">Log in</a>.</p>
  {:else}
    <p role="status">Identity service is temporarily unavailable.</p>
  {/if}
</main>

<style>
  main { max-width: 48rem; margin: 0 auto; padding: 3rem 1.5rem; }
  dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.5rem 1rem; }
  dd { margin: 0; }
</style>

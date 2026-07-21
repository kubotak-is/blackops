<script lang="ts">
  import User from 'reicon-svelte/icons/User';
  import type { PageData } from './$types';

  let { data }: { data: PageData } = $props();
</script>

<svelte:head>
  <title>Your account | BlackOps Board</title>
</svelte:head>

<main id="main-content" class="page page--reading">
  <div class="page-heading"><h1>Your account</h1><p>Your current server-managed identity.</p></div>
  {#if data.currentUser}
    <div class="panel account"><User size={24} weight="Outline" aria-hidden="true" /><dl data-testid="current-user">
      <dt>Display name</dt>
      <dd>{data.currentUser.displayName}</dd>
      <dt>Email</dt>
      <dd>{data.currentUser.email}</dd>
    </dl></div>
  {:else if data.identityAvailable}
    <p>You are not logged in. <a href="/login">Log in</a>.</p>
  {:else}
    <p role="status">Identity service is temporarily unavailable.</p>
  {/if}
</main>

<style>
  .account { display: grid; grid-template-columns: auto 1fr; gap: 1.25rem; }
  .account :global(svg) { color: var(--accent); }
  dl { display: grid; grid-template-columns: minmax(7rem, max-content) 1fr; gap: 0.75rem 1.25rem; margin: 0; }
  dt { color: var(--text-muted); font-weight: 600; }
  dd { margin: 0; }
  @media (max-width: 35rem) { dl { grid-template-columns: 1fr; gap: 0.2rem; } dd + dt { margin-top: 0.9rem; } }
</style>

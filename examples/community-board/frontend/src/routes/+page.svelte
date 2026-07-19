<script lang="ts">
  import type { PageData } from './$types';

  let { data }: { data: PageData } = $props();
</script>

<svelte:head>
  <title>BlackOps Board</title>
  <meta
    name="description"
    content="A full-stack reference application for the BlackOps PHP Framework."
  />
</svelte:head>

<main>
  <p>BlackOps PHP Framework reference application</p>
  <h1>BlackOps Board</h1>

  {#if data.welcome.available}
    <p data-testid="welcome-message">{data.welcome.message}</p>
    <p>{data.welcome.summary}</p>
  {:else}
    <p role="status" data-testid="welcome-unavailable">{data.welcome.message}</p>
  {/if}

  <p>Identity, posts, comments, and deferred digests arrive in later foundation phases.</p>
</main>

<style>
  :global(body) {
    margin: 0;
    color: #171717;
    background: #fafafa;
    font-family: system-ui, sans-serif;
  }

  main {
    max-width: 48rem;
    margin: 0 auto;
    padding: 4rem 1.5rem;
  }

  h1 {
    margin-block: 0.5rem 1.5rem;
    font-size: clamp(2rem, 8vw, 4rem);
  }

  p {
    line-height: 1.6;
  }
</style>

<script lang="ts">
  import ArrowRight from 'reicon-svelte/icons/ArrowRight';
  import Calendar from 'reicon-svelte/icons/Calendar';
  import Chat from 'reicon-svelte/icons/Chat';
  import FileText from 'reicon-svelte/icons/FileText';
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

<main id="main-content" class="page landing">
  <section class="hero">
    <div class="hero-copy">
      <p class="eyebrow">BlackOps PHP reference application</p>
      <h1>A working board for real operation flows.</h1>
      <p class="intro">Write, discuss, and generate deferred weekly digests through one server-only BFF.</p>
      <div class="actions">
        {#if data.currentUser}
          <a class="button" href="/posts">Browse posts <ArrowRight size={20} weight="Outline" aria-hidden="true" /></a>
          <a class="button button--secondary" href="/posts/new">Write a post</a>
        {:else}
          <a class="button" href="/register">Create an account <ArrowRight size={20} weight="Outline" aria-hidden="true" /></a>
          <a class="button button--secondary" href="/login">Log in</a>
        {/if}
      </div>
    </div>
    <aside class="runtime" aria-label="Reference application capabilities">
      <div><FileText size={24} weight="Outline" aria-hidden="true" /><strong>Post</strong><span>Validated inline operations</span></div>
      <div><Chat size={24} weight="Outline" aria-hidden="true" /><strong>Comment</strong><span>Authenticated conversation</span></div>
      <div><Calendar size={24} weight="Outline" aria-hidden="true" /><strong>Digest</strong><span>Deferred retry and outcome</span></div>
    </aside>
  </section>

  <section class="context" aria-label="Application status">
    {#if data.welcome.available}
      <div><p class="metadata">Backend</p><p data-testid="welcome-message">{data.welcome.message}</p><p>{data.welcome.summary}</p></div>
    {:else}
      <p class="notice" role="status" data-testid="welcome-unavailable">{data.welcome.message}</p>
    {/if}
    {#if data.currentUser}
      <div><p class="metadata">Current session</p><p data-testid="current-user">Signed in as {data.currentUser.displayName} ({data.currentUser.email})</p></div>
    {:else if !data.identityAvailable}
      <p class="notice" role="status">Identity service is temporarily unavailable.</p>
    {:else}
      <div><p class="metadata">Identity</p><p>Register or log in to use the board.</p></div>
    {/if}
  </section>
</main>

<style>
  .landing { min-height: calc(100dvh - 4.5rem); }
  .hero { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(18rem, 0.7fr); gap: clamp(2rem, 7vw, 6rem); align-items: center; min-height: 31rem; }
  .hero-copy { max-width: 44rem; }
  .intro { max-width: 20ch; color: var(--text-muted); font-size: clamp(1.2rem, 2.4vw, 1.55rem); line-height: 1.45; }
  .runtime { display: grid; gap: 0; border: 1px solid var(--border); border-radius: var(--panel-radius); background: var(--surface); box-shadow: var(--shadow); }
  .runtime div { display: grid; grid-template-columns: 2rem 1fr; gap: 0.15rem 0.7rem; padding: 1.25rem; }
  .runtime div + div { border-top: 1px solid var(--border); }
  .runtime :global(svg) { grid-row: 1 / 3; color: var(--accent); }
  .runtime span { color: var(--text-muted); font-size: 0.9rem; }
  .context { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2rem; border-top: 1px solid var(--border); padding-top: 2rem; }
  .context p:last-child { margin-bottom: 0; }
  @media (max-width: 47.99rem) {
    .landing { min-height: auto; }
    .hero { grid-template-columns: 1fr; min-height: auto; }
    .context { grid-template-columns: 1fr; }
  }
</style>

<script lang="ts">
  import { onMount } from 'svelte';
  import StatusIcon from '$lib/components/StatusIcon.svelte';
  import Refresh from 'reicon-svelte/icons/Refresh';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  onMount(() => {
    if (data.status.state === 'failed') return;
    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      try {
        const response = await fetch(`${window.location.pathname}/wait`, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        });
        if (!response.ok) return;
        const status: unknown = await response.json();
        if (
          typeof status === 'object' && status !== null &&
          'state' in status && status.state === 'completed' &&
          'digestId' in status && typeof status.digestId === 'string'
        ) {
          window.location.assign(`/digests/${encodeURIComponent(status.digestId)}`);
        } else {
          window.location.reload();
        }
      } catch {
        // Explicit refresh remains available when polling is interrupted.
      }
    }, data.status.state === 'retry_scheduled' ? data.status.retryAfterSeconds * 1000 : 0);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  });
</script>

<svelte:head><title>Digest progress | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <div class="page-heading"><h1>Digest progress</h1><p>Worker state is updated through the same-origin BFF.</p></div>
  <section class:pending={data.status.state !== 'failed'} class="panel progress" role="status" aria-live="polite" data-state={data.status.state}>
    <StatusIcon state={data.status.state} />
    <div><h2>{data.status.state === 'retry_scheduled' ? 'Retry scheduled' : data.status.state === 'failed' ? 'Generation failed' : data.status.state === 'running' ? 'Generating digest' : 'Digest accepted'}</h2>
    {#if data.status.state === 'accepted'}
      <p>Your digest is waiting for a worker.</p>
    {:else if data.status.state === 'running'}
      <p>Your digest is being generated.</p>
    {:else if data.status.state === 'retry_scheduled'}
      <p>Generation will retry shortly.</p>
    {:else if data.status.state === 'failed'}
      <p>{data.status.message}</p>
    {/if}
    </div>
  </section>
  <div class="actions progress-actions"><a class="button button--secondary" href={`/digests/operations/${encodeURIComponent(data.status.operationId)}`}><Refresh size={20} weight="Outline" aria-hidden="true" /> Refresh status</a><a href="/digests">Back to digest form</a></div>
</main>

<style>
  .progress { display: grid; grid-template-columns: auto 1fr; gap: 1rem; border-left: 4px solid var(--accent); }
  .progress h2 { margin-bottom: 0.5rem; }
  .progress p { margin-bottom: 0; color: var(--text-muted); }
  .progress-actions { margin-top: 1.5rem; }
  @media (prefers-reduced-motion: no-preference) { .progress.pending :global(svg) { animation: status-turn 1.8s ease-in-out infinite; } @keyframes status-turn { 50% { transform: rotate(18deg); } } }
</style>

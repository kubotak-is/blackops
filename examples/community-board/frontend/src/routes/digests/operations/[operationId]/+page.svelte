<script lang="ts">
  import { onMount } from 'svelte';
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

<main>
  <h1>Digest progress</h1>
  <section role="status" aria-live="polite" data-state={data.status.state}>
    {#if data.status.state === 'accepted'}
      <p>Your digest is waiting for a worker.</p>
    {:else if data.status.state === 'running'}
      <p>Your digest is being generated.</p>
    {:else if data.status.state === 'retry_scheduled'}
      <p>Generation will retry shortly.</p>
    {:else if data.status.state === 'failed'}
      <p>{data.status.message}</p>
    {/if}
  </section>
  <p><a href={`/digests/operations/${encodeURIComponent(data.status.operationId)}`}>Refresh status</a></p>
  <p><a href="/digests">Back to digest form</a></p>
</main>

<style>main { max-width: 42rem; margin: 0 auto; padding: 2rem 1.5rem; }</style>

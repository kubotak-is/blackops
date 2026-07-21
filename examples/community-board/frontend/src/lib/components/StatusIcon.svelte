<script lang="ts">
  import AlertTriangle from 'reicon-svelte/icons/AlertTriangle';
  import CheckCircle from 'reicon-svelte/icons/CheckCircle';
  import Clock from 'reicon-svelte/icons/Clock';
  import Refresh from 'reicon-svelte/icons/Refresh';

  let { state, size = 24 }: { state: string; size?: number } = $props();
</script>

<span aria-hidden="true">
  {#if state === 'completed'}
    <CheckCircle {size} weight="Outline" />
  {:else if state === 'retry_scheduled'}
    <Refresh {size} weight="Outline" />
  {:else if state === 'failed'}
    <AlertTriangle {size} weight="Outline" />
  {:else}
    <Clock {size} weight="Outline" />
  {/if}
</span>

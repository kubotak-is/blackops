<script lang="ts">
import Bell from 'reicon-svelte/icons/Bell';
import type { PageProps } from './$types';

let { data }: PageProps = $props();
</script>

<svelte:head><title>Notifications | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <div class="page-heading"><h1>Notifications</h1><p>Updates about activity on posts you own.</p></div>
  {#if !data.ok}
    <p class="notice notice--error" role="alert">{data.message}</p>
  {:else if data.notifications.length === 0}
    <section class="panel empty-state"><Bell size={28} weight="Outline" aria-hidden="true" /><p>No notifications yet.</p></section>
  {:else}
    <ul class="notification-list">
      {#each data.notifications as notification}
        <li class="panel notification-item">
          <Bell size={22} weight="Outline" aria-hidden="true" />
          <div><p>{notification.message}</p><time datetime={notification.createdAt}>{notification.createdAt}</time></div>
        </li>
      {/each}
    </ul>
  {/if}
</main>

<style>
  .notification-list { display: grid; gap: 0.75rem; padding: 0; list-style: none; }
  .notification-item { display: flex; gap: 0.75rem; align-items: flex-start; }
  .notification-item p { margin: 0; font-weight: 650; }
  .notification-item time { color: var(--text-muted); font-size: 0.875rem; }
  .empty-state { display: grid; justify-items: center; gap: 0.5rem; max-width: 34rem; }
</style>

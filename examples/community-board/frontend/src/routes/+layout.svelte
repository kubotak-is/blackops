<script lang="ts">
  import type { LayoutProps } from './$types';

  let { data, children }: LayoutProps = $props();
</script>

<header>
  <a href="/">BlackOps Board</a>
  <nav aria-label="Account">
    {#if data.currentUser}
      <a href="/posts">Posts</a>
      <a href="/digests">Digests</a>
      <a href="/me">{data.currentUser.displayName}</a>
      <form method="POST" action="/logout">
        <button type="submit">Log out</button>
      </form>
    {:else}
      <a href="/register">Register</a>
      <a href="/login">Log in</a>
    {/if}
  </nav>
</header>

{@render children()}

<style>
  header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    max-width: 48rem;
    margin: 0 auto;
    padding: 1rem 1.5rem;
  }

  nav,
  form {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0;
  }
</style>

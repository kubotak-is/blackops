<script lang="ts">
  import { page } from '$app/state';
  import BrandMark from '$lib/components/BrandMark.svelte';
  import '$lib/styles/global.css';
  import Calendar from 'reicon-svelte/icons/Calendar';
  import Bell from 'reicon-svelte/icons/Bell';
  import FileText from 'reicon-svelte/icons/FileText';
  import Login from 'reicon-svelte/icons/Login';
  import Logout from 'reicon-svelte/icons/Logout';
  import User from 'reicon-svelte/icons/User';
  import UserAdd from 'reicon-svelte/icons/UserAdd';
  import type { LayoutProps } from './$types';

  let { data, children }: LayoutProps = $props();
</script>

<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
  <div class="shell">
    <a class="brand" href="/" aria-label="BlackOps Board home"><BrandMark /> <span>BlackOps Board</span></a>
    <nav aria-label="Primary">
    {#if data.currentUser}
      <a href="/posts" aria-current={page.url.pathname.startsWith('/posts') ? 'page' : undefined}><FileText size={20} weight="Outline" aria-hidden="true" /> <span>Posts</span></a>
      <a href="/digests" aria-current={page.url.pathname.startsWith('/digests') ? 'page' : undefined}><Calendar size={20} weight="Outline" aria-hidden="true" /> <span>Digests</span></a>
      <a href="/notifications" aria-current={page.url.pathname.startsWith('/notifications') ? 'page' : undefined}><Bell size={20} weight="Outline" aria-hidden="true" /> <span>Notifications</span></a>
      <a href="/me" aria-current={page.url.pathname === '/me' ? 'page' : undefined}><User size={20} weight="Outline" aria-hidden="true" /> <span>{data.currentUser.displayName}</span></a>
      <form method="POST" action="/logout">
        <button class="nav-action" type="submit"><Logout size={20} weight="Outline" aria-hidden="true" /> <span>Log out</span></button>
      </form>
    {:else}
      <a href="/register" aria-current={page.url.pathname === '/register' ? 'page' : undefined}><UserAdd size={20} weight="Outline" aria-hidden="true" /> <span>Register</span></a>
      <a href="/login" aria-current={page.url.pathname === '/login' ? 'page' : undefined}><Login size={20} weight="Outline" aria-hidden="true" /> <span>Log in</span></a>
    {/if}
    </nav>
  </div>
</header>

{@render children()}

<style>
  .skip-link {
    position: fixed;
    top: 0.5rem;
    left: 0.5rem;
    z-index: 2;
    transform: translateY(-150%);
    border-radius: var(--control-radius);
    padding: 0.65rem 0.8rem;
    color: var(--on-accent);
    background: var(--accent);
  }
  .skip-link:focus { transform: translateY(0); }
  .site-header { border-bottom: 1px solid var(--border); background: var(--surface); }
  .shell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: min(100% - 3rem, 74rem);
    min-height: 4.5rem;
    margin: 0 auto;
  }
  .brand, nav, nav a, nav form, .nav-action {
    display: flex;
    align-items: center;
  }
  .brand { gap: 0.65rem; color: var(--text); font-weight: 720; text-decoration: none; }
  nav { gap: 0.3rem; }
  nav a, .nav-action {
    min-height: 2.75rem;
    gap: 0.4rem;
    border: 0;
    border-radius: var(--control-radius);
    padding: 0.6rem 0.7rem;
    color: var(--text-muted);
    background: transparent;
    font-weight: 600;
    text-decoration: none;
  }
  nav a:hover, .nav-action:hover, nav a[aria-current='page'] {
    color: var(--text);
    background: var(--surface-muted);
  }
  nav a[aria-current='page'] { box-shadow: inset 0 -2px 0 var(--accent); }
  nav form {
    margin: 0;
  }
  @media (max-width: 47.99rem) {
    .shell { width: min(100% - 2rem, 74rem); min-height: auto; padding: 0.75rem 0; align-items: flex-start; }
    .brand > span:last-child { display: none; }
    nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: min(100%, 18rem); }
    nav a, .nav-action { justify-content: flex-start; }
  }
</style>

<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import ArrowLeft from 'reicon-svelte/icons/ArrowLeft';
  import ArrowRight from 'reicon-svelte/icons/ArrowRight';
  import Chat from 'reicon-svelte/icons/Chat';
  import PenAdd from 'reicon-svelte/icons/PenAdd';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();
</script>

<svelte:head><title>Posts | BlackOps Board</title></svelte:head>

<main id="main-content" class="page">
  <header class="feed-heading">
    <div>
      <h1>Posts</h1>
      <p class="metadata">{data.feed.total} posts</p>
    </div>
    <a class="button" href="/posts/new"><PenAdd size={20} weight="Outline" aria-hidden="true" /> Write a post</a>
  </header>

  {#if data.feed.posts.length === 0}
    <div class="empty-state"><Chat size={24} weight="Outline" aria-hidden="true" /><h2>No posts yet. Start the conversation.</h2><a class="button button--secondary" href="/posts/new">Write a post</a></div>
  {:else}
    <ol>
      {#each data.feed.posts as post (post.id)}
        <li>
          <article>
            <h2><a href={`/posts/${post.id}`}>{post.title}</a></h2>
            <p>{post.bodyPreview}</p>
            <p class="post-meta"><span>By {post.authorDisplayName}</span><time datetime={post.createdAt}>{formatDate(post.createdAt)}</time><span>{post.commentCount} comments</span></p>
          </article>
        </li>
      {/each}
    </ol>
  {/if}

  <nav class="pagination" aria-label="Pagination">
    {#if data.feed.hasPrevious}
      <a class="button button--secondary" href={`/posts?page=${data.feed.page - 1}`}><ArrowLeft size={20} weight="Outline" aria-hidden="true" /> Previous</a>
    {/if}
    <span>Page {data.feed.page}</span>
    {#if data.feed.hasNext}
      <a class="button button--secondary" href={`/posts?page=${data.feed.page + 1}`}>Next <ArrowRight size={20} weight="Outline" aria-hidden="true" /></a>
    {/if}
  </nav>
</main>

<style>
  .feed-heading, .pagination { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
  ol { margin: 0 0 2.5rem; padding: 0; list-style: none; }
  li { border-top: 1px solid var(--border); padding: 1.5rem 0; }
  article { max-width: 68ch; }
  article h2 { margin-bottom: 0.6rem; }
  article h2 a { color: var(--text); }
  .post-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.8rem; }
  .pagination span { font-family: var(--font-mono); }
  @media (max-width: 35rem) { .feed-heading { align-items: flex-start; flex-direction: column; } .pagination { display: grid; grid-template-columns: 1fr auto 1fr; } .pagination a:last-child { grid-column: 3; } }
</style>

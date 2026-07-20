<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();
</script>

<svelte:head><title>Posts | BlackOps Board</title></svelte:head>

<main>
  <header>
    <div>
      <h1>Posts</h1>
      <p>{data.feed.total} posts</p>
    </div>
    <a href="/posts/new">Write a post</a>
  </header>

  {#if data.feed.posts.length === 0}
    <p>No posts yet. Start the conversation.</p>
  {:else}
    <ol>
      {#each data.feed.posts as post (post.id)}
        <li>
          <article>
            <h2><a href={`/posts/${post.id}`}>{post.title}</a></h2>
            <p>{post.bodyPreview}</p>
            <p>
              By {post.authorDisplayName} · <time datetime={post.createdAt}>{formatDate(post.createdAt)}</time>
              · {post.commentCount} comments
            </p>
          </article>
        </li>
      {/each}
    </ol>
  {/if}

  <nav aria-label="Pagination">
    {#if data.feed.hasPrevious}
      <a href={`/posts?page=${data.feed.page - 1}`}>Previous</a>
    {/if}
    <span>Page {data.feed.page}</span>
    {#if data.feed.hasNext}
      <a href={`/posts?page=${data.feed.page + 1}`}>Next</a>
    {/if}
  </nav>
</main>

<style>
  main { max-width: 48rem; margin: 0 auto; padding: 2rem 1.5rem; }
  header, nav { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
  ol { padding: 0; list-style: none; }
  li { border-top: 1px solid #ccc; padding: 1rem 0; }
</style>

<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import type { PageProps } from './$types';

  let { data, form }: PageProps = $props();
</script>

<svelte:head><title>{data.post.title} | BlackOps Board</title></svelte:head>

<main>
  <p><a href="/posts">Back to posts</a></p>
  <article>
    <h1>{data.post.title}</h1>
    <p>By {data.post.authorDisplayName} · <time datetime={data.post.createdAt}>{formatDate(data.post.createdAt)}</time></p>
    <p class="post-body">{data.post.body}</p>
  </article>

  {#if data.post.owner}
    <section aria-label="Post actions">
      <a href={`/posts/${data.post.id}/edit`}>Edit post</a>
      <form method="POST" action="?/delete">
        <button type="submit">Delete post</button>
      </form>
    </section>
  {/if}

  {#if form?.message}<p role="alert">{form.message}</p>{/if}

  <section>
    <h2>Comments</h2>
    {#if data.post.comments.length === 0}
      <p>No comments yet.</p>
    {:else}
      <ol>
        {#each data.post.comments as comment (comment.id)}
          <li>
            <p>{comment.body}</p>
            <p>By {comment.authorDisplayName} · <time datetime={comment.createdAt}>{formatDate(comment.createdAt)}</time></p>
          </li>
        {/each}
      </ol>
    {/if}

    <h2>Add a comment</h2>
    <form method="POST" action="?/comment">
      <label for="comment-body">Comment</label>
      <textarea
        id="comment-body"
        name="body"
        rows="5"
        aria-invalid={form?.fieldErrors?.body ? 'true' : undefined}
        aria-describedby={form?.fieldErrors?.body ? 'comment-body-error' : undefined}
      >{form?.values?.body ?? ''}</textarea>
      {#if form?.fieldErrors?.body}<p id="comment-body-error">{form.fieldErrors.body}</p>{/if}
      <button type="submit">Add comment</button>
    </form>
  </section>
</main>

<style>
  main { max-width: 48rem; margin: 0 auto; padding: 2rem 1.5rem; }
  .post-body { white-space: pre-wrap; }
  section[aria-label='Post actions'] { display: flex; align-items: center; gap: 1rem; }
  section[aria-label='Post actions'] form { margin: 0; }
  ol { padding-left: 1.5rem; }
  textarea { display: block; width: 100%; margin: .5rem 0; font: inherit; }
</style>

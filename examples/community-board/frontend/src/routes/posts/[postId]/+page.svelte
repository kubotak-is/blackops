<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import ArrowLeft from 'reicon-svelte/icons/ArrowLeft';
  import ChatPlus from 'reicon-svelte/icons/ChatPlus';
  import Edit from 'reicon-svelte/icons/Edit';
  import Trash from 'reicon-svelte/icons/Trash';
  import type { PageProps } from './$types';

  let { data, form }: PageProps = $props();
</script>

<svelte:head><title>{data.post.title} | BlackOps Board</title></svelte:head>

<main id="main-content" class="page">
  <a class="back-link" href="/posts"><ArrowLeft size={20} weight="Outline" aria-hidden="true" /> Back to posts</a>
  <article class="post reading-column">
    <h1>{data.post.title}</h1>
    <p class="metadata">By {data.post.authorDisplayName}<br /><time datetime={data.post.createdAt}>{formatDate(data.post.createdAt)}</time></p>
    <p class="post-body">{data.post.body}</p>
  </article>

  {#if data.post.owner}
    <section class="owner-actions" aria-label="Post actions">
      <a class="button button--secondary" href={`/posts/${data.post.id}/edit`}><Edit size={20} weight="Outline" aria-hidden="true" /> Edit post</a>
      <form method="POST" action="?/delete">
        <button class="button--danger" type="submit"><Trash size={20} weight="Outline" aria-hidden="true" /> Delete post</button>
      </form>
    </section>
  {/if}

  {#if form?.message}<p class="notice notice--error" role="alert">{form.message}</p>{/if}

  <section class="comments reading-column">
    <h2>Comments</h2>
    {#if data.post.comments.length === 0}
      <p class="helper">No comments yet.</p>
    {:else}
      <ol>
        {#each data.post.comments as comment (comment.id)}
          <li>
            <p>{comment.body}</p>
            <p class="metadata">By {comment.authorDisplayName}<br /><time datetime={comment.createdAt}>{formatDate(comment.createdAt)}</time></p>
          </li>
        {/each}
      </ol>
    {/if}

    <div class="comment-composer panel"><h2>Add a comment</h2>
    <form class="form-stack" method="POST" action="?/comment">
      <div class="field"><label for="comment-body">Comment</label>
      <textarea
        id="comment-body"
        name="body"
        rows="5"
        aria-invalid={form?.fieldErrors?.body ? 'true' : undefined}
        aria-describedby={form?.fieldErrors?.body ? 'comment-body-error' : undefined}
      >{form?.values?.body ?? ''}</textarea>
      {#if form?.fieldErrors?.body}<p id="comment-body-error" class="field-error" role="alert">{form.fieldErrors.body}</p>{/if}</div>
      <button type="submit"><ChatPlus size={20} weight="Outline" aria-hidden="true" /> Add comment</button>
    </form></div>
  </section>
</main>

<style>
  .reading-column { max-width: 68ch; }
  .post { margin-bottom: 2rem; }
  .post-body { white-space: pre-wrap; }
  .owner-actions { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 3rem; }
  .owner-actions form { margin: 0; }
  .comments { border-top: 1px solid var(--border); padding-top: 2rem; }
  ol { margin: 0 0 3rem; padding: 0; list-style: none; }
  li { padding: 1rem 0; }
  li + li { border-top: 1px solid var(--border); }
  li > p:first-child { font-size: 1.05rem; }
  .comment-composer { margin-top: 2rem; }
  @media (max-width: 35rem) { .owner-actions { align-items: stretch; flex-direction: column; } .owner-actions .button, .owner-actions button { width: 100%; } }
</style>

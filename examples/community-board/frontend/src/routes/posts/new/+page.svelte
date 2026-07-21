<script lang="ts">
  import ArrowLeft from 'reicon-svelte/icons/ArrowLeft';
  import PenAdd from 'reicon-svelte/icons/PenAdd';
  import type { PageProps } from './$types';

  let { form }: PageProps = $props();
</script>

<svelte:head><title>New post | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <a class="back-link" href="/posts"><ArrowLeft size={20} weight="Outline" aria-hidden="true" /> Back to posts</a>
  <div class="page-heading"><h1>Write a post</h1><p>Share a clear title and the full context with the board.</p></div>

  {#if form?.message}<p class="notice notice--error" role="alert">{form.message}</p>{/if}
  <form class="form-stack panel" method="POST">
    <div class="field"><label for="title">Title</label>
    <input
      id="title"
      name="title"
      value={form?.values?.title ?? ''}
      aria-invalid={form?.fieldErrors?.title ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.title ? 'title-error' : undefined}
    />
    {#if form?.fieldErrors?.title}<p id="title-error" class="field-error" role="alert">{form.fieldErrors.title}</p>{/if}</div>

    <div class="field"><label for="body">Body</label>
    <textarea
      id="body"
      name="body"
      rows="10"
      aria-invalid={form?.fieldErrors?.body ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.body ? 'body-error' : undefined}
    >{form?.values?.body ?? ''}</textarea>
    {#if form?.fieldErrors?.body}<p id="body-error" class="field-error" role="alert">{form.fieldErrors.body}</p>{/if}</div>

    <button type="submit"><PenAdd size={20} weight="Outline" aria-hidden="true" /> Publish post</button>
  </form>
</main>

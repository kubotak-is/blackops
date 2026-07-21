<script lang="ts">
  import ArrowLeft from 'reicon-svelte/icons/ArrowLeft';
  import Edit from 'reicon-svelte/icons/Edit';
  import type { PageProps } from './$types';

  let { data, form }: PageProps = $props();
</script>

<svelte:head><title>Edit {data.post.title} | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <a class="back-link" href={`/posts/${data.post.postId}`}><ArrowLeft size={20} weight="Outline" aria-hidden="true" /> Back to post</a>
  <div class="page-heading"><h1>Edit post</h1><p>Update the published title or body.</p></div>
  {#if form?.message}<p class="notice notice--error" role="alert">{form.message}</p>{/if}

  <form class="form-stack panel" method="POST">
    <div class="field"><label for="title">Title</label>
    <input
      id="title"
      name="title"
      value={form?.values?.title ?? data.post.title}
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
    >{form?.values?.body ?? data.post.body}</textarea>
    {#if form?.fieldErrors?.body}<p id="body-error" class="field-error" role="alert">{form.fieldErrors.body}</p>{/if}</div>

    <button type="submit"><Edit size={20} weight="Outline" aria-hidden="true" /> Save changes</button>
  </form>
</main>

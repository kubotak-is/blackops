<script lang="ts">
  import type { PageProps } from './$types';

  let { form }: PageProps = $props();
</script>

<svelte:head><title>New post | BlackOps Board</title></svelte:head>

<main>
  <h1>Write a post</h1>
  <p><a href="/posts">Back to posts</a></p>

  {#if form?.message}<p role="alert">{form.message}</p>{/if}
  <form method="POST">
    <label for="title">Title</label>
    <input
      id="title"
      name="title"
      value={form?.values?.title ?? ''}
      aria-invalid={form?.fieldErrors?.title ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.title ? 'title-error' : undefined}
    />
    {#if form?.fieldErrors?.title}<p id="title-error">{form.fieldErrors.title}</p>{/if}

    <label for="body">Body</label>
    <textarea
      id="body"
      name="body"
      rows="10"
      aria-invalid={form?.fieldErrors?.body ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.body ? 'body-error' : undefined}
    >{form?.values?.body ?? ''}</textarea>
    {#if form?.fieldErrors?.body}<p id="body-error">{form.fieldErrors.body}</p>{/if}

    <button type="submit">Publish post</button>
  </form>
</main>

<style>
  main { max-width: 42rem; margin: 0 auto; padding: 2rem 1.5rem; }
  form { display: grid; gap: .75rem; }
  input, textarea { font: inherit; padding: .6rem; }
</style>

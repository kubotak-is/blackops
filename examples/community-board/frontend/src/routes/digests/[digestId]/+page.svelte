<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();
</script>

<svelte:head><title>{data.digest.week} digest | BlackOps Board</title></svelte:head>

<main>
  <h1>{data.digest.week} digest</h1>
  <p>{data.digest.content}</p>
  <dl>
    <dt>Posts</dt><dd>{data.digest.postCount}</dd>
    <dt>Comments</dt><dd>{data.digest.commentCount}</dd>
    <dt>Generated</dt><dd><time datetime={data.digest.createdAt}>{formatDate(data.digest.createdAt)}</time></dd>
  </dl>
  <p><a href="/digests">Generate another digest</a></p>
</main>

<style>
  main { max-width: 42rem; margin: 0 auto; padding: 2rem 1.5rem; }
  dl { display: grid; grid-template-columns: max-content 1fr; gap: .5rem 1rem; }
  dd { margin: 0; }
</style>

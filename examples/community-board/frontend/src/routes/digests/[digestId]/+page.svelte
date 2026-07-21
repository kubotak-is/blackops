<script lang="ts">
  import { formatDate } from '$lib/presentation';
  import Calendar from 'reicon-svelte/icons/Calendar';
  import Chat from 'reicon-svelte/icons/Chat';
  import CheckCircle from 'reicon-svelte/icons/CheckCircle';
  import FileText from 'reicon-svelte/icons/FileText';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();
</script>

<svelte:head><title>{data.digest.week} digest | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <div class="complete-label"><CheckCircle size={24} weight="Outline" aria-hidden="true" /><span>Completed</span></div>
  <h1>{data.digest.week} digest</h1>
  <p class="digest-content">{data.digest.content}</p>
  <dl class="panel">
    <dt><FileText size={20} weight="Outline" aria-hidden="true" /> Posts</dt><dd>{data.digest.postCount}</dd>
    <dt><Chat size={20} weight="Outline" aria-hidden="true" /> Comments</dt><dd>{data.digest.commentCount}</dd>
    <dt><Calendar size={20} weight="Outline" aria-hidden="true" /> Generated</dt><dd><time datetime={data.digest.createdAt}>{formatDate(data.digest.createdAt)}</time></dd>
  </dl>
  <p><a href="/digests">Generate another digest</a></p>
</main>

<style>
  .complete-label { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--accent); font-weight: 650; }
  .digest-content { margin-bottom: 2rem; font-size: 1.25rem; }
  dl { display: grid; grid-template-columns: max-content 1fr; gap: .5rem 1rem; }
  dt { display: flex; align-items: center; gap: 0.45rem; color: var(--text-muted); }
  dd { margin: 0; }
  @media (max-width: 35rem) { dl { grid-template-columns: 1fr; } dd + dt { margin-top: 0.75rem; } }
</style>

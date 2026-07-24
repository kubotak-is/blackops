<script lang="ts">
  import Calendar from 'reicon-svelte/icons/Calendar';
  import type { PageProps } from './$types';

  let { data, form }: PageProps = $props();
  const isoWeekPattern = String.raw`[0-9]{4}[\-]W(0[1-9]|[1-4][0-9]|5[0-3])`;
</script>

<svelte:head><title>Weekly digest | BlackOps Board</title></svelte:head>

<main id="main-content" class="page page--reading">
  <div class="page-heading"><h1>Weekly digest</h1><p>Generate an immutable summary of board activity for one ISO week.</p></div>
  {#if form?.message}<p class="notice notice--error" role="alert">{form.message}</p>{/if}
  <form class="form-stack panel digest-form" method="POST">
    <div class="field"><label for="week">ISO week</label>
    <input
      id="week"
      name="week"
      value={form?.values?.week ?? data.week}
      pattern={isoWeekPattern}
      aria-invalid={form?.fieldErrors?.week ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.week ? 'week-helper week-error' : 'week-helper'}
      required
    />
    <p id="week-helper" class="helper">Use the format YYYY-Www, for example 2026-W30.</p>
    {#if form?.fieldErrors?.week}<p id="week-error" class="field-error" role="alert">{form.fieldErrors.week}</p>{/if}</div>
    <input type="hidden" name="idempotencyKey" value={form?.values?.idempotencyKey ?? data.idempotencyKey} />
    <button type="submit"><Calendar size={20} weight="Outline" aria-hidden="true" /> Generate digest</button>
  </form>
</main>

<style>
  .digest-form { max-width: 30rem; }
</style>

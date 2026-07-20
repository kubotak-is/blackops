<script lang="ts">
  import type { PageProps } from './$types';

  let { data, form }: PageProps = $props();
</script>

<svelte:head><title>Weekly digest | BlackOps Board</title></svelte:head>

<main>
  <h1>Weekly digest</h1>
  <p>Generate an immutable summary of board activity for an ISO week.</p>
  {#if form?.message}<p role="alert">{form.message}</p>{/if}
  <form method="POST">
    <label for="week">ISO week</label>
    <input
      id="week"
      name="week"
      value={form?.values?.week ?? data.week}
      pattern="[0-9]{4}-W(0[1-9]|[1-4][0-9]|5[0-3])"
      aria-invalid={form?.fieldErrors?.week ? 'true' : undefined}
      aria-describedby={form?.fieldErrors?.week ? 'week-error' : undefined}
      required
    />
    {#if form?.fieldErrors?.week}<p id="week-error">{form.fieldErrors.week}</p>{/if}
    <button type="submit">Generate digest</button>
  </form>
</main>

<style>
  main { max-width: 42rem; margin: 0 auto; padding: 2rem 1.5rem; }
  form { display: grid; gap: .75rem; max-width: 20rem; }
  input { font: inherit; padding: .6rem; }
</style>

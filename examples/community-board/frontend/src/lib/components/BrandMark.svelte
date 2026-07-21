<script lang="ts">
  import Activity from 'reicon-svelte/icons/Activity';
</script>

<span class="mark" aria-hidden="true"><Activity size={20} weight="Outline" /></span>

<style>
  .mark {
    display: inline-grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 9px;
    color: var(--on-accent);
    background: var(--accent);
  }
</style>

<script setup lang="ts">
/** Statut en texte coloré, comme la colonne « Status » de DashWin. */
const props = defineProps<{ statut: string, libelle: string }>()
const couleur = computed(() => ({
  candidat: 'text-sun-700',
  actif: 'text-leaf-600',
  suspendu: 'text-sun-600',
  abandon: 'text-destructive',
  diplome: 'text-brand-600',
} as Record<string, string>)[props.statut] ?? 'text-slate-500')
</script>

<template>
  <span class="inline-flex items-center gap-1.5 text-sm font-medium" :class="couleur">
    <span class="size-1.5 rounded-full bg-current" />
    {{ libelle }}
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ statut: string, libelle: string }>()

const COULEURS: Record<string, string> = {
  actif: 'bg-leaf-50 text-leaf-600',
  validee: 'bg-leaf-50 text-leaf-600',
  en_attente: 'bg-sun-50 text-sun-700',
  suspendu: 'bg-sun-50 text-sun-700',
  abandon: 'bg-muted text-muted-foreground',
  annulee: 'bg-muted text-muted-foreground',
  diplome: 'bg-brand-50 text-brand-800',
}
const classes = computed(() => COULEURS[props.statut] ?? 'bg-muted text-muted-foreground')
</script>

<template>
  <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium" :class="classes">{{ libelle }}</span>
</template>

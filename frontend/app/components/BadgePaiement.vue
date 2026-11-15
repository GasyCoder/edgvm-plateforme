<script setup lang="ts">
/** Situation de paiement en texte coloré (à jour, partiel, à payer, en retard...). */
const props = defineProps<{ statut?: string, libelle?: string }>()
const couleur = computed(() => ({
  solde: 'text-leaf-600',
  partiel: 'text-sun-600',
  a_venir: 'text-slate-500',
  en_retard: 'text-destructive',
  rien_a_payer: 'text-slate-400',
  annule: 'text-slate-400',
} as Record<string, string>)[props.statut ?? ''] ?? 'text-slate-400')
</script>

<template>
  <span v-if="statut" class="inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-medium" :class="couleur">
    <span class="size-1.5 rounded-full bg-current" />{{ libelle }}
  </span>
</template>

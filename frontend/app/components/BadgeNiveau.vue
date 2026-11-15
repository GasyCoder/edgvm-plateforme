<script setup lang="ts">
import type { Inscription } from '~/types'

/** Niveau de l'année : D1, D2, D3 ou dérogation (avec alerte si la décision manque). */
defineProps<{ inscription?: Inscription | null }>()
</script>

<template>
  <span v-if="!inscription" class="inline-flex items-center rounded-md border border-dashed px-2 py-0.5 text-xs text-muted-foreground">Non inscrit</span>
  <span
    v-else
    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold"
    :class="inscription.est_derogation ? 'bg-sun-100 text-sun-700' : 'bg-brand-50 text-brand-800'"
    :title="inscription.derogation_en_attente ? 'Décision de dérogation à enregistrer' : undefined"
  >
    {{ inscription.niveau_aff }}
    <span v-if="inscription.derogation_en_attente" class="size-1.5 rounded-full bg-destructive" aria-label="décision manquante" />
  </span>
</template>

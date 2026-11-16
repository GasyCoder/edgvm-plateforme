<script setup lang="ts">
import { Check, Circle } from '@lucide/vue'

/** Règles du mot de passe, cochées au fur et à mesure de la saisie. */
const props = defineProps<{ motDePasse: string, confirmation: string }>()
const regles = computed(() => [
  { libelle: '10 caractères au moins', ok: props.motDePasse.length >= 10 },
  { libelle: 'Des lettres', ok: /\p{L}/u.test(props.motDePasse) },
  { libelle: 'Des chiffres', ok: /\d/.test(props.motDePasse) },
  { libelle: 'Confirmation identique', ok: props.confirmation.length > 0 && props.confirmation === props.motDePasse },
])
</script>

<template>
  <ul class="grid gap-1.5 text-sm sm:grid-cols-2" aria-label="Règles du mot de passe">
    <li v-for="regle in regles" :key="regle.libelle" class="flex items-center gap-2" :class="regle.ok ? 'text-leaf-600' : 'text-slate-400'">
      <Check v-if="regle.ok" class="size-4 flex-none" aria-hidden="true" />
      <Circle v-else class="size-4 flex-none" aria-hidden="true" />
      {{ regle.libelle }}<span class="sr-only">{{ regle.ok ? ' : respecté' : ' : pas encore' }}</span>
    </li>
  </ul>
</template>

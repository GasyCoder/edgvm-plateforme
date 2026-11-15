<script setup lang="ts">
import type { Doctorant, Ressource } from '~/types'

definePageMeta({ roles: ['super_admin'] })

const route = useRoute()
const api = useApi()
const { data: doctorant, status, error } = useAsyncData(
  `doctorant-modifier-${route.params.id}`,
  () => api<Ressource<Doctorant>>(`/api/doctorants/${route.params.id}`).then(r => r.data),
  { lazy: true },
)
useHead(() => ({ title: doctorant.value ? `Modifier ${doctorant.value.nom_complet}` : 'Modifier le dossier' }))
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <EnTetePage :titre="doctorant ? doctorant.nom_complet : 'Modifier le dossier'" description="Modifier le dossier" />
    <SqueletteFiche v-if="status === 'pending' && !doctorant" />
    <EtatVide v-else-if="error" titre="Dossier introuvable" />
    <FormulaireDoctorant v-else-if="doctorant" :doctorant="doctorant" />
  </div>
</template>

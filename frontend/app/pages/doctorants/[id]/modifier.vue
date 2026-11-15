<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
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
  <div>
    <NuxtLink :to="`/doctorants/${route.params.id}`" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-700">
      <ArrowLeft class="size-4" /> {{ doctorant?.nom_complet ?? 'Dossier' }}
    </NuxtLink>
    <EnTetePage :titre="doctorant ? `Modifier ${doctorant.nom_complet}` : 'Modifier le dossier'" description="Les cinq étapes du dossier. Passez de l'une à l'autre librement, puis enregistrez." />
    <SqueletteFiche v-if="status === 'pending' && !doctorant" />
    <EtatVide v-else-if="error" titre="Dossier introuvable" />
    <AssistantDoctorant v-else-if="doctorant" :key="doctorant.id" :doctorant="doctorant" />
  </div>
</template>

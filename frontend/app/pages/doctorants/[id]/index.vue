<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import type { Doctorant } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
const route = useRoute()
/* Dossier chargé par la fiche : un candidat renvoie vers la liste des candidats. */
const { data: doctorant } = useNuxtData<Doctorant>(`doctorant-${route.params.id}`)
const retour = computed(() => doctorant.value?.est_candidat ? { vers: '/candidats', libelle: 'Candidats' } : { vers: '/doctorants', libelle: 'Doctorants' })
</script>

<template>
  <div>
    <NuxtLink :to="retour.vers" class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="size-4" /> {{ retour.libelle }}
    </NuxtLink>
    <FicheDoctorant :id="String(route.params.id)" :key="String(route.params.id)" />
  </div>
</template>

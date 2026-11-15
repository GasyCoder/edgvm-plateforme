<script setup lang="ts">
import { ExternalLink, FileText, Stamp } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { Acte, Doctorant, Pagine } from '~/types'

/** Actes délivrés à un doctorant, avec délivrance directe pour l'administration. */
const props = defineProps<{ doctorant: Doctorant }>()

const api = useApi()
const { estSuperAdmin } = useAuth()
const { data: actes, refresh } = useAsyncData(
  () => `actes-${props.doctorant.id}`,
  () => api<Pagine<Acte>>('/api/actes', { query: { doctorant: props.doctorant.id, par_page: 50 } }).then(r => r.data),
  { lazy: true, default: () => [] as Acte[] },
)
const delivrance = ref(false)
defineExpose({ ouvrir: () => { delivrance.value = true } })
</script>

<template>
  <Card>
    <CardHeader class="flex flex-row items-start justify-between gap-3">
      <div class="grid gap-1.5">
        <CardTitle>Actes délivrés <span v-if="actes.length" class="font-normal text-slate-400">({{ actes.length }})</span></CardTitle>
        <CardDescription>Attestations, admission, fiches d'évaluation...</CardDescription>
      </div>
      <Button v-if="estSuperAdmin" variant="outline" size="sm" @click="delivrance = true"><Stamp /> Délivrer</Button>
    </CardHeader>
    <CardContent>
      <p v-if="!actes.length" class="text-sm text-slate-400">Aucun acte délivré pour le moment.</p>
      <ul v-else class="divide-y divide-gray-200">
        <li v-for="acte in actes" :key="acte.id" class="flex items-center gap-3 py-3">
          <span class="flex size-10 flex-none items-center justify-center rounded-md" :class="acte.annule_le ? 'bg-gray-100 text-slate-400' : 'bg-brand-50 text-brand-700'"><FileText class="size-5" /></span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold" :class="acte.annule_le ? 'text-slate-400 line-through' : 'text-slate-700'">{{ acte.modele?.nom }}</p>
            <p class="truncate text-xs text-slate-400">
              {{ acte.numero }} · {{ formaterDate(acte.delivre_le) }}<template v-if="acte.annee"> · {{ acte.annee }}</template>
              <span v-if="acte.annule_le" class="font-medium text-destructive"> · Annulé</span>
            </p>
          </div>
          <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
            <a :href="acte.url_pdf" target="_blank" rel="noopener" :aria-label="`Ouvrir ${acte.numero}`" title="Ouvrir le PDF"><ExternalLink /></a>
          </Button>
        </li>
      </ul>
    </CardContent>
    <DialogueDelivrance v-if="estSuperAdmin" v-model:open="delivrance" :doctorants="[{ id: doctorant.id, nom_complet: doctorant.nom_complet }]" @delivre="refresh()" />
  </Card>
</template>

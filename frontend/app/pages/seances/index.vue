<script setup lang="ts">
import { CalendarClock, ChevronRight, MapPin, Plus, Presentation, Users, Wallet } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { Pagine, Seance } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead', 'doctorant'] })
useHead({ title: 'Séminaires et avancements' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { estSuperAdmin, estDoctorant } = useAuth()
const periode = computed(() => route.query.periode === 'passees' ? 'passees' : 'a_venir')
const { data, status, refresh } = useAsyncData('seances', () => api<Pagine<Seance>>('/api/seances', { query: { periode: periode.value } }), { lazy: true, watch: [periode] })

const dialogue = ref(false)
async function creee(seance: Seance) {
  await navigateTo(`/seances/${seance.id}`)
}
const jour = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const couleurStatut = (statut: Seance['statut']) => ({ brouillon: 'text-slate-500', publiee: 'text-leaf-600', annulee: 'text-destructive' })[statut]
</script>

<template>
  <div>
    <EnTetePage :titre="estDoctorant ? 'Mes séances' : 'Séminaires et avancements'" :description="estDoctorant ? 'Vos passages aux séminaires doctoraux et états d\'avancement.' : 'Séances de communication : passages des doctorants, modérateurs et jury, convocations.'">
      <template v-if="estSuperAdmin" #actions>
        <Button @click="dialogue = true"><Plus /> Nouvelle séance</Button>
      </template>
    </EnTetePage>

    <Tabs :model-value="periode" class="mb-4" @update:model-value="v => router.replace({ query: { periode: v === 'passees' ? 'passees' : undefined } })">
      <TabsList>
        <TabsTrigger value="a_venir" class="gap-1.5"><CalendarClock class="size-4" aria-hidden="true" />À venir</TabsTrigger>
        <TabsTrigger value="passees">Passées</TabsTrigger>
      </TabsList>
    </Tabs>

    <SqueletteListe v-if="status === 'pending' && !data" :lignes="4" />
    <EtatVide v-else-if="data && !data.data.length" :icone="Presentation" :titre="periode === 'a_venir' ? 'Aucune séance prévue' : 'Aucune séance passée'" :texte="estSuperAdmin && periode === 'a_venir' ? 'Créez un séminaire doctoral ou un état d\'avancement.' : undefined" />

    <div v-else-if="data" class="grid gap-3">
      <NuxtLink
        v-for="seance in data.data"
        :key="seance.id"
        :to="`/seances/${seance.id}`"
        class="group flex flex-wrap items-center gap-x-6 gap-y-3 rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400 sm:p-5"
        :class="seance.statut === 'annulee' ? 'opacity-60' : ''"
      >
        <div class="flex w-16 flex-none flex-col items-center rounded-md bg-brand-50 py-2 text-brand-800">
          <span class="text-xs font-semibold uppercase">{{ new Date(`${seance.date}T00:00:00`).toLocaleDateString('fr-FR', { month: 'short' }) }}</span>
          <span class="text-2xl font-bold leading-none">{{ new Date(`${seance.date}T00:00:00`).getDate() }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">{{ seance.type_libelle }}</p>
          <h2 class="font-bold text-slate-700 group-hover:text-brand-800">{{ seance.titre }}</h2>
          <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
            <span class="first-letter:uppercase">{{ jour(seance.date) }}, à partir de {{ seance.heure_debut }}</span>
            <span v-if="seance.lieu" class="inline-flex items-center gap-1"><MapPin class="size-3.5" aria-hidden="true" />{{ seance.lieu }}</span>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
          <span v-if="!estDoctorant" class="inline-flex items-center gap-1.5 text-slate-500"><Users class="size-4 text-slate-400" aria-hidden="true" />{{ seance.passages_count ?? 0 }} passage{{ (seance.passages_count ?? 0) > 1 ? 's' : '' }}</span>
          <span v-if="seance.exige_paiement_solde && !estDoctorant" class="inline-flex items-center gap-1.5 text-slate-500" title="Réservé aux doctorants à jour de paiement"><Wallet class="size-4 text-slate-400" aria-hidden="true" />Paiement soldé</span>
          <span class="inline-flex items-center gap-1.5 font-medium" :class="couleurStatut(seance.statut)"><span class="size-1.5 rounded-full bg-current" />{{ seance.statut_libelle }}</span>
          <ChevronRight class="size-4 text-slate-300 group-hover:text-brand-600" aria-hidden="true" />
        </div>
      </NuxtLink>
    </div>

    <DialogueSeance v-if="estSuperAdmin" v-model:open="dialogue" @enregistre="creee" />
  </div>
</template>

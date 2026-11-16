<script setup lang="ts">
import { ChevronLeft, ChevronRight, CircleCheck, Clock, Download, FileDown, FileSpreadsheet, FileText, Plus, Presentation, Search, Upload, UserRoundPlus, UserX, Users, X } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useDebounceFn } from '@vueuse/core'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Doctorant, Pagine } from '~/types'

/*
 * Candidats à l'École doctorale : en attente de présentation, ajournés, non admis, admis par le jury,
 * et ceux devenus doctorants. Filtres par EAD et par année de candidature.
 */
definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Candidats' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { equipes } = useReferentiels()

const filtres = computed(() => ({
  etat: (route.query.etat as string) || 'tous',
  equipe: (route.query.equipe as string) || 'toutes',
  annee: (route.query.annee as string) || 'toutes',
  recherche: (route.query.recherche as string) || '',
  page: Number(route.query.page) || 1,
}))
const parametres = computed(() => ({
  etat: filtres.value.etat !== 'tous' ? filtres.value.etat : undefined,
  equipe: filtres.value.equipe !== 'toutes' ? filtres.value.equipe : undefined,
  annee: filtres.value.annee !== 'toutes' ? filtres.value.annee : undefined,
  recherche: filtres.value.recherche || undefined,
}))
const { data, status, refresh } = useAsyncData('candidats', () => api<Pagine<Doctorant>>('/api/candidats', { query: { ...parametres.value, page: filtres.value.page } }), { lazy: true, watch: [() => route.query] })
const { data: compteurs, refresh: actualiserCompteurs } = useAsyncData('candidats-compteurs', () => api<Record<string, number>>('/api/candidats/compteurs', { query: { ...parametres.value, etat: undefined } }), { lazy: true, watch: [() => JSON.stringify({ ...parametres.value, etat: undefined })] })

function filtrer(valeurs: Record<string, string | number | undefined>) {
  const fusion: Record<string, unknown> = { ...route.query, page: undefined, ...valeurs }
  const query = Object.fromEntries(Object.entries(fusion).filter(([, v]) => v !== undefined && !['', 'tous', 'toutes'].includes(String(v))).map(([k, v]) => [k, String(v)]))
  router.replace({ query })
}
/* Export : les filtres de la liste (état, EAD, année, recherche) s'appliquent au fichier */
const filtresActifs = computed(() => Object.keys(parametres.value).some(cle => parametres.value[cle as keyof typeof parametres.value] !== undefined))
function exporter(format: 'xlsx' | 'csv') {
  const query = new URLSearchParams()
  Object.entries(parametres.value).forEach(([cle, valeur]) => { if (valeur !== undefined) query.append(cle, String(valeur)) })
  window.location.href = `/telechargements/candidats.${format}?${query.toString()}`
  toast.success('Export en cours de téléchargement.')
}
const importOuvert = ref(false)
const apresImport = () => Promise.all([refresh(), actualiserCompteurs()])

const recherche = ref(filtres.value.recherche)
watch(recherche, useDebounceFn((terme: string) => filtrer({ recherche: terme.trim() || undefined }), 350))

const CARTES = [
  { cle: 'tous', libelle: 'Candidatures en cours', aide: 'sans les admis', icone: Users, couleur: 'text-slate-700' },
  { cle: 'en_attente', libelle: 'En attente', aide: 'pas encore de décision', icone: Clock, couleur: 'text-slate-700' },
  { cle: 'admis_jury', libelle: 'Admis par le jury', aide: 'à inscrire', icone: CircleCheck, couleur: 'text-leaf-600' },
  { cle: 'ajourne', libelle: 'Ajournés', aide: 'à représenter', icone: Presentation, couleur: 'text-sun-700' },
  { cle: 'non_admis', libelle: 'Non admis', aide: 'candidature close', icone: UserX, couleur: 'text-destructive' },
  { cle: 'admis', libelle: 'Devenus doctorants', aide: 'inscrits en D1', icone: UserRoundPlus, couleur: 'text-brand-700' },
] as const

const annees = computed(() => {
  const courante = new Date().getFullYear()
  return [courante, courante - 1, courante - 2, courante - 3].map(String)
})
const etat = (d: Doctorant) => d.candidature?.admis_le
  ? { libelle: 'Devenu doctorant', classe: 'bg-brand-50 text-brand-800' }
  : ({
      admis: { libelle: 'Admis par le jury', classe: 'bg-leaf-50 text-leaf-600' },
      ajourne: { libelle: 'Ajourné', classe: 'bg-sun-100 text-sun-700' },
      non_admis: { libelle: 'Non admis', classe: 'bg-red-50 text-destructive' },
    } as Record<string, { libelle: string, classe: string }>)[d.candidature?.decision ?? ''] ?? { libelle: 'En attente', classe: 'bg-gray-100 text-slate-600' }
const derniere = (d: Doctorant) => d.candidature?.presentations?.[0]
const th = 'border-y border-gray-300 px-2 py-2.5 text-start text-sm font-normal text-slate-400 first:ps-4 last:pe-4 sm:first:ps-6 sm:last:pe-6'
const td = 'border-b border-gray-200 px-2 py-3 align-top first:ps-4 last:pe-4 sm:first:ps-6 sm:last:pe-6'
</script>

<template>
  <div>
    <EnTetePage titre="Candidats" description="Présentation du projet de thèse, décision du jury, admission en D1.">
      <template #actions>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="bg-white"><Download /> Exporter</Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-64">
            <DropdownMenuLabel class="text-xs font-normal text-slate-400">{{ filtresActifs ? 'Avec les filtres de la liste' : 'Tous les candidats en cours' }}</DropdownMenuLabel>
            <DropdownMenuItem @select="exporter('xlsx')"><FileSpreadsheet /> Excel (.xlsx)</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @select="exporter('csv')"><FileDown /> CSV</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel class="text-xs font-normal text-slate-400">Pour importer</DropdownMenuLabel>
            <DropdownMenuItem as-child>
              <a href="/telechargements/modele-import-candidats.csv"><FileText /> Modèle de fichier à remplir</a>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button variant="outline" class="bg-white" @click="importOuvert = true"><Upload /> Importer</Button>
        <Button as-child><NuxtLink to="/candidats/nouveau"><Plus /> Nouveau candidat</NuxtLink></Button>
      </template>
    </EnTetePage>
    <DialogueImport v-model:open="importOuvert" candidats @importe="apresImport" />

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
      <template v-if="compteurs">
        <button
          v-for="carte in CARTES"
          :key="carte.cle"
          type="button"
          class="rounded-md border bg-white p-4 text-start transition-colors hover:border-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          :class="filtres.etat === carte.cle ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-300'"
          :aria-pressed="filtres.etat === carte.cle"
          @click="filtrer({ etat: carte.cle })"
        >
          <p class="flex items-center gap-1.5 text-sm text-slate-400"><component :is="carte.icone" class="size-4" aria-hidden="true" />{{ carte.libelle }}</p>
          <p class="mt-1 text-2xl font-bold tabular-nums" :class="carte.couleur">{{ compteurs[carte.cle] ?? 0 }}</p>
          <p class="text-xs text-slate-400">{{ carte.aide }}</p>
        </button>
      </template>
      <div v-for="n in (compteurs ? 0 : 6)" v-else :key="n" class="h-[6.5rem] animate-pulse rounded-md bg-gray-200" />
    </div>

    <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
      <div class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center">
        <div class="relative flex-1 lg:max-w-sm">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input v-model="recherche" type="search" placeholder="Nom, numéro ou e-mail" class="pl-9" aria-label="Rechercher un candidat" />
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex lg:ms-auto">
          <Select :model-value="filtres.equipe" @update:model-value="v => filtrer({ equipe: String(v) })">
            <SelectTrigger class="w-full sm:w-44" aria-label="EAD visée"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="toutes">Toutes les EAD</SelectItem>
              <SelectItem v-for="e in equipes" :key="e.id" :value="String(e.id)">{{ e.sigle }}</SelectItem>
            </SelectContent>
          </Select>
          <Select :model-value="filtres.annee" @update:model-value="v => filtrer({ annee: String(v) })">
            <SelectTrigger class="w-full sm:w-44" aria-label="Année de candidature"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="toutes">Toutes les années</SelectItem>
              <SelectItem v-for="a in annees" :key="a" :value="a">Candidature {{ a }}</SelectItem>
            </SelectContent>
          </Select>
          <Button v-if="Object.keys(route.query).length" variant="ghost" class="text-slate-500" @click="recherche = ''; router.replace({ query: {} })"><X /> Effacer</Button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full border-collapse" :class="status === 'pending' && data ? 'opacity-60' : ''">
          <thead>
            <tr>
              <th :class="th">Candidat</th>
              <th :class="th" class="hidden md:table-cell">EAD visée</th>
              <th :class="th" class="hidden lg:table-cell">Projet de thèse</th>
              <th :class="th" class="hidden sm:table-cell">Dernière présentation</th>
              <th :class="th">État</th>
            </tr>
          </thead>
          <tbody v-if="data?.data.length">
            <tr v-for="d in data.data" :key="d.id" class="group transition-colors hover:bg-gray-50">
              <td :class="td">
                <NuxtLink :to="`/doctorants/${d.id}`" class="flex min-w-0 items-center gap-3">
                  <img v-if="d.photo_url" :src="d.photo_url" alt="" class="size-9 flex-none rounded-full object-cover object-top sm:size-10">
                  <span v-else class="flex size-9 flex-none items-center justify-center rounded-full text-sm font-semibold text-white sm:size-10" :class="couleurAvatar(d.nom_complet)">{{ initiales(d.nom_complet) }}</span>
                  <span class="min-w-0">
                    <span class="block max-w-[12rem] truncate text-sm font-semibold text-slate-700 group-hover:text-brand-700 sm:max-w-xs">{{ d.nom_complet }}</span>
                    <span class="block text-xs text-slate-400">{{ d.matricule }}<template v-if="d.candidature?.depose_le">, déposée le {{ formaterDate(d.candidature.depose_le) }}</template></span>
                  </span>
                </NuxtLink>
              </td>
              <td :class="td" class="hidden whitespace-nowrap text-sm font-medium text-slate-600 md:table-cell" :title="d.equipe?.nom">{{ d.equipe?.sigle }}</td>
              <td :class="td" class="hidden lg:table-cell"><p class="line-clamp-2 max-w-md text-sm text-slate-500">{{ d.sujet_these }}</p><p v-if="d.directeur_these" class="text-xs text-slate-400">{{ d.directeur_these }}</p></td>
              <td :class="td" class="hidden text-sm sm:table-cell">
                <NuxtLink v-if="derniere(d)" :to="`/seances/${derniere(d)!.seance_id}`" class="text-slate-600 hover:text-brand-700">{{ formaterDate(derniere(d)!.date) }}</NuxtLink>
                <span v-else class="text-slate-400">Pas encore programmé</span>
              </td>
              <td :class="td"><span class="whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold" :class="etat(d).classe">{{ etat(d).libelle }}</span></td>
            </tr>
          </tbody>
        </table>
        <SqueletteListe v-if="status === 'pending' && !data" :lignes="5" class="border-none" />
        <EtatVide v-else-if="data && !data.data.length" :icone="UserRoundPlus" titre="Aucun candidat" texte="Enregistrez une candidature avec « Nouveau candidat »." class="border-none" />
      </div>

      <div v-if="data && data.meta.last_page > 1" class="flex items-center justify-end gap-2 border-t border-gray-200 px-4 py-3 text-sm text-slate-500 sm:px-6">
        <span>{{ data.meta.from }} à {{ data.meta.to }} sur {{ data.meta.total }}</span>
        <Button variant="outline" size="icon-sm" :disabled="filtres.page <= 1" aria-label="Page précédente" @click="filtrer({ page: filtres.page - 1 })"><ChevronLeft /></Button>
        <Button variant="outline" size="icon-sm" :disabled="filtres.page >= data.meta.last_page" aria-label="Page suivante" @click="filtrer({ page: filtres.page + 1 })"><ChevronRight /></Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { CalendarPlus, ChevronLeft, ChevronRight, Copy, Download, Eye, FileDown, FileSpreadsheet, IdCard, MoreHorizontal, Pencil, Plus, ReceiptText, Search, Send, Stamp, Upload, Users, X } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import type { Doctorant, Pagine } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
useHead({ title: 'Doctorants' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { estSuperAdmin } = useAuth()
const { equipes, anneeCourante } = useReferentiels()

/* Les filtres vivent dans l'adresse : le retour arrière, l'export et le partage d'un lien gardent la liste. */
const filtres = computed(() => ({
  recherche: (route.query.recherche as string) || '',
  equipe: (route.query.equipe as string) || 'toutes',
  niveau: (route.query.niveau as string) || 'tous',
  statut: (route.query.statut as string) || 'tous',
  sans_inscription: route.query.sans_inscription === '1',
  paiement: (route.query.paiement as string) || 'tous',
  boursier: (route.query.boursier as string) || 'tous',
  page: Number(route.query.page) || 1,
  par_page: (route.query.par_page as string) || '20',
}))

const parametresApi = computed(() => ({
  recherche: filtres.value.recherche || undefined,
  equipe: filtres.value.equipe !== 'toutes' ? filtres.value.equipe : undefined,
  niveau: filtres.value.niveau !== 'tous' ? filtres.value.niveau : undefined,
  statut: filtres.value.statut !== 'tous' ? filtres.value.statut : undefined,
  sans_inscription: filtres.value.sans_inscription ? 1 : undefined,
  paiement: filtres.value.paiement !== 'tous' ? filtres.value.paiement : undefined,
  boursier: filtres.value.boursier !== 'tous' ? filtres.value.boursier : undefined,
}))

const { data, status, refresh } = useAsyncData(
  'doctorants',
  () => api<Pagine<Doctorant>>('/api/doctorants', { query: { ...parametresApi.value, page: filtres.value.page, par_page: filtres.value.par_page } }),
  { lazy: true, watch: [() => route.query] },
)

/* Cartes compteurs : doctorants par niveau de l'année, avec tous les filtres de la liste sauf le niveau. */
interface Compteurs { total: number, niveaux: Record<'D1' | 'D2' | 'D3' | 'derogation', number>, boursiers: number }
const { data: compteurs, refresh: actualiserCompteurs } = useAsyncData(
  'doctorants-compteurs',
  () => api<Compteurs>('/api/doctorants/compteurs', { query: parametresApi.value }),
  { lazy: true, watch: [() => JSON.stringify(parametresApi.value)] },
)
const actualiser = () => Promise.all([refresh(), actualiserCompteurs()])

interface Carte { cle: string, libelle: string, nombre: number, aide: string }
const cartes = computed<{ filtre: 'niveau', liste: Carte[] } | null>(() => {
  const c = compteurs.value
  if (!c) return null
  return {
    filtre: 'niveau',
    liste: [
      { cle: 'D1', libelle: 'D1', nombre: c.niveaux.D1, aide: 'première année' },
      { cle: 'D2', libelle: 'D2', nombre: c.niveaux.D2, aide: 'deuxième année' },
      { cle: 'D3', libelle: 'D3', nombre: c.niveaux.D3, aide: 'troisième année' },
      { cle: 'derogation', libelle: 'Dérogation', nombre: c.niveaux.derogation, aide: 'au-delà de la 3e année' },
    ],
  }
})
const carteActive = computed(() => filtres.value.niveau)
const inscritsCetteAnnee = computed(() => cartes.value?.liste.reduce((somme, carte) => somme + carte.nombre, 0) ?? 0)
function choisirCarte(cle: string) {
  if (!cartes.value) return
  filtrer({ [cartes.value.filtre]: carteActive.value === cle ? undefined : cle })
}

function filtrer(valeurs: Record<string, string | number | undefined>) {
  const query: Record<string, unknown> = { ...route.query, page: undefined, ...valeurs }
  const nettoyee = Object.fromEntries(Object.entries(query).filter(([, valeur]) => ![undefined, '', 'tous', 'toutes'].includes(valeur as string)))
  router.replace({ query: nettoyee as Record<string, string> })
}

const recherche = ref(filtres.value.recherche)
watch(recherche, useDebounceFn((terme: string) => filtrer({ recherche: terme.trim() || undefined }), 350))
const filtresActifs = computed(() => Object.keys(route.query).some(cle => !['page', 'par_page'].includes(cle)))
const effacer = () => {
  recherche.value = ''
  router.replace({ query: {} })
}

/* Sélection (export de la sélection) */
const selection = ref<string[]>([])
watch(() => route.query, () => { selection.value = [] })
const lignes = computed(() => data.value?.data ?? [])
const toutCoche = computed(() => lignes.value.length > 0 && lignes.value.every(d => selection.value.includes(d.id)))
const etatEntete = computed<boolean | 'indeterminate'>(() => toutCoche.value ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (coche: boolean | 'indeterminate') => { selection.value = coche === true ? lignes.value.map(d => d.id) : [] }
const basculer = (id: string, coche: boolean | 'indeterminate') => {
  selection.value = coche === true ? [...selection.value, id] : selection.value.filter(s => s !== id)
}

/* Export : lien direct (même domaine, la session suit), avec les filtres de la liste */
function exporter(seulementSelection = false, format: 'xlsx' | 'csv' = 'xlsx') {
  const query = new URLSearchParams()
  for (const [cle, valeur] of Object.entries(parametresApi.value)) {
    if (valeur !== undefined) query.append(cle, String(valeur))
  }
  if (seulementSelection) selection.value.forEach(id => query.append('ids[]', id))
  window.location.href = `/telechargements/doctorants.${format}?${query.toString()}`
  toast.success('Export en cours de téléchargement.')
}

const importOuvert = ref(false)
const delivrance = ref(false)
const fraisSelection = ref(false)

async function renvoyerLien(doctorant: Doctorant) {
  if (!doctorant.compte) return
  try {
    const reponse = await api<{ message: string }>(`/api/comptes/${doctorant.compte.id}/lien-acces`, { method: 'POST' })
    toast.success(reponse.message)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}

async function copierEmail(email?: string) {
  if (!email) return
  await navigator.clipboard.writeText(email)
  toast.success('Adresse e-mail copiée.')
}

const description = computed(() => {
  if (!data.value) return 'Chargement de la liste...'
  const total = data.value.meta.total
  return `${total} dossier${total > 1 ? 's' : ''}${filtresActifs.value ? ' correspondant aux filtres' : ''}${anneeCourante.value ? `, niveaux de l'année ${anneeCourante.value.libelle}` : ''}.`
})

const th = 'border-y border-gray-300 px-1.5 py-2.5 sm:px-2 text-start text-sm font-normal text-slate-400 first:ps-4 last:pe-3 sm:first:ps-6 sm:last:pe-6'
const td = 'border-b border-gray-200 px-1.5 py-3.5 sm:px-2 first:ps-4 last:pe-3 sm:first:ps-6 sm:last:pe-6'
</script>

<template>
  <div>
    <EnTetePage titre="Doctorants" :description="description">
      <template #actions>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" class="bg-white"><Download /> Exporter</Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-64">
            <DropdownMenuLabel class="text-xs font-normal text-slate-400">Excel (.xlsx)</DropdownMenuLabel>
            <DropdownMenuItem @select="exporter()"><FileSpreadsheet /> {{ filtresActifs ? `La liste filtrée (${data?.meta.total ?? 0})` : 'Toute la liste' }}</DropdownMenuItem>
            <DropdownMenuItem :disabled="!selection.length" @select="exporter(true)"><FileSpreadsheet /> La sélection ({{ selection.length }})</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @select="exporter(false, 'csv')"><FileDown /> {{ filtresActifs ? 'La liste filtrée' : 'Toute la liste' }} en CSV</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button v-if="estSuperAdmin" variant="outline" class="bg-white" @click="importOuvert = true"><Upload /> Importer</Button>
        <Button v-if="estSuperAdmin" as-child>
          <NuxtLink to="/doctorants/nouveau"><Plus /> Nouveau doctorant</NuxtLink>
        </Button>
      </template>
    </EnTetePage>

    <!-- Compteurs : un clic filtre la liste, un second clic retire le filtre -->
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4">
      <template v-if="cartes && compteurs">
        <button
          type="button"
          class="rounded-md border bg-white p-4 text-start transition-colors hover:border-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          :class="carteActive === 'tous' ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-300'"
          :aria-pressed="carteActive === 'tous'"
          @click="filtrer({ [cartes.filtre]: undefined })"
        >
          <p class="flex items-center gap-1.5 text-sm text-slate-400"><Users class="size-4" aria-hidden="true" /> Tous les dossiers</p>
          <p class="mt-1 text-2xl font-bold tabular-nums text-slate-700">{{ compteurs.total }}</p>
          <p class="text-xs text-slate-400">{{ inscritsCetteAnnee }} inscrit{{ inscritsCetteAnnee > 1 ? 's' : '' }} cette année</p>
        </button>
        <button
          v-for="carte in cartes.liste"
          :key="carte.cle"
          type="button"
          class="rounded-md border bg-white p-4 text-start transition-colors hover:border-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          :class="carteActive === carte.cle ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-300'"
          :aria-pressed="carteActive === carte.cle"
          @click="choisirCarte(carte.cle)"
        >
          <p class="flex items-center gap-2 text-sm text-slate-400">
            <span class="rounded px-1.5 py-0.5 text-xs font-bold" :class="carte.cle === 'derogation' ? 'bg-sun-100 text-sun-700' : 'bg-brand-50 text-brand-800'">{{ carte.libelle }}</span>
          </p>
          <p class="mt-1 text-2xl font-bold tabular-nums text-slate-700">{{ carte.nombre }}</p>
          <p class="text-xs text-slate-400">{{ carte.aide }}</p>
        </button>
      </template>
      <Skeleton v-for="n in (cartes ? 0 : 5)" v-else :key="n" class="h-[6.5rem] rounded-md" />
    </div>

    <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
      <!-- Barre d'outils : recherche, filtres, sélection -->
      <div class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center">
        <div v-if="selection.length" class="flex flex-1 flex-wrap items-center gap-3">
          <span class="text-sm font-semibold text-slate-700">{{ selection.length }} sélectionné{{ selection.length > 1 ? 's' : '' }}</span>
          <Button v-if="estSuperAdmin" size="sm" @click="delivrance = true"><Stamp /> Délivrer un acte</Button>
          <Button v-if="estSuperAdmin" size="sm" variant="outline" @click="fraisSelection = true"><ReceiptText /> Ajouter un frais</Button>
          <Button size="sm" variant="outline" @click="exporter(true)"><FileSpreadsheet /> Exporter en Excel</Button>
          <Button v-if="estSuperAdmin" size="sm" variant="outline" as-child>
            <a :href="`/telechargements/cartes-doctorat.pdf?${selection.map(id => `ids[]=${encodeURIComponent(id)}`).join('&')}`" target="_blank" rel="noopener"><IdCard /> Cartes de doctorat</a>
          </Button>
          <Button size="sm" variant="ghost" @click="selection = []"><X /> Désélectionner</Button>
        </div>
        <template v-else>
          <div class="relative flex-1 lg:max-w-sm">
            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="recherche" type="search" placeholder="Nom, matricule ou e-mail" class="pl-9" aria-label="Rechercher un doctorant" />
          </div>
          <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:ms-auto">
            <Select :model-value="filtres.equipe" @update:model-value="valeur => filtrer({ equipe: String(valeur) })">
              <SelectTrigger class="w-full sm:w-44" aria-label="Équipe d'accueil"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="toutes">Toutes les équipes</SelectItem>
                <SelectItem v-for="equipe in equipes" :key="equipe.id" :value="String(equipe.id)">{{ equipe.sigle }}</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filtres.niveau" @update:model-value="valeur => filtrer({ niveau: String(valeur) })">
              <SelectTrigger class="w-full sm:w-40" aria-label="Niveau cette année"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous les niveaux</SelectItem>
                <SelectItem value="D1">D1</SelectItem>
                <SelectItem value="D2">D2</SelectItem>
                <SelectItem value="D3">D3</SelectItem>
                <SelectItem value="derogation">Dérogation</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filtres.boursier" @update:model-value="valeur => filtrer({ boursier: String(valeur) })">
              <SelectTrigger class="w-full sm:w-40" aria-label="Bourse"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Boursiers ou non</SelectItem>
                <SelectItem value="1">Boursiers{{ compteurs ? ` (${compteurs.boursiers})` : '' }}</SelectItem>
                <SelectItem value="0">Non boursiers</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filtres.statut" @update:model-value="valeur => filtrer({ statut: String(valeur) })">
              <SelectTrigger class="w-full sm:w-36" aria-label="Statut"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous statuts</SelectItem>
                <SelectItem value="actif">Actifs</SelectItem>
                <SelectItem value="suspendu">Suspendus</SelectItem>
                <SelectItem value="abandon">Abandons</SelectItem>
                <SelectItem value="diplome">Diplômés</SelectItem>
              </SelectContent>
            </Select>
            <Select v-if="estSuperAdmin" :model-value="filtres.paiement" @update:model-value="valeur => filtrer({ paiement: String(valeur) })">
              <SelectTrigger class="w-full sm:w-40" aria-label="Paiement"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous paiements</SelectItem>
                <SelectItem value="a_jour">À jour</SelectItem>
                <SelectItem value="partiel">Partiel</SelectItem>
                <SelectItem value="a_payer">À payer</SelectItem>
                <SelectItem value="en_retard">En retard</SelectItem>
                <SelectItem value="rien">Aucun frais</SelectItem>
              </SelectContent>
            </Select>
            <Button v-if="filtresActifs" variant="ghost" class="text-slate-500" @click="effacer"><X /> Effacer</Button>
          </div>
        </template>
      </div>
      <p v-if="filtres.sans_inscription" class="mx-5 mb-4 inline-flex rounded bg-sun-100 px-2 py-0.5 text-sm text-sun-700 sm:mx-6">Actifs sans inscription cette année</p>

      <!-- Tableau -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse" :class="status === 'pending' && data ? 'opacity-60' : ''">
          <thead>
            <tr>
              <th :class="th" class="w-10"><Checkbox :model-value="etatEntete" aria-label="Tout sélectionner" @update:model-value="basculerTout" /></th>
              <th :class="th">Doctorant</th>
              <th :class="th" class="hidden sm:table-cell">Matricule</th>
              <th :class="th" class="hidden md:table-cell">Équipe</th>
              <th :class="th">Niveau</th>
              <th :class="th" class="hidden lg:table-cell">Téléphone</th>
              <th :class="th" class="hidden xl:table-cell">Paiement</th>
              <th :class="th" class="hidden md:table-cell">Statut</th>
              <th :class="th" class="text-end"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody v-if="status === 'pending' && !data">
            <tr v-for="ligne in 8" :key="ligne">
              <td :class="td"><Skeleton class="size-4" /></td>
              <td :class="td"><div class="flex items-center gap-3"><Skeleton class="size-10 rounded-full" /><div class="grid gap-1.5"><Skeleton class="h-4 w-40" /><Skeleton class="h-3 w-28" /></div></div></td>
              <td :class="td" class="hidden sm:table-cell"><Skeleton class="h-4 w-28" /></td>
              <td :class="td" class="hidden md:table-cell"><Skeleton class="h-4 w-14" /></td>
              <td :class="td"><Skeleton class="h-5 w-12" /></td>
              <td :class="td" class="hidden lg:table-cell"><Skeleton class="h-4 w-28" /></td>
              <td :class="td" class="hidden xl:table-cell"><Skeleton class="h-4 w-16" /></td>
              <td :class="td" class="hidden md:table-cell"><Skeleton class="h-4 w-14" /></td>
              <td :class="td" />
            </tr>
          </tbody>
          <tbody v-else-if="lignes.length">
            <tr v-for="doctorant in lignes" :key="doctorant.id" class="group transition-colors hover:bg-gray-50" :class="selection.includes(doctorant.id) ? 'bg-brand-50/50' : ''">
              <td :class="td">
                <Checkbox :model-value="selection.includes(doctorant.id)" :aria-label="`Sélectionner ${doctorant.nom_complet}`" @update:model-value="coche => basculer(doctorant.id, coche)" />
              </td>
              <td :class="td">
                <NuxtLink :to="`/doctorants/${doctorant.id}`" class="flex min-w-0 items-center gap-3">
                  <img v-if="doctorant.photo_url" :src="doctorant.photo_url" alt="" loading="lazy" class="size-9 flex-none rounded-full object-cover object-top sm:size-10">
                  <span v-else class="flex size-9 flex-none items-center justify-center rounded-full text-sm font-semibold text-white sm:size-10" :class="couleurAvatar(doctorant.nom_complet)">{{ initiales(doctorant.nom_complet) }}</span>
                  <span class="min-w-0">
                    <span class="block max-w-[8.5rem] truncate text-sm font-semibold text-slate-700 group-hover:text-brand-700 sm:max-w-xs">
                      {{ doctorant.nom_complet }}
                      <span class="ms-1 inline-block size-2 rounded-full md:hidden" :class="{ 'bg-leaf-400': doctorant.statut === 'actif', 'bg-sun-500': doctorant.statut === 'suspendu', 'bg-destructive': doctorant.statut === 'abandon', 'bg-brand-600': doctorant.statut === 'diplome' }" />
                    </span>
                    <span class="flex max-w-[8.5rem] items-center gap-1.5 text-xs text-slate-400 sm:max-w-xs">
                      <span v-if="doctorant.est_boursier" class="flex-none rounded bg-brand-50 px-1 text-[11px] font-semibold text-brand-700">Boursier</span>
                      <span class="truncate">{{ doctorant.email }}</span>
                    </span>
                  </span>
                </NuxtLink>
              </td>
              <td :class="td" class="hidden whitespace-nowrap text-sm text-slate-500 sm:table-cell">{{ doctorant.matricule }}</td>
              <td :class="td" class="hidden md:table-cell">
                <span class="whitespace-nowrap text-sm font-medium text-slate-600" :title="doctorant.equipe?.nom">{{ doctorant.equipe?.sigle }}</span>
              </td>
              <td :class="td"><BadgeNiveau :inscription="doctorant.inscription_courante" /></td>
              <td :class="td" class="hidden whitespace-nowrap text-sm text-slate-400 lg:table-cell">{{ doctorant.telephone || '' }}</td>
              <td :class="td" class="hidden xl:table-cell"><BadgePaiement :statut="doctorant.paiement?.statut" :libelle="doctorant.paiement?.statut_libelle" /></td>
              <td :class="td" class="hidden md:table-cell"><StatutDoctorant :statut="doctorant.statut" :libelle="doctorant.statut_libelle" /></td>
              <td :class="td" class="text-end">
                <div class="flex items-center justify-end gap-1">
                  <Button variant="ghost" size="icon-sm" class="hidden text-slate-400 hover:text-brand-700 lg:inline-flex lg:opacity-0 lg:group-hover:opacity-100" as-child>
                    <NuxtLink :to="`/doctorants/${doctorant.id}`" :aria-label="`Voir ${doctorant.nom_complet}`"><Eye /></NuxtLink>
                  </Button>
                  <Button v-if="doctorant.peut.modifier" variant="ghost" size="icon-sm" class="hidden text-slate-400 hover:text-brand-700 lg:inline-flex lg:opacity-0 lg:group-hover:opacity-100" as-child>
                    <NuxtLink :to="`/doctorants/${doctorant.id}/modifier`" :aria-label="`Modifier ${doctorant.nom_complet}`"><Pencil /></NuxtLink>
                  </Button>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon-sm" class="text-slate-500" :aria-label="`Actions pour ${doctorant.nom_complet}`"><MoreHorizontal /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                      <DropdownMenuItem as-child><NuxtLink :to="`/doctorants/${doctorant.id}`"><Eye /> Voir le dossier</NuxtLink></DropdownMenuItem>
                      <template v-if="doctorant.peut.modifier">
                        <DropdownMenuItem as-child><NuxtLink :to="`/doctorants/${doctorant.id}/modifier`"><Pencil /> Modifier</NuxtLink></DropdownMenuItem>
                        <DropdownMenuItem as-child><NuxtLink :to="`/doctorants/${doctorant.id}?inscrire=1`"><CalendarPlus /> Inscrire ou réinscrire</NuxtLink></DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @select="renvoyerLien(doctorant)"><Send /> Renvoyer le lien d'accès</DropdownMenuItem>
                      </template>
                      <DropdownMenuItem v-if="doctorant.email" @select="copierEmail(doctorant.email)"><Copy /> Copier l'e-mail</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data && !lignes.length" class="p-6">
        <EtatVide :icone="Users" titre="Aucun doctorant" :texte="filtresActifs ? 'Aucun dossier ne correspond à ces filtres.' : 'Créez un dossier ou importez une liste.'" class="border-none">
          <Button v-if="filtresActifs" variant="outline" size="sm" @click="effacer"><X /> Effacer les filtres</Button>
        </EtatVide>
      </div>

      <!-- Pagination -->
      <div v-if="data && lignes.length" class="flex flex-col gap-3 p-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="flex items-center gap-2">
          <span>Afficher</span>
          <Select :model-value="filtres.par_page" @update:model-value="valeur => filtrer({ par_page: valeur === '20' ? undefined : String(valeur) })">
            <SelectTrigger size="sm" class="w-20" aria-label="Lignes par page"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="nombre in ['10', '20', '50', '100']" :key="nombre" :value="nombre">{{ nombre }}</SelectItem>
            </SelectContent>
          </Select>
          <span class="hidden sm:inline">{{ data.meta.from }} à {{ data.meta.to }} sur {{ data.meta.total }}</span>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" :disabled="data.meta.current_page <= 1" @click="filtrer({ page: data.meta.current_page - 1 > 1 ? data.meta.current_page - 1 : undefined })">
            <ChevronLeft /> Précédent
          </Button>
          <span class="tabular-nums">Page {{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
          <Button variant="outline" size="sm" :disabled="data.meta.current_page >= data.meta.last_page" @click="filtrer({ page: data.meta.current_page + 1 })">
            Suivant <ChevronRight />
          </Button>
        </div>
      </div>
    </div>

    <DialogueImport v-if="estSuperAdmin" v-model:open="importOuvert" @importe="actualiser()" />
    <DialogueFrais v-if="estSuperAdmin" v-model:open="fraisSelection" :doctorants="lignes.filter(d => selection.includes(d.id)).map(d => ({ id: d.id, nom_complet: d.nom_complet }))" @ajoute="actualiser()" />
    <DialogueDelivrance v-if="estSuperAdmin" v-model:open="delivrance" :doctorants="lignes.filter(d => selection.includes(d.id)).map(d => ({ id: d.id, nom_complet: d.nom_complet }))" />
  </div>
</template>

<script setup lang="ts">
import { BellRing, ChevronLeft, ChevronRight, Download, Eye, MoreHorizontal, ReceiptText, Search, Wallet, X } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import type { Finances, FraisDoctorant, Pagine } from '~/types'

interface Ligne { id: string, nom_complet: string, matricule: string, equipe: string | null, niveau: string | null, prochaine_echeance: string | null, du: number, paye: number, reste: number, en_retard: number, statut: string, statut_libelle: string }
interface Totaux { doctorants: number, du: number, paye: number, reste: number, en_retard: number }

/**
 * Situation des paiements par doctorant : sélection multiple (frais, rappel, export), encaissement, totaux de la sélection filtrée.
 */
const emit = defineEmits<{ change: [] }>()
const api = useApi()
const { equipes } = useReferentiels()
const { route, valeur, filtrer } = useFiltresUrl()

const recherche = ref(valeur('recherche').value)
watch(recherche, useDebounceFn((t: string) => filtrer({ recherche: t.trim() || undefined }), 350))
const situation = valeur('situation', 'toutes')
const equipe = valeur('equipe', 'toutes')
const parPage = valeur('par_page', '20')

const parametres = computed(() => ({
  recherche: valeur('recherche').value || undefined,
  situation: situation.value !== 'toutes' ? situation.value : undefined,
  equipe: equipe.value !== 'toutes' ? equipe.value : undefined,
}))
const { data, status, refresh } = useAsyncData('paiements-situation', () => api<Pagine<Ligne> & { totaux: Totaux }>('/api/paiements/situation', {
  query: { ...parametres.value, page: Number(route.query.page) || 1, par_page: parPage.value },
}), { lazy: true, watch: [() => route.query] })
const lignes = computed(() => data.value?.data ?? [])
const filtresActifs = computed(() => Object.values(parametres.value).some(Boolean))

/* Sélection multiple */
const selection = ref<string[]>([])
watch(() => route.query, () => { selection.value = [] })
const choisis = computed(() => lignes.value.filter(l => selection.value.includes(l.id)))
const etatEntete = computed<boolean | 'indeterminate'>(() => lignes.value.length && lignes.value.every(l => selection.value.includes(l.id)) ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (c: boolean | 'indeterminate') => { selection.value = c === true ? lignes.value.map(l => l.id) : [] }
const basculer = (id: string, c: boolean | 'indeterminate') => { selection.value = c === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }

/* Actions */
const encaissement = ref<{ id: string, nom: string, frais: FraisDoctorant[] } | null>(null)
const encaissementOuvert = ref(false)
async function encaisser(ligne: Pick<Ligne, 'id' | 'nom_complet'>) {
  const finances = await api<{ data: Finances }>(`/api/doctorants/${ligne.id}/finances`)
  encaissement.value = { id: ligne.id, nom: ligne.nom_complet, frais: finances.data.frais }
  encaissementOuvert.value = true
}
const fraisPour = ref<Array<{ id: string, nom_complet: string }>>([])
const fraisOuvert = ref(false)
const ajouterFrais = (cibles: Ligne[]) => { fraisPour.value = cibles.map(l => ({ id: l.id, nom_complet: l.nom_complet })); fraisOuvert.value = true }
const rappel = ref(false)
async function rappeler(cibles: Ligne[]) {
  rappel.value = true
  try {
    const r = await api<{ data: { envoyes: number, ignores: number } }>('/api/paiements/rappels', { method: 'POST', body: { doctorants: cibles.map(l => l.id) } })
    toast.success(`${r.data.envoyes} rappel${r.data.envoyes > 1 ? 's' : ''} envoyé${r.data.envoyes > 1 ? 's' : ''} (plateforme et e-mail)${r.data.ignores ? `, ${r.data.ignores} ignoré(s) : rien à payer ou rappel déjà envoyé aujourd'hui` : ''}.`)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    rappel.value = false
  }
}
function exporter(cibles?: Ligne[]) {
  const q = new URLSearchParams(Object.entries(parametres.value).filter(([, v]) => v) as [string, string][])
  cibles?.forEach(l => q.append('ids[]', l.id))
  window.location.href = `/telechargements/situation-paiements.csv?${q}`
}
const apresChangement = async () => { await refresh(); emit('change') }

const pourcentage = (l: Pick<Ligne, 'du' | 'paye'>) => l.du ? Math.min(100, Math.round(l.paye / l.du * 100)) : 0
const echeancePassee = (date: string | null) => !!date && date < new Date().toISOString().slice(0, 10)
</script>

<template>
  <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
    <!-- Barre d'outils : filtres, ou actions sur la sélection -->
    <div class="flex min-h-[4.5rem] flex-col gap-3 p-4 sm:px-6 lg:flex-row lg:items-center">
      <div v-if="selection.length" class="flex flex-1 flex-wrap items-center gap-2">
        <span class="me-2 text-sm font-semibold text-slate-700">{{ selection.length }} sélectionné{{ selection.length > 1 ? 's' : '' }}</span>
        <Button v-if="selection.length === 1" size="sm" @click="encaisser(choisis[0]!)"><Wallet /> Encaisser</Button>
        <Button size="sm" variant="outline" @click="ajouterFrais(choisis)"><ReceiptText /> Ajouter un frais</Button>
        <Button size="sm" variant="outline" :disabled="rappel" @click="rappeler(choisis)"><BellRing /> Envoyer un rappel</Button>
        <Button size="sm" variant="outline" @click="exporter(choisis)"><Download /> Exporter</Button>
        <Button size="sm" variant="ghost" class="text-slate-500" @click="selection = []"><X /> Désélectionner</Button>
      </div>
      <template v-else>
        <div class="relative flex-1 lg:max-w-sm">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input v-model="recherche" type="search" placeholder="Nom ou matricule" class="pl-9" aria-label="Rechercher un doctorant" />
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:ms-auto">
          <Select :model-value="situation" @update:model-value="v => filtrer({ situation: String(v) })">
            <SelectTrigger class="w-full sm:w-48" aria-label="Situation"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="toutes">Toutes situations</SelectItem>
              <SelectItem value="en_retard">En retard</SelectItem>
              <SelectItem value="partiel">Partiel</SelectItem>
              <SelectItem value="a_payer">À payer (rien versé)</SelectItem>
              <SelectItem value="a_jour">À jour</SelectItem>
              <SelectItem value="rien">Aucun frais</SelectItem>
            </SelectContent>
          </Select>
          <Select :model-value="equipe" @update:model-value="v => filtrer({ equipe: String(v) })">
            <SelectTrigger class="w-full sm:w-44" aria-label="Équipe d'accueil"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="toutes">Toutes les équipes</SelectItem>
              <SelectItem v-for="e in equipes" :key="e.id" :value="String(e.id)">{{ e.sigle }}</SelectItem>
            </SelectContent>
          </Select>
          <Button variant="outline" class="col-span-2 sm:col-span-1" @click="exporter()"><Download /> Exporter</Button>
          <Button v-if="filtresActifs" variant="ghost" class="col-span-2 text-slate-500 sm:col-span-1" @click="recherche = ''; filtrer({ recherche: undefined, situation: undefined, equipe: undefined })"><X /> Effacer</Button>
        </div>
      </template>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse" :class="status === 'pending' && data ? 'opacity-60' : ''">
        <thead>
          <tr>
            <th :class="CELLULE_ENTETE" class="w-10 text-start"><Checkbox :model-value="etatEntete" aria-label="Tout sélectionner sur la page" @update:model-value="basculerTout" /></th>
            <th :class="CELLULE_ENTETE" class="text-start">Doctorant</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start xl:table-cell">Niveau</th>
            <th :class="CELLULE_ENTETE" class="hidden text-end lg:table-cell">Dû</th>
            <th :class="CELLULE_ENTETE" class="hidden text-end lg:table-cell">Payé</th>
            <th :class="CELLULE_ENTETE" class="text-end">Reste</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start 2xl:table-cell">Prochaine échéance</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start sm:table-cell">Situation</th>
            <th :class="CELLULE_ENTETE" class="text-end"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>

        <tbody v-if="status === 'pending' && !data">
          <tr v-for="n in 8" :key="n">
            <td :class="CELLULE"><Skeleton class="size-4" /></td>
            <td :class="CELLULE"><div class="flex items-center gap-3"><Skeleton class="size-9 rounded-full" /><div class="grid gap-1.5"><Skeleton class="h-4 w-36" /><Skeleton class="h-3 w-24" /></div></div></td>
            <td :class="CELLULE" class="hidden xl:table-cell"><Skeleton class="h-4 w-10" /></td>
            <td :class="CELLULE" class="hidden lg:table-cell"><Skeleton class="ms-auto h-4 w-24" /></td>
            <td :class="CELLULE" class="hidden lg:table-cell"><Skeleton class="ms-auto h-4 w-24" /></td>
            <td :class="CELLULE"><Skeleton class="ms-auto h-4 w-24" /></td>
            <td :class="CELLULE" class="hidden 2xl:table-cell"><Skeleton class="h-4 w-24" /></td>
            <td :class="CELLULE" class="hidden sm:table-cell"><Skeleton class="h-4 w-16" /></td>
            <td :class="CELLULE" />
          </tr>
        </tbody>

        <tbody v-else-if="lignes.length">
          <tr v-for="l in lignes" :key="l.id" class="group transition-colors hover:bg-gray-50" :class="selection.includes(l.id) ? 'bg-brand-50/50' : ''">
            <td :class="CELLULE"><Checkbox :model-value="selection.includes(l.id)" :aria-label="`Sélectionner ${l.nom_complet}`" @update:model-value="c => basculer(l.id, c)" /></td>
            <td :class="CELLULE">
              <NuxtLink :to="`/doctorants/${l.id}?section=paiements`" class="flex min-w-0 items-center gap-3">
                <span class="hidden size-9 flex-none items-center justify-center rounded-full text-xs font-semibold text-white sm:flex" :class="couleurAvatar(l.nom_complet)">{{ initiales(l.nom_complet) }}</span>
                <span class="min-w-0">
                  <span class="block max-w-[8rem] truncate text-sm font-semibold text-slate-700 group-hover:text-brand-700 sm:max-w-[14rem]">{{ l.nom_complet }}</span>
                  <span class="block max-w-[8rem] truncate text-xs text-slate-400 sm:max-w-[14rem]">{{ l.matricule }}<template v-if="l.equipe"> · {{ l.equipe }}</template><span class="xl:hidden"><template v-if="l.niveau"> · {{ l.niveau }}</template></span></span>
                  <span class="sm:hidden"><BadgePaiement :statut="l.statut" :libelle="l.statut_libelle" /></span>
                </span>
              </NuxtLink>
            </td>
            <td :class="CELLULE" class="hidden text-sm text-slate-500 xl:table-cell">{{ l.niveau || '' }}</td>
            <td :class="CELLULE" class="hidden whitespace-nowrap text-end text-sm tabular-nums text-slate-500 lg:table-cell">{{ formaterMontant(l.du) }}</td>
            <td :class="CELLULE" class="hidden text-end lg:table-cell">
              <span class="block whitespace-nowrap text-sm tabular-nums text-leaf-600">{{ formaterMontant(l.paye) }}</span>
              <span v-if="l.du" class="ms-auto mt-1 flex w-24 items-center gap-1.5" :title="`${pourcentage(l)} % payé`">
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200"><span class="block h-full rounded-full bg-leaf-400" :style="{ width: `${pourcentage(l)}%` }" /></span>
                <span class="w-8 text-end text-[11px] tabular-nums text-slate-400">{{ pourcentage(l) }}%</span>
              </span>
            </td>
            <td :class="CELLULE" class="whitespace-nowrap text-end">
              <span class="block text-sm font-semibold tabular-nums" :class="l.en_retard ? 'text-destructive' : l.reste ? 'text-slate-700' : 'text-slate-400'">{{ formaterMontant(l.reste) }}</span>
              <span v-if="l.en_retard" class="hidden text-[11px] tabular-nums text-destructive sm:block">dont {{ formaterMontant(l.en_retard) }} échus</span>
            </td>
            <td :class="CELLULE" class="hidden whitespace-nowrap text-sm 2xl:table-cell" :title="l.prochaine_echeance ? undefined : 'Rien à payer'">
              <span v-if="l.prochaine_echeance" :class="echeancePassee(l.prochaine_echeance) ? 'text-destructive' : 'text-slate-500'">{{ formaterDate(l.prochaine_echeance) }}</span>
              <span v-else class="text-slate-300">-</span>
            </td>
            <td :class="CELLULE" class="hidden sm:table-cell"><BadgePaiement :statut="l.statut" :libelle="l.statut_libelle" /></td>
            <td :class="CELLULE" class="text-end">
              <div class="flex items-center justify-end gap-1">
                <Button v-if="l.reste" size="sm" variant="outline" class="hidden 2xl:inline-flex" :aria-label="`Encaisser pour ${l.nom_complet}`" @click="encaisser(l)"><Wallet /> Encaisser</Button>
                <Button v-if="l.reste" size="icon-sm" variant="outline" class="hidden md:inline-flex 2xl:hidden" :aria-label="`Encaisser pour ${l.nom_complet}`" title="Encaisser" @click="encaisser(l)"><Wallet /></Button>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon-sm" class="text-slate-500" :aria-label="`Actions pour ${l.nom_complet}`"><MoreHorizontal /></Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem v-if="l.reste" @select="encaisser(l)"><Wallet /> Encaisser</DropdownMenuItem>
                    <DropdownMenuItem @select="ajouterFrais([l])"><ReceiptText /> Ajouter un frais</DropdownMenuItem>
                    <DropdownMenuItem v-if="l.reste" @select="rappeler([l])"><BellRing /> Envoyer un rappel</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child><NuxtLink :to="`/doctorants/${l.id}?section=paiements`"><Eye /> Voir le dossier</NuxtLink></DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </td>
          </tr>
        </tbody>

        <tfoot v-if="data && lignes.length" class="bg-gray-50/60">
          <tr class="text-sm">
            <td :class="CELLULE" />
            <td :class="CELLULE" class="font-semibold text-slate-600">Total<span class="font-normal text-slate-400"> ({{ data.totaux.doctorants }} doctorant{{ data.totaux.doctorants > 1 ? 's' : '' }}{{ filtresActifs ? ', filtres appliqués' : '' }})</span></td>
            <td :class="CELLULE" class="hidden xl:table-cell" />
            <td :class="CELLULE" class="hidden whitespace-nowrap text-end font-semibold tabular-nums text-slate-600 lg:table-cell">{{ formaterMontant(data.totaux.du) }}</td>
            <td :class="CELLULE" class="hidden whitespace-nowrap text-end font-semibold tabular-nums text-leaf-600 lg:table-cell">{{ formaterMontant(data.totaux.paye) }}</td>
            <td :class="CELLULE" class="whitespace-nowrap text-end">
              <span class="block font-bold tabular-nums text-slate-700">{{ formaterMontant(data.totaux.reste) }}</span>
              <span v-if="data.totaux.en_retard" class="hidden text-[11px] tabular-nums text-destructive sm:block">dont {{ formaterMontant(data.totaux.en_retard) }} échus</span>
            </td>
            <td :class="CELLULE" class="hidden 2xl:table-cell" />
            <td :class="CELLULE" class="hidden sm:table-cell" />
            <td :class="CELLULE" />
          </tr>
        </tfoot>
      </table>
    </div>

    <div v-if="data && !lignes.length" class="p-6">
      <EtatVide :icone="Wallet" titre="Aucun doctorant" texte="Aucun doctorant ne correspond à ces filtres." class="border-none" />
    </div>

    <div v-if="data && lignes.length" class="flex flex-col gap-3 px-4 py-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <div class="flex items-center gap-2">
        <span>Afficher</span>
        <Select :model-value="parPage" @update:model-value="v => filtrer({ par_page: v === '20' ? undefined : String(v) })">
          <SelectTrigger size="sm" class="w-20" aria-label="Lignes par page"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem v-for="n in ['20', '50', '100']" :key="n" :value="n">{{ n }}</SelectItem></SelectContent>
        </Select>
        <span class="hidden sm:inline">{{ data.meta.from }} à {{ data.meta.to }} sur {{ data.meta.total }}</span>
      </div>
      <div v-if="data.meta.last_page > 1" class="flex items-center gap-2">
        <Button variant="outline" size="sm" :disabled="data.meta.current_page <= 1" @click="filtrer({ page: data.meta.current_page - 1 > 1 ? data.meta.current_page - 1 : undefined })"><ChevronLeft /> Précédent</Button>
        <span class="tabular-nums">{{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
        <Button variant="outline" size="sm" :disabled="data.meta.current_page >= data.meta.last_page" @click="filtrer({ page: data.meta.current_page + 1 })">Suivant <ChevronRight /></Button>
      </div>
    </div>

    <DialoguePaiement v-if="encaissement" v-model:open="encaissementOuvert" :doctorant-id="encaissement.id" :nom-complet="encaissement.nom" :frais="encaissement.frais" @enregistre="apresChangement" />
    <DialogueFrais v-model:open="fraisOuvert" :doctorants="fraisPour" @ajoute="apresChangement" />
  </div>
</template>

<script setup lang="ts">
import { Ban, ChevronLeft, ChevronRight, Clock, Download, Eye, FileText, Loader2, MoreHorizontal, Paperclip, Printer, Search, TriangleAlert, Wallet, X } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import type { Pagine, Paiement } from '~/types'

/**
 * Journal des paiements : sélection multiple (impression des reçus en un PDF, export), reçus de versement, annulation.
 */
const emit = defineEmits<{ change: [] }>()
const api = useApi()
const { route, valeur, filtrer } = useFiltresUrl()

const recherche = ref(valeur('recherche').value)
watch(recherche, useDebounceFn((t: string) => filtrer({ recherche: t.trim() || undefined }), 350))
const mode = valeur('mode', 'tous')
const justificatif = valeur('justificatif', 'tous')
const du = valeur('du')
const au = valeur('au')
const parPage = valeur('par_page', '20')

const parametres = computed(() => ({
  recherche: valeur('recherche').value || undefined,
  mode: mode.value !== 'tous' ? mode.value : undefined,
  justificatif: justificatif.value !== 'tous' ? justificatif.value : undefined,
  du: du.value || undefined,
  au: au.value || undefined,
}))
const { data, status, refresh } = useAsyncData('paiements-journal', () => api<Pagine<Paiement> & { total_encaisse: number }>('/api/paiements', {
  query: { ...parametres.value, page: Number(route.query.page) || 1, par_page: parPage.value },
}), { lazy: true, watch: [() => route.query] })
const lignes = computed(() => data.value?.data ?? [])
const filtresActifs = computed(() => Object.values(parametres.value).some(Boolean))

/* Sélection multiple */
const selection = ref<number[]>([])
watch(() => route.query, () => { selection.value = [] })
const etatEntete = computed<boolean | 'indeterminate'>(() => lignes.value.length && lignes.value.every(p => selection.value.includes(p.id)) ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (c: boolean | 'indeterminate') => { selection.value = c === true ? lignes.value.map(p => p.id) : [] }
const basculer = (id: number, c: boolean | 'indeterminate') => { selection.value = c === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }
const totalSelection = computed(() => lignes.value.filter(p => selection.value.includes(p.id) && !p.annule_le).reduce((s, p) => s + p.montant, 0))

const imprimer = () => window.open(`/telechargements/paiements/recus.pdf?${selection.value.map(id => `ids[]=${id}`).join('&')}`, '_blank', 'noopener')
function exporter(ids?: number[]) {
  const q = new URLSearchParams(Object.entries(parametres.value).filter(([, v]) => v) as [string, string][])
  ids?.forEach(id => q.append('ids[]', String(id)))
  window.location.href = `/telechargements/paiements.csv?${q}`
}

/* Annulation */
const aAnnuler = ref<Paiement | null>(null)
const dialogue = ref(false)
const motif = ref('')
const erreur = ref('')
const envoi = ref(false)
function demanderAnnulation(p: Paiement) { aAnnuler.value = p; motif.value = ''; erreur.value = ''; dialogue.value = true }
async function annuler() {
  if (!aAnnuler.value) return
  envoi.value = true
  try {
    await api(`/api/paiements/${aAnnuler.value.id}/annuler`, { method: 'POST', body: { motif: motif.value } })
    toast.success(`Paiement ${aAnnuler.value.numero_recu} annulé : les frais qu'il réglait sont de nouveau dus.`)
    dialogue.value = false
    await refresh()
    emit('change')
  }
  catch (e) {
    erreur.value = erreursDeValidation(e).motif ?? messageErreur(e)
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
    <div class="flex min-h-[4.5rem] flex-col gap-3 p-4 sm:px-6 lg:flex-row lg:items-center">
      <div v-if="selection.length" class="flex flex-1 flex-wrap items-center gap-2">
        <span class="me-2 text-sm font-semibold text-slate-700">{{ selection.length }} sélectionné{{ selection.length > 1 ? 's' : '' }} · {{ formaterMontant(totalSelection) }}</span>
        <Button size="sm" @click="imprimer"><Printer /> Imprimer les reçus</Button>
        <Button size="sm" variant="outline" @click="exporter(selection)"><Download /> Exporter</Button>
        <Button size="sm" variant="ghost" class="text-slate-500" @click="selection = []"><X /> Désélectionner</Button>
      </div>
      <template v-else>
        <div class="relative flex-1 lg:max-w-xs">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input v-model="recherche" type="search" placeholder="Reçu, référence, nom" class="pl-9" aria-label="Rechercher un paiement" />
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:ms-auto">
          <Input type="date" :model-value="du" aria-label="Du" title="Du" class="sm:w-38" @update:model-value="v => filtrer({ du: String(v) || undefined })" />
          <Input type="date" :model-value="au" aria-label="Au" title="Au" class="sm:w-38" @update:model-value="v => filtrer({ au: String(v) || undefined })" />
          <Select :model-value="mode" @update:model-value="v => filtrer({ mode: String(v) })">
            <SelectTrigger class="w-full sm:w-44" aria-label="Mode"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="tous">Tous les modes</SelectItem>
              <SelectItem value="virement">Versement bancaire</SelectItem>
              <SelectItem value="especes">Espèces</SelectItem>
              <SelectItem value="mobile_money">Mobile Money</SelectItem>
              <SelectItem value="cheque">Chèque</SelectItem>
            </SelectContent>
          </Select>
          <Select :model-value="justificatif" @update:model-value="v => filtrer({ justificatif: String(v) })">
            <SelectTrigger class="w-full sm:w-48" aria-label="Reçu de versement"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="tous">Avec ou sans reçu</SelectItem>
              <SelectItem value="manquant">Reçu de versement manquant</SelectItem>
            </SelectContent>
          </Select>
          <Button variant="outline" @click="exporter()"><Download /> Exporter</Button>
          <Button v-if="filtresActifs" variant="ghost" class="text-slate-500" @click="recherche = ''; filtrer({ recherche: undefined, mode: undefined, justificatif: undefined, du: undefined, au: undefined })"><X /> Effacer</Button>
        </div>
      </template>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse" :class="status === 'pending' && data ? 'opacity-60' : ''">
        <thead>
          <tr>
            <th :class="CELLULE_ENTETE" class="w-10 text-start"><Checkbox :model-value="etatEntete" aria-label="Tout sélectionner sur la page" @update:model-value="basculerTout" /></th>
            <th :class="CELLULE_ENTETE" class="text-start">Date et reçu</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start md:table-cell">Doctorant</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start 2xl:table-cell">Frais réglés</th>
            <th :class="CELLULE_ENTETE" class="hidden text-start lg:table-cell">Mode et référence</th>
            <th :class="CELLULE_ENTETE" class="hidden text-center sm:table-cell">Reçu de versement</th>
            <th :class="CELLULE_ENTETE" class="text-end">Montant</th>
            <th :class="CELLULE_ENTETE" class="text-end"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>

        <tbody v-if="status === 'pending' && !data">
          <tr v-for="n in 8" :key="n">
            <td :class="CELLULE"><Skeleton class="size-4" /></td>
            <td :class="CELLULE"><div class="grid gap-1.5"><Skeleton class="h-4 w-28" /><Skeleton class="h-3 w-32" /></div></td>
            <td :class="CELLULE" class="hidden md:table-cell"><Skeleton class="h-4 w-36" /></td>
            <td :class="CELLULE" class="hidden 2xl:table-cell"><Skeleton class="h-4 w-32" /></td>
            <td :class="CELLULE" class="hidden lg:table-cell"><Skeleton class="h-4 w-28" /></td>
            <td :class="CELLULE" class="hidden sm:table-cell"><Skeleton class="mx-auto size-5" /></td>
            <td :class="CELLULE"><Skeleton class="ms-auto h-4 w-24" /></td>
            <td :class="CELLULE" />
          </tr>
        </tbody>

        <tbody v-else-if="lignes.length">
          <tr v-for="p in lignes" :key="p.id" class="group transition-colors hover:bg-gray-50" :class="[selection.includes(p.id) ? 'bg-brand-50/50' : '', p.annule_le ? 'text-slate-400' : '']">
            <td :class="CELLULE"><Checkbox :model-value="selection.includes(p.id)" :aria-label="`Sélectionner ${p.numero_recu}`" @update:model-value="c => basculer(p.id, c)" /></td>
            <td :class="CELLULE">
              <span class="block whitespace-nowrap text-sm font-semibold" :class="p.annule_le ? 'line-through' : 'text-slate-700'">{{ formaterDate(p.date_paiement) }}</span>
              <span v-if="p.enregistre_le" class="flex items-center gap-1 whitespace-nowrap text-xs text-slate-500" :title="`Enregistré le ${formaterDateHeure(p.enregistre_le)}${p.saisi_par ? ` par ${p.saisi_par}` : ''}`">
                <Clock class="size-3 shrink-0" aria-hidden="true" />{{ momentHumain(p.enregistre_le) }}
              </span>
              <span class="block font-mono text-xs text-slate-400">{{ p.numero_recu }}</span>
              <span class="block max-w-[10rem] truncate text-xs text-slate-500 md:hidden">{{ p.doctorant?.nom_complet }}</span>
              <span v-if="p.annule_le" class="mt-0.5 inline-block rounded bg-red-50 px-1.5 text-[11px] font-medium text-destructive">Annulé</span>
            </td>
            <td :class="CELLULE" class="hidden md:table-cell">
              <NuxtLink :to="`/doctorants/${p.doctorant?.id}?section=paiements`" class="block max-w-[14rem] truncate text-sm font-medium text-slate-600 hover:text-brand-700">{{ p.doctorant?.nom_complet }}</NuxtLink>
              <span class="line-clamp-1 max-w-[14rem] text-xs text-slate-400 2xl:hidden">{{ p.imputations?.map(i => i.libelle).join(', ') }}</span>
              <span class="block text-xs text-slate-400">{{ p.doctorant?.matricule }}</span>
            </td>
            <td :class="CELLULE" class="hidden max-w-[14rem] text-sm text-slate-500 2xl:table-cell">
              <span class="line-clamp-2">{{ p.imputations?.map(i => i.libelle).join(', ') }}</span>
            </td>
            <td :class="CELLULE" class="hidden text-sm lg:table-cell">
              <span class="block text-slate-600">{{ p.mode_libelle }}</span>
              <span v-if="p.reference" class="block max-w-[10rem] truncate font-mono text-xs text-slate-400" :title="p.reference">{{ p.reference }}</span>
            </td>
            <td :class="CELLULE" class="hidden text-center sm:table-cell">
              <a v-if="p.justificatif" :href="p.justificatif.url" target="_blank" rel="noopener" class="inline-flex size-8 items-center justify-center rounded-md text-leaf-600 hover:bg-leaf-50" :aria-label="`Reçu de versement ${p.numero_recu}`" title="Voir le reçu de versement"><Paperclip class="size-4" /></a>
              <span v-else-if="p.mode !== 'especes' && !p.annule_le" class="inline-flex size-8 items-center justify-center text-sun-600" title="Reçu de versement manquant"><TriangleAlert class="size-4" /></span>
              <span v-else class="text-slate-300">-</span>
            </td>
            <td :class="CELLULE" class="whitespace-nowrap text-end text-sm font-semibold tabular-nums">
              <span :class="p.annule_le ? 'line-through' : 'text-slate-700'">{{ formaterMontant(p.montant) }}</span>
            </td>
            <td :class="CELLULE" class="text-end">
              <div class="flex items-center justify-end gap-1">
                <Button variant="ghost" size="icon-sm" class="hidden text-slate-400 hover:text-brand-700 md:inline-flex" as-child>
                  <a :href="p.url_recu" target="_blank" rel="noopener" :aria-label="`Reçu ${p.numero_recu}`" title="Reçu PDF"><FileText /></a>
                </Button>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon-sm" class="text-slate-500" :aria-label="`Actions pour ${p.numero_recu}`"><MoreHorizontal /></Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem as-child><a :href="p.url_recu" target="_blank" rel="noopener"><FileText /> Reçu PDF</a></DropdownMenuItem>
                    <DropdownMenuItem v-if="p.justificatif" as-child><a :href="p.justificatif.url" target="_blank" rel="noopener"><Paperclip /> Reçu de versement</a></DropdownMenuItem>
                    <DropdownMenuItem as-child><NuxtLink :to="`/doctorants/${p.doctorant?.id}?section=paiements`"><Eye /> Voir le dossier</NuxtLink></DropdownMenuItem>
                    <template v-if="p.peut_annuler">
                      <DropdownMenuSeparator />
                      <DropdownMenuItem class="text-destructive focus:text-destructive" @select="demanderAnnulation(p)"><Ban /> Annuler le paiement</DropdownMenuItem>
                    </template>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </td>
          </tr>
        </tbody>

        <tfoot v-if="data && lignes.length" class="bg-gray-50/60">
          <tr class="text-sm">
            <td :class="CELLULE" />
            <td :class="CELLULE" class="font-semibold text-slate-600">Total encaissé<span class="font-normal text-slate-400">{{ filtresActifs ? ' (filtres appliqués)' : '' }}</span></td>
            <td :class="CELLULE" class="hidden md:table-cell" />
            <td :class="CELLULE" class="hidden 2xl:table-cell" />
            <td :class="CELLULE" class="hidden lg:table-cell" />
            <td :class="CELLULE" class="hidden sm:table-cell" />
            <td :class="CELLULE" class="whitespace-nowrap text-end font-bold tabular-nums text-slate-700">{{ formaterMontant(data.total_encaisse) }}</td>
            <td :class="CELLULE" />
          </tr>
        </tfoot>
      </table>
    </div>

    <div v-if="data && !lignes.length" class="p-6">
      <EtatVide :icone="Wallet" titre="Aucun paiement" texte="Aucun paiement ne correspond à ces filtres." class="border-none" />
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

    <Dialog v-model:open="dialogue">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Annuler le paiement {{ aAnnuler?.numero_recu }} ?</DialogTitle>
          <DialogDescription>Le paiement reste au journal, marqué « annulé ». Les frais qu'il réglait redeviennent dus.</DialogDescription>
        </DialogHeader>
        <ChampFormulaire libelle="Motif" pour="motif-annulation" :erreur="erreur" requis><Textarea id="motif-annulation" v-model="motif" rows="2" /></ChampFormulaire>
        <DialogFooter>
          <Button variant="outline" @click="dialogue = false">Garder</Button>
          <Button variant="destructive" :disabled="envoi" @click="annuler"><Loader2 v-if="envoi" class="animate-spin" /><Ban v-else /> Annuler le paiement</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

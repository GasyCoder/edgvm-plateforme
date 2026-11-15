<script setup lang="ts">
import { Ban, ChevronLeft, ChevronRight, ExternalLink, FileStack, Loader2, Printer, Search, Settings2, Stamp, X } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import type { Acte, ModeleActe, Pagine, Ressource } from '~/types'

useHead({ title: 'Actes administratifs' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { estSuperAdmin, estDoctorant } = useAuth()
const { annees } = useReferentiels()
const { data: modeles } = useAsyncData('modeles-disponibles', () => estSuperAdmin.value ? api<Ressource<ModeleActe[]>>('/api/modeles-actes/disponibles').then(r => r.data) : Promise.resolve([] as ModeleActe[]), { lazy: true, default: () => [] as ModeleActe[] })

const filtres = computed(() => ({
  recherche: (route.query.recherche as string) || '',
  modele: (route.query.modele as string) || 'tous',
  annee: (route.query.annee as string) || 'toutes',
  statut: (route.query.statut as string) || 'tous',
  page: Number(route.query.page) || 1,
}))
const { data, status, refresh } = useAsyncData('actes', () => api<Pagine<Acte>>('/api/actes', {
  query: {
    recherche: filtres.value.recherche || undefined,
    modele: filtres.value.modele !== 'tous' ? filtres.value.modele : undefined,
    annee: filtres.value.annee !== 'toutes' ? filtres.value.annee : undefined,
    statut: filtres.value.statut !== 'tous' ? filtres.value.statut : undefined,
    page: filtres.value.page,
  },
}), { lazy: true, watch: [() => route.query] })

function filtrer(valeurs: Record<string, string | number | undefined>) {
  const query: Record<string, unknown> = { ...route.query, page: undefined, ...valeurs }
  router.replace({ query: Object.fromEntries(Object.entries(query).filter(([, v]) => ![undefined, '', 'tous', 'toutes'].includes(v as string))) as Record<string, string> })
}
const recherche = ref(filtres.value.recherche)
watch(recherche, useDebounceFn((terme: string) => filtrer({ recherche: terme.trim() || undefined }), 350))

/* Sélection : impression groupée */
const selection = ref<number[]>([])
watch(() => route.query, () => { selection.value = [] })
const lignes = computed(() => data.value?.data ?? [])
const etatEntete = computed<boolean | 'indeterminate'>(() => lignes.value.length && lignes.value.every(a => selection.value.includes(a.id)) ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (coche: boolean | 'indeterminate') => { selection.value = coche === true ? lignes.value.map(a => a.id) : [] }
const basculer = (id: number, coche: boolean | 'indeterminate') => { selection.value = coche === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }
const imprimerSelection = () => window.open(`/telechargements/actes.pdf?${selection.value.map(id => `ids[]=${id}`).join('&')}`, '_blank', 'noopener')

/* Annulation */
const aAnnuler = ref<Acte | null>(null)
const dialogueAnnulation = ref(false)
const motif = ref('')
const erreurMotif = ref('')
const annulation = ref(false)
function demanderAnnulation(acte: Acte) {
  aAnnuler.value = acte
  motif.value = ''
  erreurMotif.value = ''
  dialogueAnnulation.value = true
}
async function annuler() {
  if (!aAnnuler.value) return
  annulation.value = true
  try {
    await api(`/api/actes/${aAnnuler.value.id}/annuler`, { method: 'POST', body: { motif: motif.value } })
    toast.success(`Acte n° ${aAnnuler.value.numero} annulé. La vérification en ligne l'indique désormais.`)
    dialogueAnnulation.value = false
    await refresh()
  }
  catch (e) {
    erreurMotif.value = erreursDeValidation(e).motif ?? messageErreur(e)
  }
  finally {
    annulation.value = false
  }
}

const description = computed(() => estDoctorant.value
  ? 'Vos attestations et autres actes délivrés par l\'École.'
  : data.value ? `${data.value.meta.total} acte(s). Pour délivrer, sélectionnez des doctorants dans la liste ou ouvrez un dossier.` : '')
const th = 'border-y border-gray-300 px-1.5 py-2.5 text-start text-sm font-normal text-slate-400 first:ps-4 last:pe-3 sm:px-2 sm:first:ps-6 sm:last:pe-6'
const td = 'border-b border-gray-200 px-1.5 py-3.5 first:ps-4 last:pe-3 sm:px-2 sm:first:ps-6 sm:last:pe-6'
</script>

<template>
  <div>
    <EnTetePage :titre="estDoctorant ? 'Mes actes' : 'Actes administratifs'" :description="description">
      <template v-if="estSuperAdmin" #actions>
        <Button variant="outline" class="bg-white" as-child><NuxtLink to="/actes/modeles"><Settings2 /> Modèles et signature</NuxtLink></Button>
        <Button as-child><NuxtLink to="/doctorants"><Stamp /> Délivrer des actes</NuxtLink></Button>
      </template>
    </EnTetePage>

    <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
      <div class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center">
        <div v-if="selection.length" class="flex flex-1 flex-wrap items-center gap-3">
          <span class="text-sm font-semibold text-slate-700">{{ selection.length }} sélectionné(s)</span>
          <Button size="sm" variant="outline" @click="imprimerSelection"><Printer /> Imprimer la sélection</Button>
          <Button size="sm" variant="ghost" @click="selection = []"><X /> Désélectionner</Button>
        </div>
        <template v-else>
          <div class="relative flex-1 lg:max-w-sm">
            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="recherche" type="search" :placeholder="estDoctorant ? 'Numéro' : 'Numéro, nom ou matricule'" class="pl-9" aria-label="Rechercher un acte" />
          </div>
          <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:ms-auto">
            <Select v-if="estSuperAdmin" :model-value="filtres.modele" @update:model-value="v => filtrer({ modele: String(v) })">
              <SelectTrigger class="w-full sm:w-52" aria-label="Type d'acte"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous les actes</SelectItem>
                <SelectItem v-for="m in modeles" :key="m.id" :value="String(m.id)">{{ m.nom }}</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filtres.annee" @update:model-value="v => filtrer({ annee: String(v) })">
              <SelectTrigger class="w-full sm:w-40" aria-label="Année universitaire"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="toutes">Toutes années</SelectItem>
                <SelectItem v-for="annee in annees" :key="annee.id" :value="String(annee.id)">{{ annee.libelle }}</SelectItem>
              </SelectContent>
            </Select>
            <Select v-if="!estDoctorant" :model-value="filtres.statut" @update:model-value="v => filtrer({ statut: String(v) })">
              <SelectTrigger class="w-full sm:w-36" aria-label="Statut"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous statuts</SelectItem>
                <SelectItem value="valide">Valides</SelectItem>
                <SelectItem value="annule">Annulés</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </template>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th :class="th" class="w-10"><Checkbox :model-value="etatEntete" aria-label="Tout sélectionner" @update:model-value="basculerTout" /></th>
              <th :class="th">Acte</th>
              <th v-if="!estDoctorant" :class="th" class="hidden md:table-cell">Doctorant</th>
              <th :class="th" class="hidden lg:table-cell">Année</th>
              <th :class="th" class="hidden sm:table-cell">Délivré le</th>
              <th :class="th" class="hidden md:table-cell">Statut</th>
              <th :class="th"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody v-if="status === 'pending' && !data">
            <tr v-for="ligne in 6" :key="ligne">
              <td :class="td"><Skeleton class="size-4" /></td>
              <td :class="td"><div class="grid gap-1.5"><Skeleton class="h-4 w-44" /><Skeleton class="h-3 w-28" /></div></td>
              <td v-if="!estDoctorant" :class="td" class="hidden md:table-cell"><Skeleton class="h-4 w-36" /></td>
              <td :class="td" class="hidden lg:table-cell"><Skeleton class="h-4 w-20" /></td>
              <td :class="td" class="hidden sm:table-cell"><Skeleton class="h-4 w-24" /></td>
              <td :class="td" class="hidden md:table-cell"><Skeleton class="h-4 w-14" /></td>
              <td :class="td" />
            </tr>
          </tbody>
          <tbody v-else-if="lignes.length">
            <tr v-for="acte in lignes" :key="acte.id" class="group hover:bg-gray-50" :class="acte.annule_le ? 'text-slate-400' : ''">
              <td :class="td"><Checkbox :model-value="selection.includes(acte.id)" :aria-label="`Sélectionner ${acte.numero}`" @update:model-value="c => basculer(acte.id, c)" /></td>
              <td :class="td">
                <a :href="acte.url_pdf" target="_blank" rel="noopener" class="block min-w-0">
                  <span class="block max-w-[11rem] truncate text-sm font-semibold text-slate-700 group-hover:text-brand-700 sm:max-w-xs" :class="acte.annule_le ? 'line-through' : ''">{{ acte.modele?.nom }}</span>
                  <span class="block font-mono text-xs text-slate-400">{{ acte.numero }}</span>
                  <span v-if="!estDoctorant" class="block max-w-[11rem] truncate text-xs text-slate-500 md:hidden">{{ acte.doctorant?.nom_complet }}</span>
                </a>
              </td>
              <td v-if="!estDoctorant" :class="td" class="hidden md:table-cell">
                <NuxtLink :to="`/doctorants/${acte.doctorant?.id}`" class="text-sm font-medium text-slate-600 hover:text-brand-700">{{ acte.doctorant?.nom_complet }}</NuxtLink>
                <span class="block text-xs text-slate-400">{{ acte.doctorant?.matricule }}</span>
              </td>
              <td :class="td" class="hidden text-sm text-slate-500 lg:table-cell">{{ acte.annee || '' }}</td>
              <td :class="td" class="hidden whitespace-nowrap text-sm text-slate-500 sm:table-cell">
                {{ formaterDate(acte.delivre_le) }}
                <span v-if="acte.delivre_par" class="block text-xs text-slate-400">{{ acte.delivre_par }}</span>
              </td>
              <td :class="td" class="hidden md:table-cell">
                <span v-if="acte.annule_le" class="inline-flex items-center gap-1.5 text-sm font-medium text-destructive" :title="acte.motif_annulation ?? undefined"><span class="size-1.5 rounded-full bg-current" />Annulé</span>
                <span v-else class="inline-flex items-center gap-1.5 text-sm font-medium text-leaf-600"><span class="size-1.5 rounded-full bg-current" />Valide</span>
              </td>
              <td :class="td" class="text-end">
                <div class="flex justify-end gap-1">
                  <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
                    <a :href="acte.url_pdf" target="_blank" rel="noopener" :aria-label="`Ouvrir l'acte ${acte.numero}`" title="Ouvrir le PDF"><ExternalLink /></a>
                  </Button>
                  <Button v-if="acte.peut_annuler" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Annuler l'acte ${acte.numero}`" title="Annuler l'acte" @click="demanderAnnulation(acte)"><Ban /></Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data && !lignes.length" class="p-6">
        <EtatVide :icone="FileStack" titre="Aucun acte" :texte="estSuperAdmin ? 'Délivrez un acte depuis un dossier doctorant, ou sélectionnez plusieurs doctorants dans la liste.' : 'Les actes délivrés par l\'École apparaîtront ici.'" class="border-none" />
      </div>

      <div v-if="data && data.meta.last_page > 1" class="flex items-center justify-between gap-3 p-4 text-sm text-slate-500 sm:px-6">
        <span>{{ data.meta.from }} à {{ data.meta.to }} sur {{ data.meta.total }}</span>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" :disabled="data.meta.current_page <= 1" @click="filtrer({ page: data.meta.current_page - 1 > 1 ? data.meta.current_page - 1 : undefined })"><ChevronLeft /> Précédent</Button>
          <Button variant="outline" size="sm" :disabled="data.meta.current_page >= data.meta.last_page" @click="filtrer({ page: data.meta.current_page + 1 })">Suivant <ChevronRight /></Button>
        </div>
      </div>
    </div>

    <Dialog v-model:open="dialogueAnnulation">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Annuler l'acte n° {{ aAnnuler?.numero }} ?</DialogTitle>
          <DialogDescription>L'acte reste dans le registre, marqué « annulé » sur le PDF et sur la page de vérification. Pour corriger une erreur, délivrez ensuite un nouvel acte.</DialogDescription>
        </DialogHeader>
        <ChampFormulaire libelle="Motif" pour="motif" :erreur="erreurMotif" requis>
          <Textarea id="motif" v-model="motif" rows="3" placeholder="Par exemple : erreur sur le niveau d'inscription" />
        </ChampFormulaire>
        <DialogFooter>
          <Button variant="outline" @click="dialogueAnnulation = false">Garder l'acte</Button>
          <Button variant="destructive" :disabled="annulation" @click="annuler"><Loader2 v-if="annulation" class="animate-spin" /><Ban v-else /> Annuler l'acte</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { Ban, CircleCheck, Loader2, Search, UserPlus } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Seance } from '~/types'

/**
 * Choix des doctorants à programmer : filtres par niveau et par équipe, sélection multiple.
 * Ceux qui ne peuvent pas passer (paiement non soldé...) restent visibles, grisés, avec la raison.
 */
interface Candidat { id: string, nom_complet: string, matricule: string, niveau: string | null, equipe: string | null, sujet: string | null, deja_programme: boolean, raison: string | null }

const props = defineProps<{ seance: Seance }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ ajoute: [seance: Seance] }>()

const api = useApi()
const { equipes } = useReferentiels()
const filtres = reactive({ niveau: 'tous', equipe: 'toutes', recherche: '' })
const candidats = ref<Candidat[]>([])
const selection = ref<string[]>([])
const chargement = ref(false)
const envoi = ref(false)
const refuses = ref<{ doctorant: string, raison: string }[]>([])

async function charger() {
  chargement.value = true
  try {
    const query = {
      niveau: filtres.niveau !== 'tous' ? filtres.niveau : undefined,
      equipe: filtres.equipe !== 'toutes' ? filtres.equipe : undefined,
      recherche: filtres.recherche.trim() || undefined,
    }
    candidats.value = (await api<{ data: Candidat[] }>(`/api/seances/${props.seance.id}/candidats`, { query })).data
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    chargement.value = false
  }
}
const chargerPlusTard = useDebounceFn(charger, 300)
watch(() => [filtres.niveau, filtres.equipe], charger)
watch(() => filtres.recherche, chargerPlusTard)
watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  selection.value = []
  refuses.value = []
  charger()
}, { immediate: true })

const possibles = computed(() => candidats.value.filter(c => !c.raison && !c.deja_programme))
const exclus = computed(() => candidats.value.filter(c => c.raison && !c.deja_programme).length)
const toutCoche = computed<boolean | 'indeterminate'>(() => possibles.value.length && possibles.value.every(c => selection.value.includes(c.id)) ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (coche: boolean | 'indeterminate') => { selection.value = coche === true ? possibles.value.map(c => c.id) : [] }
const basculer = (id: string, coche: boolean | 'indeterminate') => { selection.value = coche === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }

async function ajouter() {
  envoi.value = true
  try {
    const reponse = await api<{ data: Seance, ajoutes: number, refuses: { doctorant: string, raison: string }[], message: string }>(`/api/seances/${props.seance.id}/passages`, { method: 'POST', body: { doctorants: selection.value } })
    emit('ajoute', reponse.data)
    if (reponse.ajoutes) toast.success(reponse.message)
    refuses.value = reponse.refuses
    if (!reponse.refuses.length) ouvert.value = false
    else await charger()
    selection.value = []
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="flex max-h-[90dvh] flex-col sm:max-w-3xl">
      <DialogHeader>
        <DialogTitle>Programmer des doctorants</DialogTitle>
        <DialogDescription>
          Ils passent à la suite des passages déjà prévus ({{ seance.duree_passage }} min chacun{{ seance.pause ? `, ${seance.pause} min de pause` : '' }}).
          <template v-if="seance.exige_paiement_solde"> Réservé aux doctorants à jour de paiement.</template>
        </DialogDescription>
      </DialogHeader>

      <div class="grid grid-cols-2 gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_9rem]">
        <div class="relative col-span-2 sm:col-span-1">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input v-model="filtres.recherche" type="search" placeholder="Nom ou matricule" class="pl-9" aria-label="Rechercher" />
        </div>
        <Select v-model="filtres.niveau">
          <SelectTrigger class="w-full" aria-label="Niveau"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="tous">Tous niveaux</SelectItem>
            <SelectItem v-for="n in ['D1', 'D2', 'D3']" :key="n" :value="n">{{ n }}</SelectItem>
            <SelectItem value="derogation">Dérogation</SelectItem>
          </SelectContent>
        </Select>
        <Select v-model="filtres.equipe">
          <SelectTrigger class="w-full" aria-label="Équipe"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="toutes">Toutes les EAD</SelectItem>
            <SelectItem v-for="e in equipes" :key="e.id" :value="String(e.id)">{{ e.sigle }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="refuses.length" class="max-h-32 overflow-y-auto rounded-md border border-sun-300 bg-sun-50 px-3 py-2 text-sm text-sun-700">
        <p class="font-semibold">Non programmés :</p>
        <p v-for="r in refuses" :key="r.doctorant">{{ r.doctorant }} : {{ r.raison }}</p>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto rounded-md border">
        <div class="sticky top-0 z-10 flex items-center gap-3 border-b bg-gray-50 px-3 py-2 text-xs text-slate-500">
          <Checkbox :model-value="toutCoche" :disabled="!possibles.length" aria-label="Tout sélectionner" @update:model-value="basculerTout" />
          <span>{{ possibles.length }} possible(s)<template v-if="exclus">, {{ exclus }} exclu(s)</template></span>
          <Loader2 v-if="chargement" class="ms-auto size-4 animate-spin text-slate-400" />
        </div>
        <ul class="divide-y">
          <li v-for="c in candidats" :key="c.id" class="flex items-start gap-3 px-3 py-2.5" :class="c.raison || c.deja_programme ? 'bg-gray-50/60' : ''">
            <Checkbox v-if="!c.raison && !c.deja_programme" :model-value="selection.includes(c.id)" :aria-label="`Choisir ${c.nom_complet}`" class="mt-0.5" @update:model-value="v => basculer(c.id, v)" />
            <CircleCheck v-else-if="c.deja_programme" class="mt-0.5 size-4 flex-none text-leaf-600" aria-hidden="true" />
            <Ban v-else class="mt-0.5 size-4 flex-none text-slate-300" aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold" :class="c.raison ? 'text-slate-400' : 'text-slate-700'">
                {{ c.nom_complet }}
                <span class="ms-1 text-xs font-normal text-slate-400">{{ [c.niveau, c.equipe].filter(Boolean).join(', ') }}</span>
              </p>
              <p v-if="c.deja_programme" class="text-xs text-leaf-600">Déjà programmé dans cette séance</p>
              <p v-else-if="c.raison" class="text-xs text-destructive">{{ c.raison }}</p>
              <p v-else-if="c.sujet" class="line-clamp-1 text-xs text-slate-400">{{ c.sujet }}</p>
            </div>
          </li>
          <li v-if="!candidats.length && !chargement" class="px-3 py-6 text-center text-sm text-slate-400">Aucun doctorant actif avec ces filtres.</li>
        </ul>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Fermer</Button>
        <Button :disabled="envoi || !selection.length" @click="ajouter"><Loader2 v-if="envoi" class="animate-spin" /><UserPlus v-else /> Programmer {{ selection.length || '' }} doctorant{{ selection.length > 1 ? 's' : '' }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

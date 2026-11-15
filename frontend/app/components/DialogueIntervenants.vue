<script setup lang="ts">
import { CalendarX2, Loader2, Search, UserCheck } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { PassageSeance, RoleIntervenant, Seance } from '~/types'

/**
 * Affecte des modérateurs ou des membres du jury aux passages choisis.
 * Une personne déjà prise sur l'un de ces créneaux (ici ou dans une autre séance) ne peut pas être cochée.
 */
interface Personne { id: number, nom_avec_titre: string, fonction_libelle: string, equipe: string | null, email: string | null, deja_affecte: number, occupations: string[] }

const props = defineProps<{ seance: Seance, passages: PassageSeance[] }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ affecte: [seance: Seance] }>()

const api = useApi()
const { equipes } = useReferentiels()
const role = ref<RoleIntervenant>('moderateur')
const equipe = ref('toutes')
const recherche = ref('')
const personnes = ref<Personne[]>([])
const selection = ref<number[]>([])
const chargement = ref(false)
const envoi = ref(false)
const conflits = ref<string[]>([])

async function charger() {
  if (!props.passages.length) return
  chargement.value = true
  try {
    const query = new URLSearchParams()
    props.passages.forEach(p => query.append('passages[]', String(p.id)))
    if (equipe.value !== 'toutes') query.append('equipe', equipe.value)
    personnes.value = (await api<{ data: Personne[] }>(`/api/seances/${props.seance.id}/disponibilites?${query.toString()}`)).data
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    chargement.value = false
  }
}
watch(equipe, charger)
watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  selection.value = []
  conflits.value = []
  /* Par défaut : l'équipe des doctorants choisis, s'ils sont tous de la même */
  const sigles = [...new Set(props.passages.map(p => p.doctorant.equipe))]
  const meme = sigles.length === 1 ? equipes.value.find(e => e.sigle === sigles[0]) : null
  equipe.value = meme ? String(meme.id) : 'toutes'
  charger()
}, { immediate: true })

const visibles = computed(() => {
  const terme = recherche.value.trim().toLowerCase()
  return terme ? personnes.value.filter(p => p.nom_avec_titre.toLowerCase().includes(terme)) : personnes.value
})
const basculer = (id: number, coche: boolean | 'indeterminate') => { selection.value = coche === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }
const creneaux = computed(() => props.passages.map(p => `${p.heure_debut}-${p.heure_fin}`).join(', '))

async function affecter() {
  envoi.value = true
  conflits.value = []
  try {
    const reponse = await api<{ data: Seance, message: string }>(`/api/seances/${props.seance.id}/intervenants`, {
      method: 'POST',
      body: { passages: props.passages.map(p => p.id), membres: selection.value, role: role.value },
    })
    toast.success(reponse.message)
    emit('affecte', reponse.data)
    ouvert.value = false
  }
  catch (e) {
    conflits.value = (e as { data?: { conflits?: string[] } }).data?.conflits ?? []
    if (!conflits.value.length) toast.error(messageErreur(e))
    await charger()
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="flex max-h-[90dvh] flex-col sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Modérateurs et jury</DialogTitle>
        <DialogDescription>
          {{ passages.length === 1 ? `Passage de ${passages[0]!.doctorant.nom_complet}` : `${passages.length} passages` }}, {{ creneaux }}.
        </DialogDescription>
      </DialogHeader>

      <div class="grid grid-cols-2 gap-2 rounded-md bg-gray-100 p-1" role="radiogroup" aria-label="Rôle">
        <button
          v-for="[valeur, libelle] in [['moderateur', 'Modérateur'], ['jury', 'Jury']] as const"
          :key="valeur"
          type="button"
          role="radio"
          :aria-checked="role === valeur"
          class="rounded px-3 py-2 text-sm font-semibold transition-colors"
          :class="role === valeur ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="role = valeur"
        >
          {{ libelle }}
        </button>
      </div>

      <div class="grid grid-cols-[minmax(0,1fr)_10rem] gap-2">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <Input v-model="recherche" type="search" placeholder="Nom" class="pl-9" aria-label="Rechercher une personne" />
        </div>
        <Select v-model="equipe">
          <SelectTrigger class="w-full" aria-label="Équipe"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="toutes">Toutes les EAD</SelectItem>
            <SelectItem v-for="e in equipes" :key="e.id" :value="String(e.id)">{{ e.sigle }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <ul v-if="conflits.length" class="grid gap-1 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-destructive">
        <li v-for="conflit in conflits" :key="conflit">{{ conflit }}</li>
      </ul>

      <div class="min-h-0 flex-1 overflow-y-auto rounded-md border">
        <p v-if="chargement && !personnes.length" class="flex items-center justify-center gap-2 px-3 py-6 text-sm text-slate-400"><Loader2 class="size-4 animate-spin" /> Disponibilités...</p>
        <ul v-else class="divide-y">
          <li v-for="p in visibles" :key="p.id" class="flex items-start gap-3 px-3 py-2.5" :class="p.occupations.length ? 'bg-gray-50/60' : ''">
            <Checkbox v-if="!p.occupations.length" :model-value="selection.includes(p.id)" :aria-label="`Choisir ${p.nom_avec_titre}`" class="mt-0.5" @update:model-value="v => basculer(p.id, v)" />
            <CalendarX2 v-else class="mt-0.5 size-4 flex-none text-destructive" aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold" :class="p.occupations.length ? 'text-slate-400' : 'text-slate-700'">
                {{ p.nom_avec_titre }}
                <span class="ms-1 text-xs font-normal text-slate-400">{{ [p.fonction_libelle, p.equipe].filter(Boolean).join(', ') }}</span>
              </p>
              <p v-for="o in p.occupations" :key="o" class="text-xs text-destructive">Occupé : {{ o }}</p>
              <p v-if="!p.occupations.length && p.deja_affecte" class="text-xs text-leaf-600">Déjà sur {{ p.deja_affecte }} de ces passages : le rôle sera mis à jour.</p>
              <p v-if="!p.email && !p.occupations.length" class="text-xs text-sun-700">Sans e-mail : pas de convocation possible.</p>
            </div>
          </li>
          <li v-if="!visibles.length" class="px-3 py-6 text-center text-sm text-slate-400">
            Aucune personne. Ajoutez les encadrants et membres dans <NuxtLink to="/equipes" class="font-medium text-brand-700 hover:underline">Équipes d'accueil</NuxtLink>.
          </li>
        </ul>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button :disabled="envoi || !selection.length" @click="affecter">
          <Loader2 v-if="envoi" class="animate-spin" /><UserCheck v-else />
          Affecter {{ selection.length || '' }} {{ role === 'jury' ? 'au jury' : 'comme modérateur' }}{{ selection.length > 1 && role === 'moderateur' ? 's' : '' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

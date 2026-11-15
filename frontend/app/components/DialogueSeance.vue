<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { Seance, TypeSeance } from '~/types'

/** Création ou modification d'une séance : type, titre, date, horaires, lieu, règle de paiement. */
const props = defineProps<{ seance?: Seance | null }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistre: [seance: Seance] }>()

const TYPES: [TypeSeance, string][] = [['seminaire', 'Séminaire doctoral'], ['etat_avancement', "État d'avancement"], ['autre', 'Autre communication']]

const api = useApi()
const { anneeCourante } = useReferentiels()
const vide = () => ({ type: 'seminaire' as TypeSeance, titre: '', description: '', lieu: '', date: '', heure_debut: '08:00', duree_passage: 30, pause: 0, annee_universitaire_id: anneeCourante.value?.id ?? null as number | null, exige_paiement_solde: true })
const formulaire = reactive(vide())
const erreurs = ref<Record<string, string>>({})
const conflits = ref<string[]>([])
const envoi = ref(false)

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  const s = props.seance
  Object.assign(formulaire, s
    ? { type: s.type, titre: s.titre, description: s.description ?? '', lieu: s.lieu ?? '', date: s.date, heure_debut: s.heure_debut, duree_passage: s.duree_passage, pause: s.pause, annee_universitaire_id: s.annee_universitaire_id, exige_paiement_solde: s.exige_paiement_solde }
    : vide())
  erreurs.value = {}
  conflits.value = []
}, { immediate: true })

/* Titre proposé d'après le type, tant qu'il n'a pas été écrit à la main */
watch(() => formulaire.type, (type, avant) => {
  const libelle = (t?: TypeSeance) => TYPES.find(([v]) => v === t)?.[1] ?? ''
  if (!formulaire.titre || formulaire.titre === libelle(avant)) formulaire.titre = libelle(type)
}, { immediate: true })

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  conflits.value = []
  try {
    const reponse = await (props.seance
      ? api<{ data: Seance }>(`/api/seances/${props.seance.id}`, { method: 'PUT', body: formulaire })
      : api<{ data: Seance }>('/api/seances', { method: 'POST', body: formulaire }))
    toast.success(props.seance ? 'Séance modifiée.' : 'Séance créée. Ajoutez maintenant les doctorants.')
    ouvert.value = false
    emit('enregistre', reponse.data)
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    conflits.value = ((e as { data?: { conflits?: string[] } }).data?.conflits) ?? []
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{{ seance ? 'Modifier la séance' : 'Nouvelle séance' }}</DialogTitle>
        <DialogDescription>Séminaire doctoral, état d'avancement... Les passages se placent ensuite à la suite, à partir de l'heure de début.</DialogDescription>
      </DialogHeader>
      <form id="form-seance" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="enregistrer">
        <ChampFormulaire libelle="Type" pour="seance-type" :erreur="erreurs.type" requis>
          <Select v-model="formulaire.type">
            <SelectTrigger id="seance-type" class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent><SelectItem v-for="[valeur, libelle] in TYPES" :key="valeur" :value="valeur">{{ libelle }}</SelectItem></SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire libelle="Lieu" pour="seance-lieu" :erreur="erreurs.lieu">
          <Input id="seance-lieu" v-model="formulaire.lieu" placeholder="Amphithéâtre, salle, en ligne..." />
        </ChampFormulaire>
        <ChampFormulaire libelle="Titre" pour="seance-titre" :erreur="erreurs.titre" requis class="sm:col-span-2">
          <Input id="seance-titre" v-model="formulaire.titre" maxlength="190" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Date" pour="seance-date" :erreur="erreurs.date" requis>
          <Input id="seance-date" v-model="formulaire.date" type="date" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Heure de début" pour="seance-heure" :erreur="erreurs.heure_debut" requis>
          <Input id="seance-heure" v-model="formulaire.heure_debut" type="time" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Durée d'un passage (minutes)" pour="seance-duree" :erreur="erreurs.duree_passage" requis aide="Présentation et questions.">
          <Input id="seance-duree" v-model.number="formulaire.duree_passage" type="number" min="5" max="480" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Pause entre deux passages (minutes)" pour="seance-pause" :erreur="erreurs.pause">
          <Input id="seance-pause" v-model.number="formulaire.pause" type="number" min="0" max="120" />
        </ChampFormulaire>
        <div class="flex items-start justify-between gap-4 rounded-md border p-4 sm:col-span-2">
          <div>
            <Label for="seance-paiement">Réservé aux doctorants à jour de paiement</Label>
            <p class="mt-1 text-xs text-slate-400">Un doctorant qui n'a pas réglé la totalité de ses frais ne peut pas être programmé.</p>
          </div>
          <Switch id="seance-paiement" v-model="formulaire.exige_paiement_solde" />
        </div>
        <ChampFormulaire libelle="Description" pour="seance-description" :erreur="erreurs.description" aide="Consignes, thème, déroulement. Facultatif." class="sm:col-span-2">
          <Textarea id="seance-description" v-model="formulaire.description" rows="3" class="resize-y" />
        </ChampFormulaire>
        <ul v-if="conflits.length" class="grid gap-1 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-destructive sm:col-span-2">
          <li v-for="conflit in conflits" :key="conflit">{{ conflit }}</li>
        </ul>
      </form>
      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button type="submit" form="form-seance" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

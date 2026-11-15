<script setup lang="ts">
import { Loader2, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { Doctorant, Inscription, Ressource } from '~/types'

/**
 * Ajout ou modification d'une inscription annuelle (réservé à la scolarité).
 */
const props = defineProps<{ doctorant: Doctorant, inscription?: Inscription | null }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistree: [] }>()

const api = useApi()
const { annees, anneeCourante } = useReferentiels()
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

const vide = () => ({
  annee_universitaire_id: null as number | null,
  niveau: 'D1' as string,
  est_derogation: false,
  motif_derogation: '',
  decision_derogation: '',
  date_decision_derogation: '',
  montant_du: 0 as number,
  statut: 'validee' as string,
})
const formulaire = reactive(vide())

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  erreurs.value = {}
  const inscription = props.inscription
  Object.assign(formulaire, inscription
    ? {
        annee_universitaire_id: inscription.annee?.id ?? null,
        niveau: inscription.niveau,
        est_derogation: inscription.est_derogation,
        motif_derogation: inscription.motif_derogation ?? '',
        decision_derogation: inscription.decision_derogation ?? '',
        date_decision_derogation: inscription.date_decision_derogation ?? '',
        montant_du: inscription.montant_du ?? 0,
        statut: inscription.statut,
      }
    : {
        ...vide(),
        annee_universitaire_id: anneeCourante.value?.id ?? null,
        niveau: props.doctorant.prochaine_inscription?.niveau ?? 'D1',
        est_derogation: props.doctorant.prochaine_inscription?.est_derogation ?? false,
        montant_du: props.doctorant.inscriptions?.[0]?.montant_du ?? 0,
      })
})

watch(() => formulaire.est_derogation, (derogation) => {
  if (derogation) formulaire.niveau = 'D3'
})

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  const corps = {
    ...formulaire,
    decision_derogation: formulaire.decision_derogation || null,
    date_decision_derogation: formulaire.date_decision_derogation || null,
    motif_derogation: formulaire.motif_derogation || null,
  }
  try {
    await (props.inscription
      ? api<Ressource<Inscription>>(`/api/inscriptions/${props.inscription.id}`, { method: 'PUT', body: corps })
      : api<Ressource<Inscription>>(`/api/doctorants/${props.doctorant.id}/inscriptions`, { method: 'POST', body: corps }))
    toast.success(props.inscription ? 'Inscription modifiée.' : 'Inscription enregistrée.')
    ouvert.value = false
    emit('enregistree')
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

async function supprimer() {
  if (!props.inscription) return
  try {
    await api(`/api/inscriptions/${props.inscription.id}`, { method: 'DELETE' })
    toast.success('Inscription supprimée.')
    ouvert.value = false
    emit('enregistree')
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ inscription ? "Modifier l'inscription" : 'Nouvelle inscription' }}</DialogTitle>
        <DialogDescription>{{ doctorant.nom_complet }}, {{ doctorant.matricule }}</DialogDescription>
      </DialogHeader>

      <form id="form-inscription" class="grid gap-4" novalidate @submit.prevent="enregistrer">
        <div class="grid gap-4 sm:grid-cols-2">
          <ChampFormulaire libelle="Année universitaire" pour="annee" :erreur="erreurs.annee_universitaire_id" requis>
            <Select v-model="formulaire.annee_universitaire_id">
              <SelectTrigger id="annee" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="annee in annees" :key="annee.id" :value="annee.id">
                  {{ annee.libelle }}{{ annee.est_courante ? ' (en cours)' : '' }}
                </SelectItem>
              </SelectContent>
            </Select>
          </ChampFormulaire>
          <ChampFormulaire libelle="Niveau" pour="niveau" :erreur="erreurs.niveau" requis>
            <Select v-model="formulaire.niveau" :disabled="formulaire.est_derogation">
              <SelectTrigger id="niveau" class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="niveau in ['D1', 'D2', 'D3']" :key="niveau" :value="niveau">{{ niveau }}</SelectItem>
              </SelectContent>
            </Select>
          </ChampFormulaire>
        </div>

        <div class="rounded-lg border p-4" :class="formulaire.est_derogation ? 'border-sun-300 bg-sun-50' : ''">
          <div class="flex items-start justify-between gap-4">
            <div>
              <Label for="derogation">Dérogation</Label>
              <p class="mt-1 text-xs text-muted-foreground">Inscription au delà des trois années normales (D1 à D3).</p>
            </div>
            <Switch id="derogation" v-model="formulaire.est_derogation" />
          </div>
          <p v-if="erreurs.est_derogation" class="mt-2 text-sm text-destructive">{{ erreurs.est_derogation }}</p>
          <div v-if="formulaire.est_derogation" class="mt-4 grid gap-4">
            <ChampFormulaire libelle="Motif" pour="motif" :erreur="erreurs.motif_derogation" requis>
              <Textarea id="motif" v-model="formulaire.motif_derogation" rows="2" />
            </ChampFormulaire>
            <div class="grid gap-4 sm:grid-cols-2">
              <ChampFormulaire libelle="Référence de la décision" pour="decision" :erreur="erreurs.decision_derogation" aide="Laisser vide si elle n'est pas encore prise.">
                <Input id="decision" v-model="formulaire.decision_derogation" placeholder="Décision n° 12/2026/EDGVM" />
              </ChampFormulaire>
              <ChampFormulaire libelle="Date de la décision" pour="date-decision" :erreur="erreurs.date_decision_derogation">
                <Input id="date-decision" v-model="formulaire.date_decision_derogation" type="date" />
              </ChampFormulaire>
            </div>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <ChampFormulaire libelle="Droits d'inscription (Ar)" pour="montant" :erreur="erreurs.montant_du" requis>
            <Input id="montant" v-model.number="formulaire.montant_du" type="number" min="0" step="1000" inputmode="numeric" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Statut" pour="statut" :erreur="erreurs.statut" requis>
            <Select v-model="formulaire.statut">
              <SelectTrigger id="statut" class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="en_attente">En attente</SelectItem>
                <SelectItem value="validee">Validée</SelectItem>
                <SelectItem value="annulee">Annulée</SelectItem>
              </SelectContent>
            </Select>
          </ChampFormulaire>
        </div>
      </form>

      <DialogFooter class="gap-2 sm:justify-between">
        <AlertDialog v-if="inscription">
          <AlertDialogTrigger as-child>
            <Button variant="ghost" class="text-destructive hover:text-destructive"><Trash2 /> Supprimer</Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Supprimer cette inscription ?</AlertDialogTitle>
              <AlertDialogDescription>À réserver à une saisie faite par erreur. Pour une inscription réelle abandonnée, choisissez plutôt le statut « Annulée ».</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Garder</AlertDialogCancel>
              <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="supprimer">Supprimer</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
        <span v-else />
        <div class="flex gap-2">
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-inscription" :disabled="envoi">
            <Loader2 v-if="envoi" class="animate-spin" />
            Enregistrer
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Equipe, FonctionMembre, MembreEquipe } from '~/types'

/** Ajout ou modification d'une personne de l'équipe : responsable, encadrant ou membre. */
const props = defineProps<{ equipe: Equipe, membre?: MembreEquipe | null }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistre: [] }>()

const FONCTIONS: [FonctionMembre, string, string][] = [
  ['responsable', "Responsable de l'équipe", 'Dirige l\'équipe ; affiché en premier'],
  ['encadrant', 'Encadrant', 'Peut diriger ou co-diriger des thèses'],
  ['membre', 'Membre', 'Enseignant-chercheur ou chercheur de l\'équipe'],
]

const api = useApi()
const vide = () => ({ nom: '', titre: '', fonction: 'encadrant' as FonctionMembre, specialite: '', etablissement: 'Université de Mahajanga', email: '', telephone: '' })
const formulaire = reactive(vide())
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  const m = props.membre
  Object.assign(formulaire, m
    ? { nom: m.nom, titre: m.titre ?? '', fonction: m.fonction, specialite: m.specialite ?? '', etablissement: m.etablissement ?? '', email: m.email ?? '', telephone: m.telephone ?? '' }
    : vide())
  erreurs.value = {}
}, { immediate: true })

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    await (props.membre
      ? api(`/api/membres-equipe/${props.membre.id}`, { method: 'PUT', body: formulaire })
      : api(`/api/equipes/${props.equipe.id}/membres`, { method: 'POST', body: formulaire }))
    toast.success(props.membre ? 'Personne modifiée.' : 'Personne ajoutée à l\'équipe.')
    ouvert.value = false
    emit('enregistre')
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
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
        <DialogTitle>{{ membre ? `Modifier ${membre.nom_avec_titre}` : 'Ajouter une personne' }}</DialogTitle>
        <DialogDescription>{{ equipe.sigle }}. Une personne peut figurer ici sans avoir de compte sur la plateforme.</DialogDescription>
      </DialogHeader>
      <form id="form-membre" class="grid gap-5" novalidate @submit.prevent="enregistrer">
        <div class="grid gap-5 sm:grid-cols-[10rem_minmax(0,1fr)]">
          <ChampFormulaire libelle="Titre" pour="membre-titre" :erreur="erreurs.titre">
            <Input id="membre-titre" v-model="formulaire.titre" placeholder="Pr, Dr..." list="titres-membre" maxlength="120" />
            <datalist id="titres-membre">
              <option value="Pr" /><option value="Dr" /><option value="Professeur Titulaire" /><option value="Maître de conférences" /><option value="Professeur" />
            </datalist>
          </ChampFormulaire>
          <ChampFormulaire libelle="Nom et prénoms" pour="membre-nom" :erreur="erreurs.nom" requis>
            <Input id="membre-nom" v-model="formulaire.nom" placeholder="RAKOTO Jean" maxlength="255" />
          </ChampFormulaire>
        </div>
        <ChampFormulaire libelle="Fonction dans l'équipe" pour="membre-fonction" :erreur="erreurs.fonction" requis>
          <Select v-model="formulaire.fonction">
            <SelectTrigger id="membre-fonction" class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="[valeur, libelle, aide] in FONCTIONS" :key="valeur" :value="valeur">
                <span>{{ libelle }} <span class="text-xs text-muted-foreground">{{ aide }}</span></span>
              </SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <div class="grid gap-5 sm:grid-cols-2">
          <ChampFormulaire libelle="Spécialité" pour="membre-specialite" :erreur="erreurs.specialite">
            <Input id="membre-specialite" v-model="formulaire.specialite" placeholder="Chimie analytique" maxlength="255" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Établissement" pour="membre-etablissement" :erreur="erreurs.etablissement">
            <Input id="membre-etablissement" v-model="formulaire.etablissement" maxlength="255" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Adresse e-mail" pour="membre-email" :erreur="erreurs.email">
            <Input id="membre-email" v-model="formulaire.email" type="email" maxlength="190" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Téléphone" pour="membre-telephone" :erreur="erreurs.telephone">
            <Input id="membre-telephone" v-model="formulaire.telephone" type="tel" placeholder="034 12 345 67" />
          </ChampFormulaire>
        </div>
      </form>
      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button type="submit" form="form-membre" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

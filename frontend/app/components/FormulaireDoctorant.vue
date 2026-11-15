<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { Doctorant, Ressource } from '~/types'

/**
 * Création (avec compte, invitation et première inscription) ou modification d'un dossier.
 */
const props = defineProps<{ doctorant?: Doctorant | null }>()
const creation = computed(() => !props.doctorant)

const api = useApi()
const { equipes, annees, anneeCourante } = useReferentiels()
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

const d = props.doctorant
const formulaire = reactive({
  nom: d?.nom ?? '',
  prenoms: d?.prenoms ?? '',
  genre: d?.genre ?? null as string | null,
  date_naissance: d?.date_naissance ?? '',
  lieu_naissance: d?.lieu_naissance ?? '',
  nationalite: d?.nationalite ?? 'Malagasy',
  numero_piece: d?.numero_piece ?? '',
  adresse: d?.adresse ?? '',
  email: d?.email ?? '',
  telephone: d?.telephone ?? '',
  matricule: d?.matricule ?? '',
  equipe_accueil_id: d?.equipe?.id ?? null as number | null,
  sujet_these: d?.sujet_these ?? '',
  directeur_these: d?.directeur_these ?? '',
  co_directeur: d?.co_directeur ?? '',
  co_encadrant: d?.co_encadrant ?? '',
  statut: d?.statut ?? 'actif',
  visible_annuaire: d?.visible_annuaire ?? false,
  observation: d?.observation ?? '',
  envoyer_invitation: true,
})
const inscrire = ref(true)
const inscription = reactive({ annee_universitaire_id: null as number | null, niveau: 'D1', montant_du: 0 })
watch(anneeCourante, (annee) => {
  if (annee && !inscription.annee_universitaire_id) inscription.annee_universitaire_id = annee.id
}, { immediate: true })

const sansVide = (valeurs: Record<string, unknown>) => Object.fromEntries(Object.entries(valeurs).map(([cle, valeur]) => [cle, valeur === '' ? null : valeur]))

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  const corps = {
    ...sansVide(formulaire),
    ...(creation.value ? { inscription: inscrire.value ? inscription : null, statut: undefined } : { envoyer_invitation: undefined }),
  }
  try {
    const reponse = creation.value
      ? await api<Ressource<Doctorant>>('/api/doctorants', { method: 'POST', body: corps })
      : await api<Ressource<Doctorant>>(`/api/doctorants/${props.doctorant!.id}`, { method: 'PUT', body: corps })
    toast.success(creation.value
      ? `Dossier créé${formulaire.envoyer_invitation ? ', lien d\'accès envoyé par e-mail' : ''}.`
      : 'Dossier enregistré.')
    clearNuxtData(key => key.startsWith('doctorant') || key === 'tableau-de-bord')
    await navigateTo(`/doctorants/${reponse.data.id}`)
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (Object.keys(erreurs.value).length) {
      toast.error('Vérifiez les champs signalés.')
      await nextTick()
      document.querySelector('[aria-invalid="true"]')?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    }
    else {
      toast.error(messageErreur(e))
    }
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <form class="grid gap-6" novalidate @submit.prevent="enregistrer">
    <Card>
      <CardHeader><CardTitle>Identité</CardTitle></CardHeader>
      <CardContent class="grid gap-4 sm:grid-cols-2">
        <ChampFormulaire libelle="Nom" pour="nom" :erreur="erreurs.nom" requis>
          <Input id="nom" v-model="formulaire.nom" autocomplete="family-name" :aria-invalid="!!erreurs.nom" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Prénoms" pour="prenoms" :erreur="erreurs.prenoms">
          <Input id="prenoms" v-model="formulaire.prenoms" autocomplete="given-name" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Genre" pour="genre" :erreur="erreurs.genre">
          <Select v-model="formulaire.genre">
            <SelectTrigger id="genre" class="w-full"><SelectValue placeholder="Non précisé" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="F">Féminin</SelectItem>
              <SelectItem value="M">Masculin</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire libelle="Date de naissance" pour="naissance" :erreur="erreurs.date_naissance">
          <Input id="naissance" v-model="formulaire.date_naissance" type="date" :aria-invalid="!!erreurs.date_naissance" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Lieu de naissance" pour="lieu" :erreur="erreurs.lieu_naissance">
          <Input id="lieu" v-model="formulaire.lieu_naissance" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Nationalité" pour="nationalite" :erreur="erreurs.nationalite">
          <Input id="nationalite" v-model="formulaire.nationalite" />
        </ChampFormulaire>
        <ChampFormulaire libelle="CIN ou passeport" pour="piece" :erreur="erreurs.numero_piece">
          <Input id="piece" v-model="formulaire.numero_piece" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Adresse" pour="adresse" :erreur="erreurs.adresse">
          <Input id="adresse" v-model="formulaire.adresse" autocomplete="street-address" />
        </ChampFormulaire>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Contact et compte</CardTitle>
        <CardDescription>L'adresse e-mail sert d'identifiant pour se connecter à la plateforme.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-4 sm:grid-cols-2">
        <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreurs.email" requis>
          <Input id="email" v-model="formulaire.email" type="email" autocomplete="email" :aria-invalid="!!erreurs.email" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Téléphone" pour="telephone" :erreur="erreurs.telephone" aide="Pour les SMS et WhatsApp. Exemple : 034 12 345 67.">
          <Input id="telephone" v-model="formulaire.telephone" type="tel" autocomplete="tel" :aria-invalid="!!erreurs.telephone" />
        </ChampFormulaire>
        <div v-if="creation" class="flex items-start justify-between gap-4 rounded-lg border p-4 sm:col-span-2">
          <div>
            <Label for="invitation">Envoyer le lien d'accès</Label>
            <p class="mt-1 text-xs text-muted-foreground">Le doctorant reçoit un e-mail pour choisir son mot de passe (lien valable 7 jours).</p>
          </div>
          <Switch id="invitation" v-model="formulaire.envoyer_invitation" />
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader><CardTitle>Thèse</CardTitle></CardHeader>
      <CardContent class="grid gap-4 sm:grid-cols-2">
        <ChampFormulaire libelle="Équipe d'accueil" pour="equipe" :erreur="erreurs.equipe_accueil_id" requis class="sm:col-span-2">
          <Select v-model="formulaire.equipe_accueil_id">
            <SelectTrigger id="equipe" class="w-full" :aria-invalid="!!erreurs.equipe_accueil_id"><SelectValue placeholder="Choisir l'équipe" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="equipe in equipes" :key="equipe.id" :value="equipe.id">{{ equipe.sigle }}, {{ equipe.nom }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire libelle="Sujet de thèse" pour="sujet" :erreur="erreurs.sujet_these" class="sm:col-span-2">
          <Textarea id="sujet" v-model="formulaire.sujet_these" rows="2" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Directeur de thèse" pour="directeur" :erreur="erreurs.directeur_these" aide="Avec le titre : Pr RABE Hery.">
          <Input id="directeur" v-model="formulaire.directeur_these" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Co-directeur" pour="codirecteur" :erreur="erreurs.co_directeur">
          <Input id="codirecteur" v-model="formulaire.co_directeur" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Co-encadrant" pour="coencadrant" :erreur="erreurs.co_encadrant">
          <Input id="coencadrant" v-model="formulaire.co_encadrant" />
        </ChampFormulaire>
      </CardContent>
    </Card>

    <Card v-if="creation">
      <CardHeader class="flex flex-row items-start justify-between gap-4">
        <div class="grid gap-1.5">
          <CardTitle>Première inscription</CardTitle>
          <CardDescription>Inscrire tout de suite le doctorant pour une année universitaire.</CardDescription>
        </div>
        <Switch v-model="inscrire" aria-label="Inscrire maintenant" />
      </CardHeader>
      <CardContent v-if="inscrire" class="grid gap-4 sm:grid-cols-3">
        <ChampFormulaire libelle="Année" pour="insc-annee" :erreur="erreurs['inscription.annee_universitaire_id']" requis>
          <Select v-model="inscription.annee_universitaire_id">
            <SelectTrigger id="insc-annee" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="annee in annees" :key="annee.id" :value="annee.id">{{ annee.libelle }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire libelle="Niveau" pour="insc-niveau" :erreur="erreurs['inscription.niveau']" requis aide="D2 ou D3 pour un doctorant déjà avancé.">
          <Select v-model="inscription.niveau">
            <SelectTrigger id="insc-niveau" class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="niveau in ['D1', 'D2', 'D3']" :key="niveau" :value="niveau">{{ niveau }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire libelle="Droits d'inscription (Ar)" pour="insc-montant" :erreur="erreurs['inscription.montant_du']" requis>
          <Input id="insc-montant" v-model.number="inscription.montant_du" type="number" min="0" step="1000" inputmode="numeric" />
        </ChampFormulaire>
      </CardContent>
    </Card>

    <Card>
      <CardHeader><CardTitle>Suivi administratif</CardTitle></CardHeader>
      <CardContent class="grid gap-4 sm:grid-cols-2">
        <ChampFormulaire libelle="Matricule" pour="matricule" :erreur="erreurs.matricule" :aide="creation ? 'Laisser vide pour un numéro automatique (EDGVM-2026-0001).' : undefined">
          <Input id="matricule" v-model="formulaire.matricule" :aria-invalid="!!erreurs.matricule" />
        </ChampFormulaire>
        <ChampFormulaire v-if="!creation" libelle="Statut" pour="statut" :erreur="erreurs.statut" requis>
          <Select v-model="formulaire.statut">
            <SelectTrigger id="statut" class="w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="actif">Actif</SelectItem>
              <SelectItem value="suspendu">Suspendu</SelectItem>
              <SelectItem value="abandon">Abandon</SelectItem>
              <SelectItem value="diplome">Diplômé</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <div class="flex items-start justify-between gap-4 rounded-lg border p-4 sm:col-span-2">
          <div>
            <Label for="annuaire">Visible dans l'annuaire du site</Label>
            <p class="mt-1 text-xs text-muted-foreground">Seulement avec l'accord du doctorant : nom, niveau, équipe et sujet sur le site public.</p>
          </div>
          <Switch id="annuaire" v-model="formulaire.visible_annuaire" />
        </div>
        <ChampFormulaire libelle="Observation interne" pour="observation" :erreur="erreurs.observation" aide="Visible uniquement par la scolarité." class="sm:col-span-2">
          <Textarea id="observation" v-model="formulaire.observation" rows="2" />
        </ChampFormulaire>
      </CardContent>
    </Card>

    <div class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-10 flex justify-end gap-2 rounded-lg border bg-white/95 p-3 backdrop-blur xl:bottom-4">
      <Button variant="outline" as-child>
        <NuxtLink :to="doctorant ? `/doctorants/${doctorant.id}` : '/doctorants'">Annuler</NuxtLink>
      </Button>
      <Button type="submit" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        {{ creation ? 'Créer le dossier' : 'Enregistrer' }}
      </Button>
    </div>
  </form>
</template>

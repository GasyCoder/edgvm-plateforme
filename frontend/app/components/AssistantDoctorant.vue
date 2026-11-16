<script setup lang="ts">
import { ArrowLeft, ArrowRight, Check, Loader2, Pencil } from '@lucide/vue'
import { useSessionStorage } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { Doctorant, Ressource } from '~/types'

/**
 * Dossier doctorant en cinq étapes, à la création comme à la modification. Chaque étape est vérifiée par l'API
 * (mêmes règles que l'enregistrement) avant de passer à la suivante. À la création, le brouillon est gardé
 * pendant la session du navigateur ; à la modification, toutes les étapes sont accessibles d'emblée.
 */
const props = defineProps<{ doctorant?: Doctorant, candidat?: boolean }>()
const modification = computed(() => !!props.doctorant)
/* Candidat : pas d'inscription ni d'accès à la plateforme avant l'admission, numéro provisoire automatique */
const candidat = computed(() => props.candidat || !!props.doctorant?.est_candidat)

const ETAPES = computed(() => [
  { cle: 'identite', titre: 'Identité', aide: 'Nom, naissance, photo' },
  { cle: 'contact', titre: 'Contact et accès', aide: 'E-mail et téléphone' },
  candidat.value ? { cle: 'these', titre: 'Projet de thèse', aide: 'EAD visée, sujet, encadrement' } : { cle: 'these', titre: 'Thèse', aide: "Équipe d'accueil, sujet" },
  candidat.value
    ? { cle: 'inscription', titre: 'Candidature', aide: 'Bourse, observation' }
    : modification.value
      ? { cle: 'inscription', titre: 'Suivi', aide: 'Statut, bourse, annuaire' }
      : { cle: 'inscription', titre: 'Inscription', aide: 'Année, niveau, droits' },
  { cle: 'recapitulatif', titre: 'Récapitulatif', aide: modification.value ? 'Vérifier et enregistrer' : 'Vérifier et créer' },
] as const)

const api = useApi()
const { equipes, annees, anneeCourante } = useReferentiels()

const vide = () => ({
  nom: '', prenoms: '', genre: null as string | null, date_naissance: '', lieu_naissance: '', nationalite: 'Malagasy',
  numero_piece: '', adresse: '', email: '', telephone: '', envoyer_invitation: true,
  equipe_accueil_id: null as number | null, sujet_these: '', directeur_these: '', co_directeur: '', co_encadrant: '',
  inscrire: true, inscription: { annee_universitaire_id: null as number | null, niveau: 'D1' },
  matricule: '', statut: 'actif' as Doctorant['statut'], est_boursier: false, visible_annuaire: false, observation: '',
})
const depuisDossier = (d: Doctorant) => ({
  ...vide(),
  nom: d.nom, prenoms: d.prenoms ?? '', genre: d.genre ?? null, date_naissance: d.date_naissance ?? '', lieu_naissance: d.lieu_naissance ?? '',
  nationalite: d.nationalite ?? '', numero_piece: d.numero_piece ?? '', adresse: d.adresse ?? '', email: d.email ?? '', telephone: d.telephone ?? '',
  equipe_accueil_id: d.equipe?.id ?? null, sujet_these: d.sujet_these ?? '', directeur_these: d.directeur_these ?? '', co_directeur: d.co_directeur ?? '', co_encadrant: d.co_encadrant ?? '',
  inscrire: false, matricule: d.matricule, statut: d.statut, est_boursier: d.est_boursier, visible_annuaire: d.visible_annuaire, observation: d.observation ?? '',
})
/* Brouillon de session seulement à la création */
const brouillon = useSessionStorage(props.candidat ? 'edgvm-nouveau-candidat' : 'edgvm-nouveau-doctorant', vide(), { mergeDefaults: true })
const formulaire = reactive(props.doctorant ? depuisDossier(props.doctorant) : brouillon.value)
if (!props.doctorant) watch(formulaire, valeur => { brouillon.value = valeur }, { deep: true })
const photo = ref<File | null>(null)

/* Prochain matricule automatique (Paramètres > Matricule), montré à la création */
const prochainMatricule = ref('')
onMounted(async () => {
  if (props.doctorant) return
  try {
    prochainMatricule.value = (await api<{ data: { apercu: string } }>('/api/reglages-matricule')).data.apercu
  }
  catch {
    /* Simple indication : rien à signaler si elle manque. */
  }
})
watch(anneeCourante, (annee) => {
  if (annee && !formulaire.inscription.annee_universitaire_id) formulaire.inscription.annee_universitaire_id = annee.id
}, { immediate: true })

const courante = ref(0)
const atteinte = ref(props.doctorant ? 4 : 0)
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

const sansVide = (valeurs: Record<string, unknown>) => Object.fromEntries(Object.entries(valeurs).map(([cle, valeur]) => [cle, valeur === '' ? null : valeur]))
const corps = () => {
  const { inscrire, inscription, statut, envoyer_invitation, ...reste } = formulaire
  if (props.doctorant) return { ...sansVide(reste), statut }
  return candidat.value
    ? { ...sansVide(reste), matricule: null, statut: 'candidat', envoyer_invitation: false, inscription: null }
    : { ...sansVide(reste), envoyer_invitation, inscription: inscrire ? inscription : null }
}
const adresse = () => props.doctorant ? `/api/doctorants/${props.doctorant.id}` : '/api/doctorants'
const methode = () => props.doctorant ? 'PUT' as const : 'POST' as const

/** Étape de chaque champ, pour revenir à la bonne étape si l'enregistrement final signale une erreur. */
const etapeDuChamp = (champ: string) => {
  const racine = champ.split('.')[0]!
  if (['email', 'telephone', 'envoyer_invitation'].includes(racine)) return 1
  if (['equipe_accueil_id', 'sujet_these', 'directeur_these', 'co_directeur', 'co_encadrant'].includes(racine)) return 2
  if (['inscription', 'matricule', 'statut', 'est_boursier', 'visible_annuaire', 'observation'].includes(racine)) return 3
  return 0
}

async function suivant() {
  envoi.value = true
  erreurs.value = {}
  try {
    await api(adresse(), { method: methode(), body: { ...corps(), etape: ETAPES.value[courante.value]!.cle } })
    courante.value++
    atteinte.value = Math.max(atteinte.value, courante.value)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

function allerA(index: number) {
  if (index <= atteinte.value) {
    erreurs.value = {}
    courante.value = index
  }
}

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const reponse = await api<Ressource<Doctorant>>(adresse(), { method: methode(), body: corps() })
    if (props.doctorant) {
      toast.success('Dossier enregistré.')
      clearNuxtData(cle => cle.startsWith('doctorant') || cle === 'tableau-de-bord')
      await navigateTo(`/doctorants/${reponse.data.id}`)
      return
    }
    if (photo.value) {
      const donnees = new FormData()
      donnees.append('photo', photo.value)
      await api(`/api/doctorants/${reponse.data.id}/photo`, { method: 'POST', body: donnees })
        .catch(() => toast.warning("Dossier créé, mais la photo n'a pas pu être enregistrée. Ajoutez-la depuis « Modifier »."))
    }
    toast.success(candidat.value
      ? `Candidature de ${reponse.data.nom_complet} enregistrée. Ajoutez ses pièces jointes, puis programmez-le dans une présentation de projet.`
      : `Dossier créé${formulaire.envoyer_invitation ? ", lien d'accès envoyé" : ''}. Vous pouvez ajouter ses pièces jointes.`)
    brouillon.value = vide()
    photo.value = null
    clearNuxtData(cle => cle.startsWith('doctorant') || cle === 'tableau-de-bord')
    await navigateTo(`/doctorants/${reponse.data.id}?pieces=1`)
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    const champs = Object.keys(erreurs.value)
    if (champs.length) {
      courante.value = Math.min(...champs.map(etapeDuChamp))
      toast.error('Une information est à corriger.')
    }
    else {
      toast.error(messageErreur(e))
    }
  }
  finally {
    envoi.value = false
  }
}

function recommencer() {
  photo.value = null
  Object.assign(formulaire, vide())
  formulaire.inscription.annee_universitaire_id = anneeCourante.value?.id ?? null
  courante.value = 0
  atteinte.value = 0
  erreurs.value = {}
}

const STATUTS = [['actif', 'Actif'], ['suspendu', 'Suspendu'], ['abandon', 'Abandon'], ['diplome', 'Diplômé']] as const
const equipeChoisie = computed(() => equipes.value.find(e => e.id === formulaire.equipe_accueil_id))
const anneeChoisie = computed(() => annees.value.find(a => a.id === formulaire.inscription.annee_universitaire_id))
const recapitulatif = computed(() => [
  { etape: 0, titre: 'Identité', lignes: [
    ['Nom', [formulaire.nom.toUpperCase(), formulaire.prenoms].filter(Boolean).join(' ')],
    ['Genre', formulaire.genre === 'F' ? 'Féminin' : formulaire.genre === 'M' ? 'Masculin' : ''],
    ['Naissance', [formaterDate(formulaire.date_naissance), formulaire.lieu_naissance].filter(Boolean).join(' à ')],
    ['Nationalité', formulaire.nationalite],
    ['CIN ou passeport', formulaire.numero_piece],
    ['Adresse', formulaire.adresse],
  ] },
  { etape: 1, titre: 'Contact et accès', lignes: [
    ['E-mail', formulaire.email],
    ['Téléphone', formulaire.telephone],
    ["Lien d'accès", modification.value ? '' : candidat.value ? 'À l\'admission' : formulaire.envoyer_invitation ? 'Envoyé à la création' : 'Pas envoyé pour le moment'],
  ] },
  { etape: 2, titre: 'Thèse', lignes: [
    ["Équipe d'accueil", equipeChoisie.value ? `${equipeChoisie.value.sigle}, ${equipeChoisie.value.nom}` : ''],
    ['Sujet', formulaire.sujet_these],
    ['Directeur de thèse', formulaire.directeur_these],
    ['Co-directeur', formulaire.co_directeur],
    ['Co-encadrant', formulaire.co_encadrant],
  ] },
  { etape: 3, titre: modification.value ? 'Suivi' : 'Inscription et suivi', lignes: [
    ['Inscription', modification.value || candidat.value ? '' : formulaire.inscrire ? `${anneeChoisie.value?.libelle ?? ''}, ${formulaire.inscription.niveau} (droits selon les tarifs)` : 'Pas pour le moment'],
    ['Statut', modification.value && !candidat.value ? STATUTS.find(([v]) => v === formulaire.statut)?.[1] ?? '' : ''],
    ['Matricule', candidat.value ? (formulaire.matricule || 'Numéro provisoire, matricule à l\'admission') : (formulaire.matricule || 'Attribué automatiquement')],
    ['Bourse', formulaire.est_boursier ? 'Boursier' : 'Non boursier'],
    ['Annuaire du site', formulaire.visible_annuaire ? 'Visible' : 'Non visible'],
    ['Observation', formulaire.observation],
  ] },
].map(bloc => ({ ...bloc, lignes: bloc.lignes.filter(([, valeur]) => valeur) as [string, string][] })))
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">
    <!-- Étapes : liste verticale sur grand écran, barre de progression sur mobile -->
    <ol class="hidden h-fit rounded-md border border-gray-300 bg-white p-2 lg:block">
      <li v-for="(etape, index) in ETAPES" :key="etape.cle">
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition-colors"
          :class="[index === courante ? 'bg-brand-50' : '', index <= atteinte ? 'hover:bg-gray-50' : 'cursor-default']"
          :disabled="index > atteinte"
          @click="allerA(index)"
        >
          <span
            class="flex size-8 flex-none items-center justify-center rounded-full border-2 text-sm font-bold"
            :class="index === courante ? 'border-brand-600 text-brand-700' : index < atteinte ? 'border-brand-600 bg-brand-600 text-white' : index === atteinte ? 'border-brand-300 text-brand-600' : 'border-gray-300 text-slate-400'"
          >
            <Check v-if="index < atteinte && index !== courante" class="size-4" />
            <template v-else>{{ index + 1 }}</template>
          </span>
          <span class="min-w-0">
            <span class="block text-sm font-bold" :class="index === courante ? 'text-brand-800' : index <= atteinte ? 'text-slate-700' : 'text-slate-400'">{{ etape.titre }}</span>
            <span class="block truncate text-xs text-slate-400">{{ etape.aide }}</span>
          </span>
        </button>
      </li>
    </ol>

    <div class="min-w-0">
      <div class="mb-4 lg:hidden">
        <div class="flex items-center justify-between text-sm">
          <span class="font-bold text-slate-700">{{ ETAPES[courante]!.titre }}</span>
          <span class="text-slate-400">Étape {{ courante + 1 }} sur {{ ETAPES.length }}</span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200">
          <div class="h-full rounded-full bg-brand-600 transition-all" :style="{ width: `${((courante + 1) / ETAPES.length) * 100}%` }" />
        </div>
      </div>

      <form class="rounded-md border border-gray-300 bg-white" novalidate @submit.prevent="courante < ETAPES.length - 1 ? suivant() : enregistrer()">
        <div class="border-b border-gray-200 px-5 py-4 sm:px-7">
          <h2 class="text-lg font-bold text-slate-700">{{ ETAPES[courante]!.titre }}</h2>
          <p class="text-sm text-slate-400">
            <template v-if="courante === 0">Comme sur la pièce d'identité. Seul le nom est obligatoire.</template>
            <template v-else-if="courante === 1">L'adresse e-mail sert d'identifiant pour se connecter à la plateforme.</template>
            <template v-else-if="courante === 2">L'équipe d'accueil détermine qui peut suivre ce doctorant.</template>
            <template v-else-if="courante === 3 && candidat">Situation de la candidature. L'inscription viendra à l'admission.</template>
            <template v-else-if="courante === 3">{{ modification ? 'Statut du dossier, bourse et informations de suivi. Les inscriptions se gèrent depuis la fiche.' : 'Inscription pour une année universitaire et informations de suivi.' }}</template>
            <template v-else>{{ modification ? 'Vérifiez les modifications avant de les enregistrer.' : 'Vérifiez les informations avant de créer le dossier.' }}</template>
          </p>
        </div>

        <div class="px-5 py-6 sm:px-7">
          <!-- 1. Identité -->
          <div v-if="courante === 0" class="grid gap-4 sm:grid-cols-2">
            <ChampPhoto v-model:fichier="photo" :doctorant-id="doctorant?.id" :photo-url="doctorant?.photo_url" class="sm:col-span-2" />
            <ChampFormulaire libelle="Nom" pour="nom" :erreur="erreurs.nom" requis>
              <Input id="nom" v-model="formulaire.nom" autocomplete="family-name" autofocus :aria-invalid="!!erreurs.nom" />
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
          </div>

          <!-- 2. Contact et accès -->
          <div v-else-if="courante === 1" class="grid gap-4 sm:grid-cols-2">
            <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreurs.email" requis>
              <Input id="email" v-model="formulaire.email" type="email" autocomplete="email" autofocus :aria-invalid="!!erreurs.email" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Téléphone" pour="telephone" :erreur="erreurs.telephone" aide="Pour les SMS et WhatsApp. Exemple : 034 12 345 67.">
              <Input id="telephone" v-model="formulaire.telephone" type="tel" autocomplete="tel" :aria-invalid="!!erreurs.telephone" />
            </ChampFormulaire>
            <div v-if="!modification && !candidat" class="flex items-start justify-between gap-4 rounded-md border p-4 sm:col-span-2">
              <div>
                <Label for="invitation">Envoyer le lien d'accès à la création</Label>
                <p class="mt-1 text-xs text-slate-400">Le doctorant reçoit un e-mail pour choisir son mot de passe (lien valable 7 jours).</p>
              </div>
              <Switch id="invitation" v-model="formulaire.envoyer_invitation" />
            </div>
          </div>

          <!-- 3. Thèse -->
          <div v-else-if="courante === 2" class="grid gap-4">
            <ChampFormulaire libelle="Équipe d'accueil" pour="equipe" :erreur="erreurs.equipe_accueil_id" requis>
              <Select v-model="formulaire.equipe_accueil_id">
                <SelectTrigger id="equipe" class="w-full" :aria-invalid="!!erreurs.equipe_accueil_id"><SelectValue placeholder="Choisir l'équipe" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="equipe in equipes" :key="equipe.id" :value="equipe.id">{{ equipe.sigle }}, {{ equipe.nom }}</SelectItem>
                </SelectContent>
              </Select>
            </ChampFormulaire>
            <ChampFormulaire libelle="Sujet de thèse" pour="sujet" :erreur="erreurs.sujet_these" aide="Peut être précisé plus tard.">
              <Textarea id="sujet" v-model="formulaire.sujet_these" rows="3" />
            </ChampFormulaire>
            <div class="grid gap-4 sm:grid-cols-3">
              <ChampFormulaire libelle="Directeur de thèse" pour="directeur" :erreur="erreurs.directeur_these" aide="Avec le titre : Pr RABE Hery.">
                <Input id="directeur" v-model="formulaire.directeur_these" />
              </ChampFormulaire>
              <ChampFormulaire libelle="Co-directeur" pour="codirecteur" :erreur="erreurs.co_directeur">
                <Input id="codirecteur" v-model="formulaire.co_directeur" />
              </ChampFormulaire>
              <ChampFormulaire libelle="Co-encadrant" pour="coencadrant" :erreur="erreurs.co_encadrant">
                <Input id="coencadrant" v-model="formulaire.co_encadrant" />
              </ChampFormulaire>
            </div>
          </div>

          <!-- 4. Inscription et suivi -->
          <div v-else-if="courante === 3" class="grid gap-5">
            <p v-if="candidat" class="rounded-md bg-sun-50 px-4 py-3 text-sm text-sun-700">
              Candidat : pas d'inscription ni de frais pour l'instant. Il présente son projet de thèse lors d'une séance ; s'il est admis par le jury, il est inscrit en D1 et reçoit son attestation d'admission.
            </p>
            <ChampFormulaire v-else-if="modification" libelle="Statut du dossier" pour="statut" :erreur="erreurs.statut" requis class="sm:max-w-xs">
              <Select v-model="formulaire.statut">
                <SelectTrigger id="statut" class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="[valeur, libelle] in STATUTS" :key="valeur" :value="valeur">{{ libelle }}</SelectItem>
                </SelectContent>
              </Select>
            </ChampFormulaire>
            <div v-else class="rounded-md border p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <Label for="inscrire">Inscrire maintenant</Label>
                  <p class="mt-1 text-xs text-slate-400">D2 ou D3 pour un doctorant déjà avancé. Les droits d'inscription sont ajoutés selon les tarifs des Paramètres.</p>
                </div>
                <Switch id="inscrire" v-model="formulaire.inscrire" />
              </div>
              <div v-if="formulaire.inscrire" class="mt-4 grid gap-4 sm:grid-cols-2">
                <ChampFormulaire libelle="Année" pour="insc-annee" :erreur="erreurs['inscription.annee_universitaire_id']" requis>
                  <Select v-model="formulaire.inscription.annee_universitaire_id">
                    <SelectTrigger id="insc-annee" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="annee in annees" :key="annee.id" :value="annee.id">{{ annee.libelle }}</SelectItem>
                    </SelectContent>
                  </Select>
                </ChampFormulaire>
                <ChampFormulaire libelle="Niveau" pour="insc-niveau" :erreur="erreurs['inscription.niveau']" requis>
                  <Select v-model="formulaire.inscription.niveau">
                    <SelectTrigger id="insc-niveau" class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="niveau in ['D1', 'D2', 'D3']" :key="niveau" :value="niveau">{{ niveau }}</SelectItem>
                    </SelectContent>
                  </Select>
                </ChampFormulaire>

              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <ChampFormulaire v-if="!candidat" libelle="Matricule" pour="matricule" :erreur="erreurs.matricule" :aide="modification ? 'Modifiable si besoin ; il doit rester unique.' : `Laisser vide pour un numéro automatique${prochainMatricule ? ` (${prochainMatricule})` : ''}.`">
                <Input id="matricule" v-model="formulaire.matricule" :aria-invalid="!!erreurs.matricule" />
              </ChampFormulaire>
              <div class="flex items-start justify-between gap-4 rounded-md border p-4">
                <div>
                  <Label for="boursier">Boursier</Label>
                  <p class="mt-1 text-xs text-slate-400">Le doctorant bénéficie d'une bourse de thèse.</p>
                </div>
                <Switch id="boursier" v-model="formulaire.est_boursier" />
              </div>
              <div class="flex items-start justify-between gap-4 rounded-md border p-4">
                <div>
                  <Label for="annuaire">Visible dans l'annuaire du site</Label>
                  <p class="mt-1 text-xs text-slate-400">Seulement avec l'accord du doctorant.</p>
                </div>
                <Switch id="annuaire" v-model="formulaire.visible_annuaire" />
              </div>
              <ChampFormulaire libelle="Observation interne" pour="observation" :erreur="erreurs.observation" aide="Visible uniquement par la scolarité." class="sm:col-span-2">
                <Textarea id="observation" v-model="formulaire.observation" rows="2" />
              </ChampFormulaire>
            </div>
          </div>

          <!-- 5. Récapitulatif -->
          <div v-else class="grid gap-4 md:grid-cols-2">
            <section v-for="bloc in recapitulatif" :key="bloc.titre" class="rounded-md border border-gray-200 p-4">
              <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">{{ bloc.titre }}</h3>
                <Button type="button" variant="ghost" size="sm" class="text-brand-600" @click="allerA(bloc.etape)"><Pencil /> Modifier</Button>
              </div>
              <dl class="grid gap-2 text-sm">
                <div v-for="[libelle, valeur] in bloc.lignes" :key="libelle" class="grid grid-cols-[8.5rem_1fr] gap-2">
                  <dt class="text-slate-400">{{ libelle }}</dt>
                  <dd class="min-w-0 break-words font-medium text-slate-700">{{ valeur }}</dd>
                </div>
              </dl>
            </section>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-4 sm:px-7">
          <Button v-if="courante > 0" type="button" variant="outline" @click="allerA(courante - 1)"><ArrowLeft /> Précédent</Button>
          <Button v-else-if="modification" type="button" variant="ghost" class="text-slate-500" as-child><NuxtLink :to="`/doctorants/${doctorant!.id}`">Annuler</NuxtLink></Button>
          <Button v-else type="button" variant="ghost" class="text-slate-500" @click="recommencer">Vider le formulaire</Button>
          <div class="flex flex-wrap gap-2">
            <Button v-if="modification && courante < ETAPES.length - 1" type="button" variant="outline" :disabled="envoi" @click="enregistrer()">
              <Check /> Enregistrer maintenant
            </Button>
            <Button v-if="courante < ETAPES.length - 1" type="submit" :disabled="envoi">
              <Loader2 v-if="envoi" class="animate-spin" />
              Suivant <ArrowRight />
            </Button>
            <Button v-else type="submit" :disabled="envoi">
              <Loader2 v-if="envoi" class="animate-spin" /><Check v-else />
              {{ modification ? 'Enregistrer les modifications' : candidat ? 'Enregistrer la candidature' : 'Créer le dossier' }}
            </Button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

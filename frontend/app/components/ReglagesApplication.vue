<script setup lang="ts">
import { Check, ImageUp, Loader2, Monitor, Moon, RotateCcw, Save, Sun } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { Component } from 'vue'
import type { Couleur, Theme } from '~/composables/useApplication'

/**
 * Réglages généraux : nom de la plateforme, École (description, ministère, arrêté), directeur, logos
 * (repris par les actes, les reçus et la carte de doctorat), moteurs de recherche, maintenance et apparence.
 */
interface Reglages {
  app_nom: string
  app_sous_titre: string
  ecole_nom: string
  ecole_universite: string
  ecole_ministere: string
  ecole_arrete: string
  ecole_description: string
  maintenance_message: string
  signataire_nom: string
  signataire_grade: string
  signataire_titre: string
  indexation: boolean
  maintenance_active: boolean
  theme: Theme
  couleur: Couleur
  logos: Record<'logo_edgvm' | 'logo_universite', { url: string, personnalise: boolean }>
}

const api = useApi()
const { application, charger } = useApplication()
const formulaire = reactive<Omit<Reglages, 'logos'>>({
  app_nom: '', app_sous_titre: '', ecole_nom: '', ecole_universite: '', ecole_ministere: '', ecole_arrete: '', ecole_description: '',
  maintenance_message: '', signataire_nom: '', signataire_grade: '', signataire_titre: '', indexation: false, maintenance_active: false, theme: 'clair', couleur: 'vert',
})
const logos = ref<Reglages['logos'] | null>(null)
const fichiers = reactive<Record<string, File | null>>({ logo_edgvm: null, logo_universite: null })
const retirer = reactive<Record<string, boolean>>({ logo_edgvm: false, logo_universite: false })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const chargement = ref(true)

function remplir(donnees: Reglages) {
  const { logos: images, ...reste } = donnees
  Object.assign(formulaire, reste)
  logos.value = images
  Object.keys(fichiers).forEach((cle) => { fichiers[cle] = null; retirer[cle] = false })
}

onMounted(async () => {
  try {
    remplir((await api<{ data: Reglages }>('/api/reglages-application')).data)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    chargement.value = false
  }
})

const LOGOS = [
  { cle: 'logo_edgvm', libelle: "Logo de l'École (EDGVM)", aide: 'Barre latérale, connexion, icône de l\'onglet, en-têtes des documents à droite.' },
  { cle: 'logo_universite', libelle: "Logo de l'Université", aide: 'En-têtes des actes, des reçus et de la carte, à gauche.' },
] as const
const apercus = reactive<Record<string, string | null>>({ logo_edgvm: null, logo_universite: null })
function choisir(cle: string, evenement: Event) {
  const fichier = (evenement.target as HTMLInputElement).files?.[0] ?? null
  ;(evenement.target as HTMLInputElement).value = ''
  if (!fichier) return
  if (apercus[cle]) URL.revokeObjectURL(apercus[cle]!)
  fichiers[cle] = fichier
  retirer[cle] = false
  apercus[cle] = URL.createObjectURL(fichier)
}
function revenirAuDefaut(cle: string) {
  fichiers[cle] = null
  apercus[cle] = null
  retirer[cle] = true
}
const urlLogo = (cle: 'logo_edgvm' | 'logo_universite') => apercus[cle] ?? (retirer[cle] ? null : logos.value?.[cle].url ?? null)

const THEMES: { valeur: Theme, libelle: string, icone: Component }[] = [
  { valeur: 'clair', libelle: 'Clair', icone: Sun },
  { valeur: 'sombre', libelle: 'Sombre', icone: Moon },
  { valeur: 'systeme', libelle: 'Comme le système', icone: Monitor },
]

/* Aperçu immédiat de l'apparence ; si on n'enregistre pas, la page suivante revient au réglage enregistré. */
watch(() => [formulaire.theme, formulaire.couleur] as const, ([theme, couleur]) => {
  if (!chargement.value) application.value = { ...application.value, apparence: { theme, couleur } }
})

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  const donnees = new FormData()
  Object.entries(formulaire).forEach(([cle, valeur]) => donnees.append(cle, typeof valeur === 'boolean' ? (valeur ? '1' : '0') : String(valeur ?? '')))
  Object.entries(fichiers).forEach(([cle, fichier]) => { if (fichier) donnees.append(cle, fichier) })
  Object.entries(retirer).forEach(([cle, oui]) => { if (oui) donnees.append(`retirer_${cle}`, '1') })
  try {
    const reponse = await api<{ data: Reglages, message: string }>('/api/reglages-application', { method: 'POST', body: donnees })
    remplir(reponse.data)
    Object.keys(apercus).forEach((cle) => { apercus[cle] = null })
    await charger()
    toast.success(reponse.message)
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    toast.error(Object.keys(erreurs.value).length ? 'Une information est à corriger.' : messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

const section = 'rounded-md border border-gray-300 bg-white'
const enTete = 'border-b border-gray-200 px-5 py-4'
</script>

<template>
  <SqueletteFiche v-if="chargement" />
  <form v-else class="grid gap-6 xl:grid-cols-2" novalidate @submit.prevent="enregistrer">
    <!-- Plateforme et École -->
    <section :class="section" aria-labelledby="titre-ecole">
      <div :class="enTete">
        <h2 id="titre-ecole" class="font-bold text-slate-700">Plateforme et École</h2>
        <p class="text-sm text-slate-400">Nom affiché partout, et textes officiels repris sur les documents.</p>
      </div>
      <div class="grid gap-4 p-5 sm:grid-cols-2">
        <ChampFormulaire libelle="Nom de la plateforme" pour="app-nom" :erreur="erreurs.app_nom" requis aide="Barre latérale, onglet du navigateur, courriels.">
          <Input id="app-nom" v-model="formulaire.app_nom" maxlength="60" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Sous-titre" pour="app-sous-titre" :erreur="erreurs.app_sous_titre">
          <Input id="app-sous-titre" v-model="formulaire.app_sous_titre" maxlength="120" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Nom de l'École" pour="ecole-nom" :erreur="erreurs.ecole_nom" requis class="sm:col-span-2">
          <Input id="ecole-nom" v-model="formulaire.ecole_nom" maxlength="190" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Université" pour="ecole-universite" :erreur="erreurs.ecole_universite" requis class="sm:col-span-2">
          <Input id="ecole-universite" v-model="formulaire.ecole_universite" maxlength="120" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Ministère de tutelle" pour="ecole-ministere" :erreur="erreurs.ecole_ministere" requis class="sm:col-span-2">
          <Input id="ecole-ministere" v-model="formulaire.ecole_ministere" maxlength="255" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Arrêté ou décret d'habilitation" pour="ecole-arrete" :erreur="erreurs.ecole_arrete" requis aide="Imprimé sur la carte de doctorat." class="sm:col-span-2">
          <Textarea id="ecole-arrete" v-model="formulaire.ecole_arrete" rows="2" maxlength="255" class="resize-y" />
        </ChampFormulaire>
        <ChampFormulaire libelle="Description de l'École" pour="ecole-description" :erreur="erreurs.ecole_description" aide="Présentation courte : mission, domaines, équipes." class="sm:col-span-2">
          <Textarea id="ecole-description" v-model="formulaire.ecole_description" rows="4" maxlength="3000" class="resize-y leading-relaxed" />
        </ChampFormulaire>
      </div>
    </section>

    <div class="grid content-start gap-6">
      <!-- Directeur -->
      <section :class="section" aria-labelledby="titre-directeur">
        <div :class="enTete">
          <h2 id="titre-directeur" class="font-bold text-slate-700">Directeur de l'École</h2>
          <p class="text-sm text-slate-400">Repris sur la carte de doctorat et, si demandé, en bas des actes.</p>
        </div>
        <div class="grid gap-4 p-5 sm:grid-cols-[9rem_minmax(0,1fr)]">
          <ChampFormulaire libelle="Grade" pour="dir-grade" :erreur="erreurs.signataire_grade" aide="Devant le nom.">
            <Input id="dir-grade" v-model="formulaire.signataire_grade" list="grades-directeur" maxlength="60" placeholder="Professeure" />
            <datalist id="grades-directeur"><option value="Professeur" /><option value="Professeure" /><option value="Pr" /><option value="Dr" /></datalist>
          </ChampFormulaire>
          <ChampFormulaire libelle="Nom et prénoms" pour="dir-nom" :erreur="erreurs.signataire_nom" requis>
            <Input id="dir-nom" v-model="formulaire.signataire_nom" maxlength="120" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Titre complet" pour="dir-titre" :erreur="erreurs.signataire_titre" aide="Sous le nom dans les actes, en italique." class="sm:col-span-2">
            <Input id="dir-titre" v-model="formulaire.signataire_titre" maxlength="120" placeholder="Professeure Titulaire" />
          </ChampFormulaire>
          <p class="rounded-md bg-gray-50 px-4 py-3 text-sm text-slate-600 sm:col-span-2">
            Sur la carte : <span class="font-semibold text-slate-800">Le Directeur, {{ [formulaire.signataire_grade, formulaire.signataire_nom].filter(Boolean).join(' ') }}</span>
          </p>
        </div>
      </section>

      <!-- Logos -->
      <section :class="section" aria-labelledby="titre-logos">
        <div :class="enTete">
          <h2 id="titre-logos" class="font-bold text-slate-700">Logos</h2>
          <p class="text-sm text-slate-400">Utilisés par l'interface et par tous les documents générés : actes, reçus, cartes. Les actes déjà délivrés gardent les logos de leur date.</p>
        </div>
        <ul class="grid gap-4 p-5">
          <li v-for="logo in LOGOS" :key="logo.cle" class="flex flex-wrap items-center gap-4">
            <div class="flex h-20 w-28 flex-none items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-white p-1">
              <img v-if="urlLogo(logo.cle)" :src="urlLogo(logo.cle)!" alt="" class="max-h-full max-w-full object-contain">
              <span v-else class="px-1 text-center text-xs text-slate-400">Logo d'origine à l'enregistrement</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-slate-700">{{ logo.libelle }}</p>
              <p class="text-xs text-slate-400">{{ logo.aide }}</p>
              <p v-if="erreurs[logo.cle]" class="text-sm text-destructive">{{ erreurs[logo.cle] }}</p>
            </div>
            <div class="flex flex-none gap-1">
              <Button type="button" variant="outline" size="sm" as-child>
                <label class="cursor-pointer"><ImageUp /> Changer<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="choisir(logo.cle, $event)"></label>
              </Button>
              <Button v-if="(logos?.[logo.cle].personnalise || fichiers[logo.cle]) && !retirer[logo.cle]" type="button" variant="ghost" size="sm" class="text-slate-500" @click="revenirAuDefaut(logo.cle)"><RotateCcw /> Par défaut</Button>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <!-- Accès et visibilité -->
    <section :class="section" aria-labelledby="titre-acces">
      <div :class="enTete">
        <h2 id="titre-acces" class="font-bold text-slate-700">Accès et visibilité</h2>
        <p class="text-sm text-slate-400">Moteurs de recherche et maintenance.</p>
      </div>
      <div class="grid gap-4 p-5">
        <div class="flex items-start justify-between gap-4 rounded-md border p-4">
          <div>
            <Label for="indexation">Visible dans les moteurs de recherche</Label>
            <p class="mt-1 text-xs text-slate-400">Désactivé (conseillé) : Google et les autres moteurs n'indexent pas la plateforme. Les données restent de toute façon protégées par la connexion.</p>
          </div>
          <Switch id="indexation" v-model="formulaire.indexation" />
        </div>
        <div class="rounded-md border p-4" :class="formulaire.maintenance_active ? 'border-sun-300 bg-sun-50' : ''">
          <div class="flex items-start justify-between gap-4">
            <div>
              <Label for="maintenance">Mode maintenance</Label>
              <p class="mt-1 text-xs text-slate-400">Seule l'administration garde l'accès. Les doctorants et les membres d'équipe voient le message ci-dessous.</p>
            </div>
            <Switch id="maintenance" v-model="formulaire.maintenance_active" />
          </div>
          <ChampFormulaire libelle="Message de maintenance" pour="maintenance-message" :erreur="erreurs.maintenance_message" :requis="formulaire.maintenance_active" class="mt-4">
            <Textarea id="maintenance-message" v-model="formulaire.maintenance_message" rows="3" maxlength="500" class="resize-y bg-white" />
          </ChampFormulaire>
        </div>
      </div>
    </section>

    <!-- Apparence -->
    <section :class="section" aria-labelledby="titre-apparence">
      <div :class="enTete">
        <h2 id="titre-apparence" class="font-bold text-slate-700">Apparence</h2>
        <p class="text-sm text-slate-400">Réglage de l'École pour tous. Chacun peut choisir clair ou sombre pour lui-même dans son menu.</p>
      </div>
      <div class="grid gap-5 p-5">
        <fieldset>
          <legend class="mb-2 text-sm font-medium text-slate-700">Mode</legend>
          <div class="grid grid-cols-3 gap-2">
            <label
              v-for="t in THEMES"
              :key="t.valeur"
              class="flex cursor-pointer flex-col items-center gap-2 rounded-md border p-3 text-sm font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500"
              :class="formulaire.theme === t.valeur ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-gray-300 text-slate-500 hover:border-brand-300'"
            >
              <input v-model="formulaire.theme" type="radio" name="theme" :value="t.valeur" class="sr-only">
              <component :is="t.icone" class="size-5" aria-hidden="true" />{{ t.libelle }}
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend class="mb-2 text-sm font-medium text-slate-700">Couleur principale</legend>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <label
              v-for="(palette, cle) in PALETTES"
              :key="cle"
              class="flex cursor-pointer items-center gap-2 rounded-md border p-2.5 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500"
              :class="formulaire.couleur === cle ? 'border-brand-500 bg-brand-50 font-semibold text-brand-800' : 'border-gray-300 text-slate-600 hover:border-brand-300'"
            >
              <input v-model="formulaire.couleur" type="radio" name="couleur" :value="cle" class="sr-only">
              <span class="flex size-6 flex-none items-center justify-center rounded-full" :style="{ background: palette.nuances[8] }">
                <Check v-if="formulaire.couleur === cle" class="size-3.5" style="color: #fff" aria-hidden="true" />
              </span>
              {{ palette.libelle }}
            </label>
          </div>
          <p class="mt-2 text-xs text-slate-400">L'aperçu s'applique tout de suite ; il faut enregistrer pour le garder.</p>
        </fieldset>
      </div>
    </section>

    <div class="flex justify-end xl:col-span-2">
      <Button type="submit" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /><Save v-else /> Enregistrer les réglages</Button>
    </div>
  </form>
</template>

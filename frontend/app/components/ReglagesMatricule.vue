<script setup lang="ts">
import { Hash, Loader2, Save } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

/**
 * Modèle du matricule automatique des doctorants : texte libre et repères (année, numéro d'ordre),
 * année civile ou universitaire, compteur annuel ou continu, numéro de départ. Aperçu en direct.
 */
interface Reglages { format: string, annee: 'civile' | 'universitaire', remise: 'annuelle' | 'jamais', depart: number }

const api = useApi()
const formulaire = reactive<Reglages>({ format: '', annee: 'civile', remise: 'annuelle', depart: 1 })
const apercu = ref('')
const suivant = ref('')
const erreurs = ref<Record<string, string>>({})
const chargement = ref(true)
const envoi = ref(false)
const champ = ref<InstanceType<typeof Input> | null>(null)

onMounted(async () => {
  try {
    const { data } = await api<{ data: Reglages & { apercu: string } }>('/api/reglages-matricule')
    const { apercu: prochain, ...reglages } = data
    Object.assign(formulaire, reglages)
    apercu.value = prochain
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    chargement.value = false
  }
})

const actualiserApercu = useDebounceFn(async () => {
  try {
    const { data } = await api<{ data: { apercu: string, suivants: string[] } }>('/api/reglages-matricule/apercu', { method: 'POST', body: formulaire })
    apercu.value = data.apercu
    suivant.value = data.suivants[0] ?? ''
    erreurs.value = {}
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    apercu.value = ''
    suivant.value = ''
  }
}, 350)
watch(formulaire, () => { if (!chargement.value) actualiserApercu() }, { deep: true })

const REPERES = [
  { code: '{AAAA}', aide: 'Année sur 4 chiffres (2026)' },
  { code: '{AA}', aide: 'Année sur 2 chiffres (26)' },
  { code: '{NNNN}', aide: 'Numéro d\'ordre sur 4 chiffres (0001). Autant de N que de chiffres.' },
]
const MODELES = ['EDGVM-{AAAA}-{NNNN}', '{NNN}-{AA}', 'EDGVM/{AA}/{NNNNN}', 'DOC-{AAAA}{NNNN}']

/* Insère un repère là où se trouve le curseur dans le champ du modèle. */
function inserer(code: string) {
  const element = (champ.value?.$el as HTMLInputElement | undefined)
  const debut = element?.selectionStart ?? formulaire.format.length
  const fin = element?.selectionEnd ?? debut
  formulaire.format = formulaire.format.slice(0, debut) + code + formulaire.format.slice(fin)
  nextTick(() => {
    element?.focus()
    element?.setSelectionRange(debut + code.length, debut + code.length)
  })
}

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const reponse = await api<{ data: Reglages & { apercu: string }, message: string }>('/api/reglages-matricule', { method: 'POST', body: formulaire })
    apercu.value = reponse.data.apercu
    toast.success(reponse.message)
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    toast.error(Object.keys(erreurs.value).length ? 'Le modèle est à corriger.' : messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

const choix = (actif: boolean) => actif ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-gray-300 text-slate-600 hover:border-brand-300'
</script>

<template>
  <SqueletteFiche v-if="chargement" />
  <form v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]" novalidate @submit.prevent="enregistrer">
    <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-matricule">
      <div class="border-b border-gray-200 px-5 py-4">
        <h2 id="titre-matricule" class="font-bold text-slate-700">Matricule des doctorants</h2>
        <p class="text-sm text-slate-400">Numéro attribué automatiquement à la création d'un dossier quand le champ matricule est laissé vide. Les matricules déjà attribués ne changent pas.</p>
      </div>

      <div class="grid gap-6 p-5">
        <ChampFormulaire libelle="Modèle" pour="format" :erreur="erreurs.format" requis>
          <Input id="format" ref="champ" v-model="formulaire.format" maxlength="40" class="font-mono text-base" autocomplete="off" spellcheck="false" />
          <div class="flex flex-wrap gap-2">
            <button v-for="repere in REPERES" :key="repere.code" type="button" class="rounded border border-brand-200 bg-brand-50 px-2 py-1 font-mono text-xs font-semibold text-brand-800 hover:border-brand-400" :title="repere.aide" @click="inserer(repere.code)">+ {{ repere.code }}</button>
          </div>
          <ul class="grid gap-1 text-xs text-slate-500">
            <li v-for="repere in REPERES" :key="repere.code"><code class="font-semibold text-slate-700">{{ repere.code }}</code> : {{ repere.aide }}</li>
            <li>Le reste est recopié tel quel : lettres, chiffres, espace, tiret, barre oblique, point, soulignement.</li>
          </ul>
        </ChampFormulaire>

        <div>
          <p class="mb-2 text-sm font-medium text-slate-700">Modèles courants</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="modele in MODELES" :key="modele" type="button" class="rounded-md border px-2.5 py-1.5 font-mono text-xs transition-colors" :class="choix(formulaire.format === modele)" @click="formulaire.format = modele">{{ modele }}</button>
          </div>
        </div>

        <fieldset>
          <legend class="mb-2 text-sm font-medium text-slate-700">Année utilisée</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label class="cursor-pointer rounded-md border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="choix(formulaire.annee === 'civile')">
              <input v-model="formulaire.annee" type="radio" value="civile" class="sr-only">
              <span class="block font-semibold">Année civile</span>
              <span class="text-xs text-slate-500">L'année de création du dossier.</span>
            </label>
            <label class="cursor-pointer rounded-md border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="choix(formulaire.annee === 'universitaire')">
              <input v-model="formulaire.annee" type="radio" value="universitaire" class="sr-only">
              <span class="block font-semibold">Année universitaire</span>
              <span class="text-xs text-slate-500">L'année de rentrée de l'année en cours (2026 pour 2026-2027).</span>
            </label>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-2 text-sm font-medium text-slate-700">Numéro d'ordre</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label class="cursor-pointer rounded-md border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="choix(formulaire.remise === 'annuelle')">
              <input v-model="formulaire.remise" type="radio" value="annuelle" class="sr-only">
              <span class="block font-semibold">Repart à 1 chaque année</span>
              <span class="text-xs text-slate-500">Le modèle doit alors contenir l'année.</span>
            </label>
            <label class="cursor-pointer rounded-md border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="choix(formulaire.remise === 'jamais')">
              <input v-model="formulaire.remise" type="radio" value="jamais" class="sr-only">
              <span class="block font-semibold">Continue d'une année à l'autre</span>
              <span class="text-xs text-slate-500">Un seul compteur pour tous les doctorants.</span>
            </label>
          </div>
        </fieldset>

        <ChampFormulaire libelle="Numéro de départ" pour="depart" :erreur="erreurs.depart" :aide="formulaire.remise === 'annuelle' ? 'Pour reprendre la numérotation papier de cette année : 271 si le dernier numéro est 270. L\'année suivante repart à 1. Le compteur ne redescend jamais sous un numéro déjà attribué.' : 'Pour reprendre une numérotation existante : 271 si le dernier numéro est 270. Le compteur ne redescend jamais sous un numéro déjà attribué.'" class="sm:max-w-xs">
          <Input id="depart" v-model.number="formulaire.depart" type="number" min="1" />
        </ChampFormulaire>
      </div>
    </section>

    <aside class="grid content-start gap-4">
      <div class="rounded-md border border-gray-300 bg-white p-5" aria-live="polite">
        <p class="flex items-center gap-2 text-sm text-slate-400"><Hash class="size-4" aria-hidden="true" /> Prochain matricule</p>
        <p class="mt-2 break-all font-mono text-2xl font-bold" :class="apercu ? 'text-brand-800' : 'text-slate-300'">{{ apercu || 'Modèle à corriger' }}</p>
        <p v-if="suivant && apercu && suivant !== apercu" class="mt-3 text-xs text-slate-400">{{ formulaire.remise === 'annuelle' ? 'Premier numéro de l\'année suivante' : 'Le même dossier créé l\'année suivante' }} : <span class="font-mono text-slate-600">{{ suivant }}</span></p>
      </div>
      <p class="text-xs text-slate-400">Un matricule peut toujours être saisi ou corrigé à la main dans le dossier (étape Suivi).</p>
      <Button type="submit" :disabled="envoi || !apercu"><Loader2 v-if="envoi" class="animate-spin" /><Save v-else /> Enregistrer le modèle</Button>
    </aside>
  </form>
</template>

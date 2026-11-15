<script setup lang="ts">
import { ArrowLeft, Braces, Eye, Loader2, Plus, Save, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import type { ChampModele, ModeleActe, Ressource, VariableActe } from '~/types'

definePageMeta({ roles: ['super_admin'] })

const route = useRoute()
const api = useApi()
const nouveau = route.params.id === 'nouveau'

const formulaire = reactive({
  nom: '',
  titre: '',
  corps: '<p></p>',
  champs: [] as ChampModele[],
  exige_inscription: false,
  inclure_signature: true,
  actif: true,
})
const actesDelivres = ref(0)
const variables = ref<VariableActe[]>([])
const charge = ref(nouveau)
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const editeur = ref<{ inserer: (texte: string) => void } | null>(null)

onMounted(async () => {
  const [liste, modele] = await Promise.all([
    api<{ variables: VariableActe[] }>('/api/modeles-actes'),
    nouveau ? Promise.resolve(null) : api<Ressource<ModeleActe>>(`/api/modeles-actes/${route.params.id}`),
  ])
  variables.value = liste.variables
  if (modele) {
    const { nom, titre, corps, champs, exige_inscription, inclure_signature, actif } = modele.data
    Object.assign(formulaire, { nom, titre, corps, champs: champs.map(c => ({ ...c, options: c.options ?? [] })), exige_inscription, inclure_signature, actif })
    actesDelivres.value = modele.data.actes_count ?? 0
  }
  charge.value = true
})
useHead(() => ({ title: nouveau ? "Nouveau modèle d'acte" : `Modèle : ${formulaire.nom}` }))

/* Champs complémentaires : à remplir au moment de délivrer l'acte */
const TYPES = [
  { valeur: 'texte', libelle: 'Texte court' },
  { valeur: 'texte_long', libelle: 'Texte long' },
  { valeur: 'date', libelle: 'Date' },
  { valeur: 'heure', libelle: 'Heure' },
  { valeur: 'choix', libelle: 'Liste de choix' },
]
const cleDe = (libelle: string) => libelle.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')
const ajouterChamp = () => formulaire.champs.push({ cle: '', libelle: '', type: 'texte', options: [], obligatoire: true })
const optionsTexte = (champ: ChampModele) => (champ.options ?? []).join(', ')
const definirOptions = (champ: ChampModele, texte: string) => { champ.options = texte.split(',').map(o => o.trim()).filter(Boolean) }

/** « {{cle}} » construit sans accolades doubles dans le gabarit Vue */
const jeton = (cle: string) => ['{', '{', cle, '}', '}'].join('')
const inserer = (cle: string) => editeur.value?.inserer(jeton(cle))

const corpsEnvoye = () => ({
  ...formulaire,
  champs: formulaire.champs.map(c => ({ ...c, cle: cleDe(c.libelle), options: c.type === 'choix' ? c.options : undefined })),
})

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const reponse = nouveau
      ? await api<Ressource<ModeleActe>>('/api/modeles-actes', { method: 'POST', body: corpsEnvoye() })
      : await api<Ressource<ModeleActe>>(`/api/modeles-actes/${route.params.id}`, { method: 'PUT', body: corpsEnvoye() })
    toast.success('Modèle enregistré. Les prochains actes utiliseront ce texte.')
    if (nouveau) await navigateTo(`/actes/modeles/${reponse.data.id}`, { replace: true })
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    toast.error(Object.keys(erreurs.value).length ? Object.values(erreurs.value)[0]! : messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

/* Aperçu PDF du texte en cours, sans enregistrer */
const apercuUrl = ref<string | null>(null)
const apercuOuvert = ref(false)
const apercuEnCours = ref(false)
async function apercu() {
  apercuEnCours.value = true
  erreurs.value = {}
  try {
    const pdf = await api<Blob>('/api/modeles-actes/apercu', { method: 'POST', body: corpsEnvoye(), responseType: 'blob' })
    if (apercuUrl.value) URL.revokeObjectURL(apercuUrl.value)
    apercuUrl.value = URL.createObjectURL(pdf)
    apercuOuvert.value = true
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    toast.error(Object.keys(erreurs.value).length ? Object.values(erreurs.value)[0]! : messageErreur(e))
  }
  finally {
    apercuEnCours.value = false
  }
}
onBeforeUnmount(() => { if (apercuUrl.value) URL.revokeObjectURL(apercuUrl.value) })
</script>

<template>
  <div>
    <NuxtLink to="/actes/modeles" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-700">
      <ArrowLeft class="size-4" /> Modèles d'actes
    </NuxtLink>
    <EnTetePage :titre="nouveau ? 'Nouveau modèle d\'acte' : formulaire.nom || 'Modèle d\'acte'" :description="actesDelivres ? `${actesDelivres} acte(s) déjà délivré(s) avec ce modèle : ils gardent leur texte d'origine.` : 'Le texte, les variables et les champs à remplir lors de la délivrance.'">
      <template #actions>
        <Button variant="outline" class="bg-white" :disabled="apercuEnCours || !charge" @click="apercu">
          <Loader2 v-if="apercuEnCours" class="animate-spin" /><Eye v-else /> Aperçu PDF
        </Button>
        <Button :disabled="envoi || !charge" @click="enregistrer">
          <Loader2 v-if="envoi" class="animate-spin" /><Save v-else /> Enregistrer
        </Button>
      </template>
    </EnTetePage>

    <SqueletteFiche v-if="!charge" />

    <div v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="grid min-w-0 grid-cols-1 content-start gap-6">
        <div class="grid gap-4 rounded-md border border-gray-300 bg-white p-5 sm:grid-cols-2">
          <ChampFormulaire libelle="Nom du modèle" pour="nom" :erreur="erreurs.nom" aide="Affiché dans les listes, par exemple « Attestation d'inscription »." requis>
            <Input id="nom" v-model="formulaire.nom" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Titre imprimé sur l'acte" pour="titre" :erreur="erreurs.titre" aide="En majuscules, souligné sur le document." requis>
            <Input id="titre" v-model="formulaire.titre" class="font-serif uppercase" />
          </ChampFormulaire>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between gap-3">
            <Label>Texte de l'acte</Label>
            <span class="text-xs text-slate-400">L'en-tête, la date et la signature sont ajoutés automatiquement.</span>
          </div>
          <EditeurTexte ref="editeur" v-model="formulaire.corps" />
          <p v-if="erreurs.corps" class="mt-2 text-sm text-destructive">{{ erreurs.corps }}</p>
        </div>

        <div class="rounded-md border border-gray-300 bg-white">
          <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
            <div>
              <h2 class="font-bold text-slate-700">Champs à remplir à la délivrance</h2>
              <p class="text-sm text-slate-400">Pour les informations propres à cet acte : date d'audition, examinateurs...</p>
            </div>
            <Button variant="outline" size="sm" @click="ajouterChamp"><Plus /> Ajouter</Button>
          </div>
          <p v-if="!formulaire.champs.length" class="px-5 py-6 text-sm text-slate-400">Aucun champ : l'acte n'utilise que les informations du dossier.</p>
          <ul v-else class="divide-y divide-gray-200">
            <li v-for="(champ, index) in formulaire.champs" :key="index" class="grid gap-3 px-5 py-4 md:grid-cols-[1fr_11rem_auto] md:items-start">
              <div class="grid gap-1.5">
                <Input v-model="champ.libelle" placeholder="Libellé, par exemple « Date d'audition »" :aria-label="`Libellé du champ ${index + 1}`" />
                <p class="text-xs text-slate-400">
                  Variable : <button type="button" class="font-mono text-brand-700 hover:underline" :disabled="!champ.libelle" @click="inserer(cleDe(champ.libelle))">{{ champ.libelle ? jeton(cleDe(champ.libelle)) : '...' }}</button>
                </p>
                <p v-if="erreurs[`champs.${index}.cle`] || erreurs[`champs.${index}.libelle`]" class="text-sm text-destructive">{{ erreurs[`champs.${index}.cle`] || erreurs[`champs.${index}.libelle`] }}</p>
                <div v-if="champ.type === 'choix'">
                  <Input :model-value="optionsTexte(champ)" placeholder="Choix séparés par des virgules : Admission, Ajournement" :aria-label="`Choix du champ ${index + 1}`" @update:model-value="valeur => definirOptions(champ, String(valeur))" />
                  <p v-if="erreurs[`champs.${index}.options`]" class="mt-1 text-sm text-destructive">{{ erreurs[`champs.${index}.options`] }}</p>
                </div>
              </div>
              <Select v-model="champ.type">
                <SelectTrigger class="w-full" :aria-label="`Type du champ ${index + 1}`"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="type in TYPES" :key="type.valeur" :value="type.valeur">{{ type.libelle }}</SelectItem>
                </SelectContent>
              </Select>
              <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600"><Switch v-model="champ.obligatoire" /> Obligatoire</label>
                <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Retirer le champ ${champ.libelle}`" @click="formulaire.champs.splice(index, 1)"><Trash2 /></Button>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <aside class="grid min-w-0 grid-cols-1 content-start gap-6">
        <div class="rounded-md border border-gray-300 bg-white p-5">
          <h2 class="mb-4 font-bold text-slate-700">Options</h2>
          <div class="grid gap-4 text-sm">
            <div class="flex items-start justify-between gap-4">
              <div><Label for="exige">Exige une inscription</Label><p class="mt-1 text-xs text-slate-400">Seulement pour un doctorant inscrit et validé pour l'année choisie.</p></div>
              <Switch id="exige" v-model="formulaire.exige_inscription" />
            </div>
            <div class="flex items-start justify-between gap-4">
              <div><Label for="signature">Signature scannée</Label><p class="mt-1 text-xs text-slate-400">Ajoute l'image de signature et cachet des réglages.</p></div>
              <Switch id="signature" v-model="formulaire.inclure_signature" />
            </div>
            <div class="flex items-start justify-between gap-4">
              <div><Label for="actif">Proposé à la délivrance</Label><p class="mt-1 text-xs text-slate-400">Désactivez un modèle qui ne sert plus.</p></div>
              <Switch id="actif" v-model="formulaire.actif" />
            </div>
          </div>
        </div>

        <div class="rounded-md border border-gray-300 bg-white">
          <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4">
            <Braces class="size-4 text-brand-600" />
            <h2 class="font-bold text-slate-700">Variables</h2>
          </div>
          <p class="px-5 pt-3 text-xs text-slate-400">Cliquez pour insérer à l'endroit du curseur. Elles sont remplacées par les informations du dossier.</p>
          <ul class="max-h-[28rem] overflow-y-auto p-2">
            <li v-for="variable in variables" :key="variable.cle">
              <button type="button" class="w-full min-w-0 rounded px-3 py-2 text-left hover:bg-gray-50" :title="variable.exemple || undefined" @click="inserer(variable.cle)">
                <span class="block font-mono text-xs text-brand-700">{{ jeton(variable.cle) }}</span>
                <span class="block text-sm text-slate-600">{{ variable.libelle }}</span>
                <span v-if="variable.exemple" class="block truncate text-xs text-slate-400">ex. {{ variable.exemple }}</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>
    </div>

    <ApercuPdf v-model:open="apercuOuvert" :url="apercuUrl" :titre="`Aperçu : ${formulaire.nom || 'nouveau modèle'}`" />
  </div>
</template>

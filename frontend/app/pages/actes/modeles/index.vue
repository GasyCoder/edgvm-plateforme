<script setup lang="ts">
import { FilePlus2, FileText, ImageUp, Loader2, Pencil, Save, X } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { ModeleActe, ReglagesActes, Ressource } from '~/types'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: "Modèles d'actes" })

const api = useApi()
const route = useRoute()
const onglet = ref(route.query.onglet === 'reglages' ? 'reglages' : 'modeles')
const { data: modeles, status } = useAsyncData('modeles-actes', () => api<Ressource<ModeleActe[]>>('/api/modeles-actes').then(r => r.data), { lazy: true })

/* Réglages : signataire, ville, logos, signature et cachet */
const reglages = reactive({ signataire_nom: '', signataire_fonction: '', signataire_titre: '', ville: '', afficher_nom_signataire: false })
const images = ref<ReglagesActes['images'] | null>(null)
const fichiers = reactive<Record<string, File | null>>({ logo_gauche: null, logo_droite: null, signature: null })
const retirer = reactive<Record<string, boolean>>({ logo_gauche: false, logo_droite: false, signature: false })
const version = ref(Date.now())
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

async function chargerReglages() {
  const reponse = await api<Ressource<ReglagesActes>>('/api/reglages-actes')
  const { images: lesImages, ...textes } = reponse.data
  Object.assign(reglages, textes)
  images.value = lesImages
  version.value = Date.now()
}
onMounted(chargerReglages)

/* Les logos et le nom du directeur se règlent dans Paramètres > Application (communs à tous les documents). */
const IMAGES = [
  { cle: 'signature', libelle: 'Signature et cachet', aide: 'PNG à fond transparent conseillé, environ 600 × 300 px' },
] as const

const apercuImage = (cle: string) => fichiers[cle] ? URL.createObjectURL(fichiers[cle]!) : `/telechargements/reglages-actes/${cle}?v=${version.value}`
const montrerImage = (cle: 'logo_gauche' | 'logo_droite' | 'signature') => !retirer[cle] && (fichiers[cle] || images.value?.[cle]?.existe)

function choisir(cle: string, evenement: Event) {
  const fichier = (evenement.target as HTMLInputElement).files?.[0] ?? null
  fichiers[cle] = fichier
  retirer[cle] = false
}

async function enregistrerReglages() {
  envoi.value = true
  erreurs.value = {}
  const donnees = new FormData()
  for (const [cle, valeur] of Object.entries(reglages)) donnees.append(cle, typeof valeur === 'boolean' ? (valeur ? '1' : '0') : (valeur ?? ''))
  for (const cle of Object.keys(fichiers)) {
    if (fichiers[cle]) donnees.append(cle, fichiers[cle]!)
    if (retirer[cle]) donnees.append(`retirer_${cle}`, '1')
  }
  try {
    const reponse = await api<{ message: string }>('/api/reglages-actes', { method: 'POST', body: donnees })
    toast.success(`${reponse.message} Ils s'appliquent aux prochains actes délivrés.`)
    Object.keys(fichiers).forEach((cle) => { fichiers[cle] = null; retirer[cle] = false })
    await chargerReglages()
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
  <div>
    <EnTetePage titre="Modèles d'actes" description="Le texte des attestations et autres actes, et l'en-tête commun à tous les documents.">
      <template #actions>
        <Button variant="outline" class="bg-white" as-child><NuxtLink to="/actes"><FileText /> Actes délivrés</NuxtLink></Button>
        <Button as-child><NuxtLink to="/actes/modeles/nouveau"><FilePlus2 /> Nouveau modèle</NuxtLink></Button>
      </template>
    </EnTetePage>

    <Tabs v-model="onglet">
      <TabsList class="mb-5">
        <TabsTrigger value="modeles">Modèles</TabsTrigger>
        <TabsTrigger value="reglages">En-tête et signature</TabsTrigger>
      </TabsList>

      <TabsContent value="modeles">
        <SqueletteListe v-if="status === 'pending' && !modeles" :lignes="3" />
        <div v-else-if="modeles" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
          <NuxtLink v-for="modele in modeles" :key="modele.id" :to="`/actes/modeles/${modele.id}`" class="group rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
            <div class="flex items-start gap-3">
              <span class="flex size-10 flex-none items-center justify-center rounded-md bg-brand-50 text-brand-700"><FileText class="size-5" /></span>
              <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-700 group-hover:text-brand-700">{{ modele.nom }}</p>
                <p class="truncate font-serif text-sm text-slate-400">{{ modele.titre }}</p>
              </div>
              <Pencil class="size-4 text-slate-300 group-hover:text-brand-600" />
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
              <span class="rounded px-2 py-0.5 font-medium" :class="modele.actif ? 'bg-leaf-50 text-leaf-600' : 'bg-gray-100 text-slate-400'">{{ modele.actif ? 'Actif' : 'Désactivé' }}</span>
              <span v-if="modele.exige_inscription" class="rounded bg-brand-50 px-2 py-0.5 text-brand-800">Exige une inscription</span>
              <span v-if="modele.champs.length" class="rounded bg-gray-100 px-2 py-0.5 text-slate-500">{{ modele.champs.length }} champ(s) à remplir</span>
              <span class="rounded bg-gray-100 px-2 py-0.5 text-slate-500">{{ modele.actes_count }} délivré(s)</span>
            </div>
          </NuxtLink>
        </div>
      </TabsContent>

      <TabsContent value="reglages">
        <form class="grid gap-6 xl:grid-cols-2" novalidate @submit.prevent="enregistrerReglages">
          <div class="rounded-md border border-gray-300 bg-white p-5">
            <h2 class="mb-1 font-bold text-slate-700">Signataire</h2>
            <p class="mb-4 text-sm text-slate-400">En bas de chaque acte : « Fait à {{ reglages.ville || '...' }}, le ... », puis « {{ reglages.signataire_fonction || 'Le Directeur,' }} » et la place du cachet.</p>
            <div class="grid gap-4 sm:grid-cols-2">
              <ChampFormulaire libelle="Fonction" pour="sig-fonction" :erreur="erreurs.signataire_fonction" aide="Par exemple « Le Directeur, »." requis><Input id="sig-fonction" v-model="reglages.signataire_fonction" /></ChampFormulaire>
              <ChampFormulaire libelle="Ville" pour="ville" :erreur="erreurs.ville" requis><Input id="ville" v-model="reglages.ville" /></ChampFormulaire>
              <div class="flex items-start justify-between gap-4 rounded-md border p-4 sm:col-span-2">
                <div>
                  <Label for="afficher-nom">Imprimer le nom et le titre du signataire</Label>
                  <p class="mt-1 text-xs text-slate-400">Désactivé : le cachet rouge porte déjà le nom et le titre.</p>
                </div>
                <Switch id="afficher-nom" v-model="reglages.afficher_nom_signataire" />
              </div>
              <p v-if="reglages.afficher_nom_signataire" class="text-sm text-slate-500 sm:col-span-2">
                Imprimés : <span class="font-semibold text-slate-700">{{ reglages.signataire_nom }}</span>, <span class="italic">{{ reglages.signataire_titre }}</span>.
                <NuxtLink to="/parametres?onglet=application" class="font-medium text-brand-700 hover:underline">Modifier dans Paramètres</NuxtLink>
              </p>
            </div>
          </div>

          <div class="rounded-md border border-gray-300 bg-white p-5">
            <h2 class="mb-1 font-bold text-slate-700">Signature</h2>
            <p class="mb-4 text-sm text-slate-400">Les actes déjà délivrés gardent les images de leur date. Les logos de l'École et de l'Université se changent dans <NuxtLink to="/parametres?onglet=application" class="font-medium text-brand-700 hover:underline">Paramètres, Application</NuxtLink>.</p>
            <ul class="grid gap-4">
              <li v-for="image in IMAGES" :key="image.cle" class="flex items-center gap-4">
                <div class="flex h-16 w-28 flex-none items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-gray-50">
                  <img v-if="montrerImage(image.cle)" :src="apercuImage(image.cle)" alt="" class="max-h-14 max-w-24 object-contain">
                  <span v-else class="text-xs text-slate-400">Aucune</span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-slate-700">{{ image.libelle }}</p>
                  <p class="text-xs text-slate-400">{{ image.aide }}</p>
                  <p v-if="erreurs[image.cle]" class="text-sm text-destructive">{{ erreurs[image.cle] }}</p>
                </div>
                <div class="flex flex-none gap-1">
                  <Button type="button" variant="outline" size="sm" as-child>
                    <label class="cursor-pointer"><ImageUp /> Changer<input type="file" accept="image/png,image/jpeg" class="sr-only" @change="choisir(image.cle, $event)"></label>
                  </Button>
                  <Button v-if="images?.[image.cle]?.personnalisee && !retirer[image.cle]" type="button" variant="ghost" size="icon-sm" class="text-slate-400" :title="image.cle === 'signature' ? 'Retirer la signature' : 'Revenir au logo par défaut'" :aria-label="`Retirer ${image.libelle}`" @click="retirer[image.cle] = true; fichiers[image.cle] = null"><X /></Button>
                </div>
              </li>
            </ul>
          </div>

          <div class="flex justify-end xl:col-span-2">
            <Button type="submit" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /><Save v-else /> Enregistrer les réglages</Button>
          </div>
        </form>
      </TabsContent>
    </Tabs>
  </div>
</template>

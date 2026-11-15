<script setup lang="ts">
import { CalendarRange, FilePen, Loader2, Pencil, Plus, ReceiptText, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { Ressource, TypeFrais } from '~/types'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Paramètres' })

const api = useApi()
const { data: types, status, refresh } = useAsyncData('types-frais', () => api<Ressource<TypeFrais[]>>('/api/types-frais').then(r => r.data), { lazy: true })

const NIVEAUX = [['D1', 'D1'], ['D2', 'D2'], ['D3', 'D3'], ['derogation', 'Dérogation']] as const
const ouvert = ref(false)
const modifie = ref<TypeFrais | null>(null)
const vide = () => ({ libelle: '', description: '', par_niveau: false, montant: 0 as number, montants_niveaux: { D1: 0, D2: 0, D3: 0, derogation: 0 } as Record<string, number>, obligatoire: false, echeance: 'delai' as 'fin_annee' | 'delai', delai_jours: 30, tranches: 1, actif: true, appliquer_aux_frais: true })
const formulaire = reactive(vide())
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

function ouvrir(type: TypeFrais | null = null) {
  modifie.value = type
  Object.assign(formulaire, type
    ? { libelle: type.libelle, description: type.description ?? '', par_niveau: type.par_niveau, montant: type.montant, montants_niveaux: { ...(type.montants_niveaux ?? { D1: type.montant, D2: type.montant, D3: type.montant, derogation: type.montant }) }, obligatoire: type.obligatoire, echeance: type.echeance, delai_jours: type.delai_jours, tranches: type.tranches, actif: type.actif, appliquer_aux_frais: true }
    : vide())
  erreurs.value = {}
  ouvert.value = true
}

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const appliquer = !!modifie.value?.frais_non_soldes_count && formulaire.appliquer_aux_frais
    const reponse = await (modifie.value
      ? api<{ application: { modifies: number, annules: number, ignores: number } | null }>(`/api/types-frais/${modifie.value.id}`, { method: 'PUT', body: { ...formulaire, appliquer_aux_frais: appliquer } })
      : api<{ application: null }>('/api/types-frais', { method: 'POST', body: formulaire }))
    const a = reponse.application
    toast.success(!modifie.value
      ? 'Frais ajouté au catalogue.'
      : a
        ? `Tarif enregistré : ${a.modifies} frais mis à jour${a.annules ? `, ${a.annules} annulé(s) (tarif à 0)` : ''}${a.ignores ? `, ${a.ignores} laissé(s) tel(s) quel(s) car déjà payé(s) au-delà` : ''}.`
        : 'Tarif enregistré pour les prochains frais.')
    refreshNuxtData(['paiements-situation', 'paiements-stats'])
    ouvert.value = false
    clearNuxtData('types-frais-actifs')
    await refresh()
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

async function supprimer(type: TypeFrais) {
  if (!window.confirm(`Supprimer « ${type.libelle} » du catalogue ?`)) return
  try {
    await api(`/api/types-frais/${type.id}`, { method: 'DELETE' })
    toast.success('Frais supprimé du catalogue.')
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}

/* Aperçu du calendrier, avec le même calcul que le serveur (tranches arrondies au millier, la dernière prend le reste) */
const anneeExemple = new Date().getMonth() >= 9 ? new Date().getFullYear() : new Date().getFullYear() - 1
const exemple = computed(() => {
  const total = formulaire.par_niveau ? Number(formulaire.montants_niveaux.D1) || 0 : Number(formulaire.montant) || 0
  const nombre = Math.max(1, formulaire.tranches)
  const debut = new Date(Date.UTC(anneeExemple, 9, 1))
  const fin = new Date(Date.UTC(anneeExemple + 1, 8, 30))
  const jours = Math.round((fin.getTime() - debut.getTime()) / 86400000)
  const part = nombre > 1 ? Math.floor(total / nombre / 1000) * 1000 : total
  return Array.from({ length: nombre }, (_, index) => {
    const rang = index + 1
    const decalage = formulaire.echeance === 'fin_annee' ? (rang === nombre ? jours : Math.round(jours * rang / nombre)) : formulaire.delai_jours * rang
    const date = new Date(debut.getTime() + decalage * 86400000).toISOString().slice(0, 10)
    const montant = rang === nombre ? total - part * (nombre - 1) : part
    return `${nombre > 1 ? `Tranche ${rang} : ` : ''}${formaterMontant(montant)}${formulaire.par_niveau ? ' (D1)' : ''} avant le ${formaterDate(date)}`
  })
})

const niveauxANul = computed(() => NIVEAUX.filter(([cle]) => !Number(formulaire.montants_niveaux[cle])).map(([, libelle]) => libelle))

const tarif = (type: TypeFrais) => type.par_niveau && type.montants_niveaux
  ? NIVEAUX.map(([cle, libelle]) => `${libelle} ${formaterMontant(type.montants_niveaux![cle])}`).join(' · ')
  : type.montant ? formaterMontant(type.montant) : 'Montant à définir'
</script>

<template>
  <div>
    <EnTetePage titre="Paramètres" description="Les valeurs utilisées par la plateforme : frais et tarifs, années, modèles d'actes, connexion du site vitrine." />

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink to="/annees" class="flex items-center gap-3 rounded-md border border-gray-300 bg-white p-4 hover:border-brand-400"><CalendarRange class="size-5 text-brand-600" /><span><span class="block font-semibold text-slate-700">Années universitaires</span><span class="text-xs text-slate-400">Année en cours, dates</span></span></NuxtLink>
      <NuxtLink to="/actes/modeles" class="flex items-center gap-3 rounded-md border border-gray-300 bg-white p-4 hover:border-brand-400"><FilePen class="size-5 text-brand-600" /><span><span class="block font-semibold text-slate-700">Modèles d'actes</span><span class="text-xs text-slate-400">Textes, en-tête, signature</span></span></NuxtLink>
    </div>

    <div class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-4 sm:px-6">
        <div>
          <h2 class="font-bold text-slate-700">Frais et tarifs</h2>
          <p class="text-sm text-slate-400">Droits d'inscription par niveau, tee-shirt, cotisation sociale, communication... Un frais « automatique » est ajouté à chaque nouvelle inscription.</p>
        </div>
        <Button @click="ouvrir()"><Plus /> Nouveau frais</Button>
      </div>
      <SqueletteListe v-if="status === 'pending' && !types" :lignes="4" class="border-none" />
      <ul v-else-if="types" class="divide-y divide-gray-200">
        <li v-for="type in types" :key="type.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 sm:px-6" :class="type.actif ? '' : 'opacity-60'">
          <span class="flex size-10 flex-none items-center justify-center rounded-md bg-brand-50 text-brand-700"><ReceiptText class="size-5" /></span>
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-700">
              {{ type.libelle }}
              <span v-if="type.obligatoire" class="ms-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs font-medium text-brand-800">Automatique à l'inscription</span>
              <span v-if="!type.actif" class="ms-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-slate-500">Désactivé</span>
            </p>
            <p class="text-sm" :class="type.montant || type.par_niveau ? 'text-slate-500' : 'text-sun-700'">{{ tarif(type) }}</p>
            <p class="text-xs text-slate-400">{{ type.echeance === 'fin_annee' ? 'À payer avant la fin de l\'année universitaire' : `À payer sous ${type.delai_jours} jours` }}{{ type.tranches > 1 ? `, en ${type.tranches} tranches` : '' }}<template v-if="type.frais_count"> · appliqué {{ type.frais_count }} fois</template><template v-if="type.description"> · {{ type.description }}</template></p>
          </div>
          <div class="flex">
            <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" :aria-label="`Modifier ${type.libelle}`" @click="ouvrir(type)"><Pencil /></Button>
            <Button v-if="!type.frais_count" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Supprimer ${type.libelle}`" @click="supprimer(type)"><Trash2 /></Button>
          </div>
        </li>
      </ul>
    </div>

    <AccesSiteVitrine class="mt-6" />

    <Dialog v-model:open="ouvert">
      <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ modifie ? `Modifier « ${modifie.libelle} »` : 'Nouveau frais' }}</DialogTitle>
          <DialogDescription>Le tarif s'applique aux prochains frais créés{{ modifie?.frais_non_soldes_count ? ', et si vous le choisissez ci-dessous, aux frais pas encore soldés' : '' }}.</DialogDescription>
        </DialogHeader>
        <form id="form-type-frais" class="grid gap-4" novalidate @submit.prevent="enregistrer">
          <ChampFormulaire libelle="Libellé" pour="libelle" :erreur="erreurs.libelle" requis><Input id="libelle" v-model="formulaire.libelle" placeholder="Tee-shirt" /></ChampFormulaire>
          <ChampFormulaire libelle="Description" pour="description" :erreur="erreurs.description"><Textarea id="description" v-model="formulaire.description" rows="2" /></ChampFormulaire>
          <div class="flex items-start justify-between gap-4 rounded-md border p-4">
            <div><Label for="par-niveau">Montant différent selon le niveau</Label><p class="mt-1 text-xs text-slate-400">D1, D2, D3 et dérogation (par exemple les droits d'inscription).</p></div>
            <Switch id="par-niveau" v-model="formulaire.par_niveau" />
          </div>
          <div v-if="formulaire.par_niveau" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <ChampFormulaire v-for="[cle, libelle] in NIVEAUX" :key="cle" :libelle="libelle" :pour="`niveau-${cle}`" :erreur="erreurs[`montants_niveaux.${cle}`]">
              <Input :id="`niveau-${cle}`" v-model.number="formulaire.montants_niveaux[cle]" type="number" min="0" step="1000" inputmode="numeric" />
            </ChampFormulaire>
          </div>
          <p v-if="formulaire.par_niveau && niveauxANul.length" class="rounded-md bg-sun-50 p-3 text-sm text-sun-700">
            {{ niveauxANul.join(', ') }} à 0 Ar : aucun frais ne sera demandé pour {{ niveauxANul.length > 1 ? 'ces niveaux' : 'ce niveau' }}. Est-ce voulu ?
          </p>
          <ChampFormulaire v-else-if="!formulaire.par_niveau" libelle="Montant (Ar)" pour="montant" :erreur="erreurs.montant" requis>
            <Input id="montant" v-model.number="formulaire.montant" type="number" min="0" step="1000" inputmode="numeric" />
          </ChampFormulaire>
          <div class="grid gap-4 sm:grid-cols-2">
            <ChampFormulaire libelle="À payer avant" pour="echeance" :erreur="erreurs.echeance" :aide="formulaire.echeance === 'fin_annee' ? 'Fin de l\'année universitaire du frais.' : undefined" requis>
              <Select v-model="formulaire.echeance">
                <SelectTrigger id="echeance" class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="fin_annee">La fin de l'année</SelectItem>
                  <SelectItem value="delai">Un délai en jours</SelectItem>
                </SelectContent>
              </Select>
            </ChampFormulaire>
            <ChampFormulaire v-if="formulaire.echeance === 'delai'" :libelle="formulaire.tranches > 1 ? 'Jours entre deux tranches' : 'Délai (jours)'" pour="delai" :erreur="erreurs.delai_jours" requis>
              <Input id="delai" v-model.number="formulaire.delai_jours" type="number" min="0" max="365" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Paiement" pour="tranches" :erreur="erreurs.tranches" requis :class="formulaire.echeance === 'delai' ? 'sm:col-span-2' : ''">
              <Select :model-value="String(formulaire.tranches)" @update:model-value="v => formulaire.tranches = Number(v)">
                <SelectTrigger id="tranches" class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1">Une seule échéance</SelectItem>
                  <SelectItem value="2">En 2 tranches</SelectItem>
                  <SelectItem value="3">En 3 tranches</SelectItem>
                  <SelectItem value="4">En 4 tranches</SelectItem>
                </SelectContent>
              </Select>
            </ChampFormulaire>
          </div>
          <div class="rounded-md bg-gray-50 p-3 text-xs text-slate-500">
            <p class="mb-1 font-semibold text-slate-600">Exemple pour une inscription le 1er octobre {{ anneeExemple }} :</p>
            <p v-for="(ligne, index) in exemple" :key="index">{{ ligne }}</p>
            <p class="mt-1">Le doctorant peut toujours payer en plusieurs versements ; il n'est en retard que si une échéance est passée sans être couverte.</p>
          </div>
          <div class="flex items-start justify-between gap-4 rounded-md border p-4">
            <div><Label for="obligatoire">Automatique à chaque inscription</Label><p class="mt-1 text-xs text-slate-400">Ajouté au doctorant dès son inscription ou sa réinscription.</p></div>
            <Switch id="obligatoire" v-model="formulaire.obligatoire" />
          </div>
          <div v-if="modifie?.frais_non_soldes_count" class="flex items-start justify-between gap-4 rounded-md border border-brand-300 bg-brand-50/60 p-4">
            <div>
              <Label for="appliquer">Appliquer aussi aux {{ modifie.frais_non_soldes_count }} frais pas encore soldés</Label>
              <p class="mt-1 text-xs text-slate-500">Chaque frais est recalculé au tarif de son niveau. Jamais touchés : frais soldés, montants ajustés à la main (bourse, remise), frais déjà payés au-delà du nouveau tarif.</p>
            </div>
            <Switch id="appliquer" v-model="formulaire.appliquer_aux_frais" />
          </div>
          <div class="flex items-center justify-between gap-4 rounded-md border p-4">
            <Label for="actif">Proposé dans les listes</Label>
            <Switch id="actif" v-model="formulaire.actif" />
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-type-frais" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

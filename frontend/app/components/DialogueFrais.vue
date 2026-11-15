<script setup lang="ts">
import { Loader2, ReceiptText, TriangleAlert } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Ressource, TypeFrais } from '~/types'

/**
 * Ajoute un frais (tee-shirt, cotisation, séminaire...) à des doctorants choisis,
 * ou, sans sélection, à tout un groupe : inscrits de l'année (un niveau, une équipe) ou tous les actifs.
 */
const props = defineProps<{ doctorants?: Array<{ id: string, nom_complet: string }> }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ ajoute: [] }>()

const api = useApi()
const { equipes, annees, anneeCourante } = useReferentiels()
const { data: types } = useAsyncData('types-frais-actifs', () => api<Ressource<TypeFrais[]>>('/api/types-frais', { query: { actifs: 1 } }).then(r => r.data), { lazy: true, default: () => [] as TypeFrais[] })

const AUTRE = 'autre'
const formulaire = reactive({ type: '' as string, libelle: '', montant: undefined as number | undefined, annee: null as number | null, echeance: '', cible: 'inscrits', niveau: 'tous', equipe: 'toutes' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const ignores = ref<Array<{ doctorant: string, message: string }>>([])
const selection = computed(() => props.doctorants ?? [])
const type = computed(() => types.value.find(t => String(t.id) === formulaire.type))

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  erreurs.value = {}
  ignores.value = []
  Object.assign(formulaire, { type: types.value[0] ? String(types.value[0].id) : AUTRE, libelle: '', montant: undefined, annee: anneeCourante.value?.id ?? null, echeance: '', cible: 'inscrits', niveau: 'tous', equipe: 'toutes' })
})

const tarif = computed(() => {
  if (!type.value) return ''
  if (!type.value.par_niveau) return type.value.montant ? formaterMontant(type.value.montant) : 'tarif non défini'
  const n = type.value.montants_niveaux!
  return `D1 ${formaterMontant(n.D1)}, D2 ${formaterMontant(n.D2)}, D3 ${formaterMontant(n.D3)}, dérogation ${formaterMontant(n.derogation)}`
})

async function ajouter() {
  envoi.value = true
  erreurs.value = {}
  ignores.value = []
  const autre = formulaire.type === AUTRE
  try {
    const reponse = await api<{ data: { crees: number, total: number, ignores: Array<{ doctorant: string, message: string }> } }>('/api/frais', {
      method: 'POST',
      body: {
        type_frais_id: autre ? null : Number(formulaire.type),
        libelle: autre ? formulaire.libelle : null,
        montant: formulaire.montant || null,
        annee_universitaire_id: formulaire.annee,
        echeance: formulaire.echeance || null,
        ...(selection.value.length
          ? { doctorants: selection.value.map(d => d.id) }
          : { cible: formulaire.cible, niveau: formulaire.niveau === 'tous' ? null : formulaire.niveau, equipe: formulaire.equipe === 'toutes' ? null : Number(formulaire.equipe) }),
      },
    })
    ignores.value = reponse.data.ignores
    if (reponse.data.crees) {
      toast.success(`Frais ajouté à ${reponse.data.crees} doctorant${reponse.data.crees > 1 ? 's' : ''} (${formaterMontant(reponse.data.total)} au total). Ils sont prévenus.`)
      emit('ajoute')
      if (!ignores.value.length) ouvert.value = false
    }
    else {
      toast.warning('Aucun frais ajouté : voir le détail.')
    }
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
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Ajouter un frais</DialogTitle>
        <DialogDescription>
          <template v-if="selection.length === 1">{{ selection[0]!.nom_complet }}</template>
          <template v-else-if="selection.length">{{ selection.length }} doctorants sélectionnés</template>
          <template v-else>À tout un groupe de doctorants.</template>
        </DialogDescription>
      </DialogHeader>

      <form id="form-frais" class="grid gap-4" novalidate @submit.prevent="ajouter">
        <ChampFormulaire libelle="Frais" pour="type" :erreur="erreurs.type_frais_id" :aide="type ? `Tarif : ${tarif}` : undefined" requis>
          <Select v-model="formulaire.type">
            <SelectTrigger id="type" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="t in types" :key="t.id" :value="String(t.id)">{{ t.libelle }}</SelectItem>
              <SelectItem :value="AUTRE">Autre frais (saisir le libellé)</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>
        <ChampFormulaire v-if="formulaire.type === AUTRE" libelle="Libellé" pour="libelle" :erreur="erreurs.libelle" requis>
          <Input id="libelle" v-model="formulaire.libelle" placeholder="Participation au séminaire" />
        </ChampFormulaire>
        <div class="grid gap-4 sm:grid-cols-2">
          <ChampFormulaire libelle="Montant (Ar)" pour="montant" :erreur="erreurs.montant" :requis="formulaire.type === AUTRE" :aide="formulaire.type !== AUTRE ? 'Vide : tarif des paramètres.' : undefined">
            <Input id="montant" v-model.number="formulaire.montant" type="number" min="0" step="1000" inputmode="numeric" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Échéance" pour="echeance" :erreur="erreurs.echeance" :aide="!type ? 'Vide : dans 30 jours.' : type.echeance === 'fin_annee' ? `Vide : fin de l'année universitaire${type.tranches > 1 ? `, en ${type.tranches} tranches` : ''}.` : `Vide : dans ${type.delai_jours} jours${type.tranches > 1 ? `, en ${type.tranches} tranches` : ''}.`">
            <Input id="echeance" v-model="formulaire.echeance" type="date" />
          </ChampFormulaire>
        </div>
        <ChampFormulaire libelle="Année universitaire" pour="annee-frais" :erreur="erreurs.annee_universitaire_id">
          <Select v-model="formulaire.annee">
            <SelectTrigger id="annee-frais" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="annee in annees" :key="annee.id" :value="annee.id">{{ annee.libelle }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>

        <fieldset v-if="!selection.length" class="grid gap-3 rounded-md border p-4">
          <legend class="px-1 text-sm font-semibold text-slate-700">Doctorants concernés</legend>
          <Select v-model="formulaire.cible">
            <SelectTrigger class="w-full" aria-label="Groupe"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="inscrits">Inscrits de l'année choisie</SelectItem>
              <SelectItem value="actifs">Tous les doctorants actifs</SelectItem>
            </SelectContent>
          </Select>
          <div class="grid gap-3 sm:grid-cols-2">
            <Select v-if="formulaire.cible === 'inscrits'" v-model="formulaire.niveau">
              <SelectTrigger class="w-full" aria-label="Niveau"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="tous">Tous les niveaux</SelectItem>
                <SelectItem v-for="n in ['D1', 'D2', 'D3']" :key="n" :value="n">{{ n }}</SelectItem>
                <SelectItem value="derogation">Dérogation</SelectItem>
              </SelectContent>
            </Select>
            <Select v-model="formulaire.equipe">
              <SelectTrigger class="w-full" aria-label="Équipe"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="toutes">Toutes les équipes</SelectItem>
                <SelectItem v-for="e in equipes" :key="e.id" :value="String(e.id)">{{ e.sigle }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <p class="text-xs text-slate-400">Un doctorant qui a déjà ce frais pour l'année n'est pas compté deux fois.</p>
        </fieldset>

        <div v-if="ignores.length" class="max-h-40 overflow-y-auto rounded-md border border-sun-300 bg-sun-50 p-3 text-sm">
          <p class="mb-1 flex items-center gap-2 font-semibold text-sun-700"><TriangleAlert class="size-4" /> Non ajouté pour {{ ignores.length }} doctorant(s)</p>
          <p v-for="(i, index) in ignores" :key="index" class="text-slate-600">{{ i.doctorant }} : {{ i.message }}</p>
        </div>
      </form>

      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Fermer</Button>
        <Button type="submit" form="form-frais" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /><ReceiptText v-else /> Ajouter le frais</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

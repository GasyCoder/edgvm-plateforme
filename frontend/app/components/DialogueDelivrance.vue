<script setup lang="ts">
import { CircleCheck, FileDown, FileWarning, Loader2, Stamp, TriangleAlert } from '@lucide/vue'
import type { FetchError } from 'ofetch'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { Acte, ModeleActe, Ressource } from '~/types'

interface Doublon { doctorant: string, matricule: string, numero: string, delivre_le: string, annee: string | null }
interface Resultat { delivres: Acte[], erreurs: Array<{ doctorant: string, matricule: string, message: string }>, url_pdf: string | null }

/**
 * Délivrance d'un acte à un doctorant, ou à toute une sélection (un PDF unique pour imprimer le lot).
 */
const props = defineProps<{ doctorants: Array<{ id: string, nom_complet: string }> }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ delivre: [] }>()

const api = useApi()
const { annees, anneeCourante } = useReferentiels()
const { data: modeles } = useAsyncData('modeles-disponibles', () => api<Ressource<ModeleActe[]>>('/api/modeles-actes/disponibles').then(r => r.data), { lazy: true, default: () => [] as ModeleActe[] })

const lot = computed(() => props.doctorants.length > 1)
const modeleId = ref<number | null>(null)
const anneeId = ref<number | null>(null)
const champs = reactive<Record<string, string>>({})
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const resultat = ref<Resultat | null>(null)

const modele = computed(() => modeles.value.find(m => m.id === modeleId.value) ?? null)

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  resultat.value = null
  erreurs.value = {}
  anneeId.value = anneeCourante.value?.id ?? null
  if (!modeleId.value && modeles.value.length) modeleId.value = modeles.value[0]!.id
})
watch(modele, () => {
  Object.keys(champs).forEach(cle => delete champs[cle])
  modele.value?.champs.forEach((champ) => { champs[champ.cle] = '' })
})

/* Acte déjà délivré (même modèle, même année) : la scolarité confirme avant d'en délivrer un second. */
const doublons = ref<{ message: string, liste: Doublon[] } | null>(null)
const confirmationDoublon = ref(false)

async function delivrer(choixDoublons?: 'confirmer' | 'ignorer') {
  if (!modele.value) return
  envoi.value = true
  erreurs.value = {}
  confirmationDoublon.value = false
  const corps = {
    doublons: choixDoublons,
    modele_acte_id: modele.value.id,
    annee_universitaire_id: modele.value.exige_inscription ? anneeId.value : null,
    champs: Object.fromEntries(Object.entries(champs).filter(([, valeur]) => valeur !== '')),
    ...(lot.value ? { doctorants: props.doctorants.map(d => d.id) } : { doctorant: props.doctorants[0]!.id }),
  }
  try {
    if (lot.value) {
      const reponse = await api<{ data: Resultat }>('/api/actes/lot', { method: 'POST', body: corps })
      resultat.value = reponse.data
      if (reponse.data.delivres.length) emit('delivre')
    }
    else {
      const reponse = await api<Ressource<Acte>>('/api/actes', { method: 'POST', body: corps })
      toast.success(`${reponse.data.titre.charAt(0)}${reponse.data.titre.slice(1).toLowerCase()} n° ${reponse.data.numero} délivrée.`)
      window.open(reponse.data.url_pdf, '_blank', 'noopener')
      ouvert.value = false
      emit('delivre')
    }
  }
  catch (e) {
    const reponse = e as FetchError<{ code?: string, message?: string, deja?: Doublon[] }>
    if (reponse?.statusCode === 409 && reponse.data?.code === 'deja_delivre') {
      doublons.value = { message: reponse.data.message ?? '', liste: reponse.data.deja ?? [] }
      confirmationDoublon.value = true
      return
    }
    erreurs.value = erreursDeValidation(e)
    const message = erreurs.value.doctorant ?? (Object.keys(erreurs.value).length ? 'Vérifiez les champs signalés.' : messageErreur(e))
    toast.error(message)
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
      <DialogHeader>
        <DialogTitle>{{ lot ? `Délivrer un acte à ${doctorants.length} doctorants` : 'Délivrer un acte' }}</DialogTitle>
        <DialogDescription>
          <template v-if="lot">Un acte numéroté par doctorant, réunis dans un seul PDF à imprimer.</template>
          <template v-else>{{ doctorants[0]?.nom_complet }}</template>
        </DialogDescription>
      </DialogHeader>

      <!-- Résultat d'une délivrance en lot -->
      <div v-if="resultat" class="grid gap-4">
        <div class="grid grid-cols-2 gap-3 text-center">
          <div class="rounded-md border border-leaf-400/50 bg-leaf-50 p-3"><p class="text-2xl font-bold text-leaf-600">{{ resultat.delivres.length }}</p><p class="text-xs text-slate-500">acte(s) délivré(s)</p></div>
          <div class="rounded-md border p-3" :class="resultat.erreurs.length ? 'border-red-200 bg-red-50' : ''"><p class="text-2xl font-bold" :class="resultat.erreurs.length ? 'text-destructive' : 'text-slate-700'">{{ resultat.erreurs.length }}</p><p class="text-xs text-slate-500">non délivré(s)</p></div>
        </div>
        <div v-if="resultat.erreurs.length" class="max-h-56 overflow-y-auto rounded-md border">
          <p class="sticky top-0 border-b bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">À régulariser</p>
          <div v-for="erreur in resultat.erreurs" :key="erreur.matricule" class="flex gap-2 border-b px-4 py-2.5 text-sm last:border-b-0">
            <TriangleAlert class="mt-0.5 size-4 flex-none text-destructive" />
            <p><span class="font-medium text-slate-700">{{ erreur.doctorant }}</span> <span class="text-slate-500">: {{ erreur.message }}</span></p>
          </div>
        </div>
        <p v-if="resultat.delivres.length" class="flex items-center gap-2 text-sm text-slate-600"><CircleCheck class="size-4 text-leaf-600" /> Numéros {{ resultat.delivres[0]?.numero }} à {{ resultat.delivres[resultat.delivres.length - 1]?.numero }}.</p>
      </div>

      <form v-else id="form-delivrance" class="grid gap-4" novalidate @submit.prevent="delivrer()">
        <ChampFormulaire libelle="Acte" pour="modele" :erreur="erreurs.modele_acte_id" requis>
          <Select v-model="modeleId">
            <SelectTrigger id="modele" class="w-full"><SelectValue placeholder="Choisir le type d'acte" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="m in modeles" :key="m.id" :value="m.id">{{ m.nom }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>

        <ChampFormulaire v-if="modele?.exige_inscription" libelle="Année universitaire" pour="annee" :erreur="erreurs.annee_universitaire_id" aide="Le doctorant doit y avoir une inscription validée." requis>
          <Select v-model="anneeId">
            <SelectTrigger id="annee" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="annee in annees" :key="annee.id" :value="annee.id">{{ annee.libelle }}{{ annee.est_courante ? ' (en cours)' : '' }}</SelectItem>
            </SelectContent>
          </Select>
        </ChampFormulaire>

        <template v-if="modele?.champs.length">
          <p class="border-t pt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Informations de l'acte{{ lot ? ' (identiques pour tous)' : '' }}</p>
          <ChampFormulaire v-for="champ in modele.champs" :key="champ.cle" :libelle="champ.libelle" :pour="`champ-${champ.cle}`" :erreur="erreurs[`champs.${champ.cle}`]" :requis="champ.obligatoire">
            <Textarea v-if="champ.type === 'texte_long'" :id="`champ-${champ.cle}`" v-model="champs[champ.cle]" rows="2" />
            <Select v-else-if="champ.type === 'choix'" v-model="champs[champ.cle]">
              <SelectTrigger :id="`champ-${champ.cle}`" class="w-full"><SelectValue placeholder="Choisir" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in champ.options" :key="option" :value="option">{{ option }}</SelectItem>
              </SelectContent>
            </Select>
            <Input v-else :id="`champ-${champ.cle}`" v-model="champs[champ.cle]" :type="champ.type === 'date' ? 'date' : champ.type === 'heure' ? 'time' : 'text'" />
          </ChampFormulaire>
        </template>

        <p v-if="erreurs.doctorant" class="flex gap-2 rounded-md bg-red-50 p-3 text-sm text-destructive"><TriangleAlert class="size-4 flex-none" />{{ erreurs.doctorant }}</p>
      </form>

      <DialogFooter>
        <template v-if="resultat">
          <Button variant="outline" @click="ouvert = false">Fermer</Button>
          <Button v-if="resultat.url_pdf" as-child><a :href="resultat.url_pdf" target="_blank" rel="noopener"><FileDown /> Ouvrir le PDF ({{ resultat.delivres.length }})</a></Button>
        </template>
        <template v-else>
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-delivrance" :disabled="envoi || !modele">
            <Loader2 v-if="envoi" class="animate-spin" /><Stamp v-else />
            {{ lot ? `Délivrer ${doctorants.length} actes` : "Délivrer l'acte" }}
          </Button>
        </template>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <AlertDialog v-model:open="confirmationDoublon">
    <AlertDialogContent class="sm:max-w-lg">
      <AlertDialogHeader>
        <AlertDialogTitle class="flex items-center gap-2"><FileWarning class="size-5 text-sun-600" /> Acte déjà délivré</AlertDialogTitle>
        <AlertDialogDescription>{{ doublons?.message }} Voulez-vous en délivrer un nouveau ?</AlertDialogDescription>
      </AlertDialogHeader>
      <ul v-if="doublons?.liste.length" class="max-h-52 divide-y overflow-y-auto rounded-md border text-sm">
        <li v-for="d in doublons.liste" :key="d.numero" class="flex flex-wrap items-baseline justify-between gap-x-3 px-3 py-2">
          <span class="font-medium text-slate-700">{{ d.doctorant }}</span>
          <span class="text-xs text-slate-500">n° {{ d.numero }}, le {{ formaterDate(d.delivre_le) }}{{ d.annee ? `, ${d.annee}` : '' }}</span>
        </li>
      </ul>
      <AlertDialogFooter class="gap-2 sm:gap-2">
        <AlertDialogCancel :disabled="envoi">Non, annuler</AlertDialogCancel>
        <Button v-if="lot" variant="outline" :disabled="envoi" @click="delivrer('ignorer')">Délivrer seulement aux autres</Button>
        <Button :disabled="envoi" @click="delivrer('confirmer')"><Loader2 v-if="envoi" class="animate-spin" /> Oui, délivrer {{ lot ? 'à tous' : 'un nouvel acte' }}</Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>

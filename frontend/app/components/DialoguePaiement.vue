<script setup lang="ts">
import { Loader2, Wallet } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { FraisDoctorant, Paiement, Ressource } from '~/types'

/**
 * Encaissement : on coche les frais réglés (le reste de chacun est proposé), le total se calcule.
 * Le reçu s'ouvre après l'enregistrement.
 */
const props = defineProps<{ doctorantId: string, nomComplet: string, frais: FraisDoctorant[] }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistre: [] }>()

const api = useApi()
/* Les plus anciennes échéances d'abord (les frais en retard sont proposés en premier) */
const impayes = computed(() => props.frais
  .filter(f => !f.annule_le && f.reste > 0)
  .sort((a, b) => (a.echeance ?? '9999').localeCompare(b.echeance ?? '9999')))
const lignes = reactive<Record<number, { coche: boolean, montant: number }>>({})
const formulaire = reactive({ date_paiement: '', mode: 'virement', reference: '', observation: '' })
const justificatif = ref<File | null>(null)
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

/* Préparée aussi quand la fenêtre est créée déjà ouverte (encaissement depuis la liste). */
watch([ouvert, impayes], ([estOuvert]) => {
  if (!estOuvert) return
  erreurs.value = {}
  Object.assign(formulaire, { date_paiement: new Date().toISOString().slice(0, 10), mode: 'virement', reference: '', observation: '' })
  justificatif.value = null
  Object.keys(lignes).forEach(cle => delete lignes[Number(cle)])
  impayes.value.forEach((f, index) => { lignes[f.id] = { coche: index === 0, montant: f.reste } })
}, { immediate: true })

const total = computed(() => impayes.value.reduce((somme, f) => somme + (lignes[f.id]?.coche ? Number(lignes[f.id]!.montant) || 0 : 0), 0))
const referenceObligatoire = computed(() => formulaire.mode !== 'especes')

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    /* Envoi en plusieurs parties : le reçu de versement accompagne le paiement */
    const donnees = new FormData()
    donnees.append('montant', String(total.value))
    donnees.append('date_paiement', formulaire.date_paiement)
    donnees.append('mode', formulaire.mode)
    if (formulaire.reference) donnees.append('reference', formulaire.reference)
    if (formulaire.observation) donnees.append('observation', formulaire.observation)
    impayes.value.filter(f => lignes[f.id]?.coche).forEach((f, index) => {
      donnees.append(`imputations[${index}][frais]`, String(f.id))
      donnees.append(`imputations[${index}][montant]`, String(Number(lignes[f.id]!.montant) || 0))
    })
    if (justificatif.value) donnees.append('justificatif', justificatif.value)
    const reponse = await api<Ressource<Paiement>>(`/api/doctorants/${props.doctorantId}/paiements`, { method: 'POST', body: donnees })
    toast.success(`Paiement de ${formaterMontant(reponse.data.montant)} enregistré, reçu n° ${reponse.data.numero_recu}.`)
    window.open(reponse.data.url_recu, '_blank', 'noopener')
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
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
      <DialogHeader>
        <DialogTitle>Encaisser un paiement</DialogTitle>
        <DialogDescription>{{ nomComplet }}</DialogDescription>
      </DialogHeader>

      <p v-if="!impayes.length" class="rounded-md bg-leaf-50 p-4 text-sm text-leaf-600">Rien à payer : tous les frais sont réglés.</p>

      <form v-else id="form-paiement" class="grid gap-4" novalidate @submit.prevent="enregistrer">
        <div class="rounded-md border">
          <p class="border-b bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Frais réglés par ce paiement</p>
          <div v-for="f in impayes.filter(f => lignes[f.id])" :key="f.id" class="flex flex-wrap items-center gap-3 border-b px-4 py-3 last:border-b-0">
            <Checkbox :id="`frais-${f.id}`" v-model="lignes[f.id]!.coche" />
            <label :for="`frais-${f.id}`" class="min-w-0 flex-1 cursor-pointer">
              <span class="block text-sm font-semibold text-slate-700 sm:truncate">{{ f.libelle }} <span v-if="f.annee" class="font-normal text-slate-400">{{ f.annee }}</span></span>
              <span class="block text-xs" :class="f.situation === 'en_retard' ? 'text-destructive' : 'text-slate-400'">
                Reste {{ formaterMontant(f.reste) }}<template v-if="f.echeance"> · échéance {{ formaterDate(f.echeance) }}</template>
              </span>
            </label>
            <div class="relative ms-7 w-[calc(100%-1.75rem)] sm:ms-0 sm:w-36">
              <Input v-model.number="lignes[f.id]!.montant" type="number" min="0" :max="f.reste" step="1000" inputmode="numeric" class="pr-8 text-right" :disabled="!lignes[f.id]?.coche" :aria-label="`Montant pour ${f.libelle}`" />
              <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Ar</span>
            </div>
          </div>
          <div class="flex items-center justify-between bg-brand-50 px-4 py-3">
            <span class="text-sm font-semibold text-brand-800">Total encaissé</span>
            <span class="text-lg font-bold tabular-nums text-brand-800">{{ formaterMontant(total) }}</span>
          </div>
        </div>
        <p v-if="erreurs.imputations || erreurs.montant" class="text-sm text-destructive">{{ erreurs.imputations || erreurs.montant }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
          <ChampFormulaire libelle="Date du paiement" pour="date-paiement" :erreur="erreurs.date_paiement" requis>
            <Input id="date-paiement" v-model="formulaire.date_paiement" type="date" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Mode" pour="mode" :erreur="erreurs.mode" requis>
            <Select v-model="formulaire.mode">
              <SelectTrigger id="mode" class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="virement">Versement bancaire</SelectItem>
                <SelectItem value="especes">Espèces</SelectItem>
                <SelectItem value="mobile_money">Mobile Money</SelectItem>
                <SelectItem value="cheque">Chèque</SelectItem>
              </SelectContent>
            </Select>
          </ChampFormulaire>
          <ChampFormulaire :libelle="formulaire.mode === 'virement' ? 'N° du bordereau de versement' : formulaire.mode === 'mobile_money' ? 'N° de transaction' : formulaire.mode === 'cheque' ? 'N° du chèque' : 'Référence'" pour="reference" :erreur="erreurs.reference" :requis="referenceObligatoire" :aide="referenceObligatoire ? undefined : 'Facultatif pour les espèces.'" class="sm:col-span-2">
            <Input id="reference" v-model="formulaire.reference" />
          </ChampFormulaire>
          <div class="grid gap-2 sm:col-span-2">
            <p class="text-sm font-medium">Reçu de versement</p>
            <ChampJustificatif v-model="justificatif" :erreur="erreurs.justificatif" />
          </div>
          <ChampFormulaire libelle="Observation" pour="observation" :erreur="erreurs.observation" class="sm:col-span-2">
            <Textarea id="observation" v-model="formulaire.observation" rows="2" />
          </ChampFormulaire>
        </div>
      </form>

      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button v-if="impayes.length" type="submit" form="form-paiement" :disabled="envoi || total <= 0">
          <Loader2 v-if="envoi" class="animate-spin" /><Wallet v-else /> Encaisser {{ formaterMontant(total) }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

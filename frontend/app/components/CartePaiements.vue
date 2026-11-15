<script setup lang="ts">
import { Ban, FileText, ImagePlus, Loader2, Paperclip, Pencil, ReceiptText, Wallet } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import type { Finances, FraisDoctorant, Paiement } from '~/types'

/**
 * Situation financière d'un doctorant : frais dus, paiements, reçus. L'administration encaisse, ajoute,
 * ajuste ou annule ; le doctorant consulte et télécharge ses reçus.
 */
const props = defineProps<{ doctorantId: string, nomComplet: string }>()
const emit = defineEmits<{ change: [] }>()

const api = useApi()
const { estSuperAdmin } = useAuth()
const { data: finances, refresh, status } = useAsyncData(
  () => `finances-${props.doctorantId}`,
  () => api<{ data: Finances }>(`/api/doctorants/${props.doctorantId}/finances`).then(r => r.data),
  { lazy: true },
)
const actualiser = async () => { await refresh(); emit('change') }

const encaissement = ref(false)
const ajoutFrais = ref(false)
defineExpose({ encaisser: () => { encaissement.value = true } })

/* Ajustement ou annulation d'un frais, annulation d'un paiement : une seule fenêtre selon l'action */
type Action = { genre: 'ajuster', frais: FraisDoctorant } | { genre: 'annuler-frais', frais: FraisDoctorant } | { genre: 'annuler-paiement', paiement: Paiement }
const action = ref<Action | null>(null)
const dialogueAction = ref(false)
const valeurs = reactive({ montant: 0, echeance: '', motif: '' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

function demander(nouvelle: Action) {
  action.value = nouvelle
  erreurs.value = {}
  Object.assign(valeurs, nouvelle.genre === 'ajuster'
    ? { montant: nouvelle.frais.montant, echeance: nouvelle.frais.echeance ?? '', motif: nouvelle.frais.motif_ajustement ?? '' }
    : { montant: 0, echeance: '', motif: '' })
  dialogueAction.value = true
}

async function confirmer() {
  const a = action.value
  if (!a) return
  envoi.value = true
  erreurs.value = {}
  try {
    if (a.genre === 'ajuster') {
      await api(`/api/frais/${a.frais.id}`, { method: 'PUT', body: { montant: valeurs.montant, echeance: valeurs.echeance || null, motif_ajustement: valeurs.motif || null } })
      toast.success('Frais ajusté.')
    }
    else if (a.genre === 'annuler-frais') {
      await api(`/api/frais/${a.frais.id}/annuler`, { method: 'POST', body: { motif: valeurs.motif } })
      toast.success('Frais annulé.')
    }
    else {
      await api(`/api/paiements/${a.paiement.id}/annuler`, { method: 'POST', body: { motif: valeurs.motif } })
      toast.success(`Paiement ${a.paiement.numero_recu} annulé : les frais qu'il réglait sont de nouveau dus.`)
    }
    dialogueAction.value = false
    await actualiser()
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

/* Reçu de versement apporté après l'enregistrement du paiement */
const dialogueJustificatif = ref(false)
const paiementJustificatif = ref<Paiement | null>(null)
const fichierJustificatif = ref<File | null>(null)
const erreurJustificatif = ref('')
const envoiJustificatif = ref(false)
function ouvrirJustificatif(paiement: Paiement) {
  paiementJustificatif.value = paiement
  fichierJustificatif.value = null
  erreurJustificatif.value = ''
  dialogueJustificatif.value = true
}
async function joindreJustificatif() {
  if (!paiementJustificatif.value || !fichierJustificatif.value) return
  envoiJustificatif.value = true
  erreurJustificatif.value = ''
  const donnees = new FormData()
  donnees.append('justificatif', fichierJustificatif.value)
  try {
    await api(`/api/paiements/${paiementJustificatif.value.id}/justificatif`, { method: 'POST', body: donnees })
    toast.success('Reçu de versement enregistré.')
    dialogueJustificatif.value = false
    await actualiser()
  }
  catch (e) {
    erreurJustificatif.value = erreursDeValidation(e).justificatif ?? messageErreur(e)
  }
  finally {
    envoiJustificatif.value = false
  }
}

const titreAction = computed(() => {
  const a = action.value
  if (!a) return ''
  return a.genre === 'ajuster' ? `Ajuster « ${a.frais.libelle} »` : a.genre === 'annuler-frais' ? `Annuler « ${a.frais.libelle} » ?` : `Annuler le paiement ${a.paiement.numero_recu} ?`
})
const couleurFrais = (situation: string) => ({ solde: 'text-leaf-600', partiel: 'text-sun-600', en_retard: 'text-destructive', a_venir: 'text-slate-500', annule: 'text-slate-400' } as Record<string, string>)[situation] ?? 'text-slate-500'
</script>

<template>
  <Card id="paiements" class="scroll-mt-24">
    <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-3">
      <div class="grid gap-1.5">
        <CardTitle>Paiements</CardTitle>
        <CardDescription>Droits d'inscription et autres frais.</CardDescription>
      </div>
      <div v-if="estSuperAdmin" class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" @click="ajoutFrais = true"><ReceiptText /> Ajouter un frais</Button>
        <Button size="sm" :disabled="!finances?.situation.reste" @click="encaissement = true"><Wallet /> Encaisser</Button>
      </div>
    </CardHeader>
    <CardContent class="grid gap-5">
      <div v-if="status === 'pending' && !finances" class="h-20 animate-pulse rounded-md bg-gray-100" />
      <template v-else-if="finances">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-slate-400">Total dû</p><p class="font-bold tabular-nums text-slate-700">{{ formaterMontant(finances.situation.du) }}</p></div>
          <div class="rounded-md bg-leaf-50 p-3"><p class="text-xs text-slate-500">Payé</p><p class="font-bold tabular-nums text-leaf-600">{{ formaterMontant(finances.situation.paye) }}</p></div>
          <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-slate-400">Reste à payer</p><p class="font-bold tabular-nums text-slate-700">{{ formaterMontant(finances.situation.reste) }}</p></div>
          <div class="rounded-md p-3" :class="finances.situation.en_retard ? 'bg-red-50' : 'bg-gray-50'">
            <p class="text-xs text-slate-400">Situation</p>
            <BadgePaiement :statut="finances.situation.statut" :libelle="finances.situation.statut_libelle" />
            <p v-if="finances.situation.en_retard" class="text-xs text-destructive">{{ formaterMontant(finances.situation.en_retard) }} en retard</p>
          </div>
        </div>

        <GraphiquesPaiements v-if="finances.situation.du" :finances="finances" class="rounded-md border border-gray-200 p-4" />

        <Tabs default-value="frais">
          <TabsList>
            <TabsTrigger value="frais">Frais ({{ finances.frais.length }})</TabsTrigger>
            <TabsTrigger value="paiements">Paiements ({{ finances.paiements.length }})</TabsTrigger>
          </TabsList>

          <TabsContent value="frais">
            <p v-if="!finances.frais.length" class="py-4 text-sm text-slate-400">Aucun frais.</p>
            <ul v-else class="divide-y divide-gray-200">
              <li v-for="f in finances.frais" :key="f.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3" :class="f.annule_le ? 'opacity-60' : ''">
                <div class="min-w-0 flex-1 basis-full sm:basis-0">
                  <p class="truncate text-sm font-semibold text-slate-700" :class="f.annule_le ? 'line-through' : ''">{{ f.libelle }} <span v-if="f.annee" class="font-normal text-slate-400">{{ f.annee }}</span></p>
                  <p class="text-xs text-slate-400">
                    <template v-if="f.echeance">Échéance {{ formaterDate(f.echeance) }}</template>
                    <template v-if="f.motif_ajustement"> · {{ f.motif_ajustement }}</template>
                    <template v-if="f.motif_annulation"> · Annulé : {{ f.motif_annulation }}</template>
                  </p>
                </div>
                <div class="ms-auto text-right text-sm tabular-nums sm:ms-0">
                  <p class="font-semibold text-slate-700">{{ formaterMontant(f.montant) }}</p>
                  <p v-if="f.paye && f.reste" class="text-xs text-slate-400">payé {{ formaterMontant(f.paye) }}</p>
                </div>
                <span class="w-20 text-right text-xs font-semibold" :class="couleurFrais(f.situation)">{{ f.situation_libelle }}</span>
                <div v-if="estSuperAdmin" class="flex w-16 justify-end">
                  <template v-if="!f.annule_le">
                    <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" :aria-label="`Ajuster ${f.libelle}`" title="Ajuster le montant ou l'échéance" @click="demander({ genre: 'ajuster', frais: f })"><Pencil /></Button>
                    <Button v-if="!f.paye" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Annuler ${f.libelle}`" title="Annuler (exonération)" @click="demander({ genre: 'annuler-frais', frais: f })"><Ban /></Button>
                  </template>
                </div>
              </li>
            </ul>
          </TabsContent>

          <TabsContent value="paiements">
            <p v-if="!finances.paiements.length" class="py-4 text-sm text-slate-400">Aucun paiement enregistré.</p>
            <ul v-else class="divide-y divide-gray-200">
              <li v-for="p in finances.paiements" :key="p.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3" :class="p.annule_le ? 'opacity-60' : ''">
                <div class="min-w-0 flex-1 basis-full sm:basis-0">
                  <p class="text-sm font-semibold text-slate-700" :class="p.annule_le ? 'line-through' : ''">{{ formaterMontant(p.montant) }} <span class="font-normal text-slate-400">· {{ p.mode_libelle }}</span></p>
                  <p class="truncate text-xs text-slate-400">
                    {{ formaterDate(p.date_paiement) }} · {{ p.numero_recu }}<template v-if="p.reference"> · réf. {{ p.reference }}</template>
                    <template v-if="p.imputations?.length"> · {{ p.imputations.map(i => i.libelle).join(', ') }}</template>
                    <span v-if="p.annule_le" class="text-destructive"> · Annulé : {{ p.motif_annulation }}</span>
                  </p>
                </div>
                <div class="flex">
                  <Button v-if="p.justificatif" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
                    <a :href="p.justificatif.url" target="_blank" rel="noopener" :aria-label="`Reçu de versement du paiement ${p.numero_recu}`" title="Reçu de versement"><Paperclip /></a>
                  </Button>
                  <Button v-else-if="estSuperAdmin && !p.annule_le" variant="ghost" size="icon-sm" class="text-sun-600 hover:text-brand-700" :aria-label="`Joindre le reçu de versement du paiement ${p.numero_recu}`" title="Joindre le reçu de versement" @click="ouvrirJustificatif(p)"><ImagePlus /></Button>
                  <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
                    <a :href="p.url_recu" target="_blank" rel="noopener" :aria-label="`Reçu ${p.numero_recu}`" title="Reçu PDF"><FileText /></a>
                  </Button>
                  <Button v-if="p.peut_annuler" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Annuler le paiement ${p.numero_recu}`" title="Annuler le paiement" @click="demander({ genre: 'annuler-paiement', paiement: p })"><Ban /></Button>
                </div>
              </li>
            </ul>
          </TabsContent>
        </Tabs>
      </template>
    </CardContent>

    <template v-if="estSuperAdmin && finances">
      <DialoguePaiement v-model:open="encaissement" :doctorant-id="doctorantId" :nom-complet="nomComplet" :frais="finances.frais" @enregistre="actualiser" />
      <DialogueFrais v-model:open="ajoutFrais" :doctorants="[{ id: doctorantId, nom_complet: nomComplet }]" @ajoute="actualiser" />
    </template>

    <Dialog v-model:open="dialogueJustificatif">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Reçu de versement</DialogTitle>
          <DialogDescription>Paiement {{ paiementJustificatif?.numero_recu }} de {{ formaterMontant(paiementJustificatif?.montant) }}.</DialogDescription>
        </DialogHeader>
        <ChampJustificatif v-model="fichierJustificatif" :erreur="erreurJustificatif" />
        <DialogFooter>
          <Button variant="outline" @click="dialogueJustificatif = false">Fermer</Button>
          <Button :disabled="!fichierJustificatif || envoiJustificatif" @click="joindreJustificatif"><Loader2 v-if="envoiJustificatif" class="animate-spin" /><Paperclip v-else /> Enregistrer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="dialogueAction">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ titreAction }}</DialogTitle>
          <DialogDescription>
            <template v-if="action?.genre === 'ajuster'">Bourse, remise, correction : le motif est gardé. Le montant ne peut pas descendre sous ce qui est déjà payé.</template>
            <template v-else-if="action?.genre === 'annuler-frais'">Exonération ou frais ajouté par erreur. Le frais reste visible, barré.</template>
            <template v-else>Le paiement reste au journal, marqué « annulé ». Les frais qu'il réglait redeviennent dus.</template>
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4">
          <div v-if="action?.genre === 'ajuster'" class="grid gap-4 sm:grid-cols-2">
            <ChampFormulaire libelle="Montant (Ar)" pour="ajust-montant" :erreur="erreurs.montant" requis><Input id="ajust-montant" v-model.number="valeurs.montant" type="number" min="0" step="1000" /></ChampFormulaire>
            <ChampFormulaire libelle="Échéance" pour="ajust-echeance" :erreur="erreurs.echeance"><Input id="ajust-echeance" v-model="valeurs.echeance" type="date" /></ChampFormulaire>
          </div>
          <ChampFormulaire libelle="Motif" pour="motif-action" :erreur="erreurs.motif || erreurs.motif_ajustement" :requis="action?.genre !== 'ajuster'">
            <Textarea id="motif-action" v-model="valeurs.motif" rows="2" />
          </ChampFormulaire>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="dialogueAction = false">Fermer</Button>
          <Button :variant="action?.genre === 'ajuster' ? 'default' : 'destructive'" :disabled="envoi" @click="confirmer">
            <Loader2 v-if="envoi" class="animate-spin" />{{ action?.genre === 'ajuster' ? 'Enregistrer' : 'Confirmer l\'annulation' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </Card>
</template>

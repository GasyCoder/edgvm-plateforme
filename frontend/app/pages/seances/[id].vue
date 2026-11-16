<script setup lang="ts">
import { ArrowDown, ArrowLeft, ArrowUp, Ban, CalendarClock, Clock, Gavel, Loader2, Mail, MapPin, MoreHorizontal, Pencil, Presentation, RefreshCw, Send, Trash2, UserCheck, UserPlus, Wallet, X } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { IntervenantPassage, PassageSeance, Seance } from '~/types'

/*
 * Une séance : programme des passages (créneaux), modérateurs et jury par passage (sélection multiple,
 * sans double réservation), horaires recalculables, convocations par e-mail et dans la plateforme.
 */
definePageMeta({ roles: ['super_admin', 'membre_ead', 'doctorant'] })

const route = useRoute()
const api = useApi()
const { data: seance, status, error } = useAsyncData(`seance-${route.params.id}`, () => api<{ data: Seance }>(`/api/seances/${route.params.id}`).then(r => r.data), { lazy: true })
useHead({ title: () => seance.value?.titre ?? 'Séance' })

const gerer = computed(() => !!seance.value?.peut.modifier && seance.value.statut !== 'annulee')
const passages = computed(() => seance.value?.passages ?? [])
const mettreAJour = (nouvelle: Seance) => { seance.value = nouvelle }

/* Sélection multiple des passages */
const selection = ref<number[]>([])
watch(passages, liste => { selection.value = selection.value.filter(id => liste.some(p => p.id === id)) })
const toutCoche = computed<boolean | 'indeterminate'>(() => passages.value.length > 0 && passages.value.every(p => selection.value.includes(p.id)) ? true : selection.value.length ? 'indeterminate' : false)
const basculerTout = (coche: boolean | 'indeterminate') => { selection.value = coche === true ? passages.value.map(p => p.id) : [] }
const basculer = (id: number, coche: boolean | 'indeterminate') => { selection.value = coche === true ? [...selection.value, id] : selection.value.filter(s => s !== id) }

const dialogueSeance = ref(false)
const dialogueCandidats = ref(false)
const dialogueIntervenants = ref(false)
const passagesCibles = ref<PassageSeance[]>([])
function affecterA(liste: PassageSeance[]) {
  passagesCibles.value = liste
  dialogueIntervenants.value = true
}

const occupe = ref(false)
async function appeler(chemin: string, options: Parameters<typeof api>[1] = {}) {
  occupe.value = true
  try {
    const reponse = await api<{ data: Seance, message?: string, conflits?: string[] }>(chemin, options)
    mettreAJour(reponse.data)
    if (reponse.message) toast.success(reponse.message)
    return reponse
  }
  catch (e) {
    const conflits = (e as { data?: { conflits?: string[] } }).data?.conflits
    toast.error(messageErreur(e), conflits?.length ? { description: conflits.slice(0, 4).join('\n') } : undefined)
    return null
  }
  finally {
    occupe.value = false
  }
}

const retirerIntervenant = (passage: PassageSeance, intervenant: IntervenantPassage) => appeler(`/api/passages/${passage.id}/intervenants/${intervenant.id}`, { method: 'DELETE' })
const retirerPassage = (passage: PassageSeance) => appeler(`/api/passages/${passage.id}`, { method: 'DELETE' })
const recalculer = (ordre?: number[]) => appeler(`/api/seances/${seance.value!.id}/replanifier`, { method: 'POST', body: { ordre } })
function deplacer(index: number, sens: -1 | 1) {
  const ids = passages.value.map(p => p.id)
  const cible = index + sens
  if (cible < 0 || cible >= ids.length) return
  ;[ids[index], ids[cible]] = [ids[cible]!, ids[index]!]
  recalculer(ids)
}

/* Modifier un passage : heure, durée, sujet */
const passageModifie = ref<PassageSeance | null>(null)
const formulairePassage = reactive({ heure_debut: '', duree: 30, sujet: '' })
const erreursPassage = ref<Record<string, string>>({})
function modifierPassage(passage: PassageSeance) {
  passageModifie.value = passage
  Object.assign(formulairePassage, { heure_debut: passage.heure_debut, duree: passage.duree, sujet: passage.sujet ?? '' })
  erreursPassage.value = {}
}
async function enregistrerPassage() {
  if (!passageModifie.value) return
  occupe.value = true
  erreursPassage.value = {}
  try {
    const reponse = await api<{ data: Seance, message: string }>(`/api/passages/${passageModifie.value.id}`, { method: 'PUT', body: formulairePassage })
    mettreAJour(reponse.data)
    toast.success(reponse.message)
    passageModifie.value = null
  }
  catch (e) {
    erreursPassage.value = erreursDeValidation(e)
    if (!Object.keys(erreursPassage.value).length) toast.error(messageErreur(e))
  }
  finally {
    occupe.value = false
  }
}

/* Convocations */
const confirmationConvocation = ref(false)
const jamaisConvoques = computed(() => passages.value.filter(p => p.convocation === 'jamais').length)
const modifies = computed(() => passages.value.filter(p => p.convocation === 'modifiee').length)
const aEnvoyer = computed(() => jamaisConvoques.value + modifies.value)
const modeEnvoi = ref<'intelligent' | 'tous'>('intelligent')
watch(confirmationConvocation, (ouvert) => { if (ouvert) modeEnvoi.value = aEnvoyer.value ? 'intelligent' : 'tous' })
const sansModerateur = computed(() => passages.value.filter(p => !p.intervenants.some(i => i.role === 'moderateur')).length)
/* Envoi : intelligent (jamais convoqués et modifiés), à tous, ou à certains passages (un par un, sélection) */
async function convoquer(mode: 'intelligent' | 'tous' | 'passages', ids: number[] = []) {
  confirmationConvocation.value = false
  const reponse = await appeler(`/api/seances/${seance.value!.id}/convoquer`, { method: 'POST', body: { mode, passages: ids } }) as { sans_email?: string[] } | null
  if (reponse && mode === 'passages') selection.value = []
  if (reponse?.sans_email?.length) toast.warning(`Sans e-mail, non convoqués : ${reponse.sans_email.join(', ')}.`)
}

/* Annuler ou supprimer la séance */
const confirmationFin = ref(false)
async function terminer() {
  confirmationFin.value = false
  if (seance.value!.convocations_envoyees_le) {
    await appeler(`/api/seances/${seance.value!.id}/annuler`, { method: 'POST' })
    return
  }
  try {
    await api(`/api/seances/${seance.value!.id}`, { method: 'DELETE' })
    toast.success('Séance supprimée.')
    clearNuxtData('seances')
    await navigateTo('/seances')
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}

const jour = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const parRole = (passage: PassageSeance, role: 'moderateur' | 'jury') => passage.intervenants.filter(i => i.role === role)

/* Présentation de projet de thèse : décision du jury par candidat, puis admission ou information */
const DECISIONS = [
  { valeur: 'admis', libelle: 'Admis', aide: 'Il sera inscrit en D1 et recevra son attestation d\'admission.', classe: 'bg-leaf-50 text-leaf-600' },
  { valeur: 'ajourne', libelle: 'Ajourné', aide: 'Il pourra présenter à nouveau son projet.', classe: 'bg-sun-100 text-sun-700' },
  { valeur: 'non_admis', libelle: 'Non admis', aide: 'Candidature close.', classe: 'bg-red-50 text-destructive' },
] as const
const classeDecision = (decision: string | null) => DECISIONS.find(d => d.valeur === decision)?.classe ?? 'bg-gray-100 text-slate-500'
const passageDecide = ref<PassageSeance | null>(null)
const formulaireDecision = reactive({ decision: '' as string, observation: '' })
function ouvrirDecision(passage: PassageSeance) {
  passageDecide.value = passage
  Object.assign(formulaireDecision, { decision: passage.decision ?? '', observation: passage.decision_observation ?? '' })
}
async function enregistrerDecision() {
  if (!passageDecide.value) return
  const reponse = await appeler(`/api/passages/${passageDecide.value.id}/decision`, { method: 'PUT', body: { decision: formulaireDecision.decision || null, observation: formulaireDecision.observation || null } })
  if (reponse) passageDecide.value = null
}
const aAdmettre = computed(() => passages.value.filter(p => p.decision === 'admis' && p.doctorant.statut === 'candidat'))
const aInformer = computed(() => passages.value.filter(p => (p.decision === 'ajourne' || p.decision === 'non_admis') && !p.decision_notifiee_le))
const sansDecision = computed(() => passages.value.filter(p => !p.decision && p.doctorant.statut === 'candidat').length)
const confirmationAdmission = ref(false)
async function admettreTous() {
  confirmationAdmission.value = false
  const reponse = await appeler(`/api/seances/${seance.value!.id}/admettre`, { method: 'POST' }) as { refus?: { doctorant: string, raison: string }[] } | null
  if (reponse?.refus?.length) toast.warning(reponse.refus.map(r => `${r.doctorant} : ${r.raison}`).join('\n'))
}
const informer = () => appeler(`/api/seances/${seance.value!.id}/informer`, { method: 'POST' })
const th = 'border-y border-gray-300 px-2 py-2.5 text-start text-sm font-normal text-slate-400 first:ps-4 last:pe-4 sm:first:ps-5 sm:last:pe-5'
const td = 'border-b border-gray-200 px-2 py-3 align-top first:ps-4 last:pe-4 sm:first:ps-5 sm:last:pe-5'
</script>

<template>
  <div>
    <NuxtLink to="/seances" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand-700"><ArrowLeft class="size-4" /> Séances</NuxtLink>

    <SqueletteFiche v-if="status === 'pending' && !seance" />
    <EtatVide v-else-if="error" :icone="Presentation" titre="Séance introuvable" texte="Elle a peut-être été supprimée, ou vous n'y avez pas accès." />

    <div v-else-if="seance" class="grid gap-6">
      <!-- En-tête -->
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">{{ seance.type_libelle }}</p>
          <h1 class="text-2xl font-bold tracking-tight text-brand-900">{{ seance.titre }}</h1>
          <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
            <span class="inline-flex items-center gap-1.5 first-letter:uppercase"><CalendarClock class="size-4" aria-hidden="true" />{{ jour(seance.date) }}, {{ seance.heure_debut }}{{ seance.heure_fin ? ` à ${seance.heure_fin}` : '' }}</span>
            <span v-if="seance.lieu" class="inline-flex items-center gap-1.5"><MapPin class="size-4" aria-hidden="true" />{{ seance.lieu }}</span>
            <span class="inline-flex items-center gap-1.5"><Clock class="size-4" aria-hidden="true" />{{ seance.duree_passage }} min par passage{{ seance.pause ? `, ${seance.pause} min de pause` : '' }}</span>
          </p>
          <div class="mt-2 flex flex-wrap gap-2 text-xs">
            <span class="rounded px-2 py-0.5 font-semibold" :class="{ brouillon: 'bg-gray-100 text-slate-600', publiee: 'bg-leaf-50 text-leaf-600', annulee: 'bg-red-50 text-destructive' }[seance.statut]">{{ seance.statut_libelle }}</span>
            <span v-if="seance.exige_paiement_solde" class="inline-flex items-center gap-1 rounded bg-sun-50 px-2 py-0.5 font-semibold text-sun-700"><Wallet class="size-3.5" aria-hidden="true" />Réservé aux doctorants à jour de paiement</span>
          </div>
        </div>
        <div v-if="gerer" class="flex flex-wrap gap-2">
          <Button variant="outline" class="bg-white" @click="dialogueSeance = true"><Pencil /> Modifier</Button>
          <Button variant="outline" class="bg-white" @click="dialogueCandidats = true"><UserPlus /> {{ seance.pour_candidats ? 'Programmer des candidats' : 'Programmer des doctorants' }}</Button>
          <Button :disabled="!passages.length || occupe" @click="confirmationConvocation = true">
            <Send /> Envoyer les convocations
            <span v-if="aEnvoyer" class="rounded-full bg-white/20 px-1.5 text-xs font-bold tabular-nums">{{ aEnvoyer }}</span>
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger as-child><Button variant="outline" size="icon" class="bg-white" aria-label="Plus d'actions"><MoreHorizontal /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem :disabled="!passages.length" @select="recalculer()"><RefreshCw /> Recalculer les horaires</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem class="text-destructive" @select="confirmationFin = true">
                <template v-if="seance.convocations_envoyees_le"><Ban /> Annuler la séance</template>
                <template v-else><Trash2 /> Supprimer la séance</template>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </header>

      <!-- Décisions du jury (présentation de projet de thèse) -->
      <div v-if="seance.pour_candidats && gerer && passages.length" class="flex flex-wrap items-center gap-3 rounded-md border border-gray-300 bg-white px-4 py-3 sm:px-5">
        <p class="text-sm text-slate-600">
          <span class="font-semibold text-slate-700">Décisions du jury :</span>
          {{ passages.filter(p => p.decision === 'admis').length }} admis, {{ passages.filter(p => p.decision === 'ajourne').length }} ajourné(s), {{ passages.filter(p => p.decision === 'non_admis').length }} non admis<template v-if="sansDecision">, {{ sansDecision }} sans décision</template>.
        </p>
        <div class="flex flex-wrap gap-2 sm:ms-auto">
          <Button size="sm" variant="outline" :disabled="!aInformer.length || occupe" @click="informer"><Mail /> Informer ajournés et non admis ({{ aInformer.length }})</Button>
          <Button size="sm" :disabled="!aAdmettre.length || occupe" @click="confirmationAdmission = true"><UserCheck /> Admettre les admis ({{ aAdmettre.length }})</Button>
        </div>
      </div>

      <p v-if="seance.description" class="max-w-4xl whitespace-pre-line rounded-md border border-gray-300 bg-white p-4 text-sm leading-relaxed text-slate-600">{{ seance.description }}</p>

      <!-- Programme -->
      <section class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x" aria-labelledby="titre-programme">
        <div class="flex flex-wrap items-center gap-3 px-4 py-4 sm:px-5">
          <template v-if="selection.length && gerer">
            <span class="text-sm font-semibold text-slate-700">{{ selection.length }} passage{{ selection.length > 1 ? 's' : '' }} sélectionné{{ selection.length > 1 ? 's' : '' }}</span>
            <Button size="sm" @click="affecterA(passages.filter(p => selection.includes(p.id)))"><UserCheck /> Modérateurs et jury</Button>
            <Button size="sm" variant="outline" :disabled="occupe" @click="convoquer('passages', selection)"><Send /> Convoquer la sélection</Button>
            <Button size="sm" variant="ghost" @click="selection = []"><X /> Désélectionner</Button>
          </template>
          <template v-else>
            <h2 id="titre-programme" class="font-bold text-slate-700">Programme <span class="font-normal text-slate-400">({{ passages.length }} passage{{ passages.length > 1 ? 's' : '' }})</span></h2>
            <p v-if="gerer && passages.length" class="text-sm text-slate-400">Cochez plusieurs passages pour leur donner les mêmes modérateurs ou le même jury.</p>
            <p v-if="gerer && sansModerateur" class="ms-auto text-sm text-sun-700">{{ sansModerateur }} sans modérateur</p>
          </template>
        </div>

        <EtatVide v-if="!passages.length" :icone="UserPlus" titre="Aucun passage" :texte="gerer ? 'Programmez des doctorants : ils se placent à la suite, à partir de l\'heure de début.' : undefined" class="border-none" />

        <div v-else class="overflow-x-auto">
          <table class="w-full border-collapse" :class="occupe ? 'opacity-60' : ''">
            <thead>
              <tr>
                <th v-if="gerer" :class="th" class="w-10"><Checkbox :model-value="toutCoche" aria-label="Tout sélectionner" @update:model-value="basculerTout" /></th>
                <th :class="th" class="w-28">Horaire</th>
                <th :class="th">{{ seance.pour_candidats ? 'Candidat' : 'Doctorant' }}</th>
                <th :class="th" class="hidden lg:table-cell">Sujet</th>
                <th :class="th">Modération</th>
                <th :class="th">Jury</th>
                <th v-if="seance.pour_candidats" :class="th">Décision</th>
                <th v-if="gerer" :class="th" class="w-24"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(passage, index) in passages" :key="passage.id" class="group transition-colors hover:bg-gray-50" :class="selection.includes(passage.id) ? 'bg-brand-50/50' : ''">
                <td v-if="gerer" :class="td"><Checkbox :model-value="selection.includes(passage.id)" :aria-label="`Sélectionner ${passage.doctorant.nom_complet}`" @update:model-value="v => basculer(passage.id, v)" /></td>
                <td :class="td" class="whitespace-nowrap">
                  <span class="block text-sm font-bold tabular-nums text-slate-700">{{ passage.heure_debut }}</span>
                  <span class="block text-xs tabular-nums text-slate-400">à {{ passage.heure_fin }}</span>
                  <span v-if="passage.convocation === 'a_jour'" class="mt-1 inline-block rounded bg-leaf-50 px-1.5 text-[11px] font-medium text-leaf-600" :title="passage.convoque_le ? `Envoyée ${depuis(passage.convoque_le)}` : undefined">Convoqué</span>
                  <span v-else-if="passage.convocation === 'modifiee'" class="mt-1 inline-block rounded bg-sun-100 px-1.5 text-[11px] font-medium text-sun-700" title="Horaire, sujet, lieu, modérateurs ou jury changés depuis l'envoi">À reconvoquer</span>
                  <span v-else-if="gerer && seance.convocations_envoyees_le" class="mt-1 inline-block rounded bg-gray-100 px-1.5 text-[11px] font-medium text-slate-500">Non convoqué</span>
                </td>
                <td :class="td">
                  <div class="flex items-start gap-3">
                    <img v-if="passage.doctorant.photo_url" :src="passage.doctorant.photo_url" alt="" class="size-9 flex-none rounded-full object-cover object-top">
                    <span v-else class="flex size-9 flex-none items-center justify-center rounded-full text-xs font-semibold text-white" :class="couleurAvatar(passage.doctorant.nom_complet)">{{ initiales(passage.doctorant.nom_complet) }}</span>
                    <div class="min-w-0">
                      <NuxtLink v-if="gerer" :to="`/doctorants/${passage.doctorant.id}`" class="block text-sm font-semibold text-slate-700 hover:text-brand-700">{{ passage.doctorant.nom_complet }}</NuxtLink>
                      <span v-else class="block text-sm font-semibold text-slate-700">{{ passage.doctorant.nom_complet }}</span>
                      <span class="text-xs text-slate-400">{{ [passage.doctorant.niveau, passage.doctorant.equipe].filter(Boolean).join(', ') }}</span>
                      <p v-if="passage.sujet" class="line-clamp-2 max-w-xs text-xs text-slate-500 lg:hidden">{{ passage.sujet }}</p>
                    </div>
                  </div>
                </td>
                <td :class="td" class="hidden lg:table-cell"><p class="line-clamp-2 max-w-sm text-sm text-slate-500">{{ passage.sujet }}</p></td>
                <td v-for="role in (['moderateur', 'jury'] as const)" :key="role" :class="td">
                  <ul class="grid gap-1">
                    <li v-for="intervenant in parRole(passage, role)" :key="intervenant.id" class="inline-flex max-w-[14rem] items-center gap-1 rounded bg-gray-100 py-0.5 pe-1 ps-2 text-xs text-slate-600">
                      <span class="truncate">{{ intervenant.nom_avec_titre }}</span>
                      <button v-if="gerer" type="button" class="rounded p-0.5 text-slate-400 hover:bg-white hover:text-destructive" :aria-label="`Retirer ${intervenant.nom_avec_titre}`" @click="retirerIntervenant(passage, intervenant)"><X class="size-3" /></button>
                    </li>
                    <li v-if="!parRole(passage, role).length" class="text-xs" :class="role === 'moderateur' && gerer ? 'text-sun-700' : 'text-slate-300'">Aucun</li>
                  </ul>
                  <button v-if="gerer && role === 'moderateur'" type="button" class="mt-1 text-xs font-medium text-brand-700 hover:underline" @click="affecterA([passage])">+ Ajouter</button>
                </td>
                <td v-if="seance.pour_candidats" :class="td">
                  <span v-if="passage.doctorant.admis_le" class="inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800">Admis, inscrit en D1</span>
                  <button v-else-if="gerer" type="button" class="rounded px-2 py-0.5 text-xs font-semibold hover:ring-1 hover:ring-brand-300" :class="classeDecision(passage.decision)" @click="ouvrirDecision(passage)">{{ passage.decision_libelle ?? 'Décider' }}</button>
                  <span v-else class="inline-block rounded px-2 py-0.5 text-xs font-semibold" :class="classeDecision(passage.decision)">{{ passage.decision_libelle ?? 'À venir' }}</span>
                  <p v-if="passage.decision_notifiee_le && passage.decision !== 'admis'" class="mt-1 text-[11px] text-slate-400">Informé {{ depuis(passage.decision_notifiee_le) }}</p>
                  <p v-if="gerer && passage.decision_observation" class="mt-1 line-clamp-2 max-w-[12rem] text-[11px] text-slate-500">« {{ passage.decision_observation }} »</p>
                </td>
                <td v-if="gerer" :class="td" class="text-end">
                  <div class="flex justify-end">
                    <Button variant="ghost" size="icon-sm" class="text-slate-400" :disabled="index === 0 || occupe" :aria-label="`Avancer ${passage.doctorant.nom_complet}`" @click="deplacer(index, -1)"><ArrowUp /></Button>
                    <Button variant="ghost" size="icon-sm" class="text-slate-400" :disabled="index === passages.length - 1 || occupe" :aria-label="`Reculer ${passage.doctorant.nom_complet}`" @click="deplacer(index, 1)"><ArrowDown /></Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child><Button variant="ghost" size="icon-sm" class="text-slate-400" :aria-label="`Actions pour ${passage.doctorant.nom_complet}`"><MoreHorizontal /></Button></DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem v-if="seance.pour_candidats && !passage.doctorant.admis_le" @select="ouvrirDecision(passage)"><Gavel /> Décision du jury</DropdownMenuItem>
                        <DropdownMenuItem @select="modifierPassage(passage)"><Clock /> Modifier l'horaire ou le sujet</DropdownMenuItem>
                        <DropdownMenuItem @select="affecterA([passage])"><UserCheck /> Modérateurs et jury</DropdownMenuItem>
                        <DropdownMenuItem @select="convoquer('passages', [passage.id])"><Send /> {{ passage.convocation === 'jamais' ? 'Envoyer la convocation' : 'Renvoyer la convocation' }}</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-destructive" @select="retirerPassage(passage)"><Trash2 /> Retirer de la séance</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <template v-if="gerer">
        <DialogueSeance v-model:open="dialogueSeance" :seance="seance" @enregistre="mettreAJour" />
        <DialogueCandidatsSeance v-model:open="dialogueCandidats" :seance="seance" @ajoute="mettreAJour" />
        <DialogueIntervenants v-model:open="dialogueIntervenants" :seance="seance" :passages="passagesCibles" @affecte="s => { mettreAJour(s); selection = [] }" />
      </template>

      <!-- Modifier un passage -->
      <Dialog :open="passageModifie !== null" @update:open="o => { if (!o) passageModifie = null }">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Passage de {{ passageModifie?.doctorant.nom_complet }}</DialogTitle>
            <DialogDescription>Refusé si le doctorant, un modérateur ou un membre du jury est déjà pris à cette heure.</DialogDescription>
          </DialogHeader>
          <form id="form-passage" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="enregistrerPassage">
            <ChampFormulaire libelle="Début" pour="passage-heure" :erreur="erreursPassage.heure_debut" requis>
              <Input id="passage-heure" v-model="formulairePassage.heure_debut" type="time" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Durée (minutes)" pour="passage-duree" :erreur="erreursPassage.duree" requis>
              <Input id="passage-duree" v-model.number="formulairePassage.duree" type="number" min="5" max="480" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Sujet présenté" pour="passage-sujet" :erreur="erreursPassage.sujet" aide="Par défaut, le sujet de thèse." class="sm:col-span-2">
              <Textarea id="passage-sujet" v-model="formulairePassage.sujet" rows="3" class="resize-y" />
            </ChampFormulaire>
          </form>
          <DialogFooter>
            <Button variant="outline" @click="passageModifie = null">Annuler</Button>
            <Button type="submit" form="form-passage" :disabled="occupe"><Loader2 v-if="occupe" class="animate-spin" /> Enregistrer</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Décision du jury pour un candidat -->
      <Dialog :open="passageDecide !== null" @update:open="o => { if (!o) passageDecide = null }">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Décision du jury</DialogTitle>
            <DialogDescription>{{ passageDecide?.doctorant.nom_complet }}, présentation de {{ passageDecide?.heure_debut }}</DialogDescription>
          </DialogHeader>
          <form id="form-decision" class="grid gap-4" novalidate @submit.prevent="enregistrerDecision">
            <fieldset class="grid gap-2">
              <legend class="sr-only">Décision</legend>
              <label v-for="d in DECISIONS" :key="d.valeur" class="flex cursor-pointer gap-3 rounded-md border p-3 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="formulaireDecision.decision === d.valeur ? 'border-brand-500 bg-brand-50' : 'border-gray-300'">
                <input v-model="formulaireDecision.decision" type="radio" :value="d.valeur" class="mt-1 accent-brand-700">
                <span><span class="block font-semibold text-slate-700">{{ d.libelle }}</span><span class="text-slate-500">{{ d.aide }}</span></span>
              </label>
              <button v-if="formulaireDecision.decision" type="button" class="justify-self-start text-xs text-slate-400 hover:text-brand-700 hover:underline" @click="formulaireDecision.decision = ''">Retirer la décision</button>
            </fieldset>
            <ChampFormulaire libelle="Observation du jury" pour="decision-observation" aide="Communiquée au candidat ajourné ou non admis.">
              <Textarea id="decision-observation" v-model="formulaireDecision.observation" rows="3" class="resize-y" />
            </ChampFormulaire>
          </form>
          <DialogFooter>
            <Button variant="outline" @click="passageDecide = null">Annuler</Button>
            <Button type="submit" form="form-decision" :disabled="occupe"><Loader2 v-if="occupe" class="animate-spin" /> Enregistrer</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Admission des candidats admis -->
      <AlertDialog v-model:open="confirmationAdmission">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Admettre {{ aAdmettre.length }} candidat{{ aAdmettre.length > 1 ? 's' : '' }} ?</AlertDialogTitle>
            <AlertDialogDescription>Pour chacun, en une seule fois :</AlertDialogDescription>
          </AlertDialogHeader>
          <ul class="grid list-disc gap-1 ps-5 text-sm text-slate-600">
            <li>il devient doctorant et reçoit son matricule ;</li>
            <li>il est inscrit en D1 pour l'année en cours, avec ses droits d'inscription ;</li>
            <li>l'attestation d'admission est délivrée ;</li>
            <li>il reçoit un e-mail avec l'attestation et son lien d'accès à la plateforme.</li>
          </ul>
          <p class="text-sm text-slate-500">{{ aAdmettre.map(p => p.doctorant.nom_complet).join(', ') }}</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Annuler</AlertDialogCancel>
            <Button :disabled="occupe" @click="admettreTous"><UserCheck /> Admettre</Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <!-- Convocations -->
      <AlertDialog v-model:open="confirmationConvocation">
        <AlertDialogContent class="sm:max-w-lg">
          <AlertDialogHeader>
            <AlertDialogTitle>Envoyer les convocations</AlertDialogTitle>
            <AlertDialogDescription>
              Chaque doctorant reçoit son horaire ; chaque modérateur et membre du jury, la liste complète de ses passages. Par e-mail, et dans la plateforme s'il y a un compte.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <fieldset class="grid gap-2">
            <legend class="sr-only">À qui envoyer</legend>
            <label class="flex cursor-pointer gap-3 rounded-md border p-3 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="[modeEnvoi === 'intelligent' ? 'border-brand-500 bg-brand-50' : 'border-gray-300', !aEnvoyer ? 'cursor-not-allowed opacity-60' : '']">
              <input v-model="modeEnvoi" type="radio" value="intelligent" class="mt-1 accent-brand-700" :disabled="!aEnvoyer">
              <span>
                <span class="block font-semibold text-slate-700">Seulement ce qui reste à envoyer <span class="font-normal text-slate-400">(conseillé)</span></span>
                <span class="text-slate-500">
                  <template v-if="aEnvoyer">{{ [jamaisConvoques ? `${jamaisConvoques} jamais convoqué(s)` : '', modifies ? `${modifies} modifié(s) depuis l'envoi` : ''].filter(Boolean).join(', ') }}. Les autres ne reçoivent rien en double.</template>
                  <template v-else>Tout le monde a déjà une convocation à jour.</template>
                </span>
              </span>
            </label>
            <label class="flex cursor-pointer gap-3 rounded-md border p-3 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" :class="modeEnvoi === 'tous' ? 'border-brand-500 bg-brand-50' : 'border-gray-300'">
              <input v-model="modeEnvoi" type="radio" value="tous" class="mt-1 accent-brand-700">
              <span>
                <span class="block font-semibold text-slate-700">Tout le monde ({{ passages.length }} passage{{ passages.length > 1 ? 's' : '' }})</span>
                <span class="text-slate-500">Pour un rappel la veille, par exemple.</span>
              </span>
            </label>
          </fieldset>
          <p v-if="sansModerateur" class="text-sm text-sun-700">Attention : {{ sansModerateur }} passage(s) sans modérateur.</p>
          <p class="text-xs text-slate-400">Pour un seul doctorant ou une sélection : utilisez le menu du passage ou cochez les passages.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Annuler</AlertDialogCancel>
            <Button :disabled="occupe || (modeEnvoi === 'intelligent' && !aEnvoyer)" @click="convoquer(modeEnvoi)">
              <Loader2 v-if="occupe" class="animate-spin" /><Send v-else />
              Envoyer {{ modeEnvoi === 'intelligent' ? `(${aEnvoyer})` : `à tous (${passages.length})` }}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <!-- Annuler ou supprimer -->
      <AlertDialog v-model:open="confirmationFin">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ seance.convocations_envoyees_le ? 'Annuler la séance ?' : 'Supprimer la séance ?' }}</AlertDialogTitle>
            <AlertDialogDescription>
              {{ seance.convocations_envoyees_le
                ? 'Les convocations sont déjà parties : la séance reste visible comme annulée, et les modérateurs et le jury redeviennent disponibles sur ces créneaux. Prévenez les personnes concernées.'
                : 'La séance et son programme seront supprimés.' }}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Retour</AlertDialogCancel>
            <Button variant="destructive" @click="terminer">{{ seance.convocations_envoyees_le ? 'Annuler la séance' : 'Supprimer' }}</Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </div>
</template>

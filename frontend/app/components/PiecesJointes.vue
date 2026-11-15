<script setup lang="ts">
import { Download, ExternalLink, File, FileImage, FileText, Loader2, Paperclip, Plus, Trash2, Upload, X } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { CategoriePiece, PieceJointe, Ressource } from '~/types'

/**
 * Pièces jointes du dossier : diplômes, pièce d'identité, attestations, publications, autres documents.
 */
const props = defineProps<{ doctorantId: string, peutAjouter: boolean }>()

const CATEGORIES: Array<{ valeur: CategoriePiece, libelle: string }> = [
  { valeur: 'diplome', libelle: 'Diplôme' },
  { valeur: 'piece_identite', libelle: "Pièce d'identité" },
  { valeur: 'attestation', libelle: 'Attestation' },
  { valeur: 'publication', libelle: 'Publication' },
  { valeur: 'autre', libelle: 'Autre document' },
]
const EXTENSIONS = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx'
const TAILLE_MAX = 10 * 1024 * 1024

const api = useApi()
const { data: pieces, status, refresh } = useAsyncData(
  () => `pieces-${props.doctorantId}`,
  () => api<Ressource<PieceJointe[]>>(`/api/doctorants/${props.doctorantId}/pieces`).then(r => r.data),
  { lazy: true, default: () => [] as PieceJointe[] },
)

/* Ajout : plusieurs fichiers à la fois, chacun avec son titre et son type */
interface Envoi { fichier: File, titre: string, categorie: CategoriePiece, etat: 'attente' | 'envoi' | 'ok' | 'erreur', erreur?: string }
const ouvert = ref(false)
const envois = ref<Envoi[]>([])
const survol = ref(false)
const champFichier = ref<HTMLInputElement | null>(null)
const enCours = computed(() => envois.value.some(e => e.etat === 'envoi'))

function ajouterFichiers(liste: FileList | null | undefined) {
  for (const fichier of Array.from(liste ?? [])) {
    const trop = fichier.size > TAILLE_MAX
    envois.value.push({
      fichier,
      titre: fichier.name.replace(/\.[^.]+$/, ''),
      categorie: /cin|identit|passeport/i.test(fichier.name) ? 'piece_identite' : /dipl|master|licence/i.test(fichier.name) ? 'diplome' : /attest/i.test(fichier.name) ? 'attestation' : 'autre',
      etat: trop ? 'erreur' : 'attente',
      erreur: trop ? 'Plus de 10 Mo : fichier refusé.' : undefined,
    })
  }
  if (champFichier.value) champFichier.value.value = ''
}

function ouvrir() {
  envois.value = []
  ouvert.value = true
}

async function envoyer() {
  for (const envoi of envois.value.filter(e => e.etat === 'attente')) {
    envoi.etat = 'envoi'
    const donnees = new FormData()
    donnees.append('fichier', envoi.fichier)
    donnees.append('categorie', envoi.categorie)
    donnees.append('titre', envoi.titre)
    try {
      await api(`/api/doctorants/${props.doctorantId}/pieces`, { method: 'POST', body: donnees })
      envoi.etat = 'ok'
    }
    catch (e) {
      envoi.etat = 'erreur'
      envoi.erreur = erreursDeValidation(e).fichier ?? messageErreur(e)
    }
  }
  const reussis = envois.value.filter(e => e.etat === 'ok').length
  await refresh()
  if (envois.value.every(e => e.etat === 'ok')) {
    toast.success(`${reussis} document${reussis > 1 ? 's' : ''} ajouté${reussis > 1 ? 's' : ''}.`)
    ouvert.value = false
  }
  else if (reussis) {
    toast.warning(`${reussis} document${reussis > 1 ? 's' : ''} ajouté${reussis > 1 ? 's' : ''}, d'autres sont en erreur.`)
  }
}

/* Suppression */
const aSupprimer = ref<PieceJointe | null>(null)
const confirmation = ref(false)
function demanderSuppression(piece: PieceJointe) {
  aSupprimer.value = piece
  confirmation.value = true
}
async function supprimer() {
  const piece = aSupprimer.value
  if (!piece) return
  try {
    await api(`/api/pieces/${piece.id}`, { method: 'DELETE' })
    await refresh()
    toast.success('Document supprimé.')
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    aSupprimer.value = null
  }
}

const icone = (piece: PieceJointe) => piece.type_mime === 'application/pdf' ? FileText : piece.type_mime.startsWith('image/') ? FileImage : File
</script>

<template>
  <Card id="pieces" class="scroll-mt-24">
    <CardHeader class="flex flex-row items-start justify-between gap-3">
      <div class="grid gap-1.5">
        <CardTitle>Pièces jointes <span v-if="pieces.length" class="font-normal text-slate-400">({{ pieces.length }})</span></CardTitle>
        <CardDescription>Diplômes, pièce d'identité, attestations, publications...</CardDescription>
      </div>
      <Button v-if="peutAjouter" variant="outline" size="sm" @click="ouvrir"><Plus /> Ajouter</Button>
    </CardHeader>
    <CardContent>
      <div v-if="status === 'pending' && !pieces.length" class="grid gap-2">
        <div v-for="ligne in 2" :key="ligne" class="h-14 animate-pulse rounded-md bg-gray-100" />
      </div>
      <button
        v-else-if="!pieces.length && peutAjouter"
        type="button"
        class="flex w-full flex-col items-center gap-2 rounded-md border-2 border-dashed border-gray-300 px-6 py-8 text-center hover:border-brand-300"
        @click="ouvrir"
      >
        <Paperclip class="size-7 text-slate-400" />
        <span class="text-sm font-medium text-slate-600">Aucun document. Ajoutez les diplômes, la pièce d'identité...</span>
      </button>
      <p v-else-if="!pieces.length" class="text-sm text-slate-400">Aucun document.</p>
      <ul v-else class="divide-y divide-gray-200">
        <li v-for="piece in pieces" :key="piece.id" class="flex items-center gap-3 py-3">
          <span class="flex size-10 flex-none items-center justify-center rounded-md bg-brand-50 text-brand-700">
            <component :is="icone(piece)" class="size-5" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-700" :title="piece.nom_original">{{ piece.titre }}</p>
            <p class="truncate text-xs text-slate-400">
              <span class="font-medium text-slate-500">{{ piece.categorie_libelle }}</span>
              · {{ formaterTaille(piece.taille) }} · {{ formaterDate(piece.ajoute_le) }}<template v-if="piece.ajoute_par"> · {{ piece.ajoute_par }}</template>
            </p>
          </div>
          <div class="flex flex-none items-center">
            <Button v-if="piece.affichable" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
              <a :href="`/telechargements/pieces/${piece.id}?afficher=1`" target="_blank" rel="noopener" :aria-label="`Ouvrir ${piece.titre}`" title="Ouvrir"><ExternalLink /></a>
            </Button>
            <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" as-child>
              <a :href="`/telechargements/pieces/${piece.id}`" :aria-label="`Télécharger ${piece.titre}`" title="Télécharger"><Download /></a>
            </Button>
            <Button v-if="piece.peut_supprimer" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Supprimer ${piece.titre}`" title="Supprimer" @click="demanderSuppression(piece)">
              <Trash2 />
            </Button>
          </div>
        </li>
      </ul>
    </CardContent>

    <Dialog v-model:open="ouvert">
      <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Ajouter des documents</DialogTitle>
          <DialogDescription>PDF, images (JPG, PNG, WEBP) ou Word, 10 Mo au plus par fichier. Vous pouvez en choisir plusieurs.</DialogDescription>
        </DialogHeader>

        <button
          type="button"
          class="flex flex-col items-center gap-2 rounded-md border-2 border-dashed px-6 py-7 text-center transition-colors"
          :class="survol ? 'border-brand-400 bg-brand-50' : 'border-gray-300 hover:border-brand-300'"
          @click="champFichier?.click()"
          @dragover.prevent="survol = true"
          @dragleave.prevent="survol = false"
          @drop.prevent="survol = false; ajouterFichiers($event.dataTransfer?.files)"
        >
          <Upload class="size-7 text-slate-400" />
          <span class="text-sm font-medium text-slate-700">Glissez les fichiers ici ou cliquez pour les choisir</span>
        </button>
        <input ref="champFichier" type="file" multiple :accept="EXTENSIONS" class="hidden" @change="ajouterFichiers(($event.target as HTMLInputElement).files)">

        <ul v-if="envois.length" class="grid gap-3">
          <li v-for="(envoi, index) in envois" :key="index" class="rounded-md border p-3" :class="envoi.etat === 'erreur' ? 'border-red-200 bg-red-50' : envoi.etat === 'ok' ? 'border-leaf-400/50 bg-leaf-50' : ''">
            <div class="flex items-center gap-2 text-xs text-slate-500">
              <span class="min-w-0 flex-1 truncate">{{ envoi.fichier.name }} · {{ formaterTaille(envoi.fichier.size) }}</span>
              <Loader2 v-if="envoi.etat === 'envoi'" class="size-4 animate-spin text-brand-600" />
              <span v-else-if="envoi.etat === 'ok'" class="font-medium text-leaf-600">Ajouté</span>
              <button v-else-if="envoi.etat !== 'erreur' || !enCours" type="button" class="rounded p-0.5 hover:bg-gray-100" :aria-label="`Retirer ${envoi.fichier.name}`" @click="envois.splice(index, 1)"><X class="size-4" /></button>
            </div>
            <div v-if="envoi.etat === 'attente' || envoi.etat === 'envoi'" class="mt-2 grid gap-2 sm:grid-cols-[1fr_12rem]">
              <Input v-model="envoi.titre" :aria-label="`Titre de ${envoi.fichier.name}`" placeholder="Titre du document" :disabled="envoi.etat === 'envoi'" />
              <Select v-model="envoi.categorie" :disabled="envoi.etat === 'envoi'">
                <SelectTrigger class="w-full" :aria-label="`Type de ${envoi.fichier.name}`"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="categorie in CATEGORIES" :key="categorie.valeur" :value="categorie.valeur">{{ categorie.libelle }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <p v-if="envoi.erreur" class="mt-1 text-sm text-destructive">{{ envoi.erreur }}</p>
          </li>
        </ul>

        <DialogFooter>
          <Button variant="outline" :disabled="enCours" @click="ouvert = false">Fermer</Button>
          <Button :disabled="enCours || !envois.some(e => e.etat === 'attente')" @click="envoyer">
            <Loader2 v-if="enCours" class="animate-spin" /><Upload v-else />
            Ajouter {{ envois.filter(e => e.etat === 'attente').length || '' }} document{{ envois.filter(e => e.etat === 'attente').length > 1 ? 's' : '' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="confirmation">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Supprimer « {{ aSupprimer?.titre }} » ?</AlertDialogTitle>
          <AlertDialogDescription>Le fichier sera définitivement effacé du dossier.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Garder</AlertDialogCancel>
          <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="supprimer">Supprimer</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </Card>
</template>

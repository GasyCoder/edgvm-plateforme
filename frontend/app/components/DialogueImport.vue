<script setup lang="ts">
import { CircleCheck, Download, FileSpreadsheet, Loader2, TriangleAlert, Upload } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'

interface Rapport {
  simulation: boolean
  total: number
  valides: number
  crees: number
  apercu: Array<{ ligne: number, libelle: string, email: string | null, niveau: string | null }>
  erreurs: Array<{ ligne: number, libelle: string, erreurs: string[] }>
}

/**
 * Import de doctorants (ou de candidats) depuis un fichier CSV : vérification d'abord (rien n'est enregistré),
 * puis import des lignes valides. Un candidat n'a ni niveau, ni matricule, ni accès avant son admission.
 */
const props = defineProps<{ candidats?: boolean }>()
const nom = (nombre: number) => `${props.candidats ? 'candidat' : 'doctorant'}${nombre > 1 ? 's' : ''}`
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ importe: [] }>()

const api = useApi()
const fichier = ref<File | null>(null)
const invitation = ref(true)
const rapport = ref<Rapport | null>(null)
const erreur = ref('')
const envoi = ref(false)
const survol = ref(false)
const champFichier = ref<HTMLInputElement | null>(null)

watch(ouvert, (estOuvert) => {
  if (estOuvert) {
    fichier.value = null
    rapport.value = null
    erreur.value = ''
  }
})

function choisir(liste: FileList | null | undefined) {
  const choisi = liste?.[0]
  if (!choisi) return
  fichier.value = choisi
  rapport.value = null
  erreur.value = ''
}

async function envoyer(simulation: boolean) {
  if (!fichier.value) return
  envoi.value = true
  erreur.value = ''
  const donnees = new FormData()
  donnees.append('fichier', fichier.value)
  donnees.append('simulation', simulation ? '1' : '0')
  donnees.append('envoyer_invitation', invitation.value && !props.candidats ? '1' : '0')
  donnees.append('candidats', props.candidats ? '1' : '0')
  try {
    const reponse = await api<{ data: Rapport }>('/api/doctorants/import', { method: 'POST', body: donnees })
    if (simulation) {
      rapport.value = reponse.data
    }
    else {
      toast.success(`${reponse.data.crees} ${nom(reponse.data.crees)} importé${reponse.data.crees > 1 ? 's' : ''}.`)
      ouvert.value = false
      emit('importe')
    }
  }
  catch (e) {
    erreur.value = erreursDeValidation(e).fichier ?? messageErreur(e)
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{{ candidats ? 'Importer des candidats' : 'Importer des doctorants' }}</DialogTitle>
        <DialogDescription>
          Fichier CSV (dans Excel : Fichier, Enregistrer sous, « CSV UTF-8 »). Colonnes obligatoires : Nom, E-mail, Équipe.
          <template v-if="candidats"> Les colonnes Niveau et Matricule sont ignorées : un candidat reçoit un numéro provisoire, et son matricule à l'admission.</template>
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-4">
        <a :href="candidats ? '/telechargements/modele-import-candidats.csv' : '/telechargements/modele-import-doctorants.csv'" class="inline-flex w-fit items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700">
          <Download class="size-4" /> Télécharger le modèle à remplir
        </a>

        <button
          type="button"
          class="flex flex-col items-center gap-2 rounded-md border-2 border-dashed px-6 py-8 text-center transition-colors"
          :class="survol ? 'border-brand-400 bg-brand-50' : 'border-gray-300 hover:border-brand-300'"
          @click="champFichier?.click()"
          @dragover.prevent="survol = true"
          @dragleave.prevent="survol = false"
          @drop.prevent="survol = false; choisir($event.dataTransfer?.files)"
        >
          <FileSpreadsheet v-if="fichier" class="size-8 text-brand-600" />
          <Upload v-else class="size-8 text-slate-400" />
          <span class="text-sm font-medium text-slate-700">{{ fichier ? fichier.name : 'Glissez le fichier ici ou cliquez pour le choisir' }}</span>
          <span class="text-xs text-slate-400">CSV, 2 Mo et 1000 lignes au plus</span>
        </button>
        <input ref="champFichier" type="file" accept=".csv,text/csv" class="hidden" @change="choisir(($event.target as HTMLInputElement).files)">

        <p v-if="erreur" class="flex gap-2 rounded-md bg-red-50 p-3 text-sm text-destructive"><TriangleAlert class="size-4 flex-none" />{{ erreur }}</p>

        <div v-if="!candidats" class="flex items-start justify-between gap-4 rounded-md border p-4">
          <div>
            <Label for="import-invitation">Envoyer le lien d'accès</Label>
            <p class="mt-1 text-xs text-slate-400">Chaque doctorant importé reçoit un e-mail pour choisir son mot de passe.</p>
          </div>
          <Switch id="import-invitation" v-model="invitation" />
        </div>

        <div v-if="rapport" class="grid gap-3">
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-md border p-3"><p class="text-2xl font-bold text-slate-700">{{ rapport.total }}</p><p class="text-xs text-slate-400">lignes lues</p></div>
            <div class="rounded-md border border-leaf-400/50 bg-leaf-50 p-3"><p class="text-2xl font-bold text-leaf-600">{{ rapport.valides }}</p><p class="text-xs text-slate-500">prêtes à importer</p></div>
            <div class="rounded-md border p-3" :class="rapport.erreurs.length ? 'border-red-200 bg-red-50' : ''"><p class="text-2xl font-bold" :class="rapport.erreurs.length ? 'text-destructive' : 'text-slate-700'">{{ rapport.erreurs.length }}</p><p class="text-xs text-slate-500">avec erreurs</p></div>
          </div>

          <div v-if="rapport.erreurs.length" class="max-h-56 overflow-y-auto rounded-md border">
            <p class="sticky top-0 border-b bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Lignes à corriger (non importées)</p>
            <div v-for="ligne in rapport.erreurs" :key="ligne.ligne" class="border-b px-4 py-2.5 text-sm last:border-b-0">
              <p class="font-medium text-slate-700">Ligne {{ ligne.ligne }} : {{ ligne.libelle }}</p>
              <ul class="mt-1 list-disc ps-5 text-destructive">
                <li v-for="message in ligne.erreurs" :key="message">{{ message }}</li>
              </ul>
            </div>
          </div>

          <div v-if="rapport.apercu.length" class="max-h-44 overflow-y-auto rounded-md border">
            <p class="sticky top-0 border-b bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Prêtes à importer</p>
            <div v-for="ligne in rapport.apercu" :key="ligne.ligne" class="flex items-center gap-3 border-b px-4 py-2 text-sm last:border-b-0">
              <CircleCheck class="size-4 flex-none text-leaf-600" />
              <span class="min-w-0 flex-1 truncate">{{ ligne.libelle }} <span class="text-slate-400">{{ ligne.email }}</span></span>
              <span v-if="ligne.niveau" class="text-xs font-semibold text-brand-700">{{ ligne.niveau }}</span>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button v-if="!rapport" :disabled="!fichier || envoi" @click="envoyer(true)">
          <Loader2 v-if="envoi" class="animate-spin" /> Vérifier le fichier
        </Button>
        <Button v-else :disabled="!rapport.valides || envoi" @click="envoyer(false)">
          <Loader2 v-if="envoi" class="animate-spin" />
          Importer {{ rapport.valides }} {{ nom(rapport.valides) }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { CalendarClock, CircleCheck, CircleX, Hourglass, Loader2, Presentation, RotateCcw, UserCheck } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import type { Doctorant } from '~/types'

/**
 * Candidature : dépôt, présentations du projet de thèse et décisions du jury, admission.
 */
const props = defineProps<{ doctorant: Doctorant }>()
const emit = defineEmits<{ admis: [] }>()

const api = useApi()
const { estSuperAdmin } = useAuth()
const confirmation = ref(false)
const envoi = ref(false)

const candidature = computed(() => props.doctorant.candidature)
const admissible = computed(() => props.doctorant.est_candidat && candidature.value?.decision === 'admis')
/* État de la candidature, bien visible : couleur et icône selon la décision */
const etat = computed(() => {
  const c = candidature.value
  if (c?.admis_le) return { libelle: `Admis le ${formaterDate(c.admis_le)}`, icone: CircleCheck, classe: 'border-brand-200 bg-brand-50 text-brand-800' }
  return ({
    admis: { libelle: 'Admis par le jury, à inscrire', icone: CircleCheck, classe: 'border-leaf-400/50 bg-leaf-50 text-leaf-600' },
    ajourne: { libelle: 'Ajourné, à représenter', icone: RotateCcw, classe: 'border-sun-300 bg-sun-50 text-sun-700' },
    non_admis: { libelle: 'Non admis', icone: CircleX, classe: 'border-red-200 bg-red-50 text-destructive' },
  } as Record<string, { libelle: string, icone: typeof CircleCheck, classe: string }>)[c?.decision ?? '']
    ?? { libelle: 'En attente de présentation', icone: Hourglass, classe: 'border-sun-300 bg-sun-50 text-sun-700' }
})
const couleur = (decision: string | null) => ({ admis: 'bg-leaf-50 text-leaf-600', ajourne: 'bg-sun-100 text-sun-700', non_admis: 'bg-red-50 text-destructive' } as Record<string, string>)[decision ?? ''] ?? 'bg-gray-100 text-slate-500'

async function admettre() {
  envoi.value = true
  try {
    const reponse = await api<{ message: string }>(`/api/doctorants/${props.doctorant.id}/admettre`, { method: 'POST' })
    toast.success(reponse.message)
    confirmation.value = false
    clearNuxtData(cle => cle.startsWith('doctorant') || cle.startsWith('candidats'))
    emit('admis')
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Card v-if="candidature">
    <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-2">
      <CardTitle>Candidature</CardTitle>
      <span class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-semibold" :class="etat.classe" role="status">
        <component :is="etat.icone" class="size-4 flex-none" aria-hidden="true" />{{ etat.libelle }}
      </span>
    </CardHeader>
    <CardContent class="grid gap-4 text-sm">
      <p v-if="candidature.depose_le" class="text-muted-foreground">Candidature enregistrée le {{ formaterDate(candidature.depose_le) }}.</p>

      <div>
        <p class="mb-2 font-medium text-slate-700">Présentations du projet de thèse</p>
        <ul v-if="candidature.presentations?.length" class="divide-y rounded-md border">
          <li v-for="p in candidature.presentations" :key="p.seance_id + p.date" class="flex flex-wrap items-start gap-x-4 gap-y-1 px-3 py-2.5">
            <Presentation class="mt-0.5 size-4 flex-none text-brand-600" aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <NuxtLink :to="`/seances/${p.seance_id}`" class="font-medium text-slate-700 hover:text-brand-700">{{ p.seance }}</NuxtLink>
              <p class="flex items-center gap-1 text-xs text-slate-500"><CalendarClock class="size-3.5" aria-hidden="true" />{{ formaterDateHeure(p.date) }}</p>
              <p v-if="p.observation" class="mt-1 text-xs text-slate-500">« {{ p.observation }} »</p>
            </div>
            <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="couleur(p.decision)">{{ p.decision_libelle ?? 'Décision à venir' }}</span>
          </li>
        </ul>
        <p v-else class="text-muted-foreground">
          Pas encore programmé.
          <NuxtLink v-if="estSuperAdmin" to="/seances" class="font-medium text-brand-700 hover:underline">Programmer dans une présentation de projet</NuxtLink>
        </p>
      </div>

      <div v-if="estSuperAdmin && admissible" class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-leaf-400/50 bg-leaf-50 p-3">
        <p class="text-leaf-600">Le jury a déclaré ce candidat admis.</p>
        <Button size="sm" @click="confirmation = true"><UserCheck /> Admettre en doctorat</Button>
      </div>
    </CardContent>

    <AlertDialog v-model:open="confirmation">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Admettre {{ doctorant.nom_complet }} ?</AlertDialogTitle>
          <AlertDialogDescription>En une seule fois :</AlertDialogDescription>
        </AlertDialogHeader>
        <ul class="grid list-disc gap-1 ps-5 text-sm text-slate-600">
          <li>il devient doctorant et reçoit son matricule ;</li>
          <li>il est inscrit en D1 pour l'année en cours, avec ses droits d'inscription ;</li>
          <li>l'attestation d'admission est délivrée ;</li>
          <li>il reçoit un e-mail avec l'attestation et son lien d'accès à la plateforme.</li>
        </ul>
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="envoi">Annuler</AlertDialogCancel>
          <Button :disabled="envoi" @click="admettre"><Loader2 v-if="envoi" class="animate-spin" /><UserCheck v-else /> Admettre</Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </Card>
</template>

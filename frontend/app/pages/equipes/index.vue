<script setup lang="ts">
import { BookOpen, ChevronRight, GraduationCap, Network, Pencil, Plus, Trash2, UserRound, Users } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import type { Equipe, Ressource } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
useHead({ title: "Équipes d'accueil" })

const api = useApi()
const { estSuperAdmin } = useAuth()
const { data: equipes, status, refresh } = useAsyncData('equipes', () => api<Ressource<Equipe[]>>('/api/equipes').then(r => r.data), { lazy: true })

const dialogue = ref(false)
const modifiee = ref<Equipe | null>(null)
function ouvrir(equipe: Equipe | null = null) {
  modifiee.value = equipe
  dialogue.value = true
}

const aSupprimer = ref<Equipe | null>(null)
const confirmation = ref(false)
function demanderSuppression(equipe: Equipe) {
  aSupprimer.value = equipe
  confirmation.value = true
}
async function supprimer() {
  const equipe = aSupprimer.value
  if (!equipe) return
  try {
    await api(`/api/equipes/${equipe.id}`, { method: 'DELETE' })
    toast.success('Équipe supprimée.')
    clearNuxtData('ref-equipes')
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    confirmation.value = false
  }
}

const pluriel = (nombre: number | undefined, mot: string) => `${nombre ?? 0} ${mot}${(nombre ?? 0) > 1 ? 's' : ''}`
</script>

<template>
  <div>
    <EnTetePage titre="Équipes d'accueil" :description="estSuperAdmin ? 'Programmes, responsable, encadrants et membres de chaque équipe. Les sigles sont les mêmes que sur le site de l\'École.' : 'Les équipes dont vous êtes membre.'">
      <template v-if="estSuperAdmin" #actions>
        <Button @click="ouvrir()"><Plus /> Nouvelle équipe</Button>
      </template>
    </EnTetePage>

    <SqueletteListe v-if="status === 'pending' && !equipes" :lignes="4" />
    <EtatVide v-else-if="equipes && !equipes.length" :icone="Network" titre="Aucune équipe" />

    <div v-else-if="equipes" class="grid gap-4 lg:grid-cols-2">
      <article v-for="equipe in equipes" :key="equipe.id" class="group relative flex flex-col rounded-md border border-gray-300 bg-white transition-colors hover:border-brand-400">
        <div class="flex items-start gap-3 p-5 pb-3">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-brand-700">{{ equipe.sigle }}</p>
            <h2 class="mt-0.5 font-semibold leading-snug text-slate-700">
              <NuxtLink :to="`/equipes/${equipe.id}`" class="after:absolute after:inset-0 group-hover:text-brand-800">{{ equipe.nom }}</NuxtLink>
            </h2>
            <p v-if="equipe.domaine" class="mt-1 text-sm text-slate-400">{{ equipe.domaine }}</p>
          </div>
          <div v-if="estSuperAdmin" class="relative z-10 flex flex-none">
            <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" :aria-label="`Modifier ${equipe.sigle}`" @click="ouvrir(equipe)"><Pencil /></Button>
            <Button v-if="!equipe.doctorants_count" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Supprimer ${equipe.sigle}`" @click="demanderSuppression(equipe)"><Trash2 /></Button>
          </div>
        </div>

        <p class="mb-4 flex items-center gap-2 px-5 text-sm" :class="equipe.responsable ? 'text-slate-600' : 'text-slate-400'">
          <UserRound class="size-4 flex-none text-slate-400" aria-hidden="true" />
          <span class="truncate">{{ equipe.responsable ? `Responsable : ${equipe.responsable.nom_avec_titre}` : 'Responsable non renseigné' }}</span>
        </p>

        <ul v-if="equipe.programmes?.length" class="-mt-1 mb-4 grid gap-1 px-5 text-sm text-slate-500">
          <li v-for="programme in equipe.programmes.slice(0, 3)" :key="programme.id" class="flex gap-2">
            <BookOpen class="mt-0.5 size-4 flex-none text-brand-500" aria-hidden="true" />
            <span class="line-clamp-1">{{ programme.nom }}</span>
          </li>
          <li v-if="equipe.programmes.length > 3" class="ps-6 text-xs text-slate-400">et {{ equipe.programmes.length - 3 }} autre{{ equipe.programmes.length - 3 > 1 ? 's' : '' }}</li>
        </ul>

        <div class="mt-auto flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-gray-200 px-5 py-3 text-sm text-slate-500">
          <span class="inline-flex items-center gap-1.5"><BookOpen class="size-4 text-slate-400" aria-hidden="true" />{{ pluriel(equipe.programmes_count, 'programme') }}</span>
          <span class="inline-flex items-center gap-1.5"><Users class="size-4 text-slate-400" aria-hidden="true" />{{ pluriel(equipe.personnes_count, 'membre') }}</span>
          <span class="inline-flex items-center gap-1.5"><GraduationCap class="size-4 text-slate-400" aria-hidden="true" />{{ pluriel(equipe.doctorants_count, 'doctorant') }}</span>
          <ChevronRight class="ms-auto size-4 text-slate-300 group-hover:text-brand-600" aria-hidden="true" />
        </div>
      </article>
    </div>

    <DialogueEquipe v-model:open="dialogue" :equipe="modifiee" @enregistre="refresh()" />

    <AlertDialog v-model:open="confirmation">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Supprimer {{ aSupprimer?.sigle }} ?</AlertDialogTitle>
          <AlertDialogDescription>Ses programmes et la liste de ses membres seront supprimés aussi. Les comptes des membres restent.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Annuler</AlertDialogCancel>
          <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="supprimer">Supprimer</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>

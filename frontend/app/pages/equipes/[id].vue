<script setup lang="ts">
import { ArrowLeft, BookOpen, ChevronRight, KeyRound, Mail, MoreHorizontal, Pencil, Phone, Plus, Trash2, UserPlus, Users } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import type { Equipe, FonctionMembre, MembreEquipe, ProgrammeDoctoral } from '~/types'

/*
 * Fiche complète d'une équipe d'accueil : présentation, doctorants par niveau, programmes doctoraux,
 * responsable, encadrants et membres, comptes d'accès à la plateforme.
 */
definePageMeta({ roles: ['super_admin', 'membre_ead'] })

const route = useRoute()
const api = useApi()
const { estSuperAdmin } = useAuth()
const id = computed(() => route.params.id as string)
const { data: equipe, status, error, refresh } = useAsyncData(`equipe-${id.value}`, () => api<{ data: Equipe }>(`/api/equipes/${id.value}`).then(r => r.data), { lazy: true })
useHead({ title: () => equipe.value ? `${equipe.value.sigle}, équipe d'accueil` : "Équipe d'accueil" })

const dialogueEquipe = ref(false)
const dialogueProgramme = ref(false)
const programmeModifie = ref<ProgrammeDoctoral | null>(null)
const dialogueMembre = ref(false)
const membreModifie = ref<MembreEquipe | null>(null)

function ouvrirProgramme(programme: ProgrammeDoctoral | null = null) {
  programmeModifie.value = programme
  dialogueProgramme.value = true
}
function ouvrirMembre(membre: MembreEquipe | null = null) {
  membreModifie.value = membre
  dialogueMembre.value = true
}

/* Suppression confirmée d'un programme ou d'une personne */
const aSupprimer = ref<{ type: 'programme', element: ProgrammeDoctoral } | { type: 'membre', element: MembreEquipe } | null>(null)
/* Fenêtre séparée de la cible : la fenêtre se ferme avant l'action, la cible doit rester connue. */
const confirmation = ref(false)
function demanderSuppression(cible: NonNullable<typeof aSupprimer.value>) {
  aSupprimer.value = cible
  confirmation.value = true
}
async function supprimer() {
  const cible = aSupprimer.value
  if (!cible) return
  try {
    await api(cible.type === 'programme' ? `/api/programmes/${cible.element.id}` : `/api/membres-equipe/${cible.element.id}`, { method: 'DELETE' })
    toast.success(cible.type === 'programme' ? 'Programme supprimé.' : 'Personne retirée de l\'équipe.')
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    confirmation.value = false
  }
}

/* Personnes groupées par fonction, dans l'ordre responsable, encadrants, membres */
const FONCTIONS: { cle: FonctionMembre, titre: string }[] = [
  { cle: 'responsable', titre: 'Responsable' },
  { cle: 'encadrant', titre: 'Encadrants' },
  { cle: 'membre', titre: 'Membres' },
]
const groupes = computed(() => FONCTIONS
  .map(f => ({ ...f, personnes: (equipe.value?.personnes ?? []).filter(p => p.fonction === f.cle) }))
  .filter(g => g.personnes.length))

const niveaux = computed(() => {
  const e = equipe.value?.effectifs
  return e ? [['D1', e.D1], ['D2', e.D2], ['D3', e.D3], ['Dérogation', e.derogation]] as const : []
})
</script>

<template>
  <div>
    <NuxtLink to="/equipes" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand-700"><ArrowLeft class="size-4" /> Équipes d'accueil</NuxtLink>

    <SqueletteFiche v-if="status === 'pending' && !equipe" />
    <EtatVide v-else-if="error" :icone="Users" titre="Équipe introuvable" texte="Elle a peut-être été supprimée, ou vous n'y avez pas accès." />

    <div v-else-if="equipe" class="grid gap-6">
      <!-- En-tête -->
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-4xl">
          <p class="text-sm font-bold text-brand-700">{{ equipe.sigle }}<span v-if="equipe.domaine" class="font-normal text-slate-400">, {{ equipe.domaine }}</span></p>
          <h1 class="mt-1 text-2xl font-bold leading-tight tracking-tight text-brand-900">{{ equipe.nom }}</h1>
        </div>
        <Button v-if="estSuperAdmin" variant="outline" class="bg-white" @click="dialogueEquipe = true"><Pencil /> Modifier</Button>
      </header>

      <!-- Doctorants par niveau -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <NuxtLink :to="`/doctorants?equipe=${equipe.id}`" class="rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400">
          <p class="flex items-center gap-1.5 text-sm text-slate-400"><Users class="size-4" aria-hidden="true" /> Doctorants</p>
          <p class="mt-1 text-2xl font-bold tabular-nums text-slate-700">{{ equipe.doctorants_count ?? 0 }}</p>
          <p class="text-xs text-slate-400">voir la liste</p>
        </NuxtLink>
        <NuxtLink
          v-for="[libelle, nombre] in niveaux"
          :key="libelle"
          :to="`/doctorants?equipe=${equipe.id}&niveau=${libelle === 'Dérogation' ? 'derogation' : libelle}`"
          class="rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400"
        >
          <span class="rounded px-1.5 py-0.5 text-xs font-bold" :class="libelle === 'Dérogation' ? 'bg-sun-100 text-sun-700' : 'bg-brand-50 text-brand-800'">{{ libelle }}</span>
          <p class="mt-1 text-2xl font-bold tabular-nums text-slate-700">{{ nombre }}</p>
          <p class="text-xs text-slate-400">cette année</p>
        </NuxtLink>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <!-- Présentation et programmes -->
        <div class="grid content-start gap-6">
          <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-presentation">
            <h2 id="titre-presentation" class="border-b border-gray-200 px-5 py-4 font-bold text-slate-700">Présentation</h2>
            <p v-if="equipe.description" class="whitespace-pre-line px-5 py-4 text-sm leading-relaxed text-slate-600">{{ equipe.description }}</p>
            <p v-else class="px-5 py-4 text-sm text-slate-400">Aucune présentation. {{ estSuperAdmin ? 'Ajoutez-la avec « Modifier ».' : '' }}</p>
          </section>

          <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-programmes">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 id="titre-programmes" class="font-bold text-slate-700">Programmes doctoraux <span class="font-normal text-slate-400">({{ equipe.programmes?.length ?? 0 }})</span></h2>
              <Button v-if="estSuperAdmin" size="sm" variant="outline" @click="ouvrirProgramme()"><Plus /> Ajouter</Button>
            </div>
            <ul v-if="equipe.programmes?.length" class="divide-y divide-gray-200">
              <li v-for="programme in equipe.programmes" :key="programme.id" class="flex items-start gap-3 px-5 py-3">
                <BookOpen class="mt-0.5 size-5 flex-none text-brand-500" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold leading-snug text-slate-700">{{ programme.nom }}</p>
                  <p v-if="programme.description" class="mt-0.5 whitespace-pre-line text-sm text-slate-500">{{ programme.description }}</p>
                </div>
                <div v-if="estSuperAdmin" class="flex flex-none">
                  <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-brand-700" :aria-label="`Modifier ${programme.nom}`" @click="ouvrirProgramme(programme)"><Pencil /></Button>
                  <Button variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" :aria-label="`Supprimer ${programme.nom}`" @click="demanderSuppression({ type: 'programme', element: programme })"><Trash2 /></Button>
                </div>
              </li>
            </ul>
            <p v-else class="px-5 py-4 text-sm text-slate-400">Aucun programme doctoral.</p>
          </section>
        </div>

        <!-- Personnes de l'équipe -->
        <div class="grid content-start gap-6">
          <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-personnes">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 id="titre-personnes" class="font-bold text-slate-700">Responsable, encadrants et membres <span class="font-normal text-slate-400">({{ equipe.personnes?.length ?? 0 }})</span></h2>
              <Button v-if="estSuperAdmin" size="sm" variant="outline" @click="ouvrirMembre()"><UserPlus /> Ajouter</Button>
            </div>
            <div v-if="groupes.length" class="divide-y divide-gray-200">
              <div v-for="groupe in groupes" :key="groupe.cle">
                <h3 class="bg-gray-50 px-5 py-1.5 text-xs font-bold uppercase tracking-wider text-slate-400">{{ groupe.titre }} ({{ groupe.personnes.length }})</h3>
                <ul class="divide-y divide-gray-100">
                  <li v-for="personne in groupe.personnes" :key="personne.id" class="flex items-start gap-3 px-5 py-3">
                    <span class="flex size-9 flex-none items-center justify-center rounded-full text-xs font-semibold text-white" :class="couleurAvatar(personne.nom)">{{ initiales(personne.nom) }}</span>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-slate-700">{{ personne.nom_avec_titre }}</p>
                      <p v-if="personne.specialite || personne.etablissement" class="text-xs text-slate-500">{{ [personne.specialite, personne.etablissement].filter(Boolean).join(', ') }}</p>
                      <p v-if="personne.email || personne.telephone" class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
                        <a v-if="personne.email" :href="`mailto:${personne.email}`" class="inline-flex min-w-0 items-center gap-1 text-brand-700 hover:underline"><Mail class="size-3.5 flex-none" /><span class="truncate">{{ personne.email }}</span></a>
                        <a v-if="personne.telephone" :href="`tel:${personne.telephone}`" class="inline-flex items-center gap-1 text-slate-500 hover:text-brand-700"><Phone class="size-3.5" />{{ personne.telephone }}</a>
                      </p>
                    </div>
                    <DropdownMenu v-if="estSuperAdmin">
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon-sm" class="flex-none text-slate-400" :aria-label="`Actions pour ${personne.nom}`"><MoreHorizontal /></Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="ouvrirMembre(personne)"><Pencil /> Modifier</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-destructive" @select="demanderSuppression({ type: 'membre', element: personne })"><Trash2 /> Retirer de l'équipe</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </li>
                </ul>
              </div>
            </div>
            <p v-else class="px-5 py-4 text-sm text-slate-400">Personne n'est encore inscrit dans cette équipe.</p>
          </section>

          <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-comptes">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 id="titre-comptes" class="flex items-center gap-2 font-bold text-slate-700"><KeyRound class="size-4 text-slate-400" aria-hidden="true" /> Accès à la plateforme</h2>
              <NuxtLink v-if="estSuperAdmin" to="/utilisateurs" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline">Utilisateurs <ChevronRight class="size-4" /></NuxtLink>
            </div>
            <p class="px-5 pt-3 text-xs text-slate-400">Comptes qui voient les doctorants de l'équipe. Ils se gèrent dans Utilisateurs.</p>
            <ul v-if="equipe.comptes?.length" class="grid gap-2 px-5 py-3">
              <li v-for="compte in equipe.comptes" :key="compte.id" class="flex items-center justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium text-slate-600">{{ compte.name }} <span class="font-normal text-slate-400">{{ compte.email }}</span></span>
                <span class="flex-none text-xs" :class="compte.actif ? 'text-leaf-600' : 'text-slate-400'">{{ compte.actif ? 'Actif' : 'Désactivé' }}</span>
              </li>
            </ul>
            <p v-else class="px-5 py-3 text-sm text-slate-400">Aucun compte rattaché.</p>
          </section>
        </div>
      </div>

      <DialogueEquipe v-model:open="dialogueEquipe" :equipe="equipe" @enregistre="refresh()" />
      <DialogueProgramme v-model:open="dialogueProgramme" :equipe="equipe" :programme="programmeModifie" @enregistre="refresh()" />
      <DialogueMembreEquipe v-model:open="dialogueMembre" :equipe="equipe" :membre="membreModifie" @enregistre="refresh()" />

      <AlertDialog v-model:open="confirmation">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ aSupprimer?.type === 'programme' ? 'Supprimer ce programme ?' : 'Retirer cette personne de l\'équipe ?' }}</AlertDialogTitle>
            <AlertDialogDescription>
              {{ aSupprimer?.type === 'programme' ? (aSupprimer.element as ProgrammeDoctoral).nom : (aSupprimer?.element as MembreEquipe | undefined)?.nom_avec_titre }}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Annuler</AlertDialogCancel>
            <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="supprimer">{{ aSupprimer?.type === 'programme' ? 'Supprimer' : 'Retirer' }}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </div>
</template>

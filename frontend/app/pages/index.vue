<script setup lang="ts">
import { CalendarX2, TriangleAlert, UserPlus, Users, Wallet } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import type { AnneeUniversitaire } from '~/types'

interface TableauDeBord {
  annee: AnneeUniversitaire | null
  doctorants_actifs: number
  inscrits: number
  par_niveau: { D1: number, D2: number, D3: number, derogation: number }
  derogations_sans_decision: number
  doctorants_en_retard: number
  reste_a_recouvrer: number | null
  encaisse_annee: number | null
  actifs_sans_inscription: number
  par_equipe: Array<{ id: number, sigle: string, nom: string, doctorants: number }>
}

const { utilisateur, estDoctorant, estSuperAdmin } = useAuth()
const api = useApi()
const { data, status } = useAsyncData('tableau-de-bord', () => api<{ data: TableauDeBord }>('/api/tableau-de-bord').then(r => r.data), {
  lazy: true,
  immediate: !estDoctorant.value,
})
useHead({ title: estDoctorant.value ? 'Mon dossier' : 'Tableau de bord' })

const niveaux = computed(() => data.value
  ? [
      { libelle: 'D1', valeur: data.value.par_niveau.D1, filtre: 'D1' },
      { libelle: 'D2', valeur: data.value.par_niveau.D2, filtre: 'D2' },
      { libelle: 'D3', valeur: data.value.par_niveau.D3, filtre: 'D3' },
      { libelle: 'Dérogation', valeur: data.value.par_niveau.derogation, filtre: 'derogation' },
    ]
  : [])
</script>

<template>
  <FicheDoctorant v-if="estDoctorant && utilisateur?.doctorant_id" :id="utilisateur.doctorant_id" />

  <EtatVide v-else-if="estDoctorant" titre="Dossier en préparation" texte="Votre dossier n'est pas encore relié à votre compte. Contactez la scolarité de l'École." />

  <div v-else>
    <EnTetePage titre="Tableau de bord" :description="data?.annee ? `Année universitaire ${data.annee.libelle}` : 'Aucune année universitaire en cours'">
      <template v-if="estSuperAdmin" #actions>
        <Button as-child><NuxtLink to="/doctorants/nouveau"><UserPlus /> Nouveau doctorant</NuxtLink></Button>
      </template>
    </EnTetePage>

    <div v-if="status === 'pending' && !data" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Skeleton v-for="carte in 4" :key="carte" class="h-28 rounded-xl" />
    </div>

    <div v-else-if="data" class="grid gap-6">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <NuxtLink to="/doctorants?statut=actif" class="rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
          <Users class="size-5 text-brand-600" />
          <p class="mt-3 text-3xl font-bold tabular-nums text-brand-900">{{ data.doctorants_actifs }}</p>
          <p class="text-sm text-muted-foreground">Doctorants actifs</p>
        </NuxtLink>
        <div class="rounded-md border border-gray-300 bg-white p-5">
          <p class="text-sm font-medium">Inscrits cette année</p>
          <p class="mt-1 text-3xl font-bold tabular-nums text-brand-900">{{ data.inscrits }}</p>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <NuxtLink v-for="niveau in niveaux" :key="niveau.libelle" :to="`/doctorants?niveau=${niveau.filtre}`" class="rounded bg-muted px-1.5 py-0.5 text-xs hover:bg-accent">
              {{ niveau.libelle }} <span class="font-semibold tabular-nums">{{ niveau.valeur }}</span>
            </NuxtLink>
          </div>
        </div>
        <NuxtLink to="/doctorants?sans_inscription=1&statut=actif" class="rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
          <CalendarX2 class="size-5" :class="data.actifs_sans_inscription ? 'text-sun-600' : 'text-muted-foreground'" />
          <p class="mt-3 text-3xl font-bold tabular-nums text-brand-900">{{ data.actifs_sans_inscription }}</p>
          <p class="text-sm text-muted-foreground">Actifs non inscrits cette année</p>
        </NuxtLink>
        <NuxtLink to="/doctorants?niveau=derogation" class="rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
          <TriangleAlert class="size-5" :class="data.derogations_sans_decision ? 'text-destructive' : 'text-muted-foreground'" />
          <p class="mt-3 text-3xl font-bold tabular-nums text-brand-900">{{ data.derogations_sans_decision }}</p>
          <p class="text-sm text-muted-foreground">Dérogations sans décision</p>
        </NuxtLink>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <NuxtLink :to="estSuperAdmin ? '/paiements?situation=en_retard' : '/doctorants'" class="rounded-md border bg-white p-5 transition-colors hover:border-red-300" :class="data.doctorants_en_retard ? 'border-red-200' : 'border-gray-300'">
          <Wallet class="size-5" :class="data.doctorants_en_retard ? 'text-destructive' : 'text-muted-foreground'" />
          <p class="mt-3 text-3xl font-bold tabular-nums" :class="data.doctorants_en_retard ? 'text-destructive' : 'text-brand-900'">{{ data.doctorants_en_retard }}</p>
          <p class="text-sm text-muted-foreground">En retard de paiement</p>
        </NuxtLink>
        <template v-if="estSuperAdmin">
          <NuxtLink to="/paiements" class="rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
            <p class="text-sm text-muted-foreground">Reste à recouvrer</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-brand-900">{{ formaterMontant(data.reste_a_recouvrer) }}</p>
          </NuxtLink>
          <NuxtLink to="/paiements?onglet=journal" class="rounded-md border border-gray-300 bg-white p-5 transition-colors hover:border-brand-400">
            <p class="text-sm text-muted-foreground">Encaissé cette année</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-leaf-600">{{ formaterMontant(data.encaisse_annee) }}</p>
          </NuxtLink>
        </template>
      </div>

      <Card>
        <CardHeader><CardTitle>Doctorants actifs par équipe d'accueil</CardTitle></CardHeader>
        <CardContent>
          <ul class="divide-y">
            <li v-for="equipe in data.par_equipe" :key="equipe.id">
              <NuxtLink :to="`/doctorants?equipe=${equipe.id}`" class="flex items-center gap-3 py-2.5 hover:text-brand-700">
                <span class="w-14 flex-none text-sm font-semibold text-brand-800">{{ equipe.sigle }}</span>
                <span class="min-w-0 flex-1 truncate text-sm">{{ equipe.nom }}</span>
                <span class="text-sm font-semibold tabular-nums">{{ equipe.doctorants }}</span>
              </NuxtLink>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

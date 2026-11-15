<script setup lang="ts">
import { ReceiptText, Settings2, TriangleAlert, UsersRound, Wallet } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'

useHead({ title: 'Paiements' })

interface Statistiques { annee: string | null, encaisse_annee: number, encaisse_mois: number, reste_a_recouvrer: number, montant_en_retard: number, doctorants_en_retard: number }

const route = useRoute()
const router = useRouter()
const api = useApi()
const { estSuperAdmin, utilisateur } = useAuth()

const onglet = computed(() => route.query.onglet === 'journal' ? 'journal' : 'situation')
const changerOnglet = (v: string | number) => router.replace({ query: v === 'journal' ? { onglet: 'journal' } : {} })

const { data: stats, refresh: rafraichirStats } = useAsyncData('paiements-stats', () => estSuperAdmin.value ? api<{ data: Statistiques }>('/api/paiements/statistiques').then(r => r.data) : Promise.resolve(null), { lazy: true })
const fraisGroupe = ref(false)
const apresChangement = () => { rafraichirStats(); refreshNuxtData(['paiements-situation', 'paiements-journal']) }
</script>

<template>
  <!-- Doctorant : sa propre situation -->
  <div v-if="!estSuperAdmin">
    <EnTetePage titre="Mes paiements" description="Vos frais, vos versements et vos reçus. Pour payer, adressez-vous à la scolarité de l'École." />
    <CartePaiements v-if="utilisateur?.doctorant_id" :doctorant-id="utilisateur.doctorant_id" :nom-complet="utilisateur.name" />
  </div>

  <div v-else>
    <EnTetePage titre="Paiements" :description="stats?.annee ? `Année universitaire ${stats.annee} : frais dus, encaissements et retards.` : 'Frais dus, encaissements et retards.'">
      <template #actions>
        <Button variant="outline" class="bg-white" as-child><NuxtLink to="/parametres"><Settings2 /> Frais et tarifs</NuxtLink></Button>
        <Button @click="fraisGroupe = true"><ReceiptText /> Ajouter un frais à un groupe</Button>
      </template>
    </EnTetePage>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
      <template v-if="stats">
        <NuxtLink to="/paiements?onglet=journal" class="rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400 sm:p-5">
          <p class="text-sm text-slate-400">Encaissé cette année</p>
          <p class="mt-1 text-xl font-bold tabular-nums text-leaf-600 sm:text-2xl">{{ formaterMontant(stats.encaisse_annee) }}</p>
          <p class="text-xs text-slate-400">dont {{ formaterMontant(stats.encaisse_mois) }} ce mois</p>
        </NuxtLink>
        <NuxtLink to="/paiements" class="rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400 sm:p-5">
          <p class="text-sm text-slate-400">Reste à recouvrer</p>
          <p class="mt-1 text-xl font-bold tabular-nums text-slate-700 sm:text-2xl">{{ formaterMontant(stats.reste_a_recouvrer) }}</p>
          <p class="text-xs text-slate-400">tous frais non soldés</p>
        </NuxtLink>
        <NuxtLink to="/paiements?situation=en_retard" class="rounded-md border bg-white p-4 transition-colors hover:border-red-300 sm:p-5" :class="stats.doctorants_en_retard ? 'border-red-200' : 'border-gray-300'">
          <p class="flex items-center gap-1.5 text-sm text-slate-400"><TriangleAlert class="size-4" :class="stats.doctorants_en_retard ? 'text-destructive' : ''" /> En retard</p>
          <p class="mt-1 text-xl font-bold tabular-nums sm:text-2xl" :class="stats.doctorants_en_retard ? 'text-destructive' : 'text-slate-700'">{{ stats.doctorants_en_retard }} doctorant{{ stats.doctorants_en_retard > 1 ? 's' : '' }}</p>
          <p class="text-xs text-slate-400">{{ formaterMontant(stats.montant_en_retard) }} échus</p>
        </NuxtLink>
        <NuxtLink to="/paiements?onglet=journal&justificatif=manquant" class="rounded-md border border-gray-300 bg-white p-4 transition-colors hover:border-brand-400 sm:p-5">
          <p class="text-sm text-slate-400">Journal des paiements</p>
          <p class="mt-1 flex items-center gap-2 font-semibold text-brand-700"><Wallet class="size-5" /> Voir les versements</p>
          <p class="text-xs text-slate-400">reçus, justificatifs, export</p>
        </NuxtLink>
      </template>
      <Skeleton v-for="n in (stats ? 0 : 4)" v-else :key="n" class="h-28 rounded-md" />
    </div>

    <Tabs :model-value="onglet" class="mb-4" @update:model-value="changerOnglet">
      <TabsList>
        <TabsTrigger value="situation" class="gap-1.5"><UsersRound class="size-4" aria-hidden="true" />Situation des doctorants</TabsTrigger>
        <TabsTrigger value="journal" class="gap-1.5"><ReceiptText class="size-4" aria-hidden="true" />Journal des paiements</TabsTrigger>
      </TabsList>
    </Tabs>

    <TableauSituationPaiements v-if="onglet === 'situation'" @change="rafraichirStats()" />
    <TableauJournalPaiements v-else @change="rafraichirStats()" />

    <DialogueFrais v-model:open="fraisGroupe" @ajoute="apresChangement" />
  </div>
</template>

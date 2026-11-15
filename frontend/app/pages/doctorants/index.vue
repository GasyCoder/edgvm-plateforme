<script setup lang="ts">
import { ChevronRight, Search, UserPlus, Users, X } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Doctorant, Pagine } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
useHead({ title: 'Doctorants' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { estSuperAdmin } = useAuth()
const { equipes } = useReferentiels()

/* Les filtres vivent dans l'adresse : le retour arrière et le partage d'un lien gardent la liste. */
const filtres = computed(() => ({
  recherche: (route.query.recherche as string) || '',
  equipe: (route.query.equipe as string) || 'toutes',
  niveau: (route.query.niveau as string) || 'tous',
  statut: (route.query.statut as string) || 'tous',
  sans_inscription: route.query.sans_inscription === '1',
  page: Number(route.query.page) || 1,
}))

const { data, status } = useAsyncData(
  'doctorants',
  () => api<Pagine<Doctorant>>('/api/doctorants', {
    query: {
      recherche: filtres.value.recherche || undefined,
      equipe: filtres.value.equipe !== 'toutes' ? filtres.value.equipe : undefined,
      niveau: filtres.value.niveau !== 'tous' ? filtres.value.niveau : undefined,
      statut: filtres.value.statut !== 'tous' ? filtres.value.statut : undefined,
      sans_inscription: filtres.value.sans_inscription ? 1 : undefined,
      page: filtres.value.page,
    },
  }),
  { lazy: true, watch: [() => route.query] },
)

function filtrer(valeurs: Record<string, string | number | undefined>) {
  const query: Record<string, unknown> = { ...route.query, page: undefined, ...valeurs }
  const nettoyee = Object.fromEntries(Object.entries(query).filter(([, valeur]) => ![undefined, '', 'tous', 'toutes'].includes(valeur as string)))
  router.replace({ query: nettoyee as Record<string, string> })
}

const recherche = ref(filtres.value.recherche)
const rechercher = useDebounceFn((terme: string) => filtrer({ recherche: terme.trim() || undefined }), 350)
watch(recherche, rechercher)

const filtresActifs = computed(() => Object.keys(route.query).some(cle => cle !== 'page'))
const effacer = () => {
  recherche.value = ''
  router.replace({ query: {} })
}
</script>

<template>
  <div>
    <EnTetePage titre="Doctorants" :description="data ? `${data.meta.total} dossier${data.meta.total > 1 ? 's' : ''}` : undefined">
      <template v-if="estSuperAdmin" #actions>
        <Button as-child><NuxtLink to="/doctorants/nouveau"><UserPlus /> Nouveau doctorant</NuxtLink></Button>
      </template>
    </EnTetePage>

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto]">
      <div class="relative sm:col-span-2 lg:col-span-1">
        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="recherche" type="search" placeholder="Nom, matricule ou e-mail" class="bg-white pl-9" aria-label="Rechercher un doctorant" />
      </div>
      <Select :model-value="filtres.equipe" @update:model-value="valeur => filtrer({ equipe: String(valeur) })">
        <SelectTrigger class="w-full bg-white lg:w-44" aria-label="Équipe d'accueil"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="toutes">Toutes les équipes</SelectItem>
          <SelectItem v-for="equipe in equipes" :key="equipe.id" :value="String(equipe.id)">{{ equipe.sigle }}</SelectItem>
        </SelectContent>
      </Select>
      <Select :model-value="filtres.niveau" @update:model-value="valeur => filtrer({ niveau: String(valeur) })">
        <SelectTrigger class="w-full bg-white lg:w-40" aria-label="Niveau cette année"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="tous">Tous les niveaux</SelectItem>
          <SelectItem value="D1">D1</SelectItem>
          <SelectItem value="D2">D2</SelectItem>
          <SelectItem value="D3">D3</SelectItem>
          <SelectItem value="derogation">Dérogation</SelectItem>
        </SelectContent>
      </Select>
      <Select :model-value="filtres.statut" @update:model-value="valeur => filtrer({ statut: String(valeur) })">
        <SelectTrigger class="w-full bg-white lg:w-36" aria-label="Statut"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="tous">Tous statuts</SelectItem>
          <SelectItem value="actif">Actifs</SelectItem>
          <SelectItem value="suspendu">Suspendus</SelectItem>
          <SelectItem value="abandon">Abandons</SelectItem>
          <SelectItem value="diplome">Diplômés</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="filtresActifs" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
      <span v-if="filtres.sans_inscription" class="rounded-md bg-sun-100 px-2 py-0.5 text-sun-700">Sans inscription cette année</span>
      <Button variant="ghost" size="sm" @click="effacer"><X /> Effacer les filtres</Button>
    </div>

    <SqueletteListe v-if="status === 'pending' && !data" />

    <EtatVide v-else-if="data && !data.data.length" :icone="Users" titre="Aucun doctorant" :texte="filtresActifs ? 'Aucun dossier ne correspond à ces filtres.' : 'Les dossiers créés apparaîtront ici.'" />

    <template v-else-if="data">
      <ul class="overflow-hidden rounded-lg border bg-white" :class="status === 'pending' ? 'opacity-60' : ''">
        <li v-for="doctorant in data.data" :key="doctorant.id" class="border-b last:border-b-0">
          <NuxtLink :to="`/doctorants/${doctorant.id}`" class="flex items-center gap-3 p-4 hover:bg-muted/50">
            <span class="flex size-9 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800">{{ initiales(doctorant.nom_complet) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-medium">{{ doctorant.nom_complet }}</p>
              <p class="truncate text-xs text-muted-foreground">
                {{ doctorant.matricule }}<template v-if="doctorant.equipe"> · {{ doctorant.equipe.sigle }}</template>
              </p>
            </div>
            <div class="flex flex-none items-center gap-2">
              <BadgeStatut v-if="doctorant.statut !== 'actif'" :statut="doctorant.statut" :libelle="doctorant.statut_libelle" class="hidden sm:inline-flex" />
              <BadgeNiveau :inscription="doctorant.inscription_courante" />
              <ChevronRight class="size-4 text-muted-foreground" />
            </div>
          </NuxtLink>
        </li>
      </ul>
      <PaginationSimple :meta="data.meta" @page="page => filtrer({ page: page > 1 ? page : undefined })" />
    </template>
  </div>
</template>

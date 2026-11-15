<script setup lang="ts">
import { CalendarPlus, Mail, Pencil, Phone, Send, TriangleAlert } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { Doctorant, Inscription, Ressource } from '~/types'

/**
 * Dossier complet d'un doctorant. Ce qui s'affiche dépend du rôle (l'API ne renvoie que ce qui est permis).
 */
const props = defineProps<{ id: string }>()

const api = useApi()
const { estSuperAdmin, estDoctorant, utilisateur } = useAuth()
const { data: doctorant, status, refresh, error } = useAsyncData(
  () => `doctorant-${props.id}`,
  () => api<Ressource<Doctorant>>(`/api/doctorants/${props.id}`).then(r => r.data),
  { lazy: true },
)

const dialogueInscription = ref(false)
const inscriptionModifiee = ref<Inscription | null>(null)
const envoiLien = ref(false)

const ouvrirInscription = (inscription: Inscription | null = null) => {
  inscriptionModifiee.value = inscription
  dialogueInscription.value = true
}

/* « ?inscrire=1 » (depuis la liste) ouvre la fenêtre d'inscription ; « ?pieces=1 » (après création) amène aux pièces jointes. */
const route = useRoute()
watch(doctorant, async (fiche) => {
  if (!fiche) return
  if (fiche.peut.modifier && route.query.inscrire === '1') {
    ouvrirInscription()
    navigateTo({ query: { ...route.query, inscrire: undefined } }, { replace: true })
  }
  if (route.query.pieces === '1') {
    await nextTick()
    document.getElementById('pieces')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    navigateTo({ query: { ...route.query, pieces: undefined } }, { replace: true })
  }
}, { immediate: true })

async function renvoyerLien() {
  if (!doctorant.value?.compte) return
  envoiLien.value = true
  try {
    const reponse = await api<{ message: string }>(`/api/comptes/${doctorant.value.compte.id}/lien-acces`, { method: 'POST' })
    toast.success(reponse.message)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoiLien.value = false
  }
}

const identite = computed(() => {
  const d = doctorant.value
  if (!d) return []
  return [
    ['Matricule', d.matricule],
    ['Genre', d.genre === 'F' ? 'Féminin' : d.genre === 'M' ? 'Masculin' : ''],
    ['Date de naissance', [formaterDate(d.date_naissance), d.lieu_naissance].filter(Boolean).join(' à ')],
    ['Nationalité', d.nationalite],
    ["Pièce d'identité", d.numero_piece],
    ['Adresse', d.adresse],
  ].filter(([, valeur]) => valeur) as [string, string][]
})

useHead(() => ({ title: doctorant.value ? doctorant.value.nom_complet : 'Dossier doctorant' }))
</script>

<template>
  <SqueletteFiche v-if="status === 'pending' && !doctorant" />

  <EtatVide v-else-if="error" :icone="TriangleAlert" titre="Dossier inaccessible" :texte="messageErreur(error, 'Ce dossier est introuvable.')" />

  <div v-else-if="doctorant" class="grid gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-center gap-4">
        <span class="flex size-14 flex-none items-center justify-center rounded-full bg-brand-800 text-lg font-semibold text-white">{{ initiales(doctorant.nom_complet) }}</span>
        <div class="min-w-0">
          <h1 class="truncate text-2xl font-bold tracking-tight text-brand-900">{{ doctorant.nom_complet }}</h1>
          <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <span>{{ doctorant.matricule }}</span>
            <BadgeStatut :statut="doctorant.statut" :libelle="doctorant.statut_libelle" />
            <BadgeNiveau :inscription="doctorant.inscription_courante" />
          </div>
        </div>
      </div>
      <div v-if="doctorant.peut.modifier" class="flex flex-wrap gap-2">
        <Button variant="outline" as-child>
          <NuxtLink :to="`/doctorants/${doctorant.id}/modifier`"><Pencil /> Modifier</NuxtLink>
        </Button>
        <Button @click="ouvrirInscription()"><CalendarPlus /> Inscrire</Button>
      </div>
    </div>

    <div
      v-if="doctorant.inscription_courante?.derogation_en_attente"
      class="flex gap-3 rounded-lg border border-sun-300 bg-sun-50 p-4 text-sm text-sun-700"
    >
      <TriangleAlert class="size-5 flex-none" />
      <p>Dérogation en attente de décision pour {{ doctorant.inscription_courante.annee?.libelle }}. Aucune attestation ne pourra être délivrée avant la décision.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="grid content-start gap-6 lg:col-span-2">
        <Card>
          <CardHeader><CardTitle>Thèse</CardTitle></CardHeader>
          <CardContent class="grid gap-3 text-sm">
            <div>
              <p class="text-muted-foreground">Équipe d'accueil</p>
              <p class="font-medium">{{ doctorant.equipe ? `${doctorant.equipe.sigle}, ${doctorant.equipe.nom}` : 'Non renseignée' }}</p>
            </div>
            <div>
              <p class="text-muted-foreground">Sujet</p>
              <p class="font-medium">{{ doctorant.sujet_these || 'Non renseigné' }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle>Inscriptions</CardTitle>
            <Button v-if="doctorant.peut.modifier" variant="ghost" size="sm" @click="ouvrirInscription()"><CalendarPlus /> Ajouter</Button>
          </CardHeader>
          <CardContent>
            <p v-if="!doctorant.inscriptions?.length" class="text-sm text-muted-foreground">Aucune inscription enregistrée.</p>
            <ul v-else class="divide-y">
              <li v-for="inscription in doctorant.inscriptions" :key="inscription.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                <div class="min-w-28">
                  <p class="font-medium">{{ inscription.annee?.libelle }}</p>
                  <p class="text-xs text-muted-foreground">{{ inscription.type_libelle }}</p>
                </div>
                <BadgeNiveau :inscription="inscription" />
                <BadgeStatut :statut="inscription.statut" :libelle="inscription.statut_libelle" />
                <span v-if="inscription.montant_du !== undefined" class="text-sm tabular-nums">{{ formaterMontant(inscription.montant_du) }}</span>
                <span v-if="inscription.decision_derogation" class="w-full text-xs text-muted-foreground sm:w-auto">{{ inscription.decision_derogation }}</span>
                <Button v-if="doctorant.peut.modifier" variant="ghost" size="icon-sm" class="ml-auto" aria-label="Modifier l'inscription" @click="ouvrirInscription(inscription)">
                  <Pencil />
                </Button>
              </li>
            </ul>
          </CardContent>
        </Card>

        <PiecesJointes :doctorant-id="doctorant.id" :peut-ajouter="estSuperAdmin || utilisateur?.doctorant_id === doctorant.id" />
      </div>

      <div class="grid content-start gap-6">
        <Card>
          <CardHeader><CardTitle>Contact</CardTitle></CardHeader>
          <CardContent class="grid gap-3 text-sm">
            <a v-if="doctorant.email" :href="`mailto:${doctorant.email}`" class="flex items-center gap-2 break-all hover:underline"><Mail class="size-4 flex-none text-muted-foreground" />{{ doctorant.email }}</a>
            <a v-if="doctorant.telephone" :href="`tel:${doctorant.telephone}`" class="flex items-center gap-2 hover:underline"><Phone class="size-4 flex-none text-muted-foreground" />{{ doctorant.telephone }}</a>
          </CardContent>
        </Card>

        <Card v-if="identite.length">
          <CardHeader><CardTitle>Identité</CardTitle></CardHeader>
          <CardContent>
            <dl class="grid gap-3 text-sm">
              <div v-for="[libelle, valeur] in identite" :key="libelle">
                <dt class="text-muted-foreground">{{ libelle }}</dt>
                <dd class="font-medium">{{ valeur }}</dd>
              </div>
            </dl>
          </CardContent>
        </Card>

        <Card v-if="estSuperAdmin && doctorant.compte">
          <CardHeader><CardTitle>Compte</CardTitle></CardHeader>
          <CardContent class="grid gap-3 text-sm">
            <p>
              {{ doctorant.compte.derniere_connexion_le ? `Dernière connexion : ${formaterDateHeure(doctorant.compte.derniere_connexion_le)}` : "Le doctorant ne s'est jamais connecté." }}
            </p>
            <p>Annuaire du site : {{ doctorant.visible_annuaire ? 'visible' : 'non visible' }}</p>
            <p v-if="doctorant.observation" class="rounded-md bg-muted p-3">{{ doctorant.observation }}</p>
            <Button variant="outline" size="sm" :disabled="envoiLien" @click="renvoyerLien"><Send /> Renvoyer le lien d'accès</Button>
          </CardContent>
        </Card>

        <p v-if="estDoctorant" class="text-xs text-muted-foreground">Une information est inexacte ? Signalez-la à la scolarité de l'École.</p>
      </div>
    </div>

    <DialogueInscription v-if="doctorant.peut.modifier" v-model:open="dialogueInscription" :doctorant="doctorant" :inscription="inscriptionModifiee" @enregistree="refresh()" />
  </div>
</template>

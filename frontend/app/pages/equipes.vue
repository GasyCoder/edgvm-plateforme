<script setup lang="ts">
import { Loader2, Network, Pencil, Plus, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { Equipe, Ressource } from '~/types'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
useHead({ title: "Équipes d'accueil" })

const api = useApi()
const { estSuperAdmin } = useAuth()
const { data: equipes, status, refresh } = useAsyncData('equipes', () => api<Ressource<Equipe[]>>('/api/equipes').then(r => r.data), { lazy: true })

const ouvert = ref(false)
const modifiee = ref<Equipe | null>(null)
const formulaire = reactive({ sigle: '', nom: '', description: '' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

function ouvrir(equipe: Equipe | null = null) {
  modifiee.value = equipe
  Object.assign(formulaire, { sigle: equipe?.sigle ?? '', nom: equipe?.nom ?? '', description: equipe?.description ?? '' })
  erreurs.value = {}
  ouvert.value = true
}

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    await (modifiee.value
      ? api(`/api/equipes/${modifiee.value.id}`, { method: 'PUT', body: formulaire })
      : api('/api/equipes', { method: 'POST', body: formulaire }))
    toast.success(modifiee.value ? 'Équipe modifiée.' : 'Équipe créée.')
    ouvert.value = false
    clearNuxtData('ref-equipes')
    await refresh()
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

async function supprimer(equipe: Equipe) {
  if (!window.confirm(`Supprimer l'équipe ${equipe.sigle} ?`)) return
  try {
    await api(`/api/equipes/${equipe.id}`, { method: 'DELETE' })
    toast.success('Équipe supprimée.')
    clearNuxtData('ref-equipes')
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}
</script>

<template>
  <div>
    <EnTetePage titre="Équipes d'accueil" :description="estSuperAdmin ? 'Les sigles sont les mêmes que sur le site de l\'École.' : 'Les équipes dont vous êtes membre.'">
      <template v-if="estSuperAdmin" #actions>
        <Button @click="ouvrir()"><Plus /> Nouvelle équipe</Button>
      </template>
    </EnTetePage>

    <SqueletteListe v-if="status === 'pending' && !equipes" :lignes="4" />
    <EtatVide v-else-if="equipes && !equipes.length" :icone="Network" titre="Aucune équipe" />

    <div v-else-if="equipes" class="grid gap-4 md:grid-cols-2">
      <Card v-for="equipe in equipes" :key="equipe.id" class="gap-3">
        <CardHeader class="flex flex-row items-start justify-between gap-3">
          <div class="grid min-w-0 gap-1">
            <CardTitle class="text-brand-800">{{ equipe.sigle }}</CardTitle>
            <CardDescription>{{ equipe.nom }}</CardDescription>
          </div>
          <div v-if="estSuperAdmin" class="flex flex-none">
            <Button variant="ghost" size="icon-sm" :aria-label="`Modifier ${equipe.sigle}`" @click="ouvrir(equipe)"><Pencil /></Button>
            <Button v-if="!equipe.doctorants_count" variant="ghost" size="icon-sm" class="text-destructive" :aria-label="`Supprimer ${equipe.sigle}`" @click="supprimer(equipe)"><Trash2 /></Button>
          </div>
        </CardHeader>
        <CardContent class="grid gap-2 text-sm">
          <NuxtLink :to="`/doctorants?equipe=${equipe.id}`" class="font-medium text-brand-700 hover:underline">
            {{ equipe.doctorants_count }} doctorant{{ (equipe.doctorants_count ?? 0) > 1 ? 's' : '' }}
          </NuxtLink>
          <p v-if="equipe.membres?.length" class="text-muted-foreground">
            Membres : {{ equipe.membres.map(membre => membre.name).join(', ') }}
          </p>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="ouvert">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ modifiee ? `Modifier ${modifiee.sigle}` : 'Nouvelle équipe' }}</DialogTitle>
          <DialogDescription>Gardez le même sigle que sur le site de l'École.</DialogDescription>
        </DialogHeader>
        <form id="form-equipe" class="grid gap-4" novalidate @submit.prevent="enregistrer">
          <ChampFormulaire libelle="Sigle" pour="sigle" :erreur="erreurs.sigle" requis>
            <Input id="sigle" v-model="formulaire.sigle" placeholder="EAD 9" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Nom" pour="nom-equipe" :erreur="erreurs.nom" requis>
            <Input id="nom-equipe" v-model="formulaire.nom" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Description" pour="description" :erreur="erreurs.description">
            <Textarea id="description" v-model="formulaire.description" rows="3" />
          </ChampFormulaire>
        </form>
        <DialogFooter>
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-equipe" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

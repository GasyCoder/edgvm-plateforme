<script setup lang="ts">
import { Loader2, Pencil, Search, Send, UserPlus } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { Compte, Pagine } from '~/types'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Comptes' })

const api = useApi()
const { utilisateur } = useAuth()
const { equipes } = useReferentiels()
const onglet = ref<'personnel' | 'doctorant'>('personnel')
const recherche = ref('')
const terme = ref('')
const page = ref(1)
watch(recherche, useDebounceFn((valeur: string) => { terme.value = valeur.trim(); page.value = 1 }, 350))
watch(onglet, () => { page.value = 1 })

const { data, status, refresh } = useAsyncData('comptes', () => api<Pagine<Compte>>('/api/comptes', {
  query: { role: onglet.value === 'doctorant' ? 'doctorant' : undefined, recherche: terme.value || undefined, page: page.value },
}), { lazy: true, watch: [onglet, terme, page] })

const ouvert = ref(false)
const modifie = ref<Compte | null>(null)
const vide = () => ({ name: '', email: '', telephone: '', role: 'membre_ead', actif: true, equipes: [] as number[], envoyer_invitation: true })
const formulaire = reactive(vide())
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

function ouvrir(compte: Compte | null = null) {
  modifie.value = compte
  Object.assign(formulaire, compte
    ? { name: compte.name, email: compte.email, telephone: compte.telephone ?? '', role: compte.role, actif: compte.actif, equipes: compte.equipes?.map(e => e.id) ?? [], envoyer_invitation: false }
    : vide())
  erreurs.value = {}
  ouvert.value = true
}

function basculerEquipe(id: number, coche: boolean | 'indeterminate') {
  formulaire.equipes = coche === true ? [...new Set([...formulaire.equipes, id])] : formulaire.equipes.filter(e => e !== id)
}

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  const estDoctorant = modifie.value?.role === 'doctorant'
  const corps = { ...formulaire, telephone: formulaire.telephone || null, role: estDoctorant ? undefined : formulaire.role }
  try {
    await (modifie.value
      ? api(`/api/comptes/${modifie.value.id}`, { method: 'PUT', body: corps })
      : api('/api/comptes', { method: 'POST', body: corps }))
    toast.success(modifie.value ? 'Compte modifié.' : `Compte créé${formulaire.envoyer_invitation ? ', lien d\'accès envoyé' : ''}.`)
    ouvert.value = false
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

async function envoyerLien(compte: Compte) {
  try {
    const reponse = await api<{ message: string }>(`/api/comptes/${compte.id}/lien-acces`, { method: 'POST' })
    toast.success(reponse.message)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}
</script>

<template>
  <div>
    <EnTetePage titre="Comptes" description="Accès à la plateforme. Les comptes des doctorants se créent avec leur dossier.">
      <template #actions>
        <Button @click="ouvrir()"><UserPlus /> Nouveau compte</Button>
      </template>
    </EnTetePage>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <Tabs v-model="onglet">
        <TabsList>
          <TabsTrigger value="personnel">Administration et équipes</TabsTrigger>
          <TabsTrigger value="doctorant">Doctorants</TabsTrigger>
        </TabsList>
      </Tabs>
      <div class="relative sm:w-72">
        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="recherche" type="search" placeholder="Nom ou e-mail" class="bg-white pl-9" aria-label="Rechercher un compte" />
      </div>
    </div>

    <SqueletteListe v-if="status === 'pending' && !data" />
    <EtatVide v-else-if="data && !data.data.length" titre="Aucun compte" />
    <template v-else-if="data">
      <ul class="overflow-hidden rounded-lg border bg-white">
        <li v-for="compte in data.data" :key="compte.id" class="flex flex-wrap items-center gap-3 border-b p-4 last:border-b-0">
          <span class="flex size-9 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800">{{ initiales(compte.name) }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium">{{ compte.name }} <span v-if="!compte.actif" class="ml-1 rounded bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground">Désactivé</span></p>
            <p class="truncate text-xs text-muted-foreground">{{ compte.email }}</p>
          </div>
          <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <span class="rounded-md bg-muted px-2 py-0.5 text-xs">{{ compte.role_libelle }}</span>
            <span v-for="equipe in compte.equipes" :key="equipe.id" class="rounded-md bg-brand-50 px-2 py-0.5 text-xs text-brand-800">{{ equipe.sigle }}</span>
            <span class="text-xs text-muted-foreground">{{ compte.derniere_connexion_le ? `Vu le ${formaterDateHeure(compte.derniere_connexion_le)}` : 'Jamais connecté' }}</span>
            <div class="ml-auto flex">
              <Button variant="ghost" size="icon-sm" :aria-label="`Envoyer un lien d'accès à ${compte.name}`" title="Envoyer un lien d'accès" @click="envoyerLien(compte)"><Send /></Button>
              <Button variant="ghost" size="icon-sm" :aria-label="`Modifier ${compte.name}`" @click="ouvrir(compte)"><Pencil /></Button>
            </div>
          </div>
        </li>
      </ul>
      <PaginationSimple :meta="data.meta" @page="numero => page = numero" />
    </template>

    <Dialog v-model:open="ouvert">
      <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ modifie ? 'Modifier le compte' : 'Nouveau compte' }}</DialogTitle>
          <DialogDescription>{{ modifie?.role === 'doctorant' ? 'Compte de doctorant : le rôle ne change pas.' : 'Administration de l\'École ou membre d\'une équipe d\'accueil.' }}</DialogDescription>
        </DialogHeader>
        <form id="form-compte" class="grid gap-4" novalidate @submit.prevent="enregistrer">
          <ChampFormulaire libelle="Nom" pour="compte-nom" :erreur="erreurs.name" requis>
            <Input id="compte-nom" v-model="formulaire.name" placeholder="Pr RAKOTO Jean" />
          </ChampFormulaire>
          <div class="grid gap-4 sm:grid-cols-2">
            <ChampFormulaire libelle="Adresse e-mail" pour="compte-email" :erreur="erreurs.email" requis>
              <Input id="compte-email" v-model="formulaire.email" type="email" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Téléphone" pour="compte-tel" :erreur="erreurs.telephone">
              <Input id="compte-tel" v-model="formulaire.telephone" type="tel" />
            </ChampFormulaire>
          </div>
          <ChampFormulaire v-if="modifie?.role !== 'doctorant'" libelle="Rôle" pour="compte-role" :erreur="erreurs.role" requis>
            <Select v-model="formulaire.role" :disabled="modifie?.id === utilisateur?.id">
              <SelectTrigger id="compte-role" class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="membre_ead">Membre d'équipe d'accueil</SelectItem>
                <SelectItem value="super_admin">Administration (accès complet)</SelectItem>
              </SelectContent>
            </Select>
          </ChampFormulaire>
          <fieldset v-if="formulaire.role === 'membre_ead' && modifie?.role !== 'doctorant'" class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Équipes d'accueil<span class="text-destructive">*</span></legend>
            <div class="grid max-h-48 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2">
              <div v-for="equipe in equipes" :key="equipe.id" class="flex items-center gap-2">
                <Checkbox :id="`eq-${equipe.id}`" :model-value="formulaire.equipes.includes(equipe.id)" @update:model-value="coche => basculerEquipe(equipe.id, coche)" />
                <Label :for="`eq-${equipe.id}`" class="font-normal" :title="equipe.nom">{{ equipe.sigle }}</Label>
              </div>
            </div>
            <p v-if="erreurs.equipes" class="text-sm text-destructive">{{ erreurs.equipes }}</p>
          </fieldset>
          <div v-if="modifie" class="flex items-center justify-between gap-4 rounded-lg border p-4">
            <Label for="compte-actif">Compte actif</Label>
            <Switch id="compte-actif" v-model="formulaire.actif" :disabled="modifie.id === utilisateur?.id" />
          </div>
          <div v-else class="flex items-center justify-between gap-4 rounded-lg border p-4">
            <Label for="compte-invitation">Envoyer le lien d'accès par e-mail</Label>
            <Switch id="compte-invitation" v-model="formulaire.envoyer_invitation" />
          </div>
          <p v-if="erreurs.actif" class="text-sm text-destructive">{{ erreurs.actif }}</p>
        </form>
        <DialogFooter>
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-compte" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

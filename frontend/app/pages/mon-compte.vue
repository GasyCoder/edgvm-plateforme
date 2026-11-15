<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'

useHead({ title: 'Mon compte' })

const api = useApi()
const { utilisateur } = useAuth()
const formulaire = reactive({ mot_de_passe_actuel: '', password: '', password_confirmation: '' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

async function changer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const reponse = await api<{ message: string }>('/api/moi/mot-de-passe', { method: 'PUT', body: formulaire })
    toast.success(reponse.message)
    Object.assign(formulaire, { mot_de_passe_actuel: '', password: '', password_confirmation: '' })
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <EnTetePage titre="Mon compte" />
    <div v-if="utilisateur" class="grid gap-6">
      <Card>
        <CardHeader>
          <CardTitle>{{ utilisateur.name }}</CardTitle>
          <CardDescription>{{ utilisateur.role_libelle }}</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3 text-sm">
          <div><p class="text-muted-foreground">Adresse e-mail (identifiant)</p><p class="font-medium">{{ utilisateur.email }}</p></div>
          <div><p class="text-muted-foreground">Téléphone</p><p class="font-medium">{{ utilisateur.telephone || 'Non renseigné' }}</p></div>
          <div v-if="utilisateur.equipes.length">
            <p class="text-muted-foreground">Équipes d'accueil</p>
            <p class="font-medium">{{ utilisateur.equipes.map(equipe => equipe.sigle).join(', ') }}</p>
          </div>
          <p class="text-xs text-muted-foreground">Pour corriger ces informations, contactez la scolarité de l'École.</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Changer de mot de passe</CardTitle>
          <CardDescription>Au moins 10 caractères, avec des lettres et des chiffres.</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="grid gap-4" novalidate @submit.prevent="changer">
            <ChampFormulaire libelle="Mot de passe actuel" pour="actuel" :erreur="erreurs.mot_de_passe_actuel">
              <Input id="actuel" v-model="formulaire.mot_de_passe_actuel" type="password" autocomplete="current-password" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Nouveau mot de passe" pour="nouveau" :erreur="erreurs.password">
              <Input id="nouveau" v-model="formulaire.password" type="password" autocomplete="new-password" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Confirmer le nouveau mot de passe" pour="confirmation">
              <Input id="confirmation" v-model="formulaire.password_confirmation" type="password" autocomplete="new-password" />
            </ChampFormulaire>
            <div class="flex justify-end">
              <Button type="submit" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Changer le mot de passe</Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

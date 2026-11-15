<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

definePageMeta({ layout: 'connexion' })

const route = useRoute()
const api = useApi()
const premierAcces = route.query.premier === '1'
useHead({ title: premierAcces ? 'Choisir mon mot de passe' : 'Nouveau mot de passe' })

const formulaire = reactive({
  token: String(route.params.token),
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  try {
    await preparerSession()
    const reponse = await api<{ message: string }>('/api/mot-de-passe/reinitialiser', { method: 'POST', body: formulaire })
    toast.success(reponse.message)
    await navigateTo('/connexion')
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) {
      toast.error(messageErreur(e))
    }
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold tracking-tight text-brand-900">{{ premierAcces ? 'Bienvenue sur la plateforme' : 'Nouveau mot de passe' }}</h1>
      <p class="text-sm text-muted-foreground">
        {{ premierAcces ? 'Choisissez le mot de passe de votre compte.' : 'Choisissez un nouveau mot de passe.' }}
        Au moins 10 caractères, avec des lettres et des chiffres.
      </p>
    </div>

    <form class="grid gap-5" novalidate @submit.prevent="enregistrer">
      <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreurs.email">
        <Input id="email" v-model="formulaire.email" type="email" autocomplete="username" required />
      </ChampFormulaire>
      <ChampFormulaire libelle="Mot de passe" pour="password" :erreur="erreurs.password">
        <Input id="password" v-model="formulaire.password" type="password" autocomplete="new-password" required autofocus />
      </ChampFormulaire>
      <ChampFormulaire libelle="Confirmer le mot de passe" pour="password_confirmation">
        <Input id="password_confirmation" v-model="formulaire.password_confirmation" type="password" autocomplete="new-password" required />
      </ChampFormulaire>
      <Button type="submit" size="lg" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        Enregistrer le mot de passe
      </Button>
    </form>
  </div>
</template>

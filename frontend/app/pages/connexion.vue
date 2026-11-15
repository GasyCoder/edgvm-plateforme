<script setup lang="ts">
import { CircleAlert, Eye, EyeOff, Loader2 } from '@lucide/vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

definePageMeta({ layout: 'connexion' })
useHead({ title: 'Connexion, plateforme EDGVM' })

const route = useRoute()
const { connecter } = useAuth()
const formulaire = reactive({ email: '', password: '', remember: false })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const voirMotDePasse = ref(false)
const sessionExpiree = computed(() => route.query.session === 'expiree')

async function seConnecter() {
  envoi.value = true
  erreurs.value = {}
  try {
    await connecter(formulaire)
    const retour = typeof route.query.retour === 'string' && route.query.retour.startsWith('/') ? route.query.retour : '/'
    await navigateTo(retour)
  }
  catch (erreur) {
    erreurs.value = erreursDeValidation(erreur)
    if (!Object.keys(erreurs.value).length) {
      erreurs.value = { email: messageErreur(erreur) }
    }
    formulaire.password = ''
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold tracking-tight text-brand-900">Connexion</h1>
      <p class="text-sm text-muted-foreground">Doctorants, membres des équipes d'accueil et scolarité de l'École.</p>
    </div>

    <Alert v-if="sessionExpiree && !erreurs.email">
      <CircleAlert />
      <AlertDescription>Votre session a expiré. Reconnectez-vous.</AlertDescription>
    </Alert>

    <form class="grid gap-5" novalidate @submit.prevent="seConnecter">
      <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreurs.email">
        <Input id="email" v-model="formulaire.email" type="email" autocomplete="username" autofocus required :aria-invalid="!!erreurs.email" />
      </ChampFormulaire>
      <ChampFormulaire libelle="Mot de passe" pour="password" :erreur="erreurs.password">
        <div class="relative">
          <Input id="password" v-model="formulaire.password" :type="voirMotDePasse ? 'text' : 'password'" autocomplete="current-password" required class="pr-10" />
          <button
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
            :aria-label="voirMotDePasse ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
            @click="voirMotDePasse = !voirMotDePasse"
          >
            <EyeOff v-if="voirMotDePasse" class="size-4" />
            <Eye v-else class="size-4" />
          </button>
        </div>
      </ChampFormulaire>
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Checkbox id="remember" v-model="formulaire.remember" />
          <Label for="remember" class="font-normal">Rester connecté</Label>
        </div>
        <NuxtLink to="/mot-de-passe-oublie" class="text-sm font-medium text-brand-700 hover:underline">Mot de passe oublié ?</NuxtLink>
      </div>
      <Button type="submit" size="lg" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        Se connecter
      </Button>
    </form>

    <p class="text-center text-xs text-muted-foreground">Pas encore de compte ? La scolarité de l'École le crée et vous envoie un lien par e-mail.</p>
  </div>
</template>

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
  <div class="space-y-6">
    <div class="space-y-2">
      <h1 class="text-xl font-bold tracking-tight text-slate-700">Connexion</h1>
      <p class="text-sm leading-6 text-slate-400">Doctorants, membres des équipes d'accueil et scolarité de l'École.</p>
    </div>

    <Alert v-if="sessionExpiree && !erreurs.email">
      <CircleAlert />
      <AlertDescription>Votre session a expiré. Reconnectez-vous.</AlertDescription>
    </Alert>

    <form class="grid gap-5" novalidate @submit.prevent="seConnecter">
      <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreurs.email">
        <Input id="email" v-model="formulaire.email" type="email" autocomplete="username" autofocus required class="h-11" placeholder="votre.adresse@exemple.mg" :aria-invalid="!!erreurs.email" />
      </ChampFormulaire>
      <div class="grid gap-2">
        <div class="flex items-center justify-between">
          <Label for="password">Mot de passe</Label>
          <NuxtLink to="/mot-de-passe-oublie" class="text-xs font-medium text-brand-600 hover:text-brand-700">Mot de passe oublié ?</NuxtLink>
        </div>
        <div class="relative">
          <Input id="password" v-model="formulaire.password" :type="voirMotDePasse ? 'text' : 'password'" autocomplete="current-password" required class="h-11 pr-11" placeholder="Votre mot de passe" />
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
        <p v-if="erreurs.password" class="text-sm text-destructive">{{ erreurs.password }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Checkbox id="remember" v-model="formulaire.remember" />
        <Label for="remember" class="font-normal text-slate-600">Rester connecté</Label>
      </div>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        Se connecter
      </Button>
    </form>

    <p class="text-center text-sm text-slate-400">Pas encore de compte ? La scolarité de l'École le crée et vous envoie un lien par e-mail.</p>
  </div>
</template>

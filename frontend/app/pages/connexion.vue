<script setup lang="ts">
import { ArrowLeft, CircleAlert, Eye, EyeOff, KeyRound, Loader2, ShieldCheck } from '@lucide/vue'
import type { FetchError } from 'ofetch'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

definePageMeta({ layout: 'connexion' })
useHead({ title: 'Connexion' })

const route = useRoute()
const { connecter, verifierDeuxFacteurs } = useAuth()
/* Dans l'application du téléphone, on reste connecté : pas besoin de la case */
const appMobile = estAppMobile()
const formulaire = reactive({ email: '', password: '', remember: appMobile })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const voirMotDePasse = ref(false)
const sessionExpiree = computed(() => route.query.session === 'expiree')

const allerAuRetour = () => navigateTo(typeof route.query.retour === 'string' && route.query.retour.startsWith('/') ? route.query.retour : '/')

/* Double authentification : après le mot de passe, code de l'appli (6 chiffres) ou code de secours */
const deuxFacteurs = ref(false)
const codeSecours = ref(false)
const code = ref('')
const champCode = ref<{ focus: () => void, vider: () => void } | null>(null)

async function seConnecter() {
  envoi.value = true
  erreurs.value = {}
  try {
    if (await connecter(formulaire) === 'deux_facteurs') {
      deuxFacteurs.value = true
      code.value = ''
      await nextTick()
      champCode.value?.focus()
      return
    }
    await allerAuRetour()
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

async function verifierCode(saisi?: string) {
  const valeur = (typeof saisi === 'string' ? saisi : code.value).trim()
  if (!valeur || envoi.value) return
  envoi.value = true
  erreurs.value = {}
  try {
    await verifierDeuxFacteurs(valeur)
    await allerAuRetour()
  }
  catch (erreur) {
    if ((erreur as FetchError<{ recommencer?: boolean }>).data?.recommencer) {
      revenir()
      erreurs.value = { email: messageErreur(erreur) }
      return
    }
    erreurs.value = erreursDeValidation(erreur)
    if (!Object.keys(erreurs.value).length) erreurs.value = { code: messageErreur(erreur) }
    code.value = ''
  }
  finally {
    envoi.value = false
  }
  /* Les cases sont désactivées pendant l'envoi : on remet le curseur une fois réactivées */
  await nextTick()
  champCode.value?.focus()
}

function revenir() {
  deuxFacteurs.value = false
  codeSecours.value = false
  formulaire.password = ''
  code.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <div v-if="deuxFacteurs" class="space-y-2">
      <span class="flex size-11 items-center justify-center rounded-full bg-sun-100 text-sun-700"><ShieldCheck class="size-6" aria-hidden="true" /></span>
      <h1 class="text-xl font-bold tracking-tight text-slate-700">Double authentification</h1>
      <p class="text-sm leading-6 text-slate-400">
        {{ codeSecours ? 'Saisissez l\'un de vos codes de secours (chacun ne sert qu\'une fois).' : 'Ouvrez votre appli d\'authentification et saisissez le code à 6 chiffres affiché pour la plateforme.' }}
      </p>
    </div>
    <div v-else class="space-y-2">
      <h1 class="text-xl font-bold tracking-tight text-slate-700">Connexion</h1>
      <p class="text-sm leading-6 text-slate-400">Doctorants, membres des équipes d'accueil et scolarité de l'École.</p>
    </div>

    <!-- Deuxième étape : code de l'appli ou code de secours -->
    <form v-if="deuxFacteurs" class="grid gap-5" novalidate @submit.prevent="verifierCode()">
      <template v-if="!codeSecours">
        <ChampCode ref="champCode" v-model="code" :longueur="6" :invalide="!!erreurs.code" :desactive="envoi" @complet="verifierCode" />
      </template>
      <ChampFormulaire v-else libelle="Code de secours" pour="code-secours">
        <Input id="code-secours" v-model="code" autocomplete="off" autofocus class="h-11 text-center font-mono text-lg uppercase tracking-widest" placeholder="XXXX-XXXX" :aria-invalid="!!erreurs.code" />
      </ChampFormulaire>
      <p v-if="erreurs.code" class="text-center text-sm text-destructive" role="alert">{{ erreurs.code }}</p>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi || !code.trim()">
        <Loader2 v-if="envoi" class="animate-spin" /> Vérifier et se connecter
      </Button>
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <button type="button" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-brand-700" @click="revenir"><ArrowLeft class="size-4" /> Retour</button>
        <button type="button" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline" @click="codeSecours = !codeSecours; code = ''; erreurs = {}">
          <KeyRound class="size-4" /> {{ codeSecours ? 'Utiliser le code de l\'appli' : 'Utiliser un code de secours' }}
        </button>
      </div>
    </form>

    <Alert v-if="sessionExpiree && !erreurs.email && !deuxFacteurs">
      <CircleAlert />
      <AlertDescription>Votre session a expiré. Reconnectez-vous.</AlertDescription>
    </Alert>

    <form v-if="!deuxFacteurs" class="grid gap-5" novalidate @submit.prevent="seConnecter">
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
      <div v-if="!appMobile" class="flex items-center gap-2">
        <Checkbox id="remember" v-model="formulaire.remember" />
        <Label for="remember" class="font-normal text-slate-600">Rester connecté</Label>
      </div>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        Se connecter
      </Button>
    </form>

    <div v-if="!deuxFacteurs" class="rounded-md border border-sun-200 bg-sun-50 px-4 py-3 text-center text-sm text-slate-500">
      Première connexion ?
      <NuxtLink to="/creer-mon-acces" class="font-semibold text-brand-700 hover:underline">Créer mon accès</NuxtLink>
      <span class="block text-xs text-slate-400">Avec votre e-mail ou votre numéro, si la scolarité vous a enregistré.</span>
    </div>
  </div>
</template>

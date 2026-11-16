<script setup lang="ts">
import { ArrowLeft, CircleAlert, Loader2, Mail, Phone, RotateCw } from '@lucide/vue'
import type { FetchError } from 'ofetch'
import { toast } from 'vue-sonner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { Utilisateur } from '~/types'

/*
 * « Créer mon accès » : un doctorant ou un membre d'équipe déjà enregistré par la scolarité saisit son e-mail
 * ou son numéro, reçoit un code à 4 chiffres à son adresse e-mail, puis choisit son mot de passe.
 */
definePageMeta({ layout: 'connexion' })
useHead({ title: 'Créer mon accès' })

interface Demande { demande: string, canal: 'email', destination: string, renvoyer_dans: number, expire_dans: number }

const etape = ref<1 | 2 | 3>(1)
const identifiant = ref('')
const demande = ref<Demande | null>(null)
const code = ref('')
const jeton = ref('')
const nom = ref('')
const motDePasse = reactive({ password: '', password_confirmation: '' })
const erreurs = ref<Record<string, string>>({})
const dejaActive = ref('')
const envoi = ref(false)
const champCode = ref<{ focus: () => void, vider: () => void } | null>(null)

/* Compte à rebours avant de pouvoir redemander un code */
const attente = ref(0)
let minuterie: ReturnType<typeof setInterval> | undefined
function demarrerAttente(secondes: number) {
  attente.value = secondes
  clearInterval(minuterie)
  minuterie = setInterval(() => { attente.value = Math.max(0, attente.value - 1); if (!attente.value) clearInterval(minuterie) }, 1000)
}
onBeforeUnmount(() => clearInterval(minuterie))

const parTelephone = computed(() => identifiant.value.trim() !== '' && !identifiant.value.includes('@'))

async function appel<T>(chemin: string, corps: Record<string, unknown>): Promise<T | null> {
  envoi.value = true
  erreurs.value = {}
  try {
    await preparerSession()
    return await useApi()<T>(chemin, { method: 'POST', body: corps })
  }
  catch (e) {
    const reponse = e as FetchError<{ code?: string, message?: string, renvoyer_dans?: number }>
    if (reponse.statusCode === 409 && reponse.data?.code === 'deja_active') dejaActive.value = reponse.data.message ?? ''
    else if (reponse.statusCode === 429 && reponse.data?.renvoyer_dans) demarrerAttente(reponse.data.renvoyer_dans)
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length && reponse.statusCode !== 409) toast.error(messageErreur(e))
    return null
  }
  finally {
    envoi.value = false
  }
}

async function demander() {
  dejaActive.value = ''
  const reponse = await appel<{ data: Demande }>('/api/activation/demande', { identifiant: identifiant.value })
  if (!reponse) return
  demande.value = reponse.data
  code.value = ''
  etape.value = 2
  demarrerAttente(reponse.data.renvoyer_dans)
  await nextTick()
  champCode.value?.focus()
}

async function renvoyer() {
  const reponse = await appel<{ data: Demande, message: string }>('/api/activation/renvoyer', { demande: demande.value?.demande })
  if (!reponse) return
  demande.value = reponse.data
  demarrerAttente(reponse.data.renvoyer_dans)
  champCode.value?.vider()
  toast.success(reponse.message)
}

async function verifier(saisi?: string) {
  const valeur = typeof saisi === 'string' ? saisi : code.value
  if (valeur.length !== 4 || envoi.value) return
  const reponse = await appel<{ data: { jeton: string, nom: string } }>('/api/activation/verifier', { demande: demande.value?.demande, code: valeur })
  if (!reponse) {
    champCode.value?.vider()
    return
  }
  jeton.value = reponse.data.jeton
  nom.value = reponse.data.nom
  etape.value = 3
}

async function terminer() {
  const reponse = await appel<{ data: Utilisateur }>('/api/activation/terminer', { demande: demande.value?.demande, jeton: jeton.value, ...motDePasse })
  if (!reponse) return
  useState<Utilisateur | null>('utilisateur').value = reponse.data
  toast.success('Votre accès est créé. Bienvenue sur la plateforme.')
  await navigateTo('/')
}

const pretMotDePasse = computed(() => motDePasse.password.length >= 10 && /\p{L}/u.test(motDePasse.password) && /\d/.test(motDePasse.password)
  && motDePasse.password === motDePasse.password_confirmation)
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-wider text-sun-700">Étape {{ etape }} sur 3</p>
      <h1 class="text-xl font-bold tracking-tight text-slate-700">
        {{ etape === 1 ? 'Créer mon accès' : etape === 2 ? 'Saisissez le code reçu' : 'Choisissez votre mot de passe' }}
      </h1>
      <p class="text-sm leading-6 text-slate-400">
        <template v-if="etape === 1">Pour les doctorants et les membres des équipes d'accueil déjà enregistrés par la scolarité. Indiquez votre adresse e-mail ou votre numéro de téléphone.</template>
        <template v-else-if="etape === 2 && demande">
          Un code à 4 chiffres vient d'être envoyé par e-mail à
          <span class="font-semibold text-slate-600">{{ demande.destination }}</span>. Il est valable 10 minutes.
        </template>
        <template v-else>Bonjour {{ nom }}. Ce mot de passe vous servira à vous connecter, avec votre adresse e-mail.</template>
      </p>
      <div class="flex gap-1.5 pt-1" aria-hidden="true">
        <span v-for="n in 3" :key="n" class="h-1 flex-1 rounded-full" :class="n <= etape ? 'bg-sun-400' : 'bg-gray-200'" />
      </div>
    </div>

    <!-- 1. E-mail ou numéro -->
    <form v-if="etape === 1" class="grid gap-5" novalidate @submit.prevent="demander">
      <ChampFormulaire libelle="Adresse e-mail ou numéro de téléphone" pour="identifiant" :erreur="erreurs.identifiant">
        <div class="relative">
          <component :is="parTelephone ? Phone : Mail" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
          <Input id="identifiant" v-model="identifiant" type="text" autocomplete="username" autofocus class="h-11 pl-9" placeholder="votre.adresse@exemple.mg ou 034 12 345 67" :aria-invalid="!!erreurs.identifiant" />
        </div>
      </ChampFormulaire>
      <p class="-mt-2 text-xs text-slate-400">{{ parTelephone ? 'Le code sera envoyé à l\'adresse e-mail enregistrée par la scolarité pour ce numéro.' : 'Le code vous sera envoyé par e-mail.' }}</p>
      <Alert v-if="dejaActive">
        <CircleAlert />
        <AlertDescription>
          {{ dejaActive }}
          <span class="mt-2 flex gap-4"><NuxtLink to="/connexion" class="font-semibold text-brand-700 hover:underline">Se connecter</NuxtLink><NuxtLink to="/mot-de-passe-oublie" class="font-semibold text-brand-700 hover:underline">Mot de passe oublié</NuxtLink></span>
        </AlertDescription>
      </Alert>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi || !identifiant.trim()">
        <Loader2 v-if="envoi" class="animate-spin" /> Recevoir le code
      </Button>
    </form>

    <!-- 2. Code -->
    <form v-else-if="etape === 2" class="grid gap-5" novalidate @submit.prevent="verifier()">
      <ChampCode ref="champCode" v-model="code" :invalide="!!erreurs.code" :desactive="envoi" @complet="verifier" />
      <p v-if="erreurs.code" class="text-center text-sm text-destructive" role="alert">{{ erreurs.code }}</p>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi || code.length !== 4">
        <Loader2 v-if="envoi" class="animate-spin" /> Vérifier le code
      </Button>
      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <button type="button" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-brand-700" @click="etape = 1; erreurs = {}"><ArrowLeft class="size-4" /> Modifier</button>
        <button type="button" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline disabled:text-slate-400 disabled:no-underline" :disabled="attente > 0 || envoi" @click="renvoyer">
          <RotateCw class="size-4" /> {{ attente > 0 ? `Nouveau code dans ${attente} s` : 'Renvoyer un code' }}
        </button>
      </div>
    </form>

    <!-- 3. Mot de passe -->
    <form v-else class="grid gap-5" novalidate @submit.prevent="terminer">
      <ChampFormulaire libelle="Mot de passe" pour="nouveau" :erreur="erreurs.password">
        <ChampMotDePasse id="nouveau" v-model="motDePasse.password" autocomplete="new-password" :invalide="!!erreurs.password" />
      </ChampFormulaire>
      <ChampFormulaire libelle="Confirmer le mot de passe" pour="confirmation">
        <ChampMotDePasse id="confirmation" v-model="motDePasse.password_confirmation" autocomplete="new-password" />
      </ChampFormulaire>
      <ReglesMotDePasse :mot-de-passe="motDePasse.password" :confirmation="motDePasse.password_confirmation" />
      <Button type="submit" size="lg" class="w-full" :disabled="envoi || !pretMotDePasse">
        <Loader2 v-if="envoi" class="animate-spin" /> Créer mon accès et me connecter
      </Button>
    </form>

    <p class="text-center text-sm text-slate-400">Déjà un accès ? <NuxtLink to="/connexion" class="font-medium text-brand-700 hover:underline">Se connecter</NuxtLink></p>
  </div>
</template>

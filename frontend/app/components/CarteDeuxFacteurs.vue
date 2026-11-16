<script setup lang="ts">
import { Check, Copy, Download, KeyRound, Loader2, ShieldCheck, ShieldOff, Smartphone, TriangleAlert } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'

/**
 * Double authentification dans « Mon compte » : activation par QR code (Google Authenticator, Microsoft Authenticator...),
 * codes de secours montrés une seule fois, nouveaux codes et désactivation avec le mot de passe.
 */
interface Etat { active: boolean, active_le: string | null, codes_secours_restants: number }
interface Preparation { qr: string, cle: string, uri: string }

const api = useApi()
const { utilisateur } = useAuth()

const etat = ref<Etat | null>(null)
const preparation = ref<Preparation | null>(null)
const codesSecours = ref<string[]>([])
const code = ref('')
const champCode = ref<{ focus: () => void } | null>(null)
const action = ref<'codes' | 'desactiver' | null>(null)
const motDePasse = ref('')
const erreur = ref('')
const envoi = ref(false)
const cleCopiee = ref(false)

async function charger() {
  try {
    etat.value = (await api<{ data: Etat }>('/api/moi/deux-facteurs')).data
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
}
onMounted(charger)

function majUtilisateur(active: boolean) {
  if (utilisateur.value) utilisateur.value.deux_facteurs = active
}

async function commencer() {
  envoi.value = true
  erreur.value = ''
  try {
    preparation.value = (await api<{ data: Preparation }>('/api/moi/deux-facteurs/preparer', { method: 'POST' })).data
    code.value = ''
    await nextTick()
    champCode.value?.focus()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

async function confirmer(saisi?: string) {
  const valeur = typeof saisi === 'string' ? saisi : code.value
  if (valeur.length !== 6 || envoi.value) return
  envoi.value = true
  erreur.value = ''
  try {
    const reponse = await api<{ data: { codes_secours: string[] }, message: string }>('/api/moi/deux-facteurs/confirmer', { method: 'POST', body: { code: valeur } })
    codesSecours.value = reponse.data.codes_secours
    preparation.value = null
    majUtilisateur(true)
    toast.success(reponse.message)
    await charger()
  }
  catch (e) {
    erreur.value = messageErreur(e)
    code.value = ''
  }
  finally {
    envoi.value = false
  }
  if (preparation.value) {
    await nextTick()
    champCode.value?.focus()
  }
}

function ouvrir(choix: 'codes' | 'desactiver') {
  action.value = action.value === choix ? null : choix
  motDePasse.value = ''
  erreur.value = ''
}

async function valider() {
  envoi.value = true
  erreur.value = ''
  try {
    if (action.value === 'codes') {
      const reponse = await api<{ data: { codes_secours: string[] }, message: string }>('/api/moi/deux-facteurs/codes-secours', { method: 'POST', body: { password: motDePasse.value } })
      codesSecours.value = reponse.data.codes_secours
      toast.success(reponse.message)
    }
    else {
      const reponse = await api<{ message: string }>('/api/moi/deux-facteurs', { method: 'DELETE', body: { password: motDePasse.value } })
      majUtilisateur(false)
      codesSecours.value = []
      toast.success(reponse.message)
    }
    action.value = null
    motDePasse.value = ''
    await charger()
  }
  catch (e) {
    erreur.value = erreursDeValidation(e).password ?? messageErreur(e)
  }
  finally {
    envoi.value = false
  }
}

async function copier(texte: string, cle = false) {
  try {
    await navigator.clipboard.writeText(texte)
    if (cle) {
      cleCopiee.value = true
      setTimeout(() => { cleCopiee.value = false }, 2000)
    }
    else {
      toast.success('Codes copiés.')
    }
  }
  catch {
    toast.error('Copie impossible : notez-les à la main.')
  }
}

function telecharger() {
  const lignes = [
    `Codes de secours, ${useApplication().application.value.nom}`,
    utilisateur.value?.email ?? '',
    `Générés le ${formaterDateHeure(new Date().toISOString())}`,
    '',
    'Chaque code ne sert qu\'une fois, à la place du code de l\'appli.',
    '',
    ...codesSecours.value,
  ]
  const lien = document.createElement('a')
  lien.href = URL.createObjectURL(new Blob([lignes.join('\n')], { type: 'text/plain;charset=utf-8' }))
  lien.download = 'codes-de-secours.txt'
  lien.click()
  URL.revokeObjectURL(lien.href)
}
</script>

<template>
  <section class="rounded-md border border-gray-300 bg-white" aria-labelledby="titre-deux-facteurs">
    <div class="flex items-start gap-3 border-b border-gray-200 px-5 py-4">
      <ShieldCheck class="mt-0.5 size-5 flex-none text-brand-600" aria-hidden="true" />
      <div class="min-w-0 flex-1">
        <h2 id="titre-deux-facteurs" class="font-bold text-slate-700">Double authentification</h2>
        <p class="text-sm text-slate-400">En plus du mot de passe, un code à 6 chiffres donné par une appli sur votre téléphone.</p>
      </div>
      <span
        v-if="etat"
        class="flex-none rounded px-2 py-0.5 text-xs font-semibold"
        :class="etat.active ? 'bg-leaf-50 text-leaf-600' : 'bg-gray-100 text-slate-500'"
      >{{ etat.active ? 'Activée' : 'Désactivée' }}</span>
    </div>

    <div v-if="!etat" class="flex items-center gap-2 px-5 py-5 text-sm text-slate-400"><Loader2 class="size-4 animate-spin" /> Chargement...</div>

    <!-- Codes de secours, montrés une seule fois -->
    <div v-else-if="codesSecours.length" class="grid gap-4 px-5 py-5">
      <div class="flex gap-3 rounded-md border border-sun-300 bg-sun-50 px-4 py-3 text-sm text-sun-700">
        <TriangleAlert class="mt-0.5 size-4 flex-none" aria-hidden="true" />
        <p><strong>Gardez ces codes en lieu sûr.</strong> Ils remplacent l'appli si vous perdez votre téléphone. Chacun ne sert qu'une fois et ils ne seront plus affichés.</p>
      </div>
      <ul class="grid grid-cols-2 gap-2 rounded-md bg-gray-50 p-4 font-mono text-[15px] font-semibold tracking-wider text-slate-700">
        <li v-for="c in codesSecours" :key="c" class="text-center">{{ c }}</li>
      </ul>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" @click="copier(codesSecours.join('\n'))"><Copy /> Copier</Button>
        <Button variant="outline" size="sm" @click="telecharger"><Download /> Télécharger</Button>
        <Button size="sm" class="ms-auto" @click="codesSecours = []"><Check /> J'ai noté mes codes</Button>
      </div>
    </div>

    <!-- Activation : QR code puis premier code -->
    <div v-else-if="preparation" class="grid gap-5 px-5 py-5">
      <ol class="grid gap-5 text-sm text-slate-600">
        <li class="flex gap-3">
          <span class="flex size-6 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">1</span>
          <p>Installez une appli d'authentification sur votre téléphone : <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou une autre.</p>
        </li>
        <li class="flex gap-3">
          <span class="flex size-6 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">2</span>
          <div class="grid min-w-0 flex-1 gap-3">
            <p>Dans l'appli, ajoutez un compte et scannez ce QR code.</p>
            <div class="flex flex-wrap items-center gap-4">
              <img :src="preparation.qr" alt="QR code à scanner avec l'appli d'authentification" class="size-44 rounded-md border border-gray-200 bg-white p-1">
              <div class="grid min-w-0 gap-1.5">
                <p class="text-xs text-slate-400">Pas de caméra ? Saisissez cette clé :</p>
                <code class="break-all rounded bg-gray-100 px-2 py-1 font-mono text-sm font-semibold text-slate-700">{{ preparation.cle }}</code>
                <button type="button" class="inline-flex items-center gap-1 justify-self-start text-xs font-medium text-brand-700 hover:underline" @click="copier(preparation.cle.replaceAll(' ', ''), true)">
                  <Check v-if="cleCopiee" class="size-3.5" /><Copy v-else class="size-3.5" /> {{ cleCopiee ? 'Clé copiée' : 'Copier la clé' }}
                </button>
                <a :href="preparation.uri" class="inline-flex items-center gap-1 justify-self-start text-xs font-medium text-brand-700 hover:underline sm:hidden"><Smartphone class="size-3.5" /> Ouvrir dans l'appli de ce téléphone</a>
              </div>
            </div>
          </div>
        </li>
        <li class="flex gap-3">
          <span class="flex size-6 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">3</span>
          <div class="grid flex-1 gap-3">
            <p>Saisissez le code à 6 chiffres affiché par l'appli.</p>
            <form class="grid justify-items-start gap-3" novalidate @submit.prevent="confirmer()">
              <ChampCode ref="champCode" v-model="code" :longueur="6" :invalide="!!erreur" :desactive="envoi" @complet="confirmer" />
              <p v-if="erreur" class="text-sm text-destructive" role="alert">{{ erreur }}</p>
              <div class="flex gap-2">
                <Button type="submit" :disabled="envoi || code.length !== 6"><Loader2 v-if="envoi" class="animate-spin" /> Activer</Button>
                <Button type="button" variant="ghost" @click="preparation = null">Annuler</Button>
              </div>
            </form>
          </div>
        </li>
      </ol>
    </div>

    <!-- Active -->
    <div v-else-if="etat.active" class="grid gap-4 px-5 py-5 text-sm">
      <p class="text-slate-600">
        Activée le <strong>{{ formaterDate(etat.active_le) }}</strong>. À chaque connexion, le code de l'appli vous sera demandé.
      </p>
      <p
        class="flex items-center gap-2 rounded-md px-3 py-2"
        :class="etat.codes_secours_restants <= 2 ? 'bg-sun-50 text-sun-700' : 'bg-gray-50 text-slate-500'"
      >
        <KeyRound class="size-4 flex-none" aria-hidden="true" />
        {{ etat.codes_secours_restants }} code{{ etat.codes_secours_restants > 1 ? 's' : '' }} de secours restant{{ etat.codes_secours_restants > 1 ? 's' : '' }}<template v-if="etat.codes_secours_restants <= 2"> : générez-en de nouveaux.</template>
      </p>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" :class="action === 'codes' ? 'border-brand-500' : ''" @click="ouvrir('codes')"><KeyRound /> Nouveaux codes de secours</Button>
        <Button variant="outline" size="sm" class="text-destructive hover:text-destructive" :class="action === 'desactiver' ? 'border-destructive' : ''" @click="ouvrir('desactiver')"><ShieldOff /> Désactiver</Button>
      </div>
      <form v-if="action" class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-4" novalidate @submit.prevent="valider">
        <p class="text-slate-600">
          {{ action === 'codes' ? 'Les anciens codes de secours ne fonctionneront plus.' : 'Votre compte ne sera plus protégé que par le mot de passe.' }}
          Confirmez avec votre mot de passe.
        </p>
        <ChampFormulaire libelle="Mot de passe" pour="mdp-deux-facteurs" :erreur="erreur">
          <ChampMotDePasse id="mdp-deux-facteurs" v-model="motDePasse" autocomplete="current-password" :invalide="!!erreur" />
        </ChampFormulaire>
        <div class="flex gap-2">
          <Button type="submit" :variant="action === 'desactiver' ? 'destructive' : 'default'" :disabled="envoi || !motDePasse">
            <Loader2 v-if="envoi" class="animate-spin" /> {{ action === 'codes' ? 'Générer les codes' : 'Désactiver' }}
          </Button>
          <Button type="button" variant="ghost" @click="action = null">Annuler</Button>
        </div>
      </form>
    </div>

    <!-- Inactive -->
    <div v-else class="flex flex-wrap items-center justify-between gap-3 px-5 py-5">
      <p class="max-w-md text-sm text-slate-500">Même si quelqu'un découvre votre mot de passe, il ne pourra pas se connecter sans votre téléphone.</p>
      <Button :disabled="envoi" @click="commencer"><Loader2 v-if="envoi" class="animate-spin" /><ShieldCheck v-else /> Activer</Button>
    </div>
  </section>
</template>

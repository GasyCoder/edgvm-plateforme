<script setup lang="ts">
import { ArrowLeft, Loader2, MailCheck } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

definePageMeta({ layout: 'connexion' })
useHead({ title: 'Mot de passe oublié' })

const api = useApi()
const email = ref('')
const erreur = ref('')
const envoi = ref(false)
const message = ref('')

async function demander() {
  envoi.value = true
  erreur.value = ''
  try {
    await preparerSession()
    const reponse = await api<{ message: string }>('/api/mot-de-passe/oublie', { method: 'POST', body: { email: email.value } })
    message.value = reponse.message
  }
  catch (e) {
    erreur.value = erreursDeValidation(e).email ?? messageErreur(e)
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-2">
      <h1 class="text-xl font-bold tracking-tight text-slate-700">Mot de passe oublié</h1>
      <p class="text-sm leading-6 text-slate-400">Indiquez votre adresse e-mail : vous recevrez un lien pour en choisir un nouveau.</p>
    </div>

    <div v-if="message" class="flex gap-3 rounded-lg border bg-leaf-50 p-4 text-sm">
      <MailCheck class="size-5 flex-none text-leaf-600" />
      <p>{{ message }} Pensez à regarder dans les courriers indésirables.</p>
    </div>

    <form v-else class="grid gap-5" novalidate @submit.prevent="demander">
      <ChampFormulaire libelle="Adresse e-mail" pour="email" :erreur="erreur">
        <Input id="email" v-model="email" type="email" autocomplete="email" autofocus required />
      </ChampFormulaire>
      <Button type="submit" size="lg" class="w-full" :disabled="envoi">
        <Loader2 v-if="envoi" class="animate-spin" />
        Recevoir le lien
      </Button>
    </form>

    <NuxtLink to="/connexion" class="inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:underline">
      <ArrowLeft class="size-4" /> Retour à la connexion
    </NuxtLink>
  </div>
</template>

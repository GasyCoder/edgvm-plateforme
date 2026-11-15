<script setup lang="ts">
import { LogOut, RefreshCw, Wrench } from '@lucide/vue'
import { Button } from '@/components/ui/button'

/** Écran affiché aux comptes autres que l'administration pendant la maintenance. */
const { application, charger } = useApplication()
const { deconnecter } = useAuth()
const verification = ref(false)

async function reessayer() {
  verification.value = true
  await charger()
  verification.value = false
  if (!application.value.maintenance.active) reloadNuxtApp()
}
</script>

<template>
  <div class="mx-auto mt-10 max-w-lg rounded-md border border-gray-300 bg-white p-8 text-center">
    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-sun-100 text-sun-700"><Wrench class="size-7" aria-hidden="true" /></span>
    <h1 class="mt-5 text-xl font-bold text-slate-700">Plateforme en maintenance</h1>
    <p class="mt-2 whitespace-pre-line text-slate-500">{{ application.maintenance.message }}</p>
    <div class="mt-6 flex flex-wrap justify-center gap-2">
      <Button :disabled="verification" @click="reessayer"><RefreshCw :class="verification ? 'animate-spin' : ''" /> Réessayer</Button>
      <Button variant="outline" @click="deconnecter()"><LogOut /> Se déconnecter</Button>
    </div>
  </div>
</template>

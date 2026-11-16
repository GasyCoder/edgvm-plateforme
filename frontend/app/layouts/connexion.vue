<script setup lang="ts">
const { application } = useApplication()
</script>

<template>
  <!-- Fond aux couleurs du logo : jaune doux, bande jaune en haut ; la carte reste blanche pour la lisibilité -->
  <div class="flex min-h-dvh flex-col bg-gradient-to-b from-sun-200 via-sun-50 to-white">
    <div class="h-1.5 flex-none bg-gradient-to-r from-sun-400 via-sun-300 to-gold" aria-hidden="true" />
    <div class="m-auto w-full max-w-[460px] px-4 py-10 sm:max-w-[520px] sm:px-5">
      <NuxtLink to="/connexion" class="mb-7 flex flex-col items-center gap-2 text-center">
        <img :src="application.logos.edgvm" alt="" class="size-16 object-contain">
        <span class="text-xl font-extrabold tracking-tight text-brand-800">{{ application.nom }}</span>
        <span class="text-sm text-slate-500">{{ application.ecole }}</span>
      </NuxtLink>
      <div v-if="application.maintenance.active" class="mb-4 rounded-md border border-sun-300 bg-sun-50 p-4 text-sm text-sun-700" role="status">
        <p class="font-semibold">Plateforme en maintenance</p>
        <p class="mt-1 whitespace-pre-line">{{ application.maintenance.message }}</p>
        <p class="mt-1 text-xs">Seule l'administration de l'École peut se connecter pour le moment.</p>
      </div>
      <div class="rounded-lg border border-sun-300 bg-white p-5 shadow-sm shadow-sun-200/60 sm:p-8 md:p-10">
        <slot />
      </div>
      <p class="mt-6 text-center text-xs text-slate-500">© {{ new Date().getFullYear() }} {{ application.universite }}</p>
    </div>
  </div>
</template>

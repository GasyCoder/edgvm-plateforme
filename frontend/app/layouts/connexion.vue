<script setup lang="ts">
const { application } = useApplication()
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-white">
    <div class="m-auto w-full max-w-[460px] px-4 py-10 sm:max-w-[520px] sm:px-5">
      <NuxtLink to="/connexion" class="mb-7 flex flex-col items-center gap-2 text-center">
        <img :src="application.logos.edgvm" alt="" class="size-16 object-contain">
        <span class="text-xl font-extrabold tracking-tight text-brand-800">{{ application.nom }}</span>
        <span class="text-sm text-slate-400">{{ application.ecole }}</span>
      </NuxtLink>
      <div v-if="application.maintenance.active" class="mb-4 rounded-md border border-sun-300 bg-sun-50 p-4 text-sm text-sun-700" role="status">
        <p class="font-semibold">Plateforme en maintenance</p>
        <p class="mt-1 whitespace-pre-line">{{ application.maintenance.message }}</p>
        <p class="mt-1 text-xs">Seule l'administration de l'École peut se connecter pour le moment.</p>
      </div>
      <div class="rounded-md border border-gray-300 p-5 sm:p-8 md:p-10">
        <slot />
      </div>
      <p class="mt-6 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} {{ application.universite }}</p>
    </div>
  </div>
</template>

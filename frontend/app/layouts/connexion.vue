<template>
  <div class="grid min-h-dvh bg-background lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between overflow-hidden bg-brand-800 p-10 text-white lg:flex">
      <div class="flex items-center gap-3">
        <img src="/images/logo-edgvm-112.webp" alt="" class="size-12 rounded-lg bg-white object-contain p-1">
        <div class="leading-tight">
          <p class="text-lg font-bold">EDGVM</p>
          <p class="text-sm text-brand-100">École Doctorale Génie du Vivant et Modélisation</p>
        </div>
      </div>
      <div class="max-w-md space-y-3">
        <p class="text-3xl font-bold leading-tight">Plateforme de l'École doctorale</p>
        <p class="text-brand-100">Dossiers des doctorants, inscriptions, attestations et suivi des thèses.</p>
      </div>
      <p class="text-sm text-brand-200">Université de Mahajanga</p>
    </div>

    <div class="flex items-center justify-center px-4 py-12 sm:px-8">
      <div class="w-full max-w-sm">
        <img src="/images/logo-edgvm-112.webp" alt="EDGVM" class="mb-6 size-14 rounded-lg object-contain lg:hidden">
        <slot />
      </div>
    </div>
  </div>
</template>

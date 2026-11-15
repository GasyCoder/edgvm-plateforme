<template>
  <div class="flex min-h-dvh flex-col bg-white">
    <div class="m-auto w-full max-w-[460px] px-4 py-10 sm:max-w-[520px] sm:px-5">
      <NuxtLink to="/connexion" class="mb-7 flex flex-col items-center gap-2 text-center">
        <img src="/images/logo-edgvm-112.webp" alt="" class="size-16 object-contain">
        <span class="text-xl font-extrabold tracking-tight text-brand-800">EDGVM</span>
        <span class="text-sm text-slate-400">École Doctorale Génie du Vivant et Modélisation</span>
      </NuxtLink>
      <div class="rounded-md border border-gray-300 p-5 sm:p-8 md:p-10">
        <slot />
      </div>
      <p class="mt-6 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} Université de Mahajanga</p>
    </div>
  </div>
</template>

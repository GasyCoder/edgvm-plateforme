<script setup lang="ts">
import 'vue-sonner/style.css'
import { useMediaQuery } from '@vueuse/core'
import { Toaster } from '@/components/ui/sonner'

/* Toasts en bas à droite ; sur mobile, au-dessus du menu du bas. */
const grandEcran = useMediaQuery('(min-width: 1280px)')
const decalage = computed(() => grandEcran.value
  ? { bottom: '1.25rem', right: '1.25rem' }
  : { bottom: 'calc(5rem + env(safe-area-inset-bottom))', right: '1rem' })
</script>

<template>
  <NuxtLoadingIndicator color="#f6d94c" :height="3" />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
  <Toaster position="bottom-right" rich-colors close-button :duration="6000" :offset="decalage" :mobile-offset="decalage" />
</template>

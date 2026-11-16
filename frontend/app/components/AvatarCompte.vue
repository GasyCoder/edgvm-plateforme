<script setup lang="ts">
/** Photo de profil, ou initiales sur une couleur tirée du nom. */
defineProps<{ nom: string, photoUrl?: string | null }>()
</script>

<template>
  <img v-if="photoUrl" :src="photoUrl" :alt="`Photo de ${nom}`" class="flex-none rounded-full object-cover object-top">
  <span v-else class="flex flex-none items-center justify-center rounded-full font-semibold text-white" :class="couleurAvatar(nom)" aria-hidden="true">{{ initiales(nom) }}</span>
</template>

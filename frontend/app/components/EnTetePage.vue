<script setup lang="ts">
/** En-tête de page : titre et phrase de contexte à gauche, actions à droite. */
defineProps<{ titre: string, description?: string }>()
</script>

<template>
  <div class="mb-6 flex flex-col gap-4 sm:mb-7 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
      <slot name="avant" />
      <h1 class="text-2xl font-bold tracking-tight text-slate-700 sm:text-[1.75rem]">{{ titre }}</h1>
      <p v-if="description" class="mt-1 text-sm text-slate-400">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:gap-3">
      <slot name="actions" />
    </div>
  </div>
</template>

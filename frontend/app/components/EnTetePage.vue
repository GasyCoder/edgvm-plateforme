<script setup lang="ts">
defineProps<{ titre: string, description?: string }>()
</script>

<template>
  <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
      <slot name="avant" />
      <h1 class="text-2xl font-bold tracking-tight text-brand-900">{{ titre }}</h1>
      <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

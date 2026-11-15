<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'

withDefaults(defineProps<{ lignes?: number }>(), { lignes: 6 })
</script>

<template>
  <div class="overflow-hidden rounded-lg border bg-white" aria-busy="true" aria-label="Chargement">
    <div v-for="ligne in lignes" :key="ligne" class="flex items-center gap-3 border-b p-4 last:border-b-0">
      <Skeleton class="size-9 rounded-full" />
      <div class="grid flex-1 gap-2">
        <Skeleton class="h-4 w-2/5" />
        <Skeleton class="h-3 w-3/5" />
      </div>
      <Skeleton class="hidden h-6 w-16 sm:block" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'
</script>

<template>
  <div class="grid gap-6" aria-busy="true" aria-label="Chargement">
    <div class="flex items-center gap-4">
      <Skeleton class="size-14 rounded-full" />
      <div class="grid flex-1 gap-2">
        <Skeleton class="h-6 w-1/3" />
        <Skeleton class="h-4 w-1/4" />
      </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
      <div class="grid gap-3 rounded-lg border bg-white p-5 lg:col-span-2">
        <Skeleton v-for="ligne in 6" :key="ligne" class="h-4" :class="ligne % 2 ? 'w-3/4' : 'w-1/2'" />
      </div>
      <div class="grid gap-3 rounded-lg border bg-white p-5">
        <Skeleton v-for="ligne in 4" :key="ligne" class="h-4 w-2/3" />
      </div>
    </div>
  </div>
</template>

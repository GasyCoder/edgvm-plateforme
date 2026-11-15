<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{ titre: string, texte?: string, icone?: Component }>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded-lg border border-dashed bg-white px-6 py-12 text-center">
    <component :is="icone" v-if="icone" class="size-8 text-muted-foreground" />
    <p class="font-medium">{{ titre }}</p>
    <p v-if="texte" class="max-w-sm text-sm text-muted-foreground">{{ texte }}</p>
    <slot />
  </div>
</template>

<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'

definePageMeta({ roles: ['super_admin', 'membre_ead'] })
const route = useRoute()
</script>

<template>
  <div>
    <NuxtLink to="/doctorants" class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="size-4" /> Doctorants
    </NuxtLink>
    <FicheDoctorant :id="String(route.params.id)" :key="String(route.params.id)" />
  </div>
</template>

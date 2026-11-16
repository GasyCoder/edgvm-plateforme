<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Nouveau candidat' })
</script>

<template>
  <div>
    <NuxtLink to="/candidats" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-700">
      <ArrowLeft class="size-4" /> Candidats
    </NuxtLink>
    <EnTetePage titre="Nouveau candidat" description="Identité, contact et projet de thèse. Le candidat n'a ni inscription ni accès à la plateforme avant son admission par le jury." />
    <AssistantDoctorant candidat />
  </div>
</template>

<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Nouveau doctorant' })
</script>

<template>
  <div>
    <NuxtLink to="/doctorants" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-700">
      <ArrowLeft class="size-4" /> Doctorants
    </NuxtLink>
    <EnTetePage titre="Nouveau doctorant" description="Compte, dossier et première inscription, en cinq étapes. Le brouillon est gardé si vous quittez la page." />
    <AssistantNouveauDoctorant />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Nouveau doctorant' })
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <EnTetePage titre="Nouveau doctorant" description="Le compte, le dossier et la première inscription sont créés en une fois." />
    <FormulaireDoctorant />
  </div>
</template>

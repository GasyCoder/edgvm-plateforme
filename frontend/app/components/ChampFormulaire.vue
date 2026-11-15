<script setup lang="ts">
import { Label } from '@/components/ui/label'

/** Libellé, champ et message d'erreur de validation. */
defineProps<{ libelle: string, pour: string, erreur?: string, aide?: string, requis?: boolean }>()
</script>

<template>
  <div class="grid content-start gap-2">
    <Label :for="pour">{{ libelle }}<span v-if="requis" class="text-destructive" aria-hidden="true">*</span></Label>
    <slot />
    <p v-if="erreur" class="text-sm text-destructive">{{ erreur }}</p>
    <p v-else-if="aide" class="text-xs text-muted-foreground">{{ aide }}</p>
  </div>
</template>

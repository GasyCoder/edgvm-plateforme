<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue'
import { Input } from '@/components/ui/input'

/** Mot de passe avec un bouton pour l'afficher ou le masquer. */
defineProps<{ id: string, autocomplete?: string, placeholder?: string, invalide?: boolean }>()
const valeur = defineModel<string>({ default: '' })
const visible = ref(false)
</script>

<template>
  <div class="relative">
    <Input
      :id="id"
      v-model="valeur"
      :type="visible ? 'text' : 'password'"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :aria-invalid="invalide || undefined"
      class="pe-11"
      spellcheck="false"
      autocapitalize="off"
    />
    <button
      type="button"
      class="absolute inset-y-0 end-0 flex w-10 items-center justify-center rounded-e-md text-slate-400 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
      :aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
      :aria-pressed="visible"
      :aria-controls="id"
      @click="visible = !visible"
    >
      <EyeOff v-if="visible" class="size-4" />
      <Eye v-else class="size-4" />
    </button>
  </div>
</template>

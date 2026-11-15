<script setup lang="ts">
import { ExternalLink } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'

/** Fenêtre d'aperçu d'un PDF (adresse d'objet créée à partir de la réponse de l'API). */
const props = defineProps<{ url: string | null, titre?: string }>()
const ouvert = defineModel<boolean>('open', { default: false })
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="flex h-[92dvh] max-w-5xl flex-col gap-3 p-4 sm:max-w-5xl">
      <DialogHeader class="flex-row items-center justify-between gap-3 space-y-0 pe-8">
        <div class="grid gap-1">
          <DialogTitle>{{ titre ?? 'Aperçu' }}</DialogTitle>
          <DialogDescription>Aperçu sans valeur officielle : rien n'est enregistré.</DialogDescription>
        </div>
        <Button v-if="props.url" variant="outline" size="sm" as-child>
          <a :href="props.url" target="_blank" rel="noopener"><ExternalLink /> Ouvrir dans un onglet</a>
        </Button>
      </DialogHeader>
      <iframe v-if="props.url" :src="props.url" title="Aperçu PDF" class="min-h-0 w-full flex-1 rounded border bg-gray-100" />
    </DialogContent>
  </Dialog>
</template>

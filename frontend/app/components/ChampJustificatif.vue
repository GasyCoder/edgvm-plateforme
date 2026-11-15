<script setup lang="ts">
import { Camera, FileText, Loader2, Paperclip, X } from '@lucide/vue'
import { useMediaQuery } from '@vueuse/core'
import { Button } from '@/components/ui/button'

/**
 * Reçu de versement : photo prise avec le téléphone (appareil photo arrière) ou fichier déjà scanné (image ou PDF).
 * La photo est allégée avant l'envoi et un aperçu permet de vérifier qu'elle est lisible.
 */
const fichier = defineModel<File | null>({ default: null })
defineProps<{ erreur?: string }>()

const tactile = useMediaQuery('(pointer: coarse)')
const preparation = ref(false)
const apercu = ref<string | null>(null)
const photo = ref<HTMLInputElement | null>(null)
const parcourir = ref<HTMLInputElement | null>(null)

watch(fichier, (nouveau) => {
  if (apercu.value) URL.revokeObjectURL(apercu.value)
  apercu.value = nouveau && nouveau.type.startsWith('image/') ? URL.createObjectURL(nouveau) : null
}, { immediate: true })
onBeforeUnmount(() => { if (apercu.value) URL.revokeObjectURL(apercu.value) })

async function choisir(evenement: Event) {
  const champ = evenement.target as HTMLInputElement
  const choisi = champ.files?.[0]
  champ.value = ''
  if (!choisi) return
  preparation.value = true
  fichier.value = await alleger(choisi)
  preparation.value = false
}
</script>

<template>
  <div class="grid gap-2">
    <div v-if="fichier" class="flex items-center gap-3 rounded-md border border-leaf-400/50 bg-leaf-50/60 p-2.5">
      <a v-if="apercu" :href="apercu" target="_blank" rel="noopener" class="flex-none" title="Agrandir">
        <img :src="apercu" alt="Aperçu du reçu de versement" class="size-16 rounded object-cover ring-1 ring-black/10">
      </a>
      <span v-else class="flex size-16 flex-none items-center justify-center rounded bg-white text-brand-700 ring-1 ring-black/10"><FileText class="size-7" /></span>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-700">{{ fichier.name }}</p>
        <p class="text-xs text-slate-500">{{ formaterTaille(fichier.size) }} · prêt à être enregistré</p>
      </div>
      <Button type="button" variant="ghost" size="icon-sm" class="text-slate-400 hover:text-destructive" aria-label="Retirer le reçu de versement" @click="fichier = null"><X /></Button>
    </div>

    <div v-else class="flex flex-wrap gap-2">
      <Button v-if="tactile" type="button" variant="outline" :disabled="preparation" @click="photo?.click()">
        <Loader2 v-if="preparation" class="animate-spin" /><Camera v-else /> Prendre une photo
      </Button>
      <Button type="button" variant="outline" :disabled="preparation" @click="parcourir?.click()">
        <Loader2 v-if="preparation && !tactile" class="animate-spin" /><Paperclip v-else /> {{ tactile ? 'Choisir un fichier' : 'Joindre la photo ou le scan' }}
      </Button>
    </div>
    <input ref="photo" type="file" accept="image/*" capture="environment" class="hidden" @change="choisir">
    <input ref="parcourir" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="choisir">
    <p v-if="erreur" class="text-sm text-destructive">{{ erreur }}</p>
    <p v-else-if="!fichier" class="text-xs text-slate-400">Photo du bordereau ou reçu de virement remis par le doctorant (JPG, PNG ou PDF).</p>
  </div>
</template>

<script setup lang="ts">
import { Camera, ImagePlus, Loader2, Trash2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'

/**
 * Photo de profil d'un compte : choisir, prendre (téléphone), retirer. Envoyée tout de suite.
 * « adresse » : /api/moi/photo pour soi, /api/comptes/{id}/photo pour l'administration.
 */
const props = defineProps<{ nom: string, photoUrl?: string | null, adresse: string }>()
const emit = defineEmits<{ change: [url: string | null] }>()

const api = useApi()
const entree = ref<HTMLInputElement | null>(null)
const camera = ref<HTMLInputElement | null>(null)
const envoi = ref(false)
const erreur = ref('')
const url = ref<string | null>(props.photoUrl ?? null)
watch(() => props.photoUrl, (nouvelle) => { url.value = nouvelle ?? null })

async function choisir(evenement: Event) {
  const champ = evenement.target as HTMLInputElement
  const fichier = champ.files?.[0]
  champ.value = ''
  if (!fichier) return
  erreur.value = ''
  if (!fichier.type.startsWith('image/')) {
    erreur.value = 'Choisissez une image (JPEG, PNG ou WebP).'
    return
  }
  envoi.value = true
  try {
    const donnees = new FormData()
    donnees.append('photo', await alleger(fichier, 1200))
    const reponse = await api<{ data: { photo_url: string }, message: string }>(props.adresse, { method: 'POST', body: donnees })
    url.value = reponse.data.photo_url
    emit('change', reponse.data.photo_url)
    toast.success(reponse.message)
  }
  catch (e) {
    erreur.value = erreursDeValidation(e).photo ?? messageErreur(e)
  }
  finally {
    envoi.value = false
  }
}

async function retirer() {
  envoi.value = true
  try {
    const reponse = await api<{ data: { photo_url: string | null }, message: string }>(props.adresse, { method: 'DELETE' })
    url.value = reponse.data.photo_url
    emit('change', reponse.data.photo_url)
    toast.success(reponse.message)
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <div class="flex items-center gap-4">
    <div class="relative">
      <AvatarCompte :nom="nom" :photo-url="url" class="size-20 text-2xl" />
      <span v-if="envoi" class="absolute inset-0 flex items-center justify-center rounded-full bg-white/70"><Loader2 class="size-5 animate-spin text-brand-600" /></span>
    </div>
    <div class="grid gap-2">
      <div class="flex flex-wrap gap-2">
        <Button type="button" size="sm" variant="outline" :disabled="envoi" @click="entree?.click()"><ImagePlus /> {{ url ? 'Changer la photo' : 'Ajouter une photo' }}</Button>
        <Button type="button" size="sm" variant="outline" class="sm:hidden" :disabled="envoi" @click="camera?.click()"><Camera /> Prendre</Button>
        <Button v-if="url" type="button" size="sm" variant="ghost" class="text-slate-500 hover:text-destructive" :disabled="envoi" @click="retirer"><Trash2 /> Retirer</Button>
      </div>
      <p class="text-xs text-slate-400">JPEG, PNG ou WebP. Recadrée en carré, visage au centre.</p>
      <p v-if="erreur" class="text-sm text-destructive">{{ erreur }}</p>
      <input ref="entree" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="choisir">
      <input ref="camera" type="file" accept="image/*" capture="user" class="hidden" @change="choisir">
    </div>
  </div>
</template>

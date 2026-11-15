<script setup lang="ts">
import { Camera, ImagePlus, Loader2, Trash2, UserRound } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'

/**
 * Photo d'identité du doctorant (carte de doctorat), au format portrait.
 * Avec un dossier existant, la photo est envoyée tout de suite ; à la création, elle est gardée
 * dans « fichier » et envoyée juste après la création du dossier.
 */
const props = defineProps<{ doctorantId?: string, photoUrl?: string | null }>()
const fichier = defineModel<File | null>('fichier', { default: null })
const emit = defineEmits<{ change: [url: string | null] }>()

const api = useApi()
const entree = ref<HTMLInputElement | null>(null)
const camera = ref<HTMLInputElement | null>(null)
const envoi = ref(false)
const erreur = ref('')
const urlActuelle = ref<string | null>(props.photoUrl ?? null)
const apercuLocal = ref<string | null>(null)

watch(() => props.photoUrl, (url) => { urlActuelle.value = url ?? null })
watch(fichier, (nouveau) => {
  if (apercuLocal.value) URL.revokeObjectURL(apercuLocal.value)
  apercuLocal.value = nouveau ? URL.createObjectURL(nouveau) : null
}, { immediate: true })
onBeforeUnmount(() => { if (apercuLocal.value) URL.revokeObjectURL(apercuLocal.value) })

const apercu = computed(() => apercuLocal.value ?? urlActuelle.value)

async function choisir(evenement: Event) {
  const champ = evenement.target as HTMLInputElement
  const choisi = champ.files?.[0]
  champ.value = ''
  if (!choisi) return
  erreur.value = ''
  if (!choisi.type.startsWith('image/')) {
    erreur.value = 'Choisissez une image (JPEG, PNG ou WebP).'
    return
  }
  const leger = await alleger(choisi, 1600)

  if (!props.doctorantId) {
    fichier.value = leger
    return
  }

  envoi.value = true
  try {
    const donnees = new FormData()
    donnees.append('photo', leger)
    const reponse = await api<{ data: { photo_url: string }, message: string }>(`/api/doctorants/${props.doctorantId}/photo`, { method: 'POST', body: donnees })
    urlActuelle.value = reponse.data.photo_url
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
  erreur.value = ''
  if (!props.doctorantId) {
    fichier.value = null
    return
  }
  envoi.value = true
  try {
    await api(`/api/doctorants/${props.doctorantId}/photo`, { method: 'DELETE' })
    urlActuelle.value = null
    emit('change', null)
    toast.success('Photo retirée.')
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
  <div class="flex items-center gap-4 rounded-md border border-dashed border-gray-300 p-4">
    <div class="relative flex h-28 w-21 flex-none items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-gray-50">
      <img v-if="apercu" :src="apercu" alt="Photo du doctorant" class="size-full object-cover">
      <UserRound v-else class="size-9 text-slate-300" aria-hidden="true" />
      <span v-if="envoi" class="absolute inset-0 flex items-center justify-center bg-white/70"><Loader2 class="size-5 animate-spin text-brand-600" /></span>
    </div>
    <div class="grid min-w-0 gap-2">
      <p class="text-sm font-medium text-slate-700">Photo d'identité</p>
      <p class="text-xs text-slate-400">Pour la carte de doctorat. Format portrait, visage bien centré.</p>
      <div class="flex flex-wrap gap-2">
        <Button type="button" size="sm" variant="outline" :disabled="envoi" @click="entree?.click()"><ImagePlus /> {{ apercu ? 'Changer' : 'Choisir une photo' }}</Button>
        <Button type="button" size="sm" variant="outline" class="sm:hidden" :disabled="envoi" @click="camera?.click()"><Camera /> Prendre</Button>
        <Button v-if="apercu" type="button" size="sm" variant="ghost" class="text-slate-500 hover:text-destructive" :disabled="envoi" @click="retirer"><Trash2 /> Retirer</Button>
      </div>
      <p v-if="erreur" class="text-sm text-destructive">{{ erreur }}</p>
      <input ref="entree" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="choisir">
      <input ref="camera" type="file" accept="image/*" capture="user" class="hidden" @change="choisir">
    </div>
  </div>
</template>

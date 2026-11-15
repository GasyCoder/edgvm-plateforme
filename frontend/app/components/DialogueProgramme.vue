<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import type { Equipe, ProgrammeDoctoral } from '~/types'

/** Ajout ou modification d'un programme doctoral (spécialité) d'une équipe. */
const props = defineProps<{ equipe: Equipe, programme?: ProgrammeDoctoral | null }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistre: [] }>()

const api = useApi()
const formulaire = reactive({ nom: '', description: '' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  Object.assign(formulaire, { nom: props.programme?.nom ?? '', description: props.programme?.description ?? '' })
  erreurs.value = {}
}, { immediate: true })

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  const corps = { ...formulaire, nom: formulaire.nom.replace(/\s+/g, ' ').trim() }
  try {
    await (props.programme
      ? api(`/api/programmes/${props.programme.id}`, { method: 'PUT', body: corps })
      : api(`/api/equipes/${props.equipe.id}/programmes`, { method: 'POST', body: corps }))
    toast.success(props.programme ? 'Programme modifié.' : 'Programme ajouté.')
    ouvert.value = false
    emit('enregistre')
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="ouvert">
    <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{{ programme ? 'Modifier le programme' : 'Nouveau programme doctoral' }}</DialogTitle>
        <DialogDescription>{{ equipe.sigle }}, {{ equipe.nom }}</DialogDescription>
      </DialogHeader>
      <form id="form-programme" class="grid gap-5" novalidate @submit.prevent="enregistrer">
        <ChampFormulaire libelle="Nom du programme" pour="programme-nom" :erreur="erreurs.nom" requis>
          <Textarea id="programme-nom" v-model="formulaire.nom" rows="2" maxlength="255" class="min-h-16 resize-y text-[15px] leading-relaxed" @keydown.enter.prevent />
        </ChampFormulaire>
        <ChampFormulaire libelle="Description" pour="programme-description" :erreur="erreurs.description" aide="Objectifs, thèmes de recherche. Facultatif.">
          <Textarea id="programme-description" v-model="formulaire.description" rows="4" maxlength="2000" class="resize-y leading-relaxed" />
        </ChampFormulaire>
      </form>
      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button type="submit" form="form-programme" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Loader2 } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { Equipe } from '~/types'

/** Création ou modification d'une équipe d'accueil : sigle, nom complet, domaine, présentation. */
const props = defineProps<{ equipe?: Equipe | null }>()
const ouvert = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enregistre: [equipe: Equipe] }>()

const api = useApi()
const formulaire = reactive({ sigle: '', nom: '', domaine: '', description: '' })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

watch(ouvert, (estOuvert) => {
  if (!estOuvert) return
  const e = props.equipe
  Object.assign(formulaire, { sigle: e?.sigle ?? '', nom: e?.nom ?? '', domaine: e?.domaine ?? '', description: e?.description ?? '' })
  erreurs.value = {}
}, { immediate: true })

async function enregistrer() {
  envoi.value = true
  erreurs.value = {}
  /* Le nom tient sur une ligne : un retour à la ligne collé devient une espace. */
  const corps = { ...formulaire, nom: formulaire.nom.replace(/\s+/g, ' ').trim() }
  try {
    const reponse = await (props.equipe
      ? api<{ data: Equipe }>(`/api/equipes/${props.equipe.id}`, { method: 'PUT', body: corps })
      : api<{ data: Equipe }>('/api/equipes', { method: 'POST', body: corps }))
    toast.success(props.equipe ? 'Équipe modifiée.' : 'Équipe créée.')
    clearNuxtData('ref-equipes')
    ouvert.value = false
    emit('enregistre', reponse.data)
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
        <DialogTitle>{{ equipe ? `Modifier ${equipe.sigle}` : 'Nouvelle équipe' }}</DialogTitle>
        <DialogDescription>Gardez le même sigle que sur le site de l'École : c'est lui qui relie l'équipe au site.</DialogDescription>
      </DialogHeader>
      <form id="form-equipe" class="grid gap-5" novalidate @submit.prevent="enregistrer">
        <div class="grid gap-5 sm:grid-cols-[12rem_minmax(0,1fr)]">
          <ChampFormulaire libelle="Sigle" pour="sigle" :erreur="erreurs.sigle" requis>
            <Input id="sigle" v-model="formulaire.sigle" placeholder="EAD 9" maxlength="30" />
          </ChampFormulaire>
          <ChampFormulaire libelle="Domaine" pour="domaine" :erreur="erreurs.domaine">
            <Input id="domaine" v-model="formulaire.domaine" placeholder="Sciences de la vie et de l'environnement" maxlength="255" />
          </ChampFormulaire>
        </div>
        <ChampFormulaire libelle="Nom complet" pour="nom-equipe" :erreur="erreurs.nom" requis>
          <Textarea id="nom-equipe" v-model="formulaire.nom" rows="3" maxlength="255" class="min-h-20 resize-y text-[15px] leading-relaxed" @keydown.enter.prevent />
          <p class="text-end text-xs text-slate-400">{{ formulaire.nom.length }} / 255</p>
        </ChampFormulaire>
        <ChampFormulaire libelle="Présentation" pour="description" :erreur="erreurs.description" aide="Thématiques et axes de recherche. Facultatif.">
          <Textarea id="description" v-model="formulaire.description" rows="6" maxlength="2000" class="min-h-32 resize-y leading-relaxed" />
          <p class="text-end text-xs text-slate-400">{{ formulaire.description.length }} / 2000</p>
        </ChampFormulaire>
      </form>
      <DialogFooter>
        <Button variant="outline" @click="ouvert = false">Annuler</Button>
        <Button type="submit" form="form-equipe" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

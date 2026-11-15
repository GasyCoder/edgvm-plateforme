<script setup lang="ts">
import { CalendarRange, Check, Loader2, Pencil, Plus } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import type { AnneeUniversitaire, Ressource } from '~/types'

definePageMeta({ roles: ['super_admin'] })
useHead({ title: 'Années universitaires' })

const api = useApi()
const { data: annees, status, refresh } = useAsyncData('annees', () => api<Ressource<AnneeUniversitaire[]>>('/api/annees').then(r => r.data), { lazy: true })

const ouvert = ref(false)
const modifiee = ref<AnneeUniversitaire | null>(null)
const formulaire = reactive({ libelle: '', debut: '', fin: '', est_courante: false })
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)

function ouvrir(annee: AnneeUniversitaire | null = null) {
  modifiee.value = annee
  const suivante = annees.value?.[0] ? Number(annees.value[0].libelle.slice(0, 4)) + 1 : new Date().getFullYear()
  Object.assign(formulaire, annee
    ? { libelle: annee.libelle, debut: annee.debut, fin: annee.fin, est_courante: annee.est_courante }
    : { libelle: `${suivante}-${suivante + 1}`, debut: `${suivante}-10-01`, fin: `${suivante + 1}-09-30`, est_courante: false })
  erreurs.value = {}
  ouvert.value = true
}

async function enregistrer(valeurs = formulaire, annee = modifiee.value) {
  envoi.value = true
  erreurs.value = {}
  try {
    await (annee
      ? api(`/api/annees/${annee.id}`, { method: 'PUT', body: valeurs })
      : api('/api/annees', { method: 'POST', body: valeurs }))
    toast.success('Année enregistrée.')
    ouvert.value = false
    clearNuxtData(cle => cle === 'ref-annees' || cle === 'tableau-de-bord')
    await refresh()
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

const rendreCourante = (annee: AnneeUniversitaire) => enregistrer({ libelle: annee.libelle, debut: annee.debut, fin: annee.fin, est_courante: true }, annee)
</script>

<template>
  <div>
    <EnTetePage titre="Années universitaires" description="L'année en cours sert aux inscriptions, aux filtres et au tableau de bord.">
      <template #actions>
        <Button @click="ouvrir()"><Plus /> Nouvelle année</Button>
      </template>
    </EnTetePage>

    <SqueletteListe v-if="status === 'pending' && !annees" :lignes="4" />
    <EtatVide v-else-if="annees && !annees.length" :icone="CalendarRange" titre="Aucune année" texte="Créez l'année universitaire en cours." />
    <ul v-else-if="annees" class="overflow-hidden rounded-lg border bg-white">
      <li v-for="annee in annees" :key="annee.id" class="flex flex-wrap items-center gap-3 border-b p-4 last:border-b-0">
        <div class="min-w-0 flex-1">
          <p class="font-medium">
            {{ annee.libelle }}
            <span v-if="annee.est_courante" class="ml-2 rounded-md bg-leaf-50 px-2 py-0.5 text-xs font-medium text-leaf-600">En cours</span>
          </p>
          <p class="text-xs text-muted-foreground">Du {{ formaterDate(annee.debut) }} au {{ formaterDate(annee.fin) }} · {{ annee.inscriptions_count ?? 0 }} inscription(s)</p>
        </div>
        <Button v-if="!annee.est_courante" variant="outline" size="sm" :disabled="envoi" @click="rendreCourante(annee)"><Check /> Définir comme année en cours</Button>
        <Button variant="ghost" size="icon-sm" :aria-label="`Modifier ${annee.libelle}`" @click="ouvrir(annee)"><Pencil /></Button>
      </li>
    </ul>

    <Dialog v-model:open="ouvert">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ modifiee ? `Modifier ${modifiee.libelle}` : 'Nouvelle année universitaire' }}</DialogTitle>
          <DialogDescription>Format de l'année : 2026-2027.</DialogDescription>
        </DialogHeader>
        <form id="form-annee" class="grid gap-4" novalidate @submit.prevent="enregistrer()">
          <ChampFormulaire libelle="Année" pour="libelle" :erreur="erreurs.libelle" requis>
            <Input id="libelle" v-model="formulaire.libelle" placeholder="2026-2027" />
          </ChampFormulaire>
          <div class="grid gap-4 sm:grid-cols-2">
            <ChampFormulaire libelle="Début" pour="debut" :erreur="erreurs.debut" requis>
              <Input id="debut" v-model="formulaire.debut" type="date" />
            </ChampFormulaire>
            <ChampFormulaire libelle="Fin" pour="fin" :erreur="erreurs.fin" requis>
              <Input id="fin" v-model="formulaire.fin" type="date" />
            </ChampFormulaire>
          </div>
          <div class="flex items-center justify-between gap-4 rounded-lg border p-4">
            <Label for="courante">Année en cours</Label>
            <Switch id="courante" v-model="formulaire.est_courante" />
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" @click="ouvert = false">Annuler</Button>
          <Button type="submit" form="form-annee" :disabled="envoi"><Loader2 v-if="envoi" class="animate-spin" /> Enregistrer</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

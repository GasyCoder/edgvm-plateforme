<script setup lang="ts">
import { Check, Copy, Globe, KeyRound, Loader2, ShieldOff } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'

/**
 * Accès du site vitrine à l'API publique (équipes et annuaire des doctorants).
 * Le jeton n'est affiché qu'une fois, juste après sa création.
 */
interface Etat { actif: boolean, cree_le: string | null, url: string, jeton?: string }

const api = useApi()
const { data: etat, refresh } = useAsyncData('api-publique', () => api<{ data: Etat }>('/api/api-publique').then(r => r.data), { lazy: true })
const jeton = ref<string | null>(null)
const envoi = ref(false)
const confirmation = ref<'generer' | 'revoquer' | null>(null)
const copie = ref<string | null>(null)

async function generer() {
  envoi.value = true
  try {
    const reponse = await api<{ data: Etat, message: string }>('/api/api-publique/jeton', { method: 'POST' })
    jeton.value = reponse.data.jeton ?? null
    toast.success(reponse.message)
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
    confirmation.value = null
  }
}

async function revoquer() {
  envoi.value = true
  try {
    const reponse = await api<{ message: string }>('/api/api-publique/jeton', { method: 'DELETE' })
    jeton.value = null
    toast.success(reponse.message)
    await refresh()
  }
  catch (e) {
    toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
    confirmation.value = null
  }
}

async function copier(valeur: string, quoi: string) {
  await navigator.clipboard.writeText(valeur)
  copie.value = quoi
  setTimeout(() => { copie.value = null }, 1500)
}

function demanderGeneration() {
  if (etat.value?.actif) confirmation.value = 'generer'
  else generer()
}
</script>

<template>
  <section class="-mx-3 border-y border-gray-300 bg-white sm:mx-0 sm:rounded-md sm:border-x" aria-labelledby="titre-site-vitrine">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-4 sm:px-6">
      <div class="min-w-0">
        <h2 id="titre-site-vitrine" class="flex items-center gap-2 font-bold text-slate-700"><Globe class="size-5 text-brand-600" aria-hidden="true" /> Site vitrine de l'EDGVM</h2>
        <p class="text-sm text-slate-400">Le site public lit les équipes d'accueil (effectifs) et l'annuaire des doctorants qui ont accepté d'y figurer. Aucune donnée personnelle ni financière n'est transmise.</p>
      </div>
      <span v-if="etat" class="inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-medium" :class="etat.actif ? 'text-leaf-600' : 'text-slate-400'">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />{{ etat.actif ? 'Connexion activée' : 'Connexion désactivée' }}
      </span>
    </div>

    <div class="grid gap-4 px-4 py-4 sm:px-6">
      <div v-if="etat" class="grid gap-1.5">
        <p class="text-sm font-medium text-slate-600">Adresse de l'API</p>
        <div class="flex gap-2">
          <Input :model-value="etat.url" readonly class="font-mono text-sm" aria-label="Adresse de l'API" />
          <Button variant="outline" size="icon" :aria-label="copie === 'url' ? 'Adresse copiée' : 'Copier l\'adresse'" @click="copier(etat.url, 'url')"><Check v-if="copie === 'url'" /><Copy v-else /></Button>
        </div>
        <p class="text-xs text-slate-400">À mettre dans le réglage SCOLARITE_API_URL du site.</p>
      </div>

      <div v-if="jeton" class="grid gap-1.5 rounded-md border border-sun-300 bg-sun-50 p-4">
        <p class="text-sm font-semibold text-slate-700">Nouveau jeton : copiez-le maintenant, il ne sera plus affiché.</p>
        <div class="flex gap-2">
          <Input :model-value="jeton" readonly class="bg-white font-mono text-sm" aria-label="Jeton de l'API" />
          <Button variant="outline" size="icon" class="bg-white" :aria-label="copie === 'jeton' ? 'Jeton copié' : 'Copier le jeton'" @click="copier(jeton, 'jeton')"><Check v-if="copie === 'jeton'" /><Copy v-else /></Button>
        </div>
        <p class="text-xs text-slate-500">À mettre dans le réglage SCOLARITE_API_TOKEN du site, puis videz le cache depuis l'administration du site.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button :disabled="envoi || !etat" @click="demanderGeneration">
          <Loader2 v-if="envoi" class="animate-spin" /><KeyRound v-else />
          {{ etat?.actif ? 'Remplacer le jeton' : 'Créer un jeton' }}
        </Button>
        <Button v-if="etat?.actif" variant="outline" class="text-destructive hover:text-destructive" :disabled="envoi" @click="confirmation = 'revoquer'"><ShieldOff /> Couper la connexion</Button>
        <p v-if="etat?.actif && etat.cree_le" class="text-xs text-slate-400">Jeton créé le {{ formaterDateHeure(etat.cree_le) }}</p>
      </div>
    </div>

    <AlertDialog :open="confirmation !== null" @update:open="o => { if (!o) confirmation = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ confirmation === 'revoquer' ? 'Couper la connexion du site ?' : 'Remplacer le jeton ?' }}</AlertDialogTitle>
          <AlertDialogDescription>
            {{ confirmation === 'revoquer'
              ? 'Le site ne pourra plus afficher les équipes ni l\'annuaire tant qu\'un nouveau jeton n\'est pas créé et copié dans ses réglages.'
              : 'L\'ancien jeton cesse aussitôt de fonctionner : le site affichera ses données en cache, puis plus rien, jusqu\'à ce que le nouveau jeton soit copié dans ses réglages.' }}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="envoi">Annuler</AlertDialogCancel>
          <AlertDialogAction :disabled="envoi" :class="confirmation === 'revoquer' ? 'bg-destructive text-white hover:bg-destructive/90' : ''" @click.prevent="confirmation === 'revoquer' ? revoquer() : generer()">
            {{ confirmation === 'revoquer' ? 'Couper la connexion' : 'Remplacer le jeton' }}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </section>
</template>

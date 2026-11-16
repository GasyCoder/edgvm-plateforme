<script setup lang="ts">
import { KeyRound, Loader2, Mail, Monitor, Moon, Network, Phone, ShieldCheck, Sun, UserRound } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'

useHead({ title: 'Mon compte' })

const api = useApi()
const { utilisateur, estDoctorant } = useAuth()
const { themePersonnel, theme } = useApplication()

/* Changement de mot de passe : règles cochées en direct, champs affichables */
const vide = () => ({ mot_de_passe_actuel: '', password: '', password_confirmation: '' })
const formulaire = reactive(vide())
const erreurs = ref<Record<string, string>>({})
const envoi = ref(false)
const pret = computed(() => formulaire.mot_de_passe_actuel.length > 0
  && formulaire.password.length >= 10 && /\p{L}/u.test(formulaire.password) && /\d/.test(formulaire.password)
  && formulaire.password === formulaire.password_confirmation)

async function changer() {
  envoi.value = true
  erreurs.value = {}
  try {
    const reponse = await api<{ message: string }>('/api/moi/mot-de-passe', { method: 'PUT', body: formulaire })
    toast.success(reponse.message)
    Object.assign(formulaire, vide())
  }
  catch (e) {
    erreurs.value = erreursDeValidation(e)
    if (!Object.keys(erreurs.value).length) toast.error(messageErreur(e))
  }
  finally {
    envoi.value = false
  }
}

const THEMES = [
  { valeur: 'clair', libelle: 'Clair', icone: Sun },
  { valeur: 'sombre', libelle: 'Sombre', icone: Moon },
  { valeur: 'systeme', libelle: 'Comme le système', icone: Monitor },
] as const

const section = 'rounded-md border border-gray-300 bg-white'
const enTete = 'flex items-start gap-3 border-b border-gray-200 px-5 py-4'
</script>

<template>
  <div>
    <EnTetePage titre="Mon compte" description="Vos informations, votre mot de passe, la double authentification et l'affichage." />

    <div v-if="utilisateur" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
      <div class="grid content-start gap-6">
        <!-- Identité -->
        <section :class="section" aria-labelledby="titre-identite">
          <div class="grid gap-4 px-5 py-5">
            <div class="min-w-0">
              <h2 id="titre-identite" class="truncate text-lg font-bold text-slate-700">{{ utilisateur.name }}</h2>
              <span class="mt-1 inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800">{{ utilisateur.role_libelle }}</span>
            </div>
            <ChampAvatar :nom="utilisateur.name" :photo-url="utilisateur.photo_url" adresse="/api/moi/photo" @change="url => { if (utilisateur) utilisateur.photo_url = url }" />
          </div>
          <dl class="grid border-t border-gray-200 text-sm">
            <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-3">
              <Mail class="mt-0.5 size-4 flex-none text-slate-400" aria-hidden="true" />
              <div class="min-w-0"><dt class="text-xs text-slate-400">Adresse e-mail (identifiant de connexion)</dt><dd class="break-all font-medium text-slate-700">{{ utilisateur.email }}</dd></div>
            </div>
            <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-3">
              <Phone class="mt-0.5 size-4 flex-none text-slate-400" aria-hidden="true" />
              <div><dt class="text-xs text-slate-400">Téléphone</dt><dd class="font-medium" :class="utilisateur.telephone ? 'text-slate-700' : 'text-slate-400'">{{ utilisateur.telephone || 'Non renseigné' }}</dd></div>
            </div>
            <div v-if="utilisateur.equipes.length" class="flex items-start gap-3 border-b border-gray-100 px-5 py-3">
              <Network class="mt-0.5 size-4 flex-none text-slate-400" aria-hidden="true" />
              <div>
                <dt class="text-xs text-slate-400">Équipes d'accueil</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5"><span v-for="equipe in utilisateur.equipes" :key="equipe.id" class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-slate-600" :title="equipe.nom">{{ equipe.sigle }}</span></dd>
              </div>
            </div>
          </dl>
          <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-xs text-slate-400">
            <span>Pour corriger ces informations, contactez la scolarité de l'École.</span>
            <NuxtLink v-if="estDoctorant && utilisateur.doctorant_id" to="/" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline"><UserRound class="size-3.5" />Mon dossier</NuxtLink>
          </div>
        </section>

        <!-- Affichage -->
        <section :class="section" aria-labelledby="titre-affichage">
          <div :class="enTete">
            <Sun class="mt-0.5 size-5 flex-none text-brand-600" aria-hidden="true" />
            <div>
              <h2 id="titre-affichage" class="font-bold text-slate-700">Affichage</h2>
              <p class="text-sm text-slate-400">Pour vous seulement, sur cet appareil.</p>
            </div>
          </div>
          <div class="grid gap-3 px-5 py-4">
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Apparence">
              <button
                v-for="t in THEMES"
                :key="t.valeur"
                type="button"
                role="radio"
                :aria-checked="theme === t.valeur"
                class="flex flex-col items-center gap-1.5 rounded-md border p-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                :class="theme === t.valeur ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-gray-300 text-slate-500 hover:border-brand-300'"
                @click="themePersonnel = t.valeur"
              >
                <component :is="t.icone" class="size-5" aria-hidden="true" />{{ t.libelle }}
              </button>
            </div>
            <button v-if="themePersonnel" type="button" class="justify-self-start text-xs text-slate-400 hover:text-brand-700 hover:underline" @click="themePersonnel = ''">Revenir au réglage de l'École</button>
          </div>
        </section>
      </div>

      <div class="grid content-start gap-6">
        <!-- Mot de passe -->
        <section :class="section" aria-labelledby="titre-mot-de-passe" class="h-fit">
          <div :class="enTete">
            <KeyRound class="mt-0.5 size-5 flex-none text-brand-600" aria-hidden="true" />
            <div>
              <h2 id="titre-mot-de-passe" class="font-bold text-slate-700">Changer de mot de passe</h2>
              <p class="text-sm text-slate-400">Choisissez un mot de passe que vous n'utilisez nulle part ailleurs.</p>
            </div>
          </div>
          <form class="grid gap-5 px-5 py-5" novalidate @submit.prevent="changer">
            <ChampFormulaire libelle="Mot de passe actuel" pour="actuel" :erreur="erreurs.mot_de_passe_actuel">
              <ChampMotDePasse id="actuel" v-model="formulaire.mot_de_passe_actuel" autocomplete="current-password" :invalide="!!erreurs.mot_de_passe_actuel" />
            </ChampFormulaire>
            <div class="grid gap-5 sm:grid-cols-2">
              <ChampFormulaire libelle="Nouveau mot de passe" pour="nouveau" :erreur="erreurs.password">
                <ChampMotDePasse id="nouveau" v-model="formulaire.password" autocomplete="new-password" :invalide="!!erreurs.password" />
              </ChampFormulaire>
              <ChampFormulaire libelle="Confirmer le nouveau mot de passe" pour="confirmation">
                <ChampMotDePasse id="confirmation" v-model="formulaire.password_confirmation" autocomplete="new-password" />
              </ChampFormulaire>
            </div>
            <div class="rounded-md bg-gray-50 px-4 py-3">
              <ReglesMotDePasse :mot-de-passe="formulaire.password" :confirmation="formulaire.password_confirmation" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="flex items-center gap-1.5 text-xs text-slate-400"><ShieldCheck class="size-4" aria-hidden="true" />Vous restez connecté sur cet appareil.</p>
              <Button type="submit" :disabled="envoi || !pret"><Loader2 v-if="envoi" class="animate-spin" /> Changer le mot de passe</Button>
            </div>
          </form>
        </section>

        <CarteDeuxFacteurs />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ChevronsLeft, ChevronsRight, Menu, X } from '@lucide/vue'
import { useLocalStorage } from '@vueuse/core'

/*
 * Mise en page inspirée de DashWin : barre latérale blanche par sections (réductible sur grand écran),
 * en-tête fixe, contenu pleine largeur. Sur mobile : menu latéral par le hamburger et menu rapide en bas.
 */
const { liensDuBas, estActif } = useNavigation()
const { anneeCourante } = useReferentiels()
const { application } = useApplication()
const { estSuperAdmin } = useAuth()
/* Maintenance : l'administration continue à travailler (avec un bandeau), les autres voient le message. */
const enMaintenance = computed(() => application.value.maintenance.active)
const route = useRoute()

const compact = useLocalStorage('edgvm-menu-compact', false)
const survol = ref(false)
const menuMobile = ref(false)
const replie = computed(() => compact.value && !survol.value)

/*
 * Menu réduit : il se déplie en survol, par-dessus le contenu. Juste après un clic sur « Réduire »,
 * la souris est encore sur le menu : on attend qu'elle en sorte avant de le redéplier au survol.
 */
const survolBloque = ref(false)
function basculerMenu() {
  compact.value = !compact.value
  survol.value = false
  survolBloque.value = compact.value
}
function entrer() {
  if (!survolBloque.value) survol.value = true
}
function sortir() {
  survol.value = false
  survolBloque.value = false
}
watch(() => route.fullPath, () => { menuMobile.value = false })
</script>

<template>
  <div class="min-h-dvh bg-page">
    <!-- Fond assombri derrière le menu mobile -->
    <div v-if="menuMobile" class="fixed inset-0 z-40 bg-slate-900/40 xl:hidden" aria-hidden="true" @click="menuMobile = false" />

    <aside
      class="fixed inset-y-0 start-0 z-50 flex flex-col overflow-hidden border-e border-gray-200 bg-white transition-[transform,width] duration-300 xl:translate-x-0"
      :class="[menuMobile ? 'translate-x-0 shadow-xl' : '-translate-x-full', replie ? 'xl:w-[74px]' : 'w-72', compact && survol ? 'xl:shadow-xl' : '']"
      @mouseenter="entrer"
      @mouseleave="sortir"
    >
      <div class="flex h-16 flex-none items-center gap-3 border-b border-gray-200 px-5">
        <NuxtLink to="/" class="flex min-w-0 items-center gap-3">
          <img :src="application.logos.edgvm" alt="" class="size-9 flex-none object-contain">
          <span class="whitespace-nowrap leading-tight transition-opacity" :class="replie ? 'opacity-0' : ''">
            <span class="block text-lg font-extrabold tracking-tight text-brand-800">{{ application.nom }}</span>
            <span class="block text-[11px] text-slate-400">{{ application.sous_titre }}</span>
          </span>
        </NuxtLink>
        <button type="button" class="ms-auto rounded-full p-1.5 text-slate-500 hover:bg-gray-100 xl:hidden" aria-label="Fermer le menu" @click="menuMobile = false">
          <X class="size-5" />
        </button>
      </div>
      <div class="sans-barre flex-1 overflow-y-auto overflow-x-hidden">
        <NavigationLiens :compact="replie" @choisi="menuMobile = false" />
      </div>
      <button
        type="button"
        class="hidden h-12 flex-none items-center gap-3 border-t border-gray-200 px-6 text-sm text-slate-400 hover:text-brand-700 xl:flex"
        :aria-label="compact ? 'Garder le menu ouvert' : 'Réduire le menu'"
        :aria-pressed="compact"
        @click="basculerMenu"
      >
        <ChevronsRight v-if="compact" class="size-5 flex-none" />
        <ChevronsLeft v-else class="size-5 flex-none" />
        <span class="whitespace-nowrap transition-opacity" :class="replie ? 'opacity-0' : ''">{{ compact ? 'Garder le menu ouvert' : 'Réduire le menu' }}</span>
      </button>
    </aside>

    <div class="flex min-h-dvh flex-col transition-[padding] duration-300" :class="compact ? 'xl:ps-[74px]' : 'xl:ps-72'">
      <header
        class="fixed inset-x-0 top-0 z-30 h-16 border-b border-gray-200 bg-white transition-[left] duration-300"
        :class="compact ? 'xl:left-[74px]' : 'xl:left-72'"
      >
        <div class="flex h-full items-center gap-3 px-3 sm:px-6">
          <button type="button" class="rounded-full p-2 text-slate-600 hover:bg-gray-100 xl:hidden" aria-label="Ouvrir le menu" @click="menuMobile = true">
            <Menu class="size-6" />
          </button>
          <NuxtLink to="/" class="flex items-center gap-2 xl:hidden">
            <img :src="application.logos.edgvm" alt="" class="size-8 object-contain">
            <span class="font-extrabold text-brand-800">{{ application.nom }}</span>
          </NuxtLink>
          <p v-if="anneeCourante" class="hidden items-center gap-2 text-sm text-slate-500 xl:flex">
            <span class="size-2 rounded-full bg-leaf-400" />
            Année universitaire <span class="font-semibold text-slate-700">{{ anneeCourante.libelle }}</span>
          </p>
          <div class="ms-auto flex items-center gap-1 sm:gap-3">
            <ClocheNotifications />
            <MenuUtilisateur />
          </div>
        </div>
      </header>

      <main class="flex-1 px-3 pb-[calc(5.5rem+env(safe-area-inset-bottom))] pt-[calc(4rem+1.5rem)] sm:px-6 sm:pt-[calc(4rem+2rem)] xl:pb-8">
        <EcranMaintenance v-if="enMaintenance && !estSuperAdmin" />
        <template v-else>
          <div v-if="enMaintenance" class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-sun-300 bg-sun-50 px-4 py-3 text-sm text-sun-700" role="status">
            <span class="font-semibold">Maintenance en cours :</span> seule l'administration a accès à la plateforme.
            <NuxtLink to="/parametres?onglet=application" class="font-semibold underline underline-offset-2">Désactiver</NuxtLink>
          </div>
          <slot />
        </template>
      </main>

      <footer class="hidden border-t border-gray-200 bg-white px-6 py-4 text-sm text-slate-400 xl:flex xl:justify-between">
        <p>© {{ new Date().getFullYear() }} {{ application.nom }}, {{ application.universite }}</p>
        <p>{{ application.ecole }}</p>
      </footer>
    </div>

    <!-- Menu rapide en bas (mobile et tablette) -->
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] xl:hidden" aria-label="Menu rapide">
      <ul class="grid" :style="{ gridTemplateColumns: `repeat(${liensDuBas.length}, minmax(0, 1fr))` }">
        <li v-for="lien in liensDuBas" :key="lien.libelle">
          <NuxtLink
            :to="lien.vers"
            class="flex h-16 flex-col items-center justify-center gap-1 text-xs font-semibold"
            :class="estActif(lien.vers) ? 'text-brand-700' : 'text-slate-400'"
          >
            <span class="flex h-7 w-12 items-center justify-center rounded-full" :class="estActif(lien.vers) ? 'bg-brand-50' : ''">
              <component :is="lien.icone" class="size-5" />
            </span>
            {{ lien.court ?? lien.libelle }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
  </div>
</template>

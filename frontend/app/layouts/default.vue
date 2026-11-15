<script setup lang="ts">
import { LogOut, Menu } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'

const { utilisateur, deconnecter } = useAuth()
const { liensDuBas, estActif } = useNavigation()
const menuOuvert = ref(false)
</script>

<template>
  <div class="min-h-dvh bg-muted/40 xl:pl-64">
    <!-- Barre latérale (grand écran) : courte, sans défilement -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-sidebar text-sidebar-foreground xl:flex">
      <NuxtLink to="/" class="flex items-center gap-3 border-b border-sidebar-border px-5 py-4">
        <img src="/images/logo-edgvm-112.webp" alt="" class="size-10 rounded-md bg-white object-contain p-0.5">
        <span class="leading-tight">
          <span class="block font-bold text-white">EDGVM</span>
          <span class="block text-xs text-sidebar-foreground/75">Plateforme de l'École</span>
        </span>
      </NuxtLink>
      <div class="flex-1 p-3">
        <NavigationLiens sombre />
      </div>
      <div class="border-t border-sidebar-border p-3">
        <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-foreground/85 hover:bg-sidebar-accent/60 hover:text-white" @click="deconnecter()">
          <LogOut class="size-4" /> Se déconnecter
        </button>
      </div>
    </aside>

    <!-- En-tête : menu hamburger toujours présent sous xl -->
    <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b bg-white/95 px-3 backdrop-blur sm:px-5">
      <Sheet v-model:open="menuOuvert">
        <SheetTrigger as-child>
          <Button variant="ghost" size="icon" class="xl:hidden" aria-label="Ouvrir le menu">
            <Menu class="size-5" />
          </Button>
        </SheetTrigger>
        <SheetContent side="left" class="w-72 p-0">
          <SheetHeader class="border-b px-5 py-4 text-left">
            <SheetTitle class="flex items-center gap-3">
              <img src="/images/logo-edgvm-112.webp" alt="" class="size-9 rounded-md object-contain">
              EDGVM
            </SheetTitle>
            <SheetDescription>{{ utilisateur?.name }}, {{ utilisateur?.role_libelle.toLowerCase() }}</SheetDescription>
          </SheetHeader>
          <div class="p-3">
            <NavigationLiens @choisi="menuOuvert = false" />
          </div>
          <div class="border-t p-3">
            <Button variant="ghost" class="w-full justify-start" @click="deconnecter()">
              <LogOut /> Se déconnecter
            </Button>
          </div>
        </SheetContent>
      </Sheet>
      <NuxtLink to="/" class="flex items-center gap-2 xl:hidden">
        <img src="/images/logo-edgvm-112.webp" alt="" class="size-8 rounded object-contain">
        <span class="font-bold text-brand-900">EDGVM</span>
      </NuxtLink>
      <div class="ml-auto">
        <MenuUtilisateur />
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 pb-[calc(6rem+env(safe-area-inset-bottom))] pt-5 sm:px-6 xl:pb-10">
      <slot />
    </main>

    <!-- Menu du bas (mobile et tablette) -->
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t bg-white pb-[env(safe-area-inset-bottom)] xl:hidden" aria-label="Menu rapide">
      <ul class="grid" :style="{ gridTemplateColumns: `repeat(${liensDuBas.length}, minmax(0, 1fr))` }">
        <li v-for="lien in liensDuBas" :key="lien.libelle">
          <NuxtLink
            :to="lien.vers"
            class="flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium"
            :class="estActif(lien.vers) ? 'text-brand-800' : 'text-muted-foreground'"
          >
            <span class="flex h-7 w-12 items-center justify-center rounded-full" :class="estActif(lien.vers) ? 'bg-accent' : ''">
              <component :is="lien.icone" class="size-5" />
            </span>
            {{ lien.court ?? lien.libelle }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
  </div>
</template>

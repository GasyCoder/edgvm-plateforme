<script setup lang="ts">
import { ChevronDown, CircleUserRound, LogOut, Monitor, Moon, Sun } from '@lucide/vue'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

const { utilisateur, deconnecter } = useAuth()
const { themePersonnel, theme } = useApplication()

/* Apparence personnelle : sinon celle choisie par l'École dans les Paramètres. */
const THEMES = [
  { valeur: 'clair', libelle: 'Clair', icone: Sun },
  { valeur: 'sombre', libelle: 'Sombre', icone: Moon },
  { valeur: 'systeme', libelle: 'Système', icone: Monitor },
] as const
</script>

<template>
  <DropdownMenu v-if="utilisateur">
    <DropdownMenuTrigger class="flex items-center gap-3 rounded-md py-1 text-left outline-none">
      <AvatarCompte :nom="utilisateur.name" :photo-url="utilisateur.photo_url" class="size-9 text-sm" />
      <span class="hidden leading-tight md:block">
        <span class="block text-[11px] font-medium text-brand-600">{{ utilisateur.role_libelle }}</span>
        <span class="flex items-center gap-1 text-sm font-bold text-slate-600">
          {{ utilisateur.name }}
          <ChevronDown class="size-4 text-slate-400" />
        </span>
      </span>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-72 p-0">
      <div class="flex items-center gap-3 border-b bg-gray-50 px-5 py-4">
        <AvatarCompte :nom="utilisateur.name" :photo-url="utilisateur.photo_url" class="size-10 text-sm" />
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-slate-700">{{ utilisateur.name }}</p>
          <p class="truncate text-xs text-slate-400">{{ utilisateur.email }}</p>
        </div>
      </div>
      <div class="p-2">
        <DropdownMenuItem as-child class="px-3 py-2 text-slate-600">
          <NuxtLink to="/mon-compte"><CircleUserRound /> Mon compte</NuxtLink>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <div class="px-3 py-2">
          <p class="mb-2 text-xs font-semibold text-slate-400">Apparence</p>
          <div class="grid grid-cols-3 gap-1 rounded-md bg-gray-100 p-1" role="radiogroup" aria-label="Apparence">
            <button
              v-for="t in THEMES"
              :key="t.valeur"
              type="button"
              role="radio"
              :aria-checked="theme === t.valeur"
              class="flex flex-col items-center gap-1 rounded px-1 py-1.5 text-xs font-medium transition-colors"
              :class="theme === t.valeur ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
              @click="themePersonnel = t.valeur"
            >
              <component :is="t.icone" class="size-4" aria-hidden="true" />{{ t.libelle }}
            </button>
          </div>
          <button v-if="themePersonnel" type="button" class="mt-1.5 text-xs text-slate-400 underline-offset-2 hover:text-brand-700 hover:underline" @click="themePersonnel = ''">Revenir au réglage de l'École</button>
        </div>
        <DropdownMenuSeparator />
        <DropdownMenuItem class="px-3 py-2 text-slate-600" @select="deconnecter()">
          <LogOut /> Se déconnecter
        </DropdownMenuItem>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

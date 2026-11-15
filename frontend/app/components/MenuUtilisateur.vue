<script setup lang="ts">
import { ChevronDown, CircleUserRound, LogOut } from '@lucide/vue'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

const { utilisateur, deconnecter } = useAuth()
</script>

<template>
  <DropdownMenu v-if="utilisateur">
    <DropdownMenuTrigger class="flex items-center gap-3 rounded-md py-1 text-left outline-none">
      <span class="flex size-9 flex-none items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ initiales(utilisateur.name) }}</span>
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
        <span class="flex size-10 flex-none items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ initiales(utilisateur.name) }}</span>
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
        <DropdownMenuItem class="px-3 py-2 text-slate-600" @select="deconnecter()">
          <LogOut /> Se déconnecter
        </DropdownMenuItem>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup lang="ts">
import { ChevronDown, CircleUserRound, LogOut } from '@lucide/vue'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

const { utilisateur, deconnecter } = useAuth()
</script>

<template>
  <DropdownMenu v-if="utilisateur">
    <DropdownMenuTrigger class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
      <span class="flex size-8 items-center justify-center rounded-full bg-brand-800 text-xs font-semibold text-white">{{ initiales(utilisateur.name) }}</span>
      <span class="hidden max-w-40 truncate font-medium sm:block">{{ utilisateur.name }}</span>
      <ChevronDown class="hidden size-4 text-muted-foreground sm:block" />
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-60">
      <DropdownMenuLabel class="font-normal">
        <p class="truncate font-medium">{{ utilisateur.name }}</p>
        <p class="truncate text-xs text-muted-foreground">{{ utilisateur.role_libelle }}</p>
      </DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuItem as-child>
        <NuxtLink to="/mon-compte"><CircleUserRound /> Mon compte</NuxtLink>
      </DropdownMenuItem>
      <DropdownMenuItem @select="deconnecter()">
        <LogOut /> Se déconnecter
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

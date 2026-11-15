<script setup lang="ts">
import { Bell, CheckCheck, CircleDollarSign, Clock4, ReceiptText, TriangleAlert, Presentation } from '@lucide/vue'
import { useIntervalFn } from '@vueuse/core'
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { NotificationPlateforme } from '~/types'

/**
 * Cloche de l'en-tête : notifications (paiements, nouveaux frais, rappels) et, pour l'administration,
 * rappel permanent du nombre de doctorants en retard. Actualisée chaque minute.
 */
const api = useApi()
const { estSuperAdmin } = useAuth()
const notifications = ref<NotificationPlateforme[]>([])
const nonLues = ref(0)
const enRetard = ref<number | null>(null)
const ouvert = ref(false)

async function charger() {
  try {
    const reponse = await api<{ data: NotificationPlateforme[], non_lues: number, en_retard: number | null }>('/api/notifications')
    notifications.value = reponse.data
    nonLues.value = reponse.non_lues
    enRetard.value = reponse.en_retard
  }
  catch {
    // Silencieux : la cloche réessaie à la prochaine minute.
  }
}
onMounted(charger)
useIntervalFn(charger, 60_000)
const route = useRoute()
watch(() => route.path, charger)

async function ouvrirNotification(notification: NotificationPlateforme) {
  ouvert.value = false
  if (!notification.lue) {
    notification.lue = true
    nonLues.value = Math.max(0, nonLues.value - 1)
    api(`/api/notifications/${notification.id}/lue`, { method: 'POST' }).catch(() => {})
  }
  if (notification.lien) await navigateTo(notification.lien)
}

async function toutMarquerLu() {
  notifications.value.forEach((n) => { n.lue = true })
  nonLues.value = 0
  await api('/api/notifications/tout-lu', { method: 'POST' }).catch(() => {})
}

const icone = (categorie: string) => ({ paiement: CircleDollarSign, frais: ReceiptText, rappel: Clock4, retards: TriangleAlert, seance: Presentation } as Record<string, typeof Bell>)[categorie] ?? Bell
const couleur = (categorie: string) => ({ paiement: 'bg-leaf-50 text-leaf-600', frais: 'bg-brand-50 text-brand-700', rappel: 'bg-sun-50 text-sun-700', retards: 'bg-red-50 text-destructive', seance: 'bg-brand-50 text-brand-700' } as Record<string, string>)[categorie] ?? 'bg-gray-100 text-slate-500'
const pastille = computed(() => nonLues.value > 99 ? '99+' : String(nonLues.value))
</script>

<template>
  <DropdownMenu v-model:open="ouvert">
    <DropdownMenuTrigger class="relative flex size-10 items-center justify-center rounded-full text-slate-500 outline-none hover:bg-gray-100 hover:text-slate-700" :aria-label="nonLues ? `Notifications, ${nonLues} non lues` : 'Notifications'">
      <Bell class="size-5" />
      <span v-if="nonLues" class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-white">{{ pastille }}</span>
      <span v-else-if="enRetard" class="absolute right-2 top-2 size-2 rounded-full bg-sun-500" />
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[min(24rem,calc(100vw-1.5rem))] p-0">
      <div class="flex items-center justify-between border-b px-4 py-3">
        <p class="font-bold text-slate-700">Notifications</p>
        <button v-if="nonLues" type="button" class="flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700" @click="toutMarquerLu"><CheckCheck class="size-4" /> Tout marquer comme lu</button>
      </div>

      <NuxtLink
        v-if="estSuperAdmin && enRetard"
        to="/paiements?situation=en_retard"
        class="flex items-center gap-3 border-b bg-red-50/60 px-4 py-3 hover:bg-red-50"
        @click="ouvert = false"
      >
        <span class="flex size-9 flex-none items-center justify-center rounded-full bg-red-100 text-destructive"><TriangleAlert class="size-4" /></span>
        <span class="min-w-0 text-sm">
          <span class="block font-semibold text-slate-700">{{ enRetard }} doctorant{{ enRetard > 1 ? 's' : '' }} en retard de paiement</span>
          <span class="block text-xs text-slate-500">Voir la liste et relancer</span>
        </span>
      </NuxtLink>

      <ul v-if="notifications.length" class="max-h-[60vh] overflow-y-auto">
        <li v-for="notification in notifications" :key="notification.id">
          <button type="button" class="flex w-full gap-3 border-b px-4 py-3 text-left last:border-b-0 hover:bg-gray-50" :class="notification.lue ? '' : 'bg-brand-50/40'" @click="ouvrirNotification(notification)">
            <span class="flex size-9 flex-none items-center justify-center rounded-full" :class="couleur(notification.categorie)"><component :is="icone(notification.categorie)" class="size-4" /></span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center justify-between gap-2">
                <span class="truncate text-sm font-semibold text-slate-700">{{ notification.titre }}</span>
                <span v-if="!notification.lue" class="size-2 flex-none rounded-full bg-brand-600" aria-label="non lue" />
              </span>
              <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500">{{ notification.message }}</span>
              <span class="mt-1 block text-[11px] text-slate-400">{{ depuis(notification.cree_le) }}</span>
            </span>
          </button>
        </li>
      </ul>
      <p v-else class="px-4 py-8 text-center text-sm text-slate-400">Aucune notification pour le moment.</p>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

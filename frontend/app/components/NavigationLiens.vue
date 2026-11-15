<script setup lang="ts">
/** Liste des liens du menu, partagée par la barre latérale et le menu hamburger. */
defineProps<{ sombre?: boolean }>()
const emit = defineEmits<{ choisi: [] }>()
const { liens, estActif } = useNavigation()
</script>

<template>
  <nav class="grid gap-1" aria-label="Menu principal">
    <NuxtLink
      v-for="lien in liens"
      :key="lien.libelle"
      :to="lien.vers"
      class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
      :class="sombre
        ? (estActif(lien.vers) ? 'bg-sidebar-accent text-white' : 'text-sidebar-foreground/85 hover:bg-sidebar-accent/60 hover:text-white')
        : (estActif(lien.vers) ? 'bg-accent text-brand-800' : 'text-foreground hover:bg-muted')"
      :aria-current="estActif(lien.vers) ? 'page' : undefined"
      @click="emit('choisi')"
    >
      <component :is="lien.icone" class="size-4 flex-none" :class="sombre && estActif(lien.vers) ? 'text-sidebar-primary' : ''" />
      {{ lien.libelle }}
    </NuxtLink>
  </nav>
</template>

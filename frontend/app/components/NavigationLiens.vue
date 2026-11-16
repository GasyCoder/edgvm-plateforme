<script setup lang="ts">
/**
 * Menu par sections, partagé par la barre latérale (bureau et mobile).
 * Le lien de la page ouverte est bien marqué : fond teinté, barre à gauche, texte et icône en couleur.
 */
defineProps<{ compact?: boolean }>()
const emit = defineEmits<{ choisi: [] }>()
const { sections, estActif } = useNavigation()
</script>

<template>
  <nav aria-label="Menu principal" class="px-3 py-4">
    <div v-for="(section, index) in sections" :key="section.titre">
      <h6
        class="relative whitespace-nowrap px-3 pb-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400 transition-opacity"
        :class="[index === 0 ? 'pt-1' : 'pt-5', compact ? 'opacity-0' : '']"
      >
        {{ section.titre }}
        <span v-if="compact && index > 0" class="absolute inset-x-2 top-1/2 h-px bg-gray-200" />
      </h6>
      <ul class="grid gap-0.5">
        <li v-for="lien in section.liens" :key="lien.libelle">
          <NuxtLink
            :to="lien.vers"
            class="group relative flex items-center rounded-md py-2 pe-3 ps-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
            :class="estActif(lien.vers) ? 'bg-brand-50 font-bold' : 'font-semibold hover:bg-gray-100'"
            :aria-current="estActif(lien.vers) ? 'page' : undefined"
            :title="compact ? lien.libelle : undefined"
            @click="emit('choisi')"
          >
            <span v-if="estActif(lien.vers)" class="absolute inset-y-1.5 start-0 w-1 rounded-full bg-brand-600" aria-hidden="true" />
            <component
              :is="lien.icone"
              class="me-3 size-5 flex-none transition-colors"
              :class="estActif(lien.vers) ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600'"
            />
            <span
              class="whitespace-nowrap text-[15px] tracking-tight transition-all"
              :class="[estActif(lien.vers) ? 'text-brand-800' : 'text-slate-600 group-hover:text-slate-800', compact ? 'opacity-0' : '']"
            >{{ lien.libelle }}</span>
          </NuxtLink>
        </li>
      </ul>
    </div>
  </nav>
</template>

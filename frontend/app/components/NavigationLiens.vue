<script setup lang="ts">
/** Menu par sections, partagé par la barre latérale (bureau et mobile). */
defineProps<{ compact?: boolean }>()
const emit = defineEmits<{ choisi: [] }>()
const { sections, estActif } = useNavigation()
</script>

<template>
  <nav aria-label="Menu principal" class="py-4">
    <div v-for="(section, index) in sections" :key="section.titre">
      <h6
        class="relative whitespace-nowrap px-6 pb-2 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 transition-opacity"
        :class="[index === 0 ? 'pt-1' : 'pt-8', compact ? 'opacity-0' : '']"
      >
        {{ section.titre }}
        <span v-if="compact && index > 0" class="absolute inset-x-4 top-1/2 h-px bg-gray-200" />
      </h6>
      <ul>
        <li v-for="lien in section.liens" :key="lien.libelle">
          <NuxtLink
            :to="lien.vers"
            class="group flex items-center py-2.5 pe-6 ps-6 font-bold tracking-tight transition-colors"
            :aria-current="estActif(lien.vers) ? 'page' : undefined"
            :title="compact ? lien.libelle : undefined"
            @click="emit('choisi')"
          >
            <component
              :is="lien.icone"
              class="me-3 size-5 flex-none transition-colors"
              :class="estActif(lien.vers) ? 'text-brand-600' : 'text-slate-400 group-hover:text-brand-600'"
            />
            <span
              class="whitespace-nowrap text-[15px] transition-all"
              :class="[estActif(lien.vers) ? 'text-brand-700' : 'text-slate-600 group-hover:text-brand-700', compact ? 'opacity-0' : '']"
            >{{ lien.libelle }}</span>
          </NuxtLink>
        </li>
      </ul>
    </div>
  </nav>
</template>

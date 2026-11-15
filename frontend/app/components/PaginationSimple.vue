<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import type { Pagine } from '~/types'

const props = defineProps<{ meta: Pagine<unknown>['meta'] }>()
const emit = defineEmits<{ page: [numero: number] }>()
const aller = (numero: number) => emit('page', Math.min(Math.max(numero, 1), props.meta.last_page))
</script>

<template>
  <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
    <p>{{ meta.from }} à {{ meta.to }} sur {{ meta.total }}</p>
    <div class="flex items-center gap-2">
      <Button variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="aller(meta.current_page - 1)">
        <ChevronLeft /> <span class="hidden sm:inline">Précédent</span>
      </Button>
      <span class="tabular-nums">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <Button variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="aller(meta.current_page + 1)">
        <span class="hidden sm:inline">Suivant</span> <ChevronRight />
      </Button>
    </div>
  </div>
</template>

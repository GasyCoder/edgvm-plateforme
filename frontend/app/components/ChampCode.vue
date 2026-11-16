<script setup lang="ts">
/**
 * Code à N chiffres (4 par défaut) en N cases : passage automatique à la case suivante, retour arrière, collage du code entier,
 * et remplissage automatique sur téléphone (autocomplete « one-time-code »).
 */
const props = withDefaults(defineProps<{ longueur?: number, invalide?: boolean, desactive?: boolean }>(), { longueur: 4 })
const code = defineModel<string>({ default: '' })
const emit = defineEmits<{ complet: [code: string] }>()

const cases = ref<HTMLInputElement[]>([])
const chiffres = computed(() => Array.from({ length: props.longueur }, (_, i) => code.value[i] ?? ''))

function ecrire(valeur: string) {
  /* Valeur gardée localement : le modèle du parent n'est mis à jour qu'après cet appel. */
  const nettoye = valeur.replace(/\D/g, '').slice(0, props.longueur)
  code.value = nettoye
  if (nettoye.length === props.longueur) emit('complet', nettoye)
}

function saisir(index: number, evenement: Event) {
  const champ = evenement.target as HTMLInputElement
  const tape = champ.value.replace(/\D/g, '')
  if (tape.length > 1) {
    ecrire(tape)
    cases.value[Math.min(tape.length, props.longueur) - 1]?.focus()
    return
  }
  const liste = chiffres.value.slice()
  liste[index] = tape
  ecrire(liste.join(''))
  champ.value = tape
  if (tape && index < props.longueur - 1) cases.value[index + 1]?.focus()
}

function touche(index: number, evenement: KeyboardEvent) {
  if (evenement.key === 'Backspace' && !chiffres.value[index] && index > 0) {
    const liste = chiffres.value.slice()
    liste[index - 1] = ''
    ecrire(liste.join(''))
    cases.value[index - 1]?.focus()
    evenement.preventDefault()
  }
  if (evenement.key === 'ArrowLeft' && index > 0) cases.value[index - 1]?.focus()
  if (evenement.key === 'ArrowRight' && index < props.longueur - 1) cases.value[index + 1]?.focus()
}

function coller(evenement: ClipboardEvent) {
  const texte = evenement.clipboardData?.getData('text') ?? ''
  if (/\d/.test(texte)) {
    evenement.preventDefault()
    ecrire(texte)
    cases.value[Math.min(code.value.length, props.longueur) - 1]?.focus()
  }
}

defineExpose({ focus: () => cases.value[0]?.focus(), vider: () => { code.value = ''; cases.value[0]?.focus() } })
</script>

<template>
  <div class="flex justify-center" :class="longueur > 4 ? 'gap-2' : 'gap-3'" role="group" :aria-label="`Code à ${longueur} chiffres`">
    <input
      v-for="(chiffre, index) in chiffres"
      :key="index"
      :ref="el => { if (el) cases[index] = el as HTMLInputElement }"
      :value="chiffre"
      type="text"
      inputmode="numeric"
      pattern="[0-9]*"
      maxlength="4"
      :autocomplete="index === 0 ? 'one-time-code' : 'off'"
      :aria-label="`Chiffre ${index + 1} sur ${longueur}`"
      :aria-invalid="invalide || undefined"
      :disabled="desactive"
      class="rounded-md border bg-white text-center text-2xl font-bold tabular-nums text-slate-800 outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 disabled:opacity-60"
      :class="[longueur > 4 ? 'h-14 w-11 sm:w-12' : 'size-14 sm:size-16', invalide ? 'border-destructive' : chiffre ? 'border-brand-400' : 'border-gray-300']"
      @input="saisir(index, $event)"
      @keydown="touche(index, $event)"
      @paste="coller"
      @focus="($event.target as HTMLInputElement).select()"
    >
  </div>
</template>

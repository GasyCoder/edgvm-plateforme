<script setup lang="ts">
import { AlignCenter, AlignJustify, AlignLeft, AlignRight, Bold, Italic, List, ListOrdered, Redo2, Underline, Undo2 } from '@lucide/vue'
import TextAlign from '@tiptap/extension-text-align'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'

/**
 * Éditeur du texte des actes : gras, italique, souligné, alignement, listes. Les variables s'insèrent avec `inserer()`.
 */
const modele = defineModel<string>({ required: true })

const editeur = useEditor({
  content: modele.value,
  extensions: [
    StarterKit.configure({ heading: { levels: [2, 3] }, link: false, code: false, codeBlock: false, horizontalRule: false, blockquote: false, strike: false }),
    TextAlign.configure({ types: ['paragraph', 'heading'], alignments: ['left', 'center', 'right', 'justify'] }),
  ],
  editorProps: { attributes: { class: 'texte-acte min-h-[22rem] px-6 py-5 outline-none sm:px-10' } },
  onUpdate: ({ editor }) => { modele.value = editor.getHTML() },
})

watch(modele, (valeur) => {
  if (editeur.value && valeur !== editeur.value.getHTML()) editeur.value.commands.setContent(valeur, { emitUpdate: false })
})

function inserer(texte: string) {
  editeur.value?.chain().focus().insertContent(texte).run()
}
defineExpose({ inserer })

const boutons = computed(() => {
  const e = editeur.value
  if (!e) return []
  return [
    [
      { icone: Bold, libelle: 'Gras', actif: e.isActive('bold'), action: () => e.chain().focus().toggleBold().run() },
      { icone: Italic, libelle: 'Italique', actif: e.isActive('italic'), action: () => e.chain().focus().toggleItalic().run() },
      { icone: Underline, libelle: 'Souligné', actif: e.isActive('underline'), action: () => e.chain().focus().toggleUnderline().run() },
    ],
    [
      { icone: AlignLeft, libelle: 'Aligner à gauche', actif: e.isActive({ textAlign: 'left' }), action: () => e.chain().focus().setTextAlign('left').run() },
      { icone: AlignCenter, libelle: 'Centrer', actif: e.isActive({ textAlign: 'center' }), action: () => e.chain().focus().setTextAlign('center').run() },
      { icone: AlignRight, libelle: 'Aligner à droite', actif: e.isActive({ textAlign: 'right' }), action: () => e.chain().focus().setTextAlign('right').run() },
      { icone: AlignJustify, libelle: 'Justifier', actif: e.isActive({ textAlign: 'justify' }), action: () => e.chain().focus().setTextAlign('justify').run() },
    ],
    [
      { icone: List, libelle: 'Liste à puces', actif: e.isActive('bulletList'), action: () => e.chain().focus().toggleBulletList().run() },
      { icone: ListOrdered, libelle: 'Liste numérotée', actif: e.isActive('orderedList'), action: () => e.chain().focus().toggleOrderedList().run() },
    ],
    [
      { icone: Undo2, libelle: 'Annuler', actif: false, action: () => e.chain().focus().undo().run() },
      { icone: Redo2, libelle: 'Rétablir', actif: false, action: () => e.chain().focus().redo().run() },
    ],
  ]
})
</script>

<template>
  <div class="overflow-hidden rounded-md border border-gray-300 bg-white focus-within:border-brand-400">
    <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-2 py-1.5" role="toolbar" aria-label="Mise en forme">
      <template v-for="(groupe, index) in boutons" :key="index">
        <span v-if="index" class="mx-1 h-5 w-px bg-gray-300" />
        <button
          v-for="bouton in groupe"
          :key="bouton.libelle"
          type="button"
          class="flex size-8 items-center justify-center rounded text-slate-500 hover:bg-white hover:text-slate-800"
          :class="bouton.actif ? 'bg-white text-brand-700 shadow-sm' : ''"
          :title="bouton.libelle"
          :aria-label="bouton.libelle"
          :aria-pressed="bouton.actif"
          @click="bouton.action()"
        >
          <component :is="bouton.icone" class="size-4" />
        </button>
      </template>
    </div>
    <EditorContent :editor="editeur" />
  </div>
</template>

<style>
/* Aperçu fidèle au PDF : police à empattements, interligne des actes */
.texte-acte { font-family: 'Times New Roman', Times, serif; font-size: 16px; line-height: 1.65; color: #111; }
.texte-acte p { margin: 0 0 0.75em; }
.texte-acte h2 { font-size: 1.15em; font-weight: 700; margin: 1em 0 0.5em; }
.texte-acte h3 { font-size: 1.05em; font-weight: 700; margin: 1em 0 0.5em; }
.texte-acte ul { list-style: disc; padding-left: 1.5em; margin: 0 0 0.75em; }
.texte-acte ol { list-style: decimal; padding-left: 1.5em; margin: 0 0 0.75em; }
</style>

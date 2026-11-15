<script setup lang="ts">
import { useElementSize } from '@vueuse/core'
import type { Finances } from '~/types'

/**
 * Graphiques de la situation financière d'un doctorant :
 * 1. répartition du dû (payé, à payer dans les délais, en retard) ;
 * 2. colonnes par année universitaire ;
 * 3. versements cumulés dans le temps, face au total dû.
 * SVG dessiné à la main ; chaque valeur est aussi lisible sans survol (légende chiffrée et vue tableau).
 */
const props = defineProps<{ finances: Finances }>()

/* Couleurs validées (scripts/validate_palette.js, surface blanche) : vert payé, or à payer, rouge en retard. */
const COULEURS = { paye: '#378a49', aPayer: '#d4ae16', retard: '#c42b2b' } as const
const ENCRE_DANS = { paye: '#ffffff', aPayer: '#0b0b0b', retard: '#ffffff' } as const
const LIBELLES = { paye: 'Payé', aPayer: 'À payer dans les délais', retard: 'En retard' } as const
type Part = keyof typeof COULEURS
const ORDRE: Part[] = ['paye', 'retard', 'aPayer']

const fraisActifs = computed(() => props.finances.frais.filter(f => !f.annule_le))
const totaux = computed(() => {
  const t = { paye: 0, aPayer: 0, retard: 0 }
  for (const f of fraisActifs.value) {
    t.paye += f.paye
    if (f.situation === 'en_retard') t.retard += f.reste
    else t.aPayer += f.reste
  }
  return t
})
const du = computed(() => totaux.value.paye + totaux.value.aPayer + totaux.value.retard)
const part = (v: number) => du.value ? Math.round(v / du.value * 100) : 0

/* Infobulle partagée (le texte passe par l'interpolation Vue : jamais de HTML injecté) */
const infobulle = ref<{ x: number, y: number, titre: string, lignes: Array<{ couleur?: string, libelle: string, valeur: string }> } | null>(null)
const cadre = ref<HTMLElement | null>(null)
function montrer(evenement: PointerEvent | FocusEvent, titre: string, lignes: Array<{ couleur?: string, libelle: string, valeur: string }>) {
  const boite = cadre.value!.getBoundingClientRect()
  const cible = (evenement.currentTarget as Element).getBoundingClientRect()
  const x = 'clientX' in evenement ? evenement.clientX - boite.left : cible.left + cible.width / 2 - boite.left
  const y = 'clientY' in evenement ? evenement.clientY - boite.top : cible.top - boite.top
  infobulle.value = { x, y, titre, lignes }
}
const cacher = () => { infobulle.value = null }

/* 1. Barre de répartition (une seule barre, parties proportionnelles) */
const zoneBarre = ref<HTMLElement | null>(null)
const { width: largeurBarre } = useElementSize(zoneBarre)
const segments = computed(() => {
  const utiles = ORDRE.filter(cle => totaux.value[cle] > 0)
  const espace = Math.max(0, largeurBarre.value - 2 * (utiles.length - 1))
  let x = 0
  return utiles.map((cle) => {
    const largeur = du.value ? espace * totaux.value[cle] / du.value : 0
    const s = { cle, x, largeur, valeur: totaux.value[cle], pourcent: part(totaux.value[cle]) }
    x += largeur + 2
    return s
  })
})

/* 2. Colonnes par année universitaire */
const annees = computed(() => {
  const groupes = new Map<string, { paye: number, aPayer: number, retard: number }>()
  for (const f of fraisActifs.value) {
    const cle = f.annee ?? 'Sans année'
    const g = groupes.get(cle) ?? { paye: 0, aPayer: 0, retard: 0 }
    g.paye += f.paye
    if (f.situation === 'en_retard') g.retard += f.reste
    else g.aPayer += f.reste
    groupes.set(cle, g)
  }
  return [...groupes.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([annee, v]) => ({ annee, ...v, total: v.paye + v.aPayer + v.retard }))
})
const zoneColonnes = ref<HTMLElement | null>(null)
const { width: largeurColonnes } = useElementSize(zoneColonnes)
const HAUTEUR = 200
const MARGE = { haut: 22, droite: 8, bas: 26, gauche: 44 }
function graduations(max: number): number[] {
  if (max <= 0) return [0]
  const brut = max / 4
  const puissance = 10 ** Math.floor(Math.log10(brut))
  const pas = [1, 2, 2.5, 5, 10].map(m => m * puissance).find(p => p >= brut)!
  return Array.from({ length: Math.ceil(max / pas) + 1 }, (_, i) => i * pas)
}
const colonnes = computed(() => {
  const ticks = graduations(Math.max(...annees.value.map(a => a.total), 1))
  const plafond = ticks[ticks.length - 1]!
  const hauteurUtile = HAUTEUR - MARGE.haut - MARGE.bas
  const largeurUtile = Math.max(0, largeurColonnes.value - MARGE.gauche - MARGE.droite)
  const bande = annees.value.length ? largeurUtile / annees.value.length : 0
  const epaisseur = Math.min(24, bande * 0.5)
  const y = (v: number) => MARGE.haut + hauteurUtile - (v / plafond) * hauteurUtile
  return {
    ticks: ticks.map(t => ({ valeur: t, y: y(t) })),
    barres: annees.value.map((a, i) => {
      const centre = MARGE.gauche + bande * i + bande / 2
      let base = y(0)
      /* Espace de 2 px (couleur du fond) entre deux parties ; la première part de la ligne de base */
      const morceaux = ORDRE.filter(cle => a[cle] > 0).map((cle, rang) => {
        const h = (a[cle] / plafond) * hauteurUtile
        const m = { cle, x: centre - epaisseur / 2, y: base - h, h: Math.max(0, rang === 0 ? h : h - 2), valeur: a[cle] }
        base -= h
        return m
      })
      return { ...a, centre, epaisseur, morceaux, haut: y(a.total) }
    }),
  }
})
/** Rectangle aux coins supérieurs arrondis (bout des données), carré à la ligne de base */
function chemin(x: number, y: number, l: number, h: number, arrondi: boolean): string {
  const r = arrondi ? Math.min(4, l / 2, h) : 0
  return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + l - r}Q${x + l},${y} ${x + l},${y + r}V${y + h}Z`
}

/* 3. Versements cumulés */
const paiementsValides = computed(() => props.finances.paiements.filter(p => !p.annule_le).sort((a, b) => a.date_paiement.localeCompare(b.date_paiement)))
const zoneCourbe = ref<HTMLElement | null>(null)
const { width: largeurCourbe } = useElementSize(zoneCourbe)
const courbe = computed(() => {
  const points = paiementsValides.value.reduce<Array<{ date: number, montant: number, cumul: number, recu: string }>>((liste, p) => {
    const cumul = (liste[liste.length - 1]?.cumul ?? 0) + p.montant
    liste.push({ date: new Date(`${p.date_paiement}T00:00:00`).getTime(), montant: p.montant, cumul, recu: p.numero_recu })
    return liste
  }, [])
  if (!points.length) return null
  const debut = points[0]!.date - 15 * 86400000
  const fin = Math.max(Date.now(), points[points.length - 1]!.date) + 10 * 86400000
  const ticks = graduations(Math.max(du.value, points[points.length - 1]!.cumul))
  const plafond = ticks[ticks.length - 1]!
  const largeurUtile = Math.max(0, largeurCourbe.value - MARGE.gauche - MARGE.droite)
  const hauteurUtile = HAUTEUR - MARGE.haut - MARGE.bas
  const x = (t: number) => MARGE.gauche + ((t - debut) / (fin - debut)) * largeurUtile
  const y = (v: number) => MARGE.haut + hauteurUtile - (v / plafond) * hauteurUtile
  let trace = `M${x(debut)},${y(0)}`
  for (const p of points) trace += `H${x(p.date)}V${y(p.cumul)}`
  trace += `H${x(fin)}`
  const aire = `${trace}V${y(0)}H${x(debut)}Z`
  /* Graduations de dates : début de mois, au plus 6 */
  const mois: Array<{ x: number, libelle: string }> = []
  const curseur = new Date(debut); curseur.setDate(1); curseur.setMonth(curseur.getMonth() + 1)
  const tous: Date[] = []
  while (curseur.getTime() <= fin) { tous.push(new Date(curseur)); curseur.setMonth(curseur.getMonth() + 1) }
  /* Une étiquette tous les ~72 px au plus, pour qu'elles ne se chevauchent jamais */
  const pas = Math.max(1, Math.ceil(tous.length / Math.max(2, Math.floor(largeurUtile / 72))))
  tous.filter((_, i) => i % pas === 0).forEach(d => mois.push({ x: x(d.getTime()), libelle: d.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }) }))
  return { trace, aire, points: points.map(p => ({ ...p, px: x(p.date), py: y(p.cumul) })), ticks: ticks.map(t => ({ valeur: t, y: y(t) })), yDu: y(du.value), mois, base: y(0), gauche: MARGE.gauche, droite: x(fin) }
})
const survolCourbe = ref<number | null>(null)
function suivre(evenement: PointerEvent) {
  const c = courbe.value
  if (!c) return
  const boite = (evenement.currentTarget as Element).getBoundingClientRect()
  const px = evenement.clientX - boite.left
  const proche = c.points.reduce((meilleur, p, i) => Math.abs(p.px - px) < Math.abs(c.points[meilleur]!.px - px) ? i : meilleur, 0)
  survolCourbe.value = proche
  const p = c.points[proche]!
  montrer(evenement, new Date(p.date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }), [
    { couleur: COULEURS.paye, libelle: `Versement ${p.recu}`, valeur: formaterMontant(p.montant) },
    { libelle: 'Total versé à cette date', valeur: formaterMontant(p.cumul) },
  ])
}

const vueTableau = ref(false)
</script>

<template>
  <div ref="cadre" class="relative grid gap-6">
    <!-- 1. Répartition -->
    <section aria-labelledby="titre-repartition">
      <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 id="titre-repartition" class="text-sm font-semibold text-slate-700">Où en sont les paiements</h3>
        <p class="text-sm text-slate-500"><span class="font-semibold text-slate-700">{{ part(totaux.paye) }} %</span> du total payé</p>
      </div>
      <div ref="zoneBarre" class="relative h-7 w-full" role="img" :aria-label="`Sur ${formaterMontant(du)} dus : ${ORDRE.map(c => `${LIBELLES[c]} ${formaterMontant(totaux[c])}`).join(', ')}.`">
        <div
          v-for="s in segments"
          :key="s.cle"
          class="absolute inset-y-0 flex items-center rounded outline-none transition-[filter] hover:brightness-110 focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-1"
          :style="{ left: `${s.x}px`, width: `${s.largeur}px`, background: COULEURS[s.cle] }"
          tabindex="0"
          :aria-label="`${LIBELLES[s.cle]} : ${formaterMontant(s.valeur)}, ${s.pourcent} %`"
          @pointermove="e => montrer(e, LIBELLES[s.cle], [{ couleur: COULEURS[s.cle], libelle: `${s.pourcent} % du total dû`, valeur: formaterMontant(s.valeur) }])"
          @pointerleave="cacher"
          @focus="e => montrer(e, LIBELLES[s.cle], [{ couleur: COULEURS[s.cle], libelle: `${s.pourcent} % du total dû`, valeur: formaterMontant(s.valeur) }])"
          @blur="cacher"
        >
          <span v-if="s.largeur >= 44" class="ps-2 text-xs font-semibold" :style="{ color: ENCRE_DANS[s.cle] }">{{ s.pourcent }} %</span>
        </div>
      </div>
      <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
        <li v-for="cle in ORDRE" :key="cle" class="flex items-center gap-2">
          <span class="size-2.5 flex-none rounded-sm" :style="{ background: COULEURS[cle] }" aria-hidden="true" />
          <span class="text-slate-500">{{ LIBELLES[cle] }}</span>
          <span class="font-semibold tabular-nums text-slate-700">{{ formaterMontant(totaux[cle]) }}</span>
        </li>
      </ul>
    </section>

    <div class="@container">
      <div class="grid gap-6 @2xl:grid-cols-2">
        <!-- 2. Par année -->
        <section aria-labelledby="titre-annees">
          <h3 id="titre-annees" class="text-sm font-semibold text-slate-700">Par année universitaire</h3>
          <p class="mb-2 text-xs text-slate-400">Montants en ariary ; total dû au-dessus de chaque colonne.</p>
          <div ref="zoneColonnes" class="w-full">
            <svg v-if="largeurColonnes" :width="largeurColonnes" :height="HAUTEUR" role="img" :aria-label="`Montants par année : ${annees.map(a => `${a.annee}, ${formaterMontant(a.total)} dus dont ${formaterMontant(a.paye)} payés`).join(' ; ')}.`">
              <g>
                <template v-for="t in colonnes.ticks" :key="t.valeur">
                  <line :x1="MARGE.gauche" :x2="largeurColonnes - MARGE.droite" :y1="t.y" :y2="t.y" :stroke="t.valeur === 0 ? '#c3c2b7' : '#e1e0d9'" stroke-width="1" shape-rendering="crispEdges" />
                  <text :x="MARGE.gauche - 8" :y="t.y" text-anchor="end" dominant-baseline="middle" class="fill-slate-400 text-[11px] tabular-nums">{{ formaterCompact(t.valeur) }}</text>
                </template>
              </g>
              <g v-for="b in colonnes.barres" :key="b.annee">
                <path
                  v-for="(m, i) in b.morceaux"
                  :key="m.cle"
                  :d="chemin(m.x, m.y, b.epaisseur, m.h, i === b.morceaux.length - 1)"
                  :fill="COULEURS[m.cle]"
                  class="outline-none transition-[filter] hover:brightness-110 focus-visible:brightness-110"
                  tabindex="0"
                  :aria-label="`${b.annee}, ${LIBELLES[m.cle]} : ${formaterMontant(m.valeur)}`"
                  @pointermove="e => montrer(e, b.annee, ORDRE.filter(c => b[c] > 0).map(c => ({ couleur: COULEURS[c], libelle: LIBELLES[c], valeur: formaterMontant(b[c]) })))"
                  @pointerleave="cacher"
                  @focus="e => montrer(e, b.annee, ORDRE.filter(c => b[c] > 0).map(c => ({ couleur: COULEURS[c], libelle: LIBELLES[c], valeur: formaterMontant(b[c]) })))"
                  @blur="cacher"
                />
                <text :x="b.centre" :y="b.haut - 6" text-anchor="middle" class="fill-slate-600 text-[11px] font-semibold tabular-nums">{{ formaterCompact(b.total) }}</text>
                <text :x="b.centre" :y="HAUTEUR - 8" text-anchor="middle" class="fill-slate-500 text-[11px]">{{ b.annee }}</text>
              </g>
            </svg>
          </div>
        </section>

        <!-- 3. Versements cumulés -->
        <section aria-labelledby="titre-versements">
          <h3 id="titre-versements" class="text-sm font-semibold text-slate-700">Versements cumulés</h3>
          <p class="mb-2 text-xs text-slate-400">Chaque marche est un paiement ; la ligne grise est le total dû.</p>
          <div ref="zoneCourbe" class="w-full">
            <p v-if="!courbe" class="flex h-[200px] items-center justify-center rounded-md border border-dashed text-sm text-slate-400">Aucun versement enregistré.</p>
            <svg
              v-else-if="largeurCourbe"
              :width="largeurCourbe"
              :height="HAUTEUR"
              role="img"
              :aria-label="`Versements cumulés : ${formaterMontant(totaux.paye)} versés sur ${formaterMontant(du)} dus, en ${courbe.points.length} paiement(s).`"
              @pointermove="suivre"
              @pointerleave="survolCourbe = null; cacher()"
            >
              <template v-for="t in courbe.ticks" :key="t.valeur">
                <line :x1="courbe.gauche" :x2="courbe.droite" :y1="t.y" :y2="t.y" :stroke="t.valeur === 0 ? '#c3c2b7' : '#e1e0d9'" stroke-width="1" shape-rendering="crispEdges" />
                <text :x="courbe.gauche - 8" :y="t.y" text-anchor="end" dominant-baseline="middle" class="fill-slate-400 text-[11px] tabular-nums">{{ formaterCompact(t.valeur) }}</text>
              </template>
              <text v-for="m in courbe.mois" :key="m.libelle" :x="m.x" :y="HAUTEUR - 8" text-anchor="middle" class="fill-slate-400 text-[11px]">{{ m.libelle }}</text>
              <line :x1="courbe.gauche" :x2="courbe.droite" :y1="courbe.yDu" :y2="courbe.yDu" stroke="#898781" stroke-width="1" shape-rendering="crispEdges" />
              <text :x="courbe.droite" :y="courbe.yDu - 6" text-anchor="end" class="fill-slate-500 text-[11px]">Total dû {{ formaterCompact(du) }}</text>
              <path :d="courbe.aire" :fill="COULEURS.paye" fill-opacity="0.1" />
              <path :d="courbe.trace" fill="none" :stroke="COULEURS.paye" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <line v-if="survolCourbe !== null" :x1="courbe.points[survolCourbe]!.px" :x2="courbe.points[survolCourbe]!.px" :y1="MARGE.haut" :y2="courbe.base" stroke="#898781" stroke-width="1" />
              <circle v-for="(p, i) in courbe.points" :key="p.recu" :cx="p.px" :cy="p.py" :r="survolCourbe === i ? 5 : 4" :fill="COULEURS.paye" stroke="#ffffff" stroke-width="2" />
              <text :x="courbe.points[courbe.points.length - 1]!.px + 8" :y="courbe.points[courbe.points.length - 1]!.py + 4" class="fill-slate-700 text-[11px] font-semibold tabular-nums" :text-anchor="courbe.points[courbe.points.length - 1]!.px > courbe.droite - 60 ? 'end' : 'start'" :dx="courbe.points[courbe.points.length - 1]!.px > courbe.droite - 60 ? -16 : 0">{{ formaterCompact(courbe.points[courbe.points.length - 1]!.cumul) }}</text>
            </svg>
          </div>
        </section>
      </div>
    </div>

    <!-- Vue tableau : toutes les valeurs sans survol -->
    <div>
      <button type="button" class="text-xs font-medium text-brand-600 hover:text-brand-700" :aria-expanded="vueTableau" @click="vueTableau = !vueTableau">
        {{ vueTableau ? 'Masquer les données' : 'Voir les données en tableau' }}
      </button>
      <table v-if="vueTableau" class="mt-2 w-full text-sm">
        <thead>
          <tr class="border-b text-slate-400">
            <th class="py-1.5 text-start font-normal">Année</th>
            <th class="py-1.5 text-end font-normal">Dû</th>
            <th class="py-1.5 text-end font-normal">Payé</th>
            <th class="py-1.5 text-end font-normal">À payer</th>
            <th class="py-1.5 text-end font-normal">En retard</th>
          </tr>
        </thead>
        <tbody class="tabular-nums">
          <tr v-for="a in annees" :key="a.annee" class="border-b border-gray-100">
            <td class="py-1.5 text-slate-600">{{ a.annee }}</td>
            <td class="py-1.5 text-end text-slate-600">{{ formaterMontant(a.total) }}</td>
            <td class="py-1.5 text-end text-slate-600">{{ formaterMontant(a.paye) }}</td>
            <td class="py-1.5 text-end text-slate-600">{{ formaterMontant(a.aPayer) }}</td>
            <td class="py-1.5 text-end text-slate-600">{{ formaterMontant(a.retard) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Infobulle : la valeur d'abord, le libellé ensuite -->
    <div
      v-if="infobulle"
      class="pointer-events-none absolute z-20 w-max max-w-72 -translate-x-1/2 -translate-y-full rounded-md border bg-white px-3 py-2 text-xs shadow-lg"
      :style="{ left: `${Math.min(Math.max(infobulle.x, 90), (cadre?.clientWidth ?? 0) - 90)}px`, top: `${infobulle.y - 10}px` }"
      role="status"
    >
      <p class="mb-1 font-semibold text-slate-700">{{ infobulle.titre }}</p>
      <p v-for="(l, i) in infobulle.lignes" :key="i" class="flex items-center gap-2">
        <span v-if="l.couleur" class="h-0.5 w-3 flex-none rounded" :style="{ background: l.couleur }" />
        <span v-else class="w-3 flex-none" />
        <span class="whitespace-nowrap font-semibold tabular-nums text-slate-800">{{ l.valeur }}</span>
        <span class="text-slate-400">{{ l.libelle }}</span>
      </p>
    </div>
  </div>
</template>

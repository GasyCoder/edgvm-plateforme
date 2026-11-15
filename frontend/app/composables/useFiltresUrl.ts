/**
 * Filtres gardés dans l'adresse de la page (retour arrière, partage d'un lien). Les valeurs vides ou « tous » sont retirées.
 */
export function useFiltresUrl() {
  const route = useRoute()
  const router = useRouter()

  const valeur = (cle: string, parDefaut = '') => computed(() => (route.query[cle] as string) || parDefaut)

  function filtrer(valeurs: Record<string, string | number | undefined | null>) {
    const query: Record<string, unknown> = { ...route.query, page: undefined, ...valeurs }
    router.replace({ query: Object.fromEntries(Object.entries(query).filter(([, v]) => ![undefined, null, '', 'tous', 'toutes'].includes(v as string))) as Record<string, string> })
  }

  return { route, valeur, filtrer }
}

/** Classes communes des tableaux (style DashWin) : l'alignement se choisit colonne par colonne. */
export const CELLULE_ENTETE = 'border-y border-gray-300 bg-gray-50/60 px-2 py-2.5 text-sm font-normal whitespace-nowrap text-slate-400 first:ps-4 last:pe-4 sm:first:ps-6 sm:last:pe-6'
export const CELLULE = 'border-b border-gray-200 px-2 py-3 align-middle first:ps-4 last:pe-4 sm:first:ps-6 sm:last:pe-6'

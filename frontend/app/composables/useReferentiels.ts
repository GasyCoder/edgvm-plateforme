import type { AnneeUniversitaire, Equipe, Ressource } from '~/types'

/**
 * Équipes d'accueil et années universitaires (listes des formulaires et des filtres), chargées une fois.
 */
export function useReferentiels() {
  const api = useApi()
  const equipes = useAsyncData('ref-equipes', () => api<Ressource<Equipe[]>>('/api/equipes').then(r => r.data), { default: () => [] as Equipe[], lazy: true, dedupe: 'defer' })
  const annees = useAsyncData('ref-annees', () => api<Ressource<AnneeUniversitaire[]>>('/api/annees').then(r => r.data), { default: () => [] as AnneeUniversitaire[], lazy: true, dedupe: 'defer' })
  const anneeCourante = computed(() => annees.data.value.find(annee => annee.est_courante) ?? null)

  return { equipes: equipes.data, annees: annees.data, anneeCourante, rafraichirEquipes: equipes.refresh, rafraichirAnnees: annees.refresh }
}

import type { Role } from '~/types'

const PAGES_PUBLIQUES = ['/connexion', '/mot-de-passe-oublie', '/creer-mon-acces']

/**
 * Toutes les pages demandent une connexion, sauf la connexion et le mot de passe.
 * Une page réservée déclare `definePageMeta({ roles: ['super_admin'] })`.
 * Ce n'est qu'un confort : les droits réels sont vérifiés par l'API.
 */
export default defineNuxtRouteMiddleware(async (vers) => {
  const { utilisateur, charge, charger, aLeRole } = useAuth()
  const publique = PAGES_PUBLIQUES.includes(vers.path) || vers.path.startsWith('/mot-de-passe/')

  if (!charge.value) {
    await charger()
  }

  if (publique) {
    return utilisateur.value && ['/connexion', '/creer-mon-acces'].includes(vers.path) ? navigateTo('/') : undefined
  }

  if (!utilisateur.value) {
    return navigateTo({ path: '/connexion', query: vers.fullPath !== '/' ? { retour: vers.fullPath } : {} })
  }

  const roles = vers.meta.roles as Role[] | undefined
  if (roles && !aLeRole(...roles)) {
    return navigateTo('/')
  }
})

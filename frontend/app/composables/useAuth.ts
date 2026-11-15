import type { Ressource, Role, Utilisateur } from '~/types'

/**
 * Utilisateur connecté, partagé par toute l'interface.
 */
export function useAuth() {
  const utilisateur = useState<Utilisateur | null>('utilisateur', () => null)
  const charge = useState<boolean>('utilisateur-charge', () => false)
  const api = useApi()

  async function charger(): Promise<Utilisateur | null> {
    try {
      const reponse = await api<Ressource<Utilisateur>>('/api/moi')
      utilisateur.value = reponse.data
    }
    catch {
      utilisateur.value = null
    }
    charge.value = true
    return utilisateur.value
  }

  async function connecter(identifiants: { email: string, password: string, remember: boolean }): Promise<Utilisateur> {
    await preparerSession()
    const reponse = await api<Ressource<Utilisateur>>('/api/connexion', { method: 'POST', body: identifiants })
    utilisateur.value = reponse.data
    charge.value = true
    return reponse.data
  }

  async function deconnecter(): Promise<void> {
    try {
      await api('/api/deconnexion', { method: 'POST' })
    }
    finally {
      utilisateur.value = null
      await navigateTo('/connexion')
    }
  }

  const aLeRole = (...roles: Role[]) => !!utilisateur.value && roles.includes(utilisateur.value.role)

  return {
    utilisateur,
    charge,
    charger,
    connecter,
    deconnecter,
    aLeRole,
    estSuperAdmin: computed(() => utilisateur.value?.role === 'super_admin'),
    estMembreEad: computed(() => utilisateur.value?.role === 'membre_ead'),
    estDoctorant: computed(() => utilisateur.value?.role === 'doctorant'),
  }
}

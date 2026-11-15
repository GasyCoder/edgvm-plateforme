import { CalendarRange, CircleUserRound, FolderOpen, LayoutDashboard, Network, UserCog, Users } from '@lucide/vue'
import type { Component } from 'vue'
import type { Role } from '~/types'

export interface LienNavigation {
  libelle: string
  court?: string
  vers: string
  icone: Component
  roles: Role[]
  section: string
  /** Affiché dans le menu du bas sur mobile */
  enBas?: boolean
}

const LIENS: LienNavigation[] = [
  { libelle: 'Tableau de bord', court: 'Accueil', vers: '/', icone: LayoutDashboard, roles: ['super_admin', 'membre_ead'], section: 'Pilotage', enBas: true },
  { libelle: 'Mon dossier', court: 'Dossier', vers: '/', icone: FolderOpen, roles: ['doctorant'], section: 'Mon espace', enBas: true },
  { libelle: 'Doctorants', vers: '/doctorants', icone: Users, roles: ['super_admin', 'membre_ead'], section: 'Gestion', enBas: true },
  { libelle: "Équipes d'accueil", court: 'Équipes', vers: '/equipes', icone: Network, roles: ['super_admin', 'membre_ead'], section: 'Gestion', enBas: true },
  { libelle: 'Comptes', vers: '/comptes', icone: UserCog, roles: ['super_admin'], section: 'Administration', enBas: true },
  { libelle: 'Années universitaires', court: 'Années', vers: '/annees', icone: CalendarRange, roles: ['super_admin'], section: 'Administration' },
  { libelle: 'Mon compte', court: 'Compte', vers: '/mon-compte', icone: CircleUserRound, roles: ['super_admin', 'membre_ead', 'doctorant'], section: 'Mon espace' },
]

/**
 * Menus selon le rôle de la personne connectée, regroupés par section.
 */
export function useNavigation() {
  const { utilisateur } = useAuth()
  const route = useRoute()

  const liens = computed(() => LIENS.filter(lien => utilisateur.value && lien.roles.includes(utilisateur.value.role)))
  const sections = computed(() => {
    const groupes = new Map<string, LienNavigation[]>()
    for (const lien of liens.value) {
      groupes.set(lien.section, [...(groupes.get(lien.section) ?? []), lien])
    }
    return [...groupes.entries()].map(([titre, elements]) => ({ titre, liens: elements }))
  })
  const liensDuBas = computed(() => {
    const enBas = liens.value.filter(lien => lien.enBas)
    return enBas.length >= 2 ? enBas.slice(0, 4) : liens.value.slice(0, 4)
  })

  const estActif = (vers: string) => vers === '/' ? route.path === '/' : route.path.startsWith(vers)

  return { liens, sections, liensDuBas, estActif }
}

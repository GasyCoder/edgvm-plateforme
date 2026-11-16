import { CalendarRange, CircleUserRound, Presentation, UserRoundPlus, FilePen, FileStack, FolderOpen, LayoutDashboard, Network, Settings2, UserCog, Users, Wallet } from '@lucide/vue'
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
  { libelle: 'Candidats', vers: '/candidats', icone: UserRoundPlus, roles: ['super_admin'], section: 'Gestion' },
  { libelle: "Équipes d'accueil", court: 'Équipes', vers: '/equipes', icone: Network, roles: ['super_admin', 'membre_ead'], section: 'Gestion' },
  { libelle: 'Séminaires et avancements', court: 'Séances', vers: '/seances', icone: Presentation, roles: ['super_admin', 'membre_ead'], section: 'Communication' },
  { libelle: 'Mes séances', court: 'Séances', vers: '/seances', icone: Presentation, roles: ['doctorant'], section: 'Mon espace' },
  { libelle: 'Paiements', vers: '/paiements', icone: Wallet, roles: ['super_admin'], section: 'Finances', enBas: true },
  { libelle: 'Mes paiements', court: 'Paiements', vers: '/paiements', icone: Wallet, roles: ['doctorant'], section: 'Mon espace', enBas: true },
  { libelle: 'Actes délivrés', court: 'Actes', vers: '/actes', icone: FileStack, roles: ['super_admin', 'membre_ead'], section: 'Actes administratifs', enBas: true },
  { libelle: "Modèles d'actes", court: 'Modèles', vers: '/actes/modeles', icone: FilePen, roles: ['super_admin'], section: 'Actes administratifs' },
  { libelle: 'Mes actes', court: 'Actes', vers: '/actes', icone: FileStack, roles: ['doctorant'], section: 'Mon espace', enBas: true },
  { libelle: 'Utilisateurs', vers: '/utilisateurs', icone: UserCog, roles: ['super_admin'], section: 'Administration' },
  { libelle: 'Paramètres', vers: '/parametres', icone: Settings2, roles: ['super_admin'], section: 'Administration' },
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
    return enBas.length >= 3 ? enBas.slice(0, 4) : [...enBas, ...liens.value.filter(l => !l.enBas)].slice(0, 4)
  })

  /* Le lien le plus précis gagne : « /actes/modeles » n'allume pas aussi « /actes ». */
  const estActif = (vers: string) => {
    if (vers === '/') return route.path === '/'
    const correspondants = liens.value.map(l => l.vers).filter(v => v !== '/' && (route.path === v || route.path.startsWith(`${v}/`)))
    return correspondants.length > 0 && vers === correspondants.sort((a, b) => b.length - a.length)[0]
  }

  return { liens, sections, liensDuBas, estActif }
}

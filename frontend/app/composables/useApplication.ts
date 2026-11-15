import { useLocalStorage, usePreferredDark } from '@vueuse/core'

export type Theme = 'systeme' | 'clair' | 'sombre'
export type Couleur = 'vert' | 'bleu' | 'ardoise' | 'bordeaux'

export interface Application {
  nom: string
  sous_titre: string
  ecole: string
  universite: string
  logos: { edgvm: string, universite: string }
  maintenance: { active: boolean, message: string }
  apparence: { theme: Theme, couleur: Couleur }
}

/** Nuances 50 à 950 de la couleur principale (la première : vert du logo EDGVM). */
export const PALETTES: Record<Couleur, { libelle: string, nuances: string[] }> = {
  vert: { libelle: 'Vert EDGVM', nuances: ['#eef6f2', '#d5eadf', '#abd4bf', '#7dbb9c', '#4e9e7a', '#2f8463', '#267058', '#1d6049', '#14563f', '#0f4231', '#082a1f'] },
  bleu: { libelle: 'Bleu Université', nuances: ['#eef5fc', '#d6e7f7', '#aecdee', '#7eaee1', '#4f8dd1', '#2f72bd', '#2361a6', '#1d5190', '#184374', '#133559', '#0b213a'] },
  ardoise: { libelle: 'Ardoise', nuances: ['#f3f5f7', '#e3e8ed', '#c7d1da', '#a3b2c1', '#7b8fa3', '#5d7287', '#4a5c6f', '#3d4c5c', '#2f3b48', '#232c36', '#151b22'] },
  bordeaux: { libelle: 'Bordeaux', nuances: ['#fbf1f3', '#f5dde2', '#ebbac5', '#dc8ea0', '#c8637b', '#b0455f', '#96354d', '#7d2a40', '#662236', '#521c2c', '#33101b'] },
}
const PALIERS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]

const DEFAUT: Application = {
  nom: 'EDGVM',
  sous_titre: "Plateforme de l'École doctorale",
  ecole: 'École Doctorale Génie du Vivant et Modélisation',
  universite: 'Université de Mahajanga',
  logos: { edgvm: '/marque/logo_droite', universite: '/marque/logo_gauche' },
  maintenance: { active: false, message: '' },
  apparence: { theme: 'clair', couleur: 'vert' },
}

/**
 * Réglages publics de la plateforme (nom, logos, maintenance, apparence), lus au démarrage.
 * Le thème choisi par l'administration s'applique à tous, sauf si l'utilisateur en a choisi un dans son menu.
 */
export function useApplication() {
  const application = useState<Application>('application', () => DEFAUT)
  const themePersonnel = useLocalStorage<Theme | ''>('edgvm-theme', '')
  const sombreSysteme = usePreferredDark()

  const theme = computed<Theme>(() => themePersonnel.value || application.value.apparence.theme)
  const estSombre = computed(() => theme.value === 'sombre' || (theme.value === 'systeme' && sombreSysteme.value))

  async function charger() {
    try {
      const reponse = await $fetch<{ data: Application }>('/api/application', { credentials: 'include' })
      application.value = reponse.data
    }
    catch {
      /* L'interface reste utilisable avec les valeurs par défaut. */
    }
  }

  /** Classe « dark » et nuances de la couleur principale (inversées en mode sombre, pour garder le contraste). */
  function appliquer() {
    const racine = document.documentElement
    racine.classList.toggle('dark', estSombre.value)
    racine.style.colorScheme = estSombre.value ? 'dark' : 'light'
    const nuances = PALETTES[application.value.apparence.couleur]?.nuances ?? PALETTES.vert.nuances
    const ordre = estSombre.value ? [...nuances].reverse() : nuances
    PALIERS.forEach((palier, index) => racine.style.setProperty(`--color-brand-${palier}`, ordre[index]!))
    racine.style.setProperty('--primary', estSombre.value ? nuances[6]! : nuances[8]!)
    racine.style.setProperty('--ring', nuances[6]!)
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', estSombre.value ? '#0e1211' : nuances[8]!)
  }

  return { application, themePersonnel, theme, estSombre, charger, appliquer }
}

const nombre = new Intl.NumberFormat('fr-FR')
const date = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
const dateHeure = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

/** 1 500 000 Ar */
export function formaterMontant(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '' : `${nombre.format(valeur)} Ar`
}

/** 15 novembre 2026 */
export function formaterDate(valeur: string | null | undefined): string {
  return valeur ? date.format(new Date(valeur.length === 10 ? `${valeur}T00:00:00` : valeur)) : ''
}

export function formaterDateHeure(valeur: string | null | undefined): string {
  return valeur ? dateHeure.format(new Date(valeur)) : ''
}

const TITRES = ['pr', 'dr', 'm.', 'mme', 'mlle']

/** Initiales pour les avatars : « RABE Fanja » donne « RF », « Pr RAKOTO Jean » donne « RJ ». */
export function initiales(nom: string): string {
  return nom.split(/\s+/).filter(partie => partie && !TITRES.includes(partie.toLowerCase())).slice(0, 2).map(partie => partie[0]?.toUpperCase()).join('')
}

const TEINTES = ['bg-brand-600', 'bg-leaf-600', 'bg-sun-500', 'bg-slate-500', 'bg-brand-800', 'bg-gold']

/** Couleur d'avatar stable pour un même nom (comme les avatars à initiales de DashWin). */
export function couleurAvatar(nom: string): string {
  let somme = 0
  for (const caractere of nom) somme = (somme + caractere.charCodeAt(0)) % 997
  return TEINTES[somme % TEINTES.length]!
}

/** 245 Ko, 3,2 Mo */
export function formaterTaille(octets: number): string {
  if (octets < 1024 * 1024) return `${Math.max(1, Math.round(octets / 1024))} Ko`
  return `${(octets / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`
}

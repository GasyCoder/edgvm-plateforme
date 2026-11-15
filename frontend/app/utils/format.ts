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

/** « il y a 5 min », « hier », sinon la date courte */
export function depuis(valeur: string): string {
  const secondes = Math.round((Date.now() - new Date(valeur).getTime()) / 1000)
  if (secondes < 60) return "à l'instant"
  if (secondes < 3600) return `il y a ${Math.floor(secondes / 60)} min`
  if (secondes < 86400) return `il y a ${Math.floor(secondes / 3600)} h`
  if (secondes < 172800) return 'hier'
  return formaterDate(valeur)
}

const heure = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' })
const jourCourt = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })

/** Moment lisible avec l'heure : « il y a 4 h », « hier à 14:30 », « 12 nov. 2026 à 09:15 » */
export function momentHumain(valeur: string | null | undefined): string {
  if (!valeur) return ''
  const moment = new Date(valeur)
  const secondes = Math.round((Date.now() - moment.getTime()) / 1000)
  if (secondes < 60) return "à l'instant"
  if (secondes < 3600) return `il y a ${Math.floor(secondes / 60)} min`
  const hier = new Date()
  hier.setDate(hier.getDate() - 1)
  if (moment.toDateString() === new Date().toDateString()) return `il y a ${Math.floor(secondes / 3600)} h`
  if (moment.toDateString() === hier.toDateString()) return `hier à ${heure.format(moment)}`
  return `${jourCourt.format(moment)} à ${heure.format(moment)}`
}

/** Montant court pour les axes : 750 k, 1,5 M (sans « Ar », ajouté par le titre de l'axe) */
export function formaterCompact(valeur: number): string {
  if (Math.abs(valeur) >= 1_000_000) return `${(valeur / 1_000_000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} M`
  if (Math.abs(valeur) >= 1_000) return `${Math.round(valeur / 1_000).toLocaleString('fr-FR')} k`
  return valeur.toLocaleString('fr-FR')
}

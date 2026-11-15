export type Role = 'super_admin' | 'membre_ead' | 'doctorant'
export type Niveau = 'D1' | 'D2' | 'D3'

export interface Equipe {
  id: number
  sigle: string
  nom: string
  description: string | null
  fonction?: string
  doctorants_count?: number
  membres?: Compte[]
}

export interface Utilisateur {
  id: number
  name: string
  email: string
  telephone: string | null
  role: Role
  role_libelle: string
  doctorant_id: string | null
  equipes: Equipe[]
}

export interface Compte {
  id: number
  name: string
  email: string
  telephone: string | null
  role: Role
  role_libelle: string
  actif: boolean
  fonction?: string
  derniere_connexion_le: string | null
  equipes?: Equipe[]
  doctorant_id?: string | null
}

export interface AnneeUniversitaire {
  id: number
  libelle: string
  debut: string
  fin: string
  est_courante: boolean
  inscriptions_count?: number
}

export interface Inscription {
  id: number
  annee?: AnneeUniversitaire
  niveau: Niveau
  niveau_aff: string
  type: 'premiere' | 'reinscription'
  type_libelle: string
  est_derogation: boolean
  derogation_en_attente: boolean
  motif_derogation?: string | null
  decision_derogation: string | null
  date_decision_derogation: string | null
  statut: 'en_attente' | 'validee' | 'annulee'
  statut_libelle: string
  montant_du?: number
  date_inscription: string | null
}

export interface Doctorant {
  id: string
  matricule: string
  nom: string
  prenoms: string | null
  nom_complet: string
  genre: 'M' | 'F' | null
  email?: string
  telephone?: string | null
  date_naissance?: string | null
  lieu_naissance?: string | null
  nationalite?: string | null
  numero_piece?: string | null
  adresse?: string | null
  observation?: string | null
  equipe?: Equipe
  sujet_these: string | null
  directeur_these?: string | null
  co_directeur?: string | null
  co_encadrant?: string | null
  statut: 'actif' | 'suspendu' | 'abandon' | 'diplome'
  statut_libelle: string
  visible_annuaire: boolean
  compte?: { id: number, actif: boolean, derniere_connexion_le: string | null }
  inscription_courante?: Inscription | null
  inscriptions?: Inscription[]
  prochaine_inscription?: { niveau: Niveau, est_derogation: boolean, type: string }
  peut: { modifier: boolean }
}

export interface Pagine<T> {
  data: T[]
  meta: { current_page: number, last_page: number, per_page: number, total: number, from: number | null, to: number | null }
}

export interface Ressource<T> {
  data: T
}

export type CategoriePiece = 'diplome' | 'piece_identite' | 'attestation' | 'publication' | 'autre'

export interface PieceJointe {
  id: number
  categorie: CategoriePiece
  categorie_libelle: string
  titre: string
  nom_original: string
  type_mime: string
  taille: number
  affichable: boolean
  ajoute_par?: string | null
  ajoute_le: string | null
  peut_supprimer: boolean
}

export interface ChampModele {
  cle: string
  libelle: string
  type: 'texte' | 'texte_long' | 'date' | 'heure' | 'choix'
  options?: string[]
  obligatoire?: boolean
}

export interface ModeleActe {
  id: number
  code: string
  nom: string
  titre: string
  corps: string
  champs: ChampModele[]
  exige_inscription: boolean
  inclure_signature: boolean
  actif: boolean
  actes_count?: number
  modifie_le: string | null
}

export interface VariableActe {
  cle: string
  libelle: string
  exemple: string
}

export interface Acte {
  id: number
  numero: string
  titre: string
  modele?: { id: number, nom: string }
  doctorant?: { id: string, nom_complet: string, matricule: string }
  annee?: string | null
  delivre_le: string
  delivre_par?: string | null
  annule_le: string | null
  motif_annulation: string | null
  url_pdf: string
  peut_annuler: boolean
}

export interface ReglagesActes {
  signataire_nom: string
  signataire_fonction: string
  signataire_titre: string
  ville: string
  afficher_nom_signataire: boolean
  images: Record<'logo_gauche' | 'logo_droite' | 'signature', { personnalisee: boolean, existe: boolean }>
}

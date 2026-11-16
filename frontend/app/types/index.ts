export type Role = 'super_admin' | 'membre_ead' | 'doctorant'
export type Niveau = 'D1' | 'D2' | 'D3'

export interface Equipe {
  id: number
  sigle: string
  nom: string
  domaine: string | null
  description: string | null
  fonction?: string
  doctorants_count?: number
  programmes_count?: number
  personnes_count?: number
  responsable?: MembreEquipe | null
  programmes?: ProgrammeDoctoral[]
  personnes?: MembreEquipe[]
  /** Comptes de la plateforme rattachés à l'équipe (accès à ses doctorants) */
  comptes?: Compte[]
  effectifs?: { D1: number, D2: number, D3: number, derogation: number }
}

export interface ProgrammeDoctoral {
  id: number
  nom: string
  description: string | null
  ordre: number
}

export type FonctionMembre = 'responsable' | 'encadrant' | 'membre'

export interface MembreEquipe {
  id: number
  nom: string
  titre: string | null
  nom_avec_titre: string
  fonction: FonctionMembre
  fonction_libelle: string
  specialite: string | null
  etablissement: string | null
  email: string | null
  telephone: string | null
}

export interface Utilisateur {
  id: number
  name: string
  email: string
  telephone: string | null
  role: Role
  role_libelle: string
  photo_url: string | null
  doctorant_id: string | null
  equipes: Equipe[]
}

export interface Compte {
  id: number
  name: string
  email: string
  telephone: string | null
  photo_url?: string | null
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
  frais?: number
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
  statut: 'candidat' | 'actif' | 'suspendu' | 'abandon' | 'diplome'
  statut_libelle: string
  est_candidat?: boolean
  candidature?: Candidature
  est_boursier: boolean
  visible_annuaire: boolean
  inscription_courante?: Inscription | null
  inscriptions?: Inscription[]
  prochaine_inscription?: { niveau: Niveau, est_derogation: boolean, type: string }
  paiement?: SituationPaiement
  compte?: { id: number, actif: boolean, derniere_connexion_le: string | null }
  photo_url: string | null
  url_carte?: string
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

export type StatutPaiement = 'solde' | 'partiel' | 'a_venir' | 'en_retard' | 'rien_a_payer' | 'annule'

export interface SituationPaiement {
  du?: number
  paye?: number
  reste?: number
  en_retard?: number
  statut: StatutPaiement
  statut_libelle: string
}

export interface TypeFrais {
  id: number
  code: string
  libelle: string
  description: string | null
  par_niveau: boolean
  montant: number
  montants_niveaux: Record<'D1' | 'D2' | 'D3' | 'derogation', number> | null
  obligatoire: boolean
  echeance: 'fin_annee' | 'delai'
  delai_jours: number
  tranches: number
  actif: boolean
  frais_count?: number
  frais_non_soldes_count?: number
}

export interface FraisDoctorant {
  id: number
  libelle: string
  annee?: string | null
  montant: number
  paye: number
  reste: number
  echeance: string | null
  situation: StatutPaiement
  situation_libelle: string
  motif_ajustement: string | null
  annule_le: string | null
  motif_annulation: string | null
}

export interface Paiement {
  id: number
  numero_recu: string
  date_paiement: string
  enregistre_le: string | null
  montant: number
  mode: 'especes' | 'virement' | 'mobile_money' | 'cheque'
  mode_libelle: string
  reference: string | null
  observation: string | null
  doctorant?: { id: string, nom_complet: string, matricule: string }
  imputations?: Array<{ libelle: string | null, montant: number }>
  saisi_par?: string | null
  annule_le: string | null
  motif_annulation: string | null
  url_recu: string
  reste_apres?: number | null
  justificatif: { nom: string, type: string, taille: number, est_image: boolean, url: string } | null
  peut_annuler: boolean
}

export interface Finances {
  situation: Required<SituationPaiement>
  frais: FraisDoctorant[]
  paiements: Paiement[]
}

export interface NotificationPlateforme {
  id: string
  titre: string
  message: string
  lien: string | null
  categorie: 'paiement' | 'frais' | 'rappel' | 'retards' | string
  lue: boolean
  cree_le: string
}

export type DecisionCandidature = 'admis' | 'ajourne' | 'non_admis'

export interface Candidature {
  depose_le: string | null
  decision: DecisionCandidature | null
  decision_libelle: string | null
  admis_le: string | null
  presentations?: { seance_id: number, seance: string, date: string, decision: DecisionCandidature | null, decision_libelle: string | null, observation: string | null }[]
}

export type TypeSeance = 'seminaire' | 'etat_avancement' | 'presentation_projet' | 'autre'
export type RoleIntervenant = 'moderateur' | 'jury'

export interface IntervenantPassage {
  id: number
  nom_avec_titre: string
  role: RoleIntervenant
  role_libelle: string
  equipe: string | null
  email: string | null
}

export interface PassageSeance {
  id: number
  debut: string
  fin: string
  heure_debut: string
  heure_fin: string
  duree: number
  sujet: string | null
  ordre: number
  convoque_le: string | null
  /** jamais envoyée, à jour, ou modifiée depuis l'envoi (horaire, lieu, sujet, modérateurs, jury) */
  convocation: 'jamais' | 'a_jour' | 'modifiee'
  decision: DecisionCandidature | null
  decision_libelle: string | null
  decision_observation: string | null
  decision_notifiee_le: string | null
  doctorant: { id: string, nom_complet: string, matricule: string, photo_url: string | null, niveau: string | null, est_derogation: boolean, equipe: string | null, statut: Doctorant['statut'], admis_le: string | null }
  intervenants: IntervenantPassage[]
}

export interface Seance {
  id: number
  type: TypeSeance
  type_libelle: string
  titre: string
  description: string | null
  lieu: string | null
  date: string
  heure_debut: string
  heure_fin?: string | null
  duree_passage: number
  pause: number
  annee_universitaire_id: number | null
  exige_paiement_solde: boolean
  pour_candidats: boolean
  statut: 'brouillon' | 'publiee' | 'annulee'
  statut_libelle: string
  convocations_envoyees_le: string | null
  passages_count?: number
  passages?: PassageSeance[]
  peut: { modifier: boolean }
}

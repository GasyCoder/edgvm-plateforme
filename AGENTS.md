# EDGVM Plateforme : consignes pour les agents de code

Plateforme de gestion de l'École Doctorale Génie du Vivant et Modélisation (EDGVM, Université de Mahajanga).
Ce fichier est lu par Codex (`AGENTS.md`) et par Claude Code (via `CLAUDE.md`). Il fait foi pour tout le dépôt.

## Ce que fait la plateforme

1. **Doctorants** : dossier de chaque doctorant (identité, équipe d'accueil, thèse, encadrants, inscriptions annuelles).
2. **Actes administratifs** : attestation d'inscription, de réinscription, autres attestations ; PDF numérotés et vérifiables par QR code.
3. **Notifications** : informations aux doctorants et aux membres des équipes d'accueil, rappels de paiement, rappels d'échéance.
4. **Suivi** : reste à payer, niveau (D1, D2, D3), dérogations, avancement de la thèse, comités de suivi.
5. **API publique en lecture** pour le site vitrine (banque de thèses, annuaire des doctorants). Voir le skill `edgvm-api-publique`.

## Rôles

Trois rôles, pas d'autre sans accord de l'utilisateur :

| Rôle (valeur en base) | Qui | Voit et fait |
|---|---|---|
| `super_admin` | Direction et scolarité de l'École | Tout : doctorants, inscriptions, paiements, actes, notifications, réglages. |
| `membre_ead` | Membre d'une équipe d'accueil (enseignant, encadrant) | Les doctorants de son ou ses équipes d'accueil, leur avancement ; reçoit les informations de l'École. Pas les paiements détaillés, seulement « à jour / en retard ». |
| `doctorant` | Doctorant inscrit | Son propre dossier : inscriptions, reste à payer, actes téléchargeables, notifications, avancement. |

Chaque accès passe par une **Policy** Laravel. Un doctorant ne doit jamais pouvoir lire le dossier d'un autre : chaque nouvelle route a un test qui le vérifie.

## Stack

| Partie | Dossier | Technologies |
|---|---|---|
| API | `backend/` | PHP 8.3+, Laravel 13, MySQL, Sanctum (authentification SPA par cookie), Pest 5, Pint |
| Interface | `frontend/` | Nuxt 4 en SPA (`ssr: false`), Vue 3, TypeScript, Tailwind CSS v4, shadcn-vue (module `shadcn-nuxt`) |

- En production, l'interface est construite en fichiers statiques (`scripts/construire.sh`) et copiée dans `backend/public` ; Laravel la sert pour toute adresse qui n'est pas l'API (`InterfaceController`). Interface et API partagent donc le même domaine : la session Sanctum fonctionne sans CORS.
- En local, Nuxt redirige `/api` et `/sanctum` vers Laravel (`nitro.devProxy` dans `nuxt.config.ts`).
- Ne pas ajouter ni changer de dépendance (Composer ou npm) sans l'accord de l'utilisateur. Les dépendances déjà prévues sont listées dans les skills.
- Pas de SSR : la plateforme est privée (pas de SEO), et l'interface doit pouvoir être servie en fichiers statiques sur un hébergement mutualisé cPanel (o2switch), comme le site vitrine.

## Commandes

```bash
# API
cd backend
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # en local : comptes admin@, membre@, doctorant@edgvm.test (mot de passe « password »)
php artisan serve                     # http://localhost:8000
php artisan queue:work                # envoie les e-mails (notifications en file d'attente)
php artisan plateforme:creer-admin adresse@exemple.mg   # premier compte d'administration en production
php artisan test --compact            # tous les tests
php artisan test --compact --filter=NomDuTest
vendor/bin/pint --dirty               # formatage, avant chaque livraison

# Interface
cd frontend
npm install
npm run dev                           # http://localhost:3000 (API_LOCALE=http://localhost:8001 si l'API n'est pas sur 8000)
npx nuxi typecheck

# Construction pour la production (interface copiée dans backend/public)
scripts/construire.sh
```

## Règles de travail

- **Tester chaque changement.** Côté API : un test Pest (feature) par comportement, avec le cas qui marche, le cas refusé (mauvais rôle, autre doctorant) et le cas invalide. Lancer les tests concernés, puis toute la suite avant de dire que c'est fini.
- **Vérifier avant d'affirmer.** Ne pas écrire « ça marche » sans avoir lancé le test ou ouvert la page. Si un test échoue, le dire avec la sortie.
- **Pas de commit ni de push sans demande explicite.** Branche de travail plutôt que `master`.
- **Créer les fichiers avec `php artisan make:*`** (`--no-interaction`), puis les adapter.
- **Ne jamais committer** de `.env`, de jeton, de mot de passe, de données réelles de doctorants ou de fichiers déposés (`storage/`). Le dépôt GitHub est public.
- **Pas de fichier de documentation** non demandé.

## Conventions

### Langue et nommage

- Le métier se nomme **en français**, comme sur le site vitrine : `Doctorant`, `Inscription`, `Paiement`, `Acte`, `EquipeAccueil`, `estAJour()`, `resteAPayer()`. Les mots-clés techniques Laravel restent en anglais (`store`, `index`, `casts()`).
- Tables et colonnes en `snake_case` français : `doctorants`, `annees_universitaires`, `montant_du`, `date_soutenance`.
- Textes affichés et messages d'erreur en français, courts et directs. **Jamais de tiret cadratin ou demi-cadratin** (— ou –) dans les textes : virgule, deux-points ou parenthèses à la place.

### PHP / Laravel

- Accolades sur toutes les structures de contrôle ; types de retour et types de paramètres partout ; promotion de propriétés dans les constructeurs.
- PHPDoc plutôt que commentaires en ligne ; formes de tableaux (`array{...}`) quand c'est utile.
- Validation dans des **Form Requests** (règles en tableau + messages en français), jamais dans le contrôleur.
- Réponses via des **API Resources**. Listes paginées au format `{ "data": [...], "meta": {...} }`.
- Enums PHP pour les statuts et rôles (cas en `TitleCase`, valeurs en `snake_case`) : `RoleUtilisateur::SuperAdmin = 'super_admin'`.
- Relations Eloquent typées, chargement anticipé (`with()`) pour éviter les requêtes N+1. Pas de `DB::` quand Eloquent suffit.
- `casts()` en méthode. `config()` hors des fichiers de config, jamais `env()`.
- Traitements longs (envoi de notifications en masse, génération de PDF en lot) dans des jobs `ShouldQueue`.

### Modèle de données (référence)

À créer progressivement, une migration par table :

- `users` : compte de connexion, `role`, `telephone` (format E.164, `+261...`).
- `equipes_accueil` : `sigle`, `nom`. Le `sigle` est le même que sur le site vitrine.
- `membres_ead` (pivot `user` × `equipe_accueil`, avec la fonction : responsable, membre).
- `equipes_accueil` : `sigle` (identique au site vitrine), `nom`, `domaine`, `description` ; `programmes_doctoraux` (nom unique par équipe, `ordre`) ; `membres_equipe` (personnes de l'équipe sans compte obligatoire : `nom`, `titre`, `fonction` responsable, encadrant ou membre, spécialité, établissement, contacts). Les comptes qui voient les doctorants de l'équipe restent dans `equipe_accueil_user`.
- `doctorants` : `user_id`, `matricule` unique, identité, `equipe_accueil_id`, `est_boursier`, `visible_annuaire` (accord pour l'annuaire public), `statut` (`actif`, `suspendu`, `abandon`, `diplome`).
- `annees_universitaires` : `libelle` (« 2026-2027 »), `debut`, `fin`, `est_courante`.
- `inscriptions` : `doctorant` × `annee_universitaire`, `niveau` (`D1`, `D2`, `D3`), `type` (`premiere`, `reinscription`), `est_derogation` + `motif_derogation` + `decision_derogation`, `statut`.
- `types_frais` (paramètres) : catalogue des frais (droits d'inscription, tee-shirt, cotisation...), montant unique ou `montants_niveaux` (D1, D2, D3, dérogation), `obligatoire` (créé à chaque inscription), `delai_jours`.
- `frais_doctorants` : montant dû par un doctorant (`libelle`, `montant`, `montant_paye` recalculé, `echeance`, ajustement ou annulation avec motif).
- `paiements` : versement avec reçu `REC-{année}-{000001}`, `montant`, `reste_apres`, `date_paiement`, `mode` (`especes`, `virement`, `mobile_money`, `cheque`), `reference`, `saisi_par`, annulation avec motif ; `paiement_frais` répartit un versement entre les frais.
- `theses` : `doctorant_id`, `titre`, `resume`, `mots_cles`, `statut` (`en_cours`, `soutenue`), `date_soutenance`, `pdf` ; `these_encadrants` (directeur, codirecteur) ; `jury_membres`.
- `suivis` : étapes d'avancement (comité de suivi, séminaire, publication, rapport annuel) avec date, avis et pièce jointe.
- `actes` : `type`, `numero` unique (`EDGVM/2026/000123`), `doctorant_id`, `inscription_id`, `code_verification`, `fichier`, `delivre_par`, `delivre_le`, `annule_le`.
- `notifications` (table Laravel) + `diffusions` (message envoyé à un groupe : tous les doctorants, un niveau, une équipe).

Règles métier :

- **Montants en ariary (MGA), entiers** (`unsignedBigInteger`), jamais de flottant. Affichage `1 250 000 Ar`.
- **Reste à payer** = somme des frais non annulés moins leur payé (`App\Support\SituationFinanciere`). Calculé, jamais saisi. Un frais en retard = impayé dont l'échéance est passée.
- **Niveau** : D1 à D3 en durée normale. Au delà, l'inscription est une **dérogation** : elle exige un motif et une décision enregistrée avant de pouvoir délivrer une attestation.
- Les paiements sont **saisis par la scolarité** (`super_admin`) via `App\Actions\EnregistrerPaiement` (répartition automatique par échéance, ou donnée ; jamais plus que le reste). Un paiement ne se supprime pas : `AnnulerPaiement`. Pas de paiement en ligne pour l'instant ; garder le code prêt à recevoir un mode `mobile_money` automatique plus tard.
- Les frais obligatoires sont créés à l'inscription (`AppliquerFraisInscription`) ; un nouveau tarif ne change pas les montants déjà dus. Un membre d'équipe ne voit que le statut de paiement, jamais les montants.
- Un acte délivré ne se modifie pas : on l'annule (`annule_le`) et on en délivre un nouveau. La vérification publique par QR code indique « valide » ou « annulé ».

### Interface

- shadcn-vue + Tailwind v4, couleurs du logo EDGVM : vert forêt `#14563F` (`brand-800`), vert `#267058` (`brand-600`), vert clair `#55B66F` (`leaf-400`), jaune or `#C2B24A`, jaune vif `#F6D94C` (`sun-300`). Police unique : Open Sans.
- Style sobre et « humain » : pas de décor superflu (filets, motifs, dégradés, effets), pas de textes longs. L'utilisateur a déjà refusé un rendu jugé « trop IA ».
- Mobile d'abord : menu en bas de l'écran sur mobile + menu hamburger toujours présent.
- Notifications toast en **bas à droite**. Squelettes de chargement cohérents avec la page attendue.

### Notifications

Canaux : plateforme (base de données), e-mail, SMS, WhatsApp. Voir le skill `edgvm-notifications`.
Les numéros sont en E.164. SMS et WhatsApp passent par des canaux maison derrière une interface, avec un pilote `log` en local et dans les tests (aucun envoi réel hors production).

## Réglages de l'application

- `App\Support\ReglagesApplication` (table `reglages`, Paramètres > Application) : nom et sous-titre de la plateforme, École (nom, université, ministère, arrêté d'habilitation, description), directeur (`actes.signataire_nom`, `actes.signataire_grade`, `actes.signataire_titre`, partagés avec les actes), logos (`actes.logo_droite` EDGVM, `actes.logo_gauche` Université, servis publiquement par `/marque/{cle}`), indexation, maintenance, apparence.
- Tout document généré (actes, reçus, carte de doctorat) lit ces réglages : ne jamais écrire en dur le nom de l'École, de l'Université ou du directeur.
- `GET /api/application` (sans connexion) donne ce que l'interface lit au démarrage (`useApplication`, plugin `application.client.ts`).
- Maintenance : middleware `ModeMaintenance` sur l'API connectée ; seul `super_admin` passe, les autres reçoivent 503 `{ message, maintenance: true }`.
- `robots.txt` est servi par Laravel (fermé par défaut) ; ne pas remettre de fichier statique dans `public/`.
- Mode sombre : variables CSS inversées sous `.dark` dans `main.css` ; la couleur principale (`brand`) est posée par `useApplication` selon la palette choisie.

## Séances de communication

- `seances` (séminaire doctoral, état d'avancement, autre) : date, heure de début, durée d'un passage, pause, lieu, `exige_paiement_solde`, statut `brouillon` / `publiee` / `annulee`.
- `passages` : un doctorant par créneau (`debut`, `fin`, heures locales sans fuseau), unique par séance ; `intervenants_passage` : personnes des équipes (`membres_equipe`) avec le rôle `moderateur` ou `jury`.
- `App\Support\Disponibilites` : une personne ou un doctorant ne peut pas être à deux passages qui se chevauchent (toutes séances non annulées). Toute affectation, modification d'horaire ou replanification passe par ce contrôle ; en cas de conflit, rien n'est enregistré.
- Paiement : si `exige_paiement_solde`, un doctorant dont le reste à payer est supérieur à 0 ne peut pas être programmé (`Seance::raisonInadmissible`).
- Convocations (`SeanceController::convoquer`) : `ConvocationPassage` au doctorant (cloche et e-mail), `ConvocationIntervenant` à chaque modérateur ou jury (e-mail ; cloche aussi si un compte a la même adresse).
- Modes d'envoi : `intelligent` (par défaut : jamais convoqués, ou modifiés depuis l'envoi d'après `passages.convocation_empreinte`), `tous`, `passages` (un ou plusieurs). Un modérateur ou jury reçoit toujours la liste complète de ses passages dans la séance.

## Skills disponibles

Dans `.agents/skills/` (Codex) et `.claude/skills/` (Claude Code, lien vers le même dossier) :

| Skill | Quand l'utiliser |
|---|---|
| `edgvm-api-laravel` | Ajouter ou modifier un module de l'API (migration, modèle, policy, routes, tests). |
| `edgvm-interface-nuxt` | Créer ou modifier une page, un composant ou un composable Nuxt. |
| `edgvm-notifications` | Envoyer une information, un rappel, ajouter un canal (e-mail, SMS, WhatsApp). |
| `edgvm-actes-administratifs` | Générer une attestation ou un autre acte PDF, numérotation, vérification QR. |
| `edgvm-api-publique` | Toucher aux routes lues par le site vitrine (thèses, annuaire des doctorants). |

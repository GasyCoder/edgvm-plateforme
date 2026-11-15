---
name: edgvm-api-publique
description: Contrat de l'API publique en lecture que le site vitrine EDGVM consomme (banque de thèses et annuaire des doctorants). À utiliser avant de créer ou modifier les routes /api/public/v1, les ressources Thèse et Doctorant exposées, ou les champs visibles publiquement.
---

# API publique pour le site vitrine

Le site vitrine (dépôt `edgvm`) lit ces routes avec les classes `BanqueTheses`, `AnnuaireDoctorants` et `EquipesScolarite` (fiches des équipes d'accueil : effectifs et premiers doctorants de l'annuaire).

- Ses réglages côté site :
  - `SCOLARITE_API_URL` : la base, par exemple `https://<domaine de la plateforme>/api/public/v1` ;
  - `SCOLARITE_API_TOKEN` : jeton « Bearer » créé dans Paramètres, bloc « Site vitrine de l'EDGVM » (montré une seule fois, remplaçable, révocable).
- Le site met les réponses en cache pendant 10 minutes. Il ne met jamais une erreur en cache.
- **Ne pas changer ce format sans mettre à jour le site.** Les champs absents sont tolérés, mais un champ renommé disparaît de l'affichage.

## Routes

Préfixe `api/public/v1`.

Protection :

- le middleware `VerifierJetonApiPublique` vérifie le jeton Bearer (sans jeton créé, tout est refusé en 401) ;
- le jeton est stocké haché (sha256) dans les réglages, via `App\Support\JetonApiPublique` ;
- une limite de débit : `throttle:120,1`.

| Méthode et chemin | Filtres (query) | Réponse |
|---|---|---|
| `GET /theses` | `recherche`, `statut` (`en_cours` ou `soutenue`), `equipe` (sigle), `page` | `{ "data": [These], "meta": { "current_page", "last_page", "per_page", "total" } }` |
| `GET /theses/{id}` | aucun | `{ "data": These }`, ou 404 |
| `GET /doctorants` | `recherche`, `equipe` (sigle), `niveau` (`D1`...), `page` | `{ "data": [Doctorant], "meta": {...} }` |
| `GET /equipes` | aucun | `{ "data": [Equipe], "meta": { "total" } }` |
| `GET /equipes/{sigle}` | aucun | `{ "data": Equipe }`, ou 404 |

En place : `/doctorants` et `/equipes` (`App\Http\Controllers\ApiPublique`). Pas encore : `/theses` (la plateforme n'a pas de banque de thèses), le test de connexion du site le signale.

## Objets

**These**

```json
{
  "id": "uuid",
  "titre": "...",
  "resume": "...",
  "statut": "en_cours | soutenue",
  "annee": 2026,
  "date_soutenance": "AAAA-MM-JJ ou null",
  "mots_cles": ["..."],
  "doctorant": { "nom": "..." },
  "directeur": { "nom": "...", "titre": "Pr" },
  "equipe": { "sigle": "...", "nom": "..." },
  "specialite": "...",
  "universite": "Université de Mahajanga",
  "pdf_url": "URL publique ou null",
  "jury": [{ "nom": "...", "titre": "...", "role": "Président | Rapporteur | Examinateur | Directeur" }]
}
```

**Doctorant**

```json
{
  "id": "uuid",
  "nom": "...",
  "niveau": "D1 | D2 | D3 (une dérogation n'est jamais signalée)",
  "annee_inscription": 2024,
  "sujet": "...",
  "equipe": { "sigle": "...", "nom": "..." },
  "directeur": { "nom": "...", "titre": "Pr" },
  "these_id": "uuid ou null",
  "photo_url": "URL ou null"
}
```

**Equipe**

```json
{
  "sigle": "EAD 1",
  "nom": "...",
  "description": "... ou null",
  "effectifs": { "total": 5, "D1": 2, "D2": 1, "D3": 1 },
  "doctorants_annuaire": 2
}
```

`effectifs` compte les doctorants actifs (`total` inclut ceux sans inscription cette année ou en dérogation) ; `doctorants_annuaire` ceux qui figurent dans l'annuaire.

## Règles de confidentialité

- Identifiants publics : des **UUID**, jamais l'`id` auto-incrémenté ni le matricule.
- L'annuaire ne liste que les doctorants dont le statut est `actif` et dont `visible_annuaire` vaut `true`.
- Jamais exposés :
  - la date de naissance ;
  - le téléphone et l'e-mail ;
  - les paiements et la bourse ;
  - les dérogations et leurs motifs ;
  - les actes ;
  - les notes de suivi.
- Le `pdf_url` d'une thèse n'est rempli que si la diffusion du PDF est autorisée.
- Le sigle d'équipe est identique à celui du site vitrine, sinon le filtre `equipe` du site ne trouve rien.

## Tests

- Format exact des listes et des fiches : clés présentes, pagination dans `meta`.
- Refus sans jeton ou avec un mauvais jeton (`assertUnauthorized`).
- Les doctorants non visibles ou non actifs n'apparaissent pas.
- Aucun champ interdit dans la réponse : `assertJsonMissingPath` sur `data.0.telephone`, `data.0.date_naissance`, etc.
- Tester chaque filtre.

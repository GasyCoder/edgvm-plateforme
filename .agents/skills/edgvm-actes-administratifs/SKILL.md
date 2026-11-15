---
name: edgvm-actes-administratifs
description: Générer les actes administratifs de l'EDGVM en PDF (attestation d'inscription, de réinscription, autres attestations) avec numérotation unique, QR code et page publique de vérification ; annuler un acte. À utiliser pour tout modèle de document officiel ou toute règle de délivrance.
---

# Actes administratifs (backend/)

## Délivrance d'un acte

1. **Contrôles avant délivrance**, dans une action `app/Actions/DelivrerActe.php` :
   - l'inscription existe pour l'année demandée et n'est pas annulée ;
   - si c'est une **dérogation**, la décision est enregistrée ; sinon, refus avec un message clair ;
   - les règles de paiement exigées pour ce type d'acte sont respectées. Par exemple, une attestation d'inscription peut exiger le premier versement. Ces règles sont un réglage, pas une constante dans le code.
2. **Numéro unique** au format `EDGVM/{année}/{séquence sur 6 chiffres}`.
   - Il est attribué dans une transaction, avec un verrou sur le compteur de l'année.
   - Ne jamais réutiliser un numéro.
3. **Code de vérification** : chaîne aléatoire non devinable (`Str::random(32)`), stockée dans l'acte.
4. **PDF** :
   - généré depuis une vue Blade, `resources/views/actes/{type}.blade.php` ;
   - enregistré dans `storage/app/private/actes/{année}/`, donc **jamais dans `public`** ;
   - téléchargé par une route protégée par la policy (le doctorant concerné ou le `super_admin`).
5. **QR code** imprimé sur le PDF. Il pointe vers la page publique `GET /verifier/{code}`.

## Bibliothèques

- **PDF** : `barryvdh/laravel-dompdf`. C'est du PHP pur, qui fonctionne sur l'hébergement mutualisé, sans Chrome.
  - Pas de Browsershot ni de Puppeteer.
- **QR code** : une bibliothèque PHP pure qui produit du SVG ou du PNG, par exemple `chillerlan/php-qrcode`.
- Ces deux dépendances ne sont pas encore installées. Les proposer à l'utilisateur et attendre son accord.

## Modèle du document

- En-tête :
  - République de Madagascar ;
  - Université de Mahajanga ;
  - École Doctorale Génie du Vivant et Modélisation ;
  - logo.
- Corps : texte officiel avec l'identité du doctorant, le matricule, l'année universitaire, le niveau (D1, D2, D3, ou « en dérogation »), l'équipe d'accueil et le sujet de thèse.
- Pied :
  - lieu et date ;
  - fonction et nom du signataire, tirés des réglages et non codés en dur ;
  - QR code et numéro.
- Mise en page :
  - CSS simple compatible dompdf : pas de flex ni de grid, utiliser des tableaux ;
  - polices embarquées ;
  - format A4.
- Les textes officiels (formules, titre du signataire) sont validés par l'École avant mise en production.

## Annulation

- Un acte délivré **ne se modifie jamais**.
- Pour corriger une erreur :
  1. renseigner `annule_le` et `motif_annulation` ;
  2. délivrer un nouvel acte, avec un nouveau numéro.
- La page `/verifier/{code}` affiche :
  - « Acte valide », avec le type, le numéro, le nom du doctorant et la date ;
  - ou « Acte annulé le ... » ;
  - et jamais d'autres données personnelles.

## Tests

- Délivrance réussie : numéro au bon format et fichier créé (`Storage::fake('local')`).
- Deux délivrances simultanées : numéros différents et consécutifs.
- Refus :
  - dérogation sans décision ;
  - inscription d'une autre année ;
  - doctorant qui télécharge l'acte d'un autre (`assertForbidden`).
- Page de vérification :
  - acte valide ;
  - acte annulé ;
  - code inconnu (`assertNotFound`).

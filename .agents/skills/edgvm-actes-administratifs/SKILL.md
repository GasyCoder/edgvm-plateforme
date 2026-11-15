---
name: edgvm-actes-administratifs
description: Module des actes administratifs de l'EDGVM (attestation d'inscription, admission, fiche d'évaluation et autres) : modèles modifiables avec variables, délivrance unitaire ou en lot, PDF dompdf avec QR code, vérification publique, annulation. À utiliser pour toute modification d'un modèle, d'une variable, de la mise en page PDF ou d'une règle de délivrance.
---

# Actes administratifs

Module en place : à faire évoluer, pas à recréer.

## Où se trouve quoi

- **Modèles** (`modeles_actes`, `App\Models\ModeleActe`) : titre, texte HTML avec variables `{{nom_complet}}`, champs complémentaires (`champs` json : cle, libelle, type `texte|texte_long|date|heure|choix`, options, obligatoire), options `exige_inscription`, `inclure_signature`, `actif`. Modifiables dans l'interface (`/actes/modeles`, éditeur Tiptap). Modèles de départ : `ModelesActesSeeder` (attestation d'inscription, admission, fiche d'évaluation), créés seulement s'ils n'existent pas.
- **Variables** : `App\Support\GabaritActe::VARIABLES` (liste affichée dans l'éditeur). Pour en ajouter une : la déclarer là avec libellé et exemple, puis la calculer dans `GabaritActe::valeurs()`. Un champ complémentaire ne peut pas porter le nom d'une variable du système.
- **Délivrance** : `App\Actions\DelivrerActe`. Contrôles : modèle actif ; si `exige_inscription`, inscription validée pour l'année et dérogation décidée. Sinon `ActeImpossible` (message montré tel quel). Numéro `EDGVM/{année}/{000001}` dans une transaction (`Acte::prochainNumero`), code de vérification aléatoire.
- **Texte figé** : l'acte stocke son titre, son texte rempli et nettoyé (`NettoyeurHtml`), et un instantané du signataire et des images (`ReglagesActes::instantane`). Modifier un modèle ou les réglages ne change jamais un acte déjà délivré. Les images remplacées ne sont pas effacées.
- **PDF** : `App\Support\GenerateurPdfActes` + vue `resources/views/actes/document.blade.php` (dompdf, A4, une page par acte, mise en page resserrée automatique pour les textes longs, QR code chillerlan). Rien n'est stocké : le PDF est produit à la demande à partir de l'acte.
- **Réglages** : `reglages` (clé/valeur) via `ReglagesActes` : signataire, fonction, titre, ville, logos gauche et droite, signature et cachet (disque privé `local`, dossier `reglages/`). Logos par défaut dans `resources/images/actes/`.
- **Routes** : API `/api/modeles-actes` (+ `/disponibles`, `/apercu`), `/api/actes` (+ `/lot`, `/{acte}/annuler`), `/api/reglages-actes`. Téléchargements par lien (session web, `routes/web.php`) : `/telechargements/actes/{id}.pdf`, `/telechargements/actes.pdf?ids[]=`. Vérification publique : `/verifier/{code}` (vue `actes/verification.blade.php`).
- **Droits** (`ActePolicy`, `ModeleActePolicy`) : l'administration délivre, annule et gère les modèles ; un membre d'équipe consulte les actes de ses doctorants ; un doctorant télécharge ses actes valides.

## Règles

- Avant de délivrer un acte qu'un doctorant a déjà (même modèle, non annulé, même année si le modèle exige l'inscription), l'API répond 409 `deja_delivre` avec la liste `deja`. L'interface demande confirmation, puis renvoie `doublons: confirmer` (ou, pour un lot, `ignorer` pour ne délivrer qu'aux autres). `Acte::dejaDelivres()` fait la recherche.
- Carte de doctorat : `App\Support\CarteDoctorat` + vue `cartes/carte.blade.php` (format 17 x 10 cm, une carte par page), photo du doctorant via `PhotoDoctorant` (disque privé `local`, dossier `photos/`, JPEG 450 x 600). Routes `/telechargements/doctorants/{uuid}/carte.pdf` et `/telechargements/cartes-doctorat.pdf?ids[]=`. Textes ministère et arrêté modifiables par les réglages `carte.ministere` et `carte.arrete`.
- Un acte délivré ne se modifie pas et ne se supprime pas : on l'annule (`annule_le`, `motif_annulation`, `annule_par`) et on en délivre un nouveau. Le PDF d'un acte annulé porte un filigrane « ANNULÉ ».
- La page `/verifier/{code}` n'affiche que le type, le numéro, le nom et la date : jamais d'autre donnée personnelle.
- Le HTML des modèles passe toujours par `NettoyeurHtml` (paragraphes, gras, italique, souligné, listes, alignement). Pas d'image ni de lien dans le texte.
- CSS de la vue PDF compatible dompdf : tableaux plutôt que flex ou grid, polices Times et Helvetica.
- Textes officiels (formules, signataire) : validés par l'École avant toute mise en production.

## Tests

`tests/Feature/ActesTest.php` et `ReglagesActesTest.php` couvrent :
- la délivrance et la numérotation ;
- les refus (sans inscription, inscription en attente, dérogation sans décision) ;
- les champs obligatoires ;
- le lot (avec les erreurs par doctorant) ;
- les droits par rôle, l'annulation et la vérification publique ;
- le nettoyage du HTML, les variables inconnues, l'aperçu ;
- le texte figé après modification du modèle ;
- les réglages et les images.

Ajouter un test à chaque nouvelle règle.

---
name: edgvm-api-laravel
description: Ajouter ou modifier un module de l'API Laravel de la plateforme EDGVM (backend/) : migration, modèle, enum, policy, Form Request, API Resource, contrôleur, routes et tests Pest. À utiliser pour tout travail sur doctorants, inscriptions, paiements, thèses, suivis, équipes d'accueil ou comptes utilisateurs. Ne couvre pas l'interface Nuxt, les notifications ni les actes PDF (skills dédiés).
---

# Module de l'API Laravel (backend/)

Lire d'abord `AGENTS.md` (rôles, modèle de données, règles métier, conventions).

## Étapes pour un nouveau module

Exemple : les paiements.

1. **Migration**
   - `php artisan make:migration create_paiements_table --no-interaction`.
   - Clés étrangères avec `constrained()` et `cascadeOnDelete()` ou `restrictOnDelete()` selon le métier. Un paiement ne disparaît jamais avec son inscription : utiliser `restrictOnDelete()`.
   - Montants en `unsignedBigInteger` (ariary).
2. **Modèle, factory et seeder**
   - `php artisan make:model Paiement -f --no-interaction`.
   - Dans le modèle : `$fillable`, une méthode `casts()`, des relations typées (`BelongsTo`, `HasMany`) et des scopes nommés en français (`scopeDeLAnnee`).
   - La factory doit avoir des états utiles, par exemple `->enRetard()` ou `->derogation()`.
3. **Enums** dans `app/Enums/`, par exemple `ModePaiement`, `NiveauDoctorat`, `StatutInscription`.
   - Ajouter une méthode `libelle(): string` pour l'affichage en français.
4. **Policy** : `php artisan make:policy PaiementPolicy --model=Paiement --no-interaction`.
   - `super_admin` peut tout faire.
   - `doctorant` ne voit que ce qui lui appartient : `$paiement->inscription->doctorant->user_id === $user->id`.
   - `membre_ead` ne voit que les doctorants de ses équipes d'accueil, et jamais le détail des paiements.
5. **Form Requests** (`StorePaiementRequest`, `UpdatePaiementRequest`)
   - Règles en tableau et messages en français.
   - `authorize()` délègue à la policy.
6. **API Resource** (`PaiementResource`)
   - Ne renvoyer que ce que le rôle a le droit de voir, avec `$this->when(...)`.
   - Les dates sont au format ISO. Les montants restent des entiers, et le texte formaté va dans un champ `*_aff`.
7. **Contrôleur** dans `app/Http/Controllers/Api/`.
   - Garder les méthodes fines.
   - Placer la logique métier réutilisable dans une classe d'action (`app/Actions/EnregistrerPaiement.php`) ou dans une méthode du modèle.
8. **Routes** dans `routes/api.php`, sous `auth:sanctum`, regroupées par domaine.
   - Utiliser des routes nommées (`paiements.store`) et `Route::apiResource` quand c'est possible.
9. **Tests Pest** dans `tests/Feature/` (voir ci-dessous).
10. Lancer `vendor/bin/pint --dirty`, puis les tests du module, puis toute la suite.

## Authentification

- Sanctum en mode SPA, par cookie :
  - le frontend appelle `GET /sanctum/csrf-cookie`, puis `POST /login` ;
  - les routes API sont protégées par `auth:sanctum`.
- Les domaines autorisés sont dans `SANCTUM_STATEFUL_DOMAINS` (local : `localhost:3000`) et `SESSION_DOMAIN`.
- La création des comptes est réservée au `super_admin`, sans inscription libre. Un compte doctorant est créé avec son dossier et reçoit un lien pour choisir son mot de passe.
- Le rôle est un enum `RoleUtilisateur` casté sur `users.role`. Prévoir des helpers `estSuperAdmin()`, `estDoctorant()` et `estMembreEad()`.

## Tests attendus pour chaque route

```php
it('lets the school record a payment', function (): void {
    $inscription = Inscription::factory()->create(['montant_du' => 1_500_000]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson(route('paiements.store'), [
            'inscription_id' => $inscription->id,
            'montant' => 500_000,
            'date_paiement' => '2026-11-15',
            'mode' => 'especes',
        ])
        ->assertCreated();

    expect($inscription->fresh()->resteAPayer())->toBe(1_000_000);
});

it('forbids a doctoral student from seeing another student file', function (): void {
    $autre = Doctorant::factory()->create();

    $this->actingAs(User::factory()->doctorant()->create())
        ->getJson(route('doctorants.show', $autre))
        ->assertForbidden();
});
```

- Utiliser les assertions précises : `assertForbidden()`, `assertNotFound()`, `assertUnprocessable()` et `assertCreated()`.
- Couvrir les règles métier :
  - reste à payer ;
  - dérogation sans décision (aucune attestation possible) ;
  - niveau D1 à D3 ;
  - montant négatif ou nul (refusé).
- Utiliser des datasets Pest pour les règles de validation.

## À éviter

- Calculer le reste à payer ailleurs que dans le modèle : il doit exister à un seul endroit.
- Renvoyer un modèle brut (`return $doctorant;`) : toujours passer par une Resource.
- Exposer des données personnelles (date de naissance, téléphone, paiements) dans une route accessible à un autre rôle.

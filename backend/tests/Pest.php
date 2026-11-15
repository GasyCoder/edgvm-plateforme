<?php

use App\Models\AnneeUniversitaire;
use App\Models\TypeFrais;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Requête venant de l'interface Nuxt (domaine « stateful » : session Sanctum active).
 */
function depuisInterface(): TestCase
{
    return test()->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/']);
}

function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

function anneeCourante(): AnneeUniversitaire
{
    return AnneeUniversitaire::factory()->rentree(2026)->courante()->create();
}

/**
 * Tarifs des droits d'inscription (le frais est créé par la migration) : D1, D2, D3, dérogation.
 *
 * @param  array{D1: int, D2: int, D3: int, derogation: int}  $tarifs
 */
function tarifsInscription(array $tarifs = ['D1' => 1_500_000, 'D2' => 1_200_000, 'D3' => 1_200_000, 'derogation' => 2_000_000]): TypeFrais
{
    $type = TypeFrais::query()->where('code', 'droits-inscription')->sole();
    $type->update(['montants_niveaux' => $tarifs, 'montant' => $tarifs['D1']]);

    return $type;
}

<?php

use App\Models\AnneeUniversitaire;
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

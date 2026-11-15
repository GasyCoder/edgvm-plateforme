<?php

use App\Enums\NiveauDoctorat;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\User;

it('counts the current year registrations by level', function (): void {
    $annee = anneeCourante();
    $equipe = EquipeAccueil::factory()->create();
    foreach ([NiveauDoctorat::D1, NiveauDoctorat::D1, NiveauDoctorat::D3] as $niveau) {
        Inscription::factory()->for(Doctorant::factory()->dansEquipe($equipe))->for($annee, 'anneeUniversitaire')->niveau($niveau)->create();
    }
    Inscription::factory()->for(Doctorant::factory())->for($annee, 'anneeUniversitaire')->derogation(null)->create();
    Doctorant::factory()->create();

    $this->actingAs(superAdmin())->getJson(route('tableau-de-bord'))
        ->assertOk()
        ->assertJsonPath('data.annee.libelle', '2026-2027')
        ->assertJsonPath('data.doctorants_actifs', 5)
        ->assertJsonPath('data.inscrits', 4)
        ->assertJsonPath('data.par_niveau', ['D1' => 2, 'D2' => 0, 'D3' => 1, 'derogation' => 1])
        ->assertJsonPath('data.derogations_sans_decision', 1)
        ->assertJsonPath('data.actifs_sans_inscription', 1);

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('tableau-de-bord'))
        ->assertJsonPath('data.inscrits', 3)
        ->assertJsonCount(1, 'data.par_equipe');
});

it('points a student to their own file', function (): void {
    $doctorant = Doctorant::factory()->create();

    $this->actingAs($doctorant->user)->getJson(route('tableau-de-bord'))->assertJsonPath('data.doctorant_id', $doctorant->uuid);
});

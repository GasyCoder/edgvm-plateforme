<?php

use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\User;

it('lists every team for the school and only their own for a team member', function (): void {
    $equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 2']);
    EquipeAccueil::factory()->create(['sigle' => 'EAD 10']);
    EquipeAccueil::factory()->create(['sigle' => 'EAD 1']);

    $this->actingAs(superAdmin())->getJson(route('equipes.index'))
        ->assertOk()
        ->assertJsonPath('data.*.sigle', ['EAD 1', 'EAD 2', 'EAD 10']);
    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('equipes.index'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sigle', 'EAD 2');
    $this->actingAs(Doctorant::factory()->create()->user)->getJson(route('equipes.index'))->assertForbidden();
});

it('creates, renames and deletes an empty team', function (): void {
    $admin = superAdmin();

    $id = $this->actingAs($admin)->postJson(route('equipes.store'), ['sigle' => 'EAD 9', 'nom' => 'Nouvelle équipe'])->assertCreated()->json('data.id');
    $this->actingAs($admin)->postJson(route('equipes.store'), ['sigle' => 'EAD 9', 'nom' => 'Doublon'])->assertUnprocessable()->assertJsonValidationErrors('sigle');
    $this->actingAs($admin)->putJson(route('equipes.update', $id), ['sigle' => 'EAD 9', 'nom' => 'Nom corrigé'])->assertOk()->assertJsonPath('data.nom', 'Nom corrigé');
    $this->actingAs($admin)->deleteJson(route('equipes.destroy', $id))->assertNoContent();
});

it('keeps a team that still has students', function (): void {
    $equipe = EquipeAccueil::factory()->create();
    Doctorant::factory()->dansEquipe($equipe)->create();

    $this->actingAs(superAdmin())->deleteJson(route('equipes.destroy', $equipe))->assertConflict();
    expect(EquipeAccueil::query()->find($equipe->id))->not->toBeNull();
});

it('keeps a single current academic year', function (): void {
    $ancienne = AnneeUniversitaire::factory()->rentree(2025)->courante()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('annees.store'), [
        'libelle' => '2026-2027', 'debut' => '2026-10-01', 'fin' => '2027-09-30', 'est_courante' => true,
    ])->assertCreated()->assertJsonPath('data.est_courante', true);

    expect($ancienne->fresh()->est_courante)->toBeFalse()
        ->and(AnneeUniversitaire::courante()->libelle)->toBe('2026-2027');

    $this->actingAs($admin)->postJson(route('annees.store'), ['libelle' => '2026/27', 'debut' => '2026-10-01', 'fin' => '2026-01-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['libelle', 'fin']);
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('annees.store'), [
        'libelle' => '2027-2028', 'debut' => '2027-10-01', 'fin' => '2028-09-30',
    ])->assertForbidden();
});

it('keeps a long team name on a single line, without extra spaces', function (): void {
    $equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1']);
    $nom = "Biotechnologies analytiques et impacts physiopathologiques\n des déchets,  de l'environnement et de l'eau";

    $this->actingAs(superAdmin())->putJson(route('equipes.update', $equipe), ['sigle' => ' EAD 1 ', 'nom' => $nom, 'description' => "Axe 1\nAxe 2"])
        ->assertOk()
        ->assertJsonPath('data.sigle', 'EAD 1')
        ->assertJsonPath('data.nom', "Biotechnologies analytiques et impacts physiopathologiques des déchets, de l'environnement et de l'eau")
        ->assertJsonPath('data.description', "Axe 1\nAxe 2");
});

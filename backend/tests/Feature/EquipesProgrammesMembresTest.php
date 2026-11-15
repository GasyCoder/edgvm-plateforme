<?php

use App\Enums\NiveauDoctorat;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\MembreEquipe;
use App\Models\ProgrammeDoctoral;
use App\Models\User;

beforeEach(function (): void {
    $this->equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1', 'nom' => 'Biotechnologies']);
});

it('shows the full team file: programmes, people, accounts and students by level', function (): void {
    $annee = anneeCourante();
    ProgrammeDoctoral::factory()->for($this->equipe)->create(['nom' => 'Phytothérapie', 'ordre' => 2]);
    ProgrammeDoctoral::factory()->for($this->equipe)->create(['nom' => 'Allopathie', 'ordre' => 1]);
    MembreEquipe::factory()->for($this->equipe)->membre()->create(['nom' => 'ZAFY Paul']);
    MembreEquipe::factory()->for($this->equipe)->create(['nom' => 'BEMA Lova']);
    MembreEquipe::factory()->for($this->equipe)->responsable()->create(['nom' => 'RAKOTO Eliot', 'titre' => 'Pr']);
    $compte = User::factory()->membreEad($this->equipe)->create();
    foreach (['D1', 'D1', 'D3'] as $niveau) {
        Inscription::factory()->for(Doctorant::factory()->dansEquipe($this->equipe))->for($annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::from($niveau))->create();
    }

    $this->actingAs(superAdmin())->getJson(route('equipes.show', $this->equipe))
        ->assertOk()
        ->assertJsonPath('data.programmes.*.nom', ['Allopathie', 'Phytothérapie'])
        ->assertJsonPath('data.personnes.*.nom', ['RAKOTO Eliot', 'BEMA Lova', 'ZAFY Paul'])
        ->assertJsonPath('data.personnes.0.nom_avec_titre', 'Pr RAKOTO Eliot')
        ->assertJsonPath('data.personnes.0.fonction_libelle', "Responsable de l'équipe")
        ->assertJsonPath('data.comptes.0.id', $compte->id)
        ->assertJsonPath('data.doctorants_count', 3)
        ->assertJsonPath('data.effectifs', ['D1' => 2, 'D2' => 0, 'D3' => 1, 'derogation' => 0]);

    $this->actingAs(superAdmin())->getJson(route('equipes.index'))
        ->assertJsonPath('data.0.programmes_count', 2)
        ->assertJsonPath('data.0.personnes_count', 3)
        ->assertJsonPath('data.0.responsable.nom', 'RAKOTO Eliot');
});

it('lets a team member read only the file of their own team', function (): void {
    $membre = User::factory()->membreEad($this->equipe)->create();

    $this->actingAs($membre)->getJson(route('equipes.show', $this->equipe))->assertOk();
    $this->actingAs($membre)->getJson(route('equipes.show', EquipeAccueil::factory()->create()))->assertForbidden();
    $this->actingAs($membre)->postJson(route('programmes.store', $this->equipe), ['nom' => 'Non'])->assertForbidden();
    $this->actingAs($membre)->postJson(route('membres-equipe.store', $this->equipe), ['nom' => 'Non', 'fonction' => 'membre'])->assertForbidden();
    $this->actingAs(Doctorant::factory()->dansEquipe($this->equipe)->create()->user)->getJson(route('equipes.show', $this->equipe))->assertForbidden();
});

it('adds, renames, orders and removes the programmes of a team', function (): void {
    $admin = superAdmin();

    $premier = $this->actingAs($admin)->postJson(route('programmes.store', $this->equipe), ['nom' => "  Santé et\n bien-être "])
        ->assertCreated()->assertJsonPath('data.nom', 'Santé et bien-être')->assertJsonPath('data.ordre', 1)->json('data.id');
    $this->actingAs($admin)->postJson(route('programmes.store', $this->equipe), ['nom' => 'Phytothérapie'])->assertJsonPath('data.ordre', 2);
    $this->actingAs($admin)->postJson(route('programmes.store', $this->equipe), ['nom' => 'Phytothérapie'])->assertJsonValidationErrors(['nom' => 'déjà un programme']);
    $this->actingAs($admin)->postJson(route('programmes.store', EquipeAccueil::factory()->create()), ['nom' => 'Phytothérapie'])->assertCreated();
    $this->actingAs($admin)->postJson(route('programmes.store', $this->equipe), ['nom' => ''])->assertJsonValidationErrors('nom');

    $this->actingAs($admin)->putJson(route('programmes.update', $premier), ['nom' => 'Santé et Bien-être', 'description' => 'Axe santé', 'ordre' => 5])
        ->assertOk()->assertJsonPath('data.description', 'Axe santé')->assertJsonPath('data.ordre', 5);
    $this->actingAs($admin)->deleteJson(route('programmes.destroy', $premier))->assertNoContent();

    expect($this->equipe->programmes()->pluck('nom')->all())->toBe(['Phytothérapie']);
});

it('keeps several people per team with their role, title and contacts', function (): void {
    $admin = superAdmin();

    $id = $this->actingAs($admin)->postJson(route('membres-equipe.store', $this->equipe), [
        'nom' => 'RAKOTO  Eliot', 'titre' => 'Professeur Titulaire', 'fonction' => 'responsable',
        'specialite' => 'Chimie analytique', 'email' => ' Eliot.Rakoto@Exemple.MG ', 'telephone' => '034 12 345 67',
    ])->assertCreated()
        ->assertJsonPath('data.nom', 'RAKOTO Eliot')
        ->assertJsonPath('data.email', 'eliot.rakoto@exemple.mg')
        ->assertJsonPath('data.telephone', '+261341234567')
        ->json('data.id');
    $this->actingAs($admin)->postJson(route('membres-equipe.store', $this->equipe), ['nom' => 'BEMA Lova', 'titre' => 'Dr', 'fonction' => 'encadrant'])->assertCreated();
    $this->actingAs($admin)->postJson(route('membres-equipe.store', $this->equipe), ['nom' => 'ZAFY Paul', 'fonction' => 'membre'])->assertCreated();

    $this->actingAs($admin)->postJson(route('membres-equipe.store', $this->equipe), ['nom' => 'X', 'fonction' => 'chef', 'email' => 'pas-un-mail', 'telephone' => '12'])
        ->assertJsonValidationErrors(['fonction', 'email', 'telephone']);

    $this->actingAs($admin)->putJson(route('membres-equipe.update', $id), ['nom' => 'RAKOTO Eliot', 'titre' => 'Pr', 'fonction' => 'encadrant'])
        ->assertOk()->assertJsonPath('data.fonction', 'encadrant')->assertJsonPath('data.nom_avec_titre', 'Pr RAKOTO Eliot');

    expect($this->equipe->personnes()->count())->toBe(3);
    $this->actingAs($admin)->deleteJson(route('membres-equipe.destroy', $id))->assertNoContent();
    expect($this->equipe->personnes()->count())->toBe(2);
});

it('saves the scientific domain of the team', function (): void {
    $this->actingAs(superAdmin())->putJson(route('equipes.update', $this->equipe), ['sigle' => 'EAD 1', 'nom' => 'Biotechnologies', 'domaine' => '  Sciences  de la vie '])
        ->assertOk()->assertJsonPath('data.domaine', 'Sciences de la vie');
});

it('deletes the programmes and people with an empty team', function (): void {
    ProgrammeDoctoral::factory()->for($this->equipe)->create();
    MembreEquipe::factory()->for($this->equipe)->create();

    $this->actingAs(superAdmin())->deleteJson(route('equipes.destroy', $this->equipe))->assertNoContent();

    expect(ProgrammeDoctoral::query()->count())->toBe(0)->and(MembreEquipe::query()->count())->toBe(0);
});

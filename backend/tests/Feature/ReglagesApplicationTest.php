<?php

use App\Models\Doctorant;
use App\Models\User;
use App\Support\CarteDoctorat;
use App\Support\ReglagesApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Réglages valides, à compléter ou modifier par test. */
function reglagesApplication(array $valeurs = []): array
{
    return [
        'app_nom' => 'EDGVM', 'app_sous_titre' => "Plateforme de l'École doctorale",
        'ecole_nom' => 'École Doctorale Génie du Vivant et Modélisation', 'ecole_universite' => 'Université de Mahajanga',
        'ecole_ministere' => "MINISTERE DE L'ENSEIGNEMENT SUPERIEUR", 'ecole_arrete' => 'Arrêté N° 1', 'ecole_description' => '',
        'signataire_nom' => 'ASSOUMACOU Elia Béatrice', 'signataire_grade' => 'Professeure', 'signataire_titre' => 'Professeure Titulaire',
        'indexation' => false, 'maintenance_active' => false, 'maintenance_message' => '', 'theme' => 'clair', 'couleur' => 'vert',
        ...$valeurs,
    ];
}

it('gives the public settings without login and the full settings to the school only', function (): void {
    $this->getJson(route('application'))
        ->assertOk()
        ->assertJsonPath('data.nom', 'EDGVM')
        ->assertJsonPath('data.maintenance.active', false)
        ->assertJsonPath('data.apparence', ['theme' => 'clair', 'couleur' => 'vert'])
        ->assertJsonMissingPath('data.signataire_nom');

    $this->actingAs(superAdmin())->getJson(route('reglages-application.show'))
        ->assertOk()->assertJsonPath('data.signataire_grade', 'Professeure')->assertJsonPath('data.ecole_arrete', "Arrêté d'habilitation N° 12259-2022-MESupRES du 29 Avril 2022.");
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('reglages-application.show'))->assertForbidden();
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('reglages-application.update'), reglagesApplication())->assertForbidden();
});

it('saves the name, the school, the director with grade and the appearance', function (): void {
    $this->actingAs(superAdmin())->postJson(route('reglages-application.update'), reglagesApplication([
        'app_nom' => 'EDGVM Scolarité', 'ecole_arrete' => 'Décret N° 2026-001 du 2 janvier 2026.', 'ecole_description' => "L'École forme des docteurs.",
        'signataire_nom' => 'RAKOTO  Jean', 'signataire_grade' => 'Professeur', 'theme' => 'sombre', 'couleur' => 'bleu',
    ]))->assertOk()->assertJsonPath('data.app_nom', 'EDGVM Scolarité')->assertJsonPath('data.theme', 'sombre');

    expect(ReglagesApplication::directeurAvecGrade())->toBe('Professeur RAKOTO Jean')
        ->and(ReglagesApplication::public()['apparence'])->toBe(['theme' => 'sombre', 'couleur' => 'bleu'])
        ->and(ReglagesApplication::nomPourCourriels())->toBe('EDGVM Scolarité');

    $carte = CarteDoctorat::html(collect([Doctorant::factory()->create()]));
    expect($carte)->toContain('Professeur RAKOTO Jean')->toContain('Décret N° 2026-001 du 2 janvier 2026.');
});

it('validates the settings', function (array $valeurs, string $champ): void {
    $this->actingAs(superAdmin())->postJson(route('reglages-application.update'), reglagesApplication($valeurs))
        ->assertUnprocessable()->assertJsonValidationErrors($champ);
})->with([
    'nom vide' => [['app_nom' => ''], 'app_nom'],
    'directeur vide' => [['signataire_nom' => ''], 'signataire_nom'],
    'thème inconnu' => [['theme' => 'rose'], 'theme'],
    'couleur inconnue' => [['couleur' => 'fuchsia'], 'couleur'],
    'maintenance sans message' => [['maintenance_active' => true, 'maintenance_message' => ''], 'maintenance_message'],
]);

it('replaces the logos used by the interface, the acts and the card, and serves them publicly', function (): void {
    Storage::fake('local');
    $admin = superAdmin();

    $this->get(route('marque.logo', 'logo_droite'))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->get(route('marque.logo', 'signature'))->assertNotFound();

    $this->actingAs($admin)->post(route('reglages-application.update'), [...reglagesApplication(), 'logo_edgvm' => UploadedFile::fake()->image('logo.png', 300, 300)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.logos.logo_edgvm.personnalise', true)->assertJsonPath('data.logos.logo_universite.personnalise', false);
    $this->get(route('marque.logo', 'logo_droite'))->assertOk()->assertHeader('content-type', 'image/png');

    $this->actingAs($admin)->postJson(route('reglages-application.update'), [...reglagesApplication(), 'retirer_logo_edgvm' => true])
        ->assertJsonPath('data.logos.logo_edgvm.personnalise', false);
});

it('closes the platform to everyone but the school during maintenance', function (): void {
    $admin = superAdmin();
    $membre = User::factory()->membreEad()->create();
    $this->actingAs($admin)->postJson(route('reglages-application.update'), reglagesApplication(['maintenance_active' => true, 'maintenance_message' => 'Retour à 14 h.']))->assertOk();

    $this->getJson(route('application'))->assertJsonPath('data.maintenance', ['active' => true, 'message' => 'Retour à 14 h.']);
    $this->actingAs($membre)->getJson(route('doctorants.index'))->assertStatus(503)->assertJsonPath('message', 'Retour à 14 h.')->assertJsonPath('maintenance', true);
    $this->actingAs($membre)->getJson(route('moi'))->assertOk();
    $this->actingAs($admin)->getJson(route('doctorants.index'))->assertOk();

    $this->actingAs($admin)->postJson(route('reglages-application.update'), reglagesApplication())->assertOk();
    $this->actingAs($membre)->getJson(route('doctorants.index'))->assertOk();
});

it('keeps search engines out unless indexing is allowed', function (): void {
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);

    $this->actingAs(superAdmin())->postJson(route('reglages-application.update'), reglagesApplication(['indexation' => true]))->assertOk();
    auth()->forgetGuards();

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /api/')->assertDontSee("Disallow: /\n", false);
});

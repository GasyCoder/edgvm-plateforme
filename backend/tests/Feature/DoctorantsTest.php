<?php

use App\Enums\NiveauDoctorat;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\InvitationPlateforme;
use Illuminate\Support\Facades\Notification;

/**
 * @return array<string, mixed>
 */
function nouveauDoctorant(array $valeurs = []): array
{
    return [
        'nom' => 'Rabe',
        'prenoms' => 'Fanja',
        'genre' => 'F',
        'email' => 'Fanja.Rabe@gmail.com',
        'telephone' => '034 12 345 67',
        'equipe_accueil_id' => EquipeAccueil::factory()->create()->id,
        'sujet_these' => 'Valorisation des plantes médicinales du Boeny',
        ...$valeurs,
    ];
}

it('creates the account, the file and the first registration, then sends the access link', function (): void {
    Notification::fake();
    $annee = anneeCourante();

    $reponse = $this->actingAs(superAdmin())->postJson(route('doctorants.store'), nouveauDoctorant([
        'inscription' => ['annee_universitaire_id' => $annee->id, 'niveau' => 'D1', 'montant_du' => 1_500_000],
    ]))->assertCreated();

    $doctorant = Doctorant::query()->sole();
    expect($doctorant->user->email)->toBe('fanja.rabe@gmail.com')
        ->and($doctorant->user->telephone)->toBe('+261341234567')
        ->and($doctorant->user->name)->toBe('RABE Fanja')
        ->and($doctorant->user->estDoctorant())->toBeTrue()
        ->and($doctorant->matricule)->toBe('EDGVM-'.now()->year.'-0001');

    $reponse->assertJsonPath('data.id', $doctorant->uuid)
        ->assertJsonPath('data.inscription_courante.niveau_aff', 'D1')
        ->assertJsonPath('data.inscription_courante.type', 'premiere')
        ->assertJsonPath('data.inscription_courante.montant_du', 1_500_000)
        ->assertJsonPath('data.prochaine_inscription.niveau', 'D2')
        ->assertJsonPath('data.compte.id', $doctorant->user_id);

    Notification::assertSentTo($doctorant->user, InvitationPlateforme::class);
});

it('does not send the access link when asked not to', function (): void {
    Notification::fake();

    $this->actingAs(superAdmin())->postJson(route('doctorants.store'), nouveauDoctorant(['envoyer_invitation' => false]))->assertCreated();

    Notification::assertNothingSent();
});

it('numbers the student ids one after the other', function (): void {
    Doctorant::factory()->create(['matricule' => 'EDGVM-'.now()->year.'-0041']);

    expect(Doctorant::prochainMatricule())->toBe('EDGVM-'.now()->year.'-0042');
});

it('validates the file', function (array $valeurs, string $champ): void {
    User::factory()->create(['email' => 'pris@edgvm.mg']);

    $this->actingAs(superAdmin())->postJson(route('doctorants.store'), nouveauDoctorant($valeurs))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($champ);
})->with([
    'nom manquant' => [['nom' => ''], 'nom'],
    'e-mail déjà utilisé' => [['email' => 'pris@edgvm.mg'], 'email'],
    'téléphone illisible' => [['telephone' => '12'], 'telephone'],
    'équipe inconnue' => [['equipe_accueil_id' => 999], 'equipe_accueil_id'],
    'niveau inconnu' => [['inscription' => ['annee_universitaire_id' => 1, 'niveau' => 'D7', 'montant_du' => 0]], 'inscription.niveau'],
    'montant négatif' => [['inscription' => ['annee_universitaire_id' => 1, 'niveau' => 'D1', 'montant_du' => -5]], 'inscription.montant_du'],
]);

it('updates the file and keeps the account in sync', function (): void {
    $doctorant = Doctorant::factory()->create();

    $this->actingAs(superAdmin())->putJson(route('doctorants.update', $doctorant), [
        ...nouveauDoctorant(['nom' => 'Andria', 'email' => 'nouvelle@edgvm.mg']),
        'statut' => 'suspendu',
    ])->assertOk()->assertJsonPath('data.statut', 'suspendu');

    expect($doctorant->user->fresh())->name->toBe('ANDRIA Fanja')->email->toBe('nouvelle@edgvm.mg');
});

it('lists and filters the files for the school', function (): void {
    $annee = anneeCourante();
    $equipe = EquipeAccueil::factory()->create();
    $d2 = Doctorant::factory()->dansEquipe($equipe)->create(['nom' => 'Zafy']);
    Inscription::factory()->for($d2)->for($annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::D2)->create();
    $derogation = Doctorant::factory()->create(['nom' => 'Bema']);
    Inscription::factory()->for($derogation)->for($annee, 'anneeUniversitaire')->derogation()->create();
    Doctorant::factory()->create(['nom' => 'Sans']);
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('doctorants.index'))->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);
    $this->actingAs($admin)->getJson(route('doctorants.index', ['niveau' => 'D2']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.nom', 'Zafy');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['niveau' => 'derogation']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.inscription_courante.niveau_aff', 'Dérogation');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['equipe' => $equipe->id]))->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['recherche' => 'bem']))->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['sans_inscription' => 1]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.nom', 'Sans');
});

it('shows a team member only the students of their teams, without personal data', function (): void {
    $equipe = EquipeAccueil::factory()->create();
    $membre = User::factory()->membreEad($equipe)->create();
    $sien = Doctorant::factory()->dansEquipe($equipe)->create(['date_naissance' => '1990-01-01']);
    $autre = Doctorant::factory()->create();

    $this->actingAs($membre)->getJson(route('doctorants.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sien->uuid);
    $this->actingAs($membre)->getJson(route('doctorants.show', $sien))
        ->assertOk()
        ->assertJsonMissingPath('data.date_naissance')
        ->assertJsonMissingPath('data.observation')
        ->assertJsonPath('data.peut.modifier', false);
    $this->actingAs($membre)->getJson(route('doctorants.show', $autre))->assertForbidden();
    $this->actingAs($membre)->postJson(route('doctorants.store'), nouveauDoctorant())->assertForbidden();
});

it('hides registration fees from team members', function (): void {
    $annee = anneeCourante();
    $equipe = EquipeAccueil::factory()->create();
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create();
    Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire')->derogation()->create();

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('doctorants.show', $doctorant))
        ->assertJsonMissingPath('data.inscriptions.0.montant_du')
        ->assertJsonMissingPath('data.inscriptions.0.motif_derogation');
    $this->actingAs($doctorant->user)->getJson(route('doctorants.show', $doctorant))
        ->assertJsonPath('data.inscriptions.0.montant_du', 1_500_000);
});

it('lets a student see only their own file', function (): void {
    $doctorant = Doctorant::factory()->create(['date_naissance' => '1990-05-04']);
    $autre = Doctorant::factory()->create();

    $this->actingAs($doctorant->user)->getJson(route('doctorants.show', $doctorant))
        ->assertOk()
        ->assertJsonPath('data.date_naissance', '1990-05-04')
        ->assertJsonMissingPath('data.observation');
    $this->actingAs($doctorant->user)->getJson(route('doctorants.show', $autre))->assertForbidden();
    $this->actingAs($doctorant->user)->getJson(route('doctorants.index'))->assertForbidden();
    $this->actingAs($doctorant->user)->putJson(route('doctorants.update', $doctorant), nouveauDoctorant())->assertForbidden();
});

it('uses the uuid in addresses, never the internal id', function (): void {
    $doctorant = Doctorant::factory()->create();

    $this->actingAs(superAdmin())->getJson('/api/doctorants/'.$doctorant->id)->assertNotFound();
});

it('checks one wizard step at a time without saving anything', function (): void {
    User::factory()->create(['email' => 'pris@edgvm.mg']);
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('doctorants.store'), ['etape' => 'identite', 'nom' => 'Rabe'])->assertNoContent();
    $this->actingAs($admin)->postJson(route('doctorants.store'), ['etape' => 'identite', 'nom' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('nom')->assertJsonMissingValidationErrors(['email', 'equipe_accueil_id']);
    $this->actingAs($admin)->postJson(route('doctorants.store'), ['etape' => 'contact', 'email' => 'pris@edgvm.mg'])
        ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonMissingValidationErrors('nom');
    $this->actingAs($admin)->postJson(route('doctorants.store'), ['etape' => 'inscription', 'inscription' => ['annee_universitaire_id' => 999, 'niveau' => 'D9', 'montant_du' => 0]])
        ->assertUnprocessable()->assertJsonValidationErrors(['inscription.annee_universitaire_id', 'inscription.niveau']);

    expect(Doctorant::query()->count())->toBe(0);
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('doctorants.store'), ['etape' => 'identite', 'nom' => 'X'])->assertForbidden();
});

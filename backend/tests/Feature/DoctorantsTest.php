<?php

use App\Enums\NiveauDoctorat;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\FraisDoctorant;
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
    tarifsInscription();

    $reponse = $this->actingAs(superAdmin())->postJson(route('doctorants.store'), nouveauDoctorant([
        'inscription' => ['annee_universitaire_id' => $annee->id, 'niveau' => 'D1'],
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
        ->assertJsonPath('data.paiement.du', 1_500_000)
        ->assertJsonPath('data.paiement.statut', 'a_venir')
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
    'niveau inconnu' => [['inscription' => ['annee_universitaire_id' => 1, 'niveau' => 'D7']], 'inscription.niveau'],
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
    FraisDoctorant::factory()->for($doctorant)->echu()->create(['montant' => 1_500_000]);

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('doctorants.show', $doctorant))
        ->assertJsonPath('data.paiement.statut', 'en_retard')
        ->assertJsonMissingPath('data.paiement.du')
        ->assertJsonMissingPath('data.inscriptions.0.motif_derogation');
    $this->actingAs($doctorant->user)->getJson(route('doctorants.show', $doctorant))
        ->assertJsonPath('data.paiement.du', 1_500_000)
        ->assertJsonPath('data.paiement.en_retard', 1_500_000);
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
    $this->actingAs($admin)->postJson(route('doctorants.store'), ['etape' => 'inscription', 'inscription' => ['annee_universitaire_id' => 999, 'niveau' => 'D9']])
        ->assertUnprocessable()->assertJsonValidationErrors(['inscription.annee_universitaire_id', 'inscription.niveau']);

    expect(Doctorant::query()->count())->toBe(0);
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('doctorants.store'), ['etape' => 'identite', 'nom' => 'X'])->assertForbidden();
});

it('marks scholarship holders and filters and counts them', function (): void {
    $boursier = Doctorant::factory()->create(['nom' => 'Boursier']);
    Doctorant::factory()->create(['nom' => 'Autre']);
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(route('doctorants.update', $boursier), [...nouveauDoctorant(['nom' => 'Boursier', 'email' => $boursier->user->email]), 'statut' => 'actif', 'est_boursier' => true])
        ->assertOk()->assertJsonPath('data.est_boursier', true);
    $this->actingAs($admin)->putJson(route('doctorants.update', $boursier), [...nouveauDoctorant(['email' => $boursier->user->email]), 'statut' => 'actif', 'est_boursier' => 'peut-être'])
        ->assertJsonValidationErrors('est_boursier');

    $this->actingAs($admin)->getJson(route('doctorants.index', ['boursier' => 1]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $boursier->uuid);
    $this->actingAs($admin)->getJson(route('doctorants.index', ['boursier' => 0]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.nom', 'Autre');
    $this->actingAs($admin)->getJson(route('doctorants.compteurs'))->assertJsonPath('boursiers', 1)->assertJsonPath('total', 2);
    $this->actingAs($admin)->getJson(route('doctorants.compteurs', ['boursier' => 1]))->assertJsonPath('total', 1);
});

it('counts the students by level of the current year, with every filter but the level', function (): void {
    $annee = anneeCourante();
    $equipe = EquipeAccueil::factory()->create();
    foreach (['D1', 'D1', 'D2', 'D3'] as $niveau) {
        $doctorant = Doctorant::factory()->dansEquipe($equipe)->create();
        Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::from($niveau))->create();
    }
    $derogation = Doctorant::factory()->dansEquipe($equipe)->create();
    Inscription::factory()->for($derogation)->for($annee, 'anneeUniversitaire')->derogation()->create();
    Doctorant::factory()->dansEquipe($equipe)->create();
    $ailleurs = Doctorant::factory()->create();
    Inscription::factory()->for($ailleurs)->for($annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::D1)->create();
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('doctorants.compteurs', ['equipe' => $equipe->id, 'niveau' => 'D1']))
        ->assertOk()
        ->assertJsonPath('total', 6)
        ->assertJsonPath('niveaux', ['D1' => 2, 'D2' => 1, 'D3' => 1, 'derogation' => 1])
        ->assertJsonMissingPath('paiement');
    $this->actingAs($admin)->getJson(route('doctorants.compteurs'))->assertJsonPath('total', 7)->assertJsonPath('niveaux.D1', 3);

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('doctorants.compteurs'))
        ->assertOk()->assertJsonPath('total', 6)->assertJsonPath('niveaux.D1', 2);
    $this->actingAs($derogation->user)->getJson(route('doctorants.compteurs'))->assertForbidden();
});

it('checks one step of the edit wizard without saving anything', function (): void {
    $doctorant = Doctorant::factory()->create(['nom' => 'Avant']);
    User::factory()->create(['email' => 'pris@edgvm.mg']);
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(route('doctorants.update', $doctorant), ['etape' => 'identite', 'nom' => 'Apres'])->assertNoContent();
    $this->actingAs($admin)->putJson(route('doctorants.update', $doctorant), ['etape' => 'contact', 'email' => $doctorant->user->email])->assertNoContent();
    $this->actingAs($admin)->putJson(route('doctorants.update', $doctorant), ['etape' => 'contact', 'email' => 'pris@edgvm.mg'])
        ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonMissingValidationErrors('nom');
    $this->actingAs($admin)->putJson(route('doctorants.update', $doctorant), ['etape' => 'inscription', 'statut' => 'parti'])
        ->assertUnprocessable()->assertJsonValidationErrors('statut');
    $this->actingAs($admin)->putJson(route('doctorants.update', $doctorant), ['etape' => 'inscription', 'statut' => 'actif', 'inscription' => ['niveau' => 'D1']])
        ->assertUnprocessable()->assertJsonValidationErrors('inscription');

    expect($doctorant->fresh()->nom)->toBe('Avant');
    $this->actingAs($doctorant->user)->putJson(route('doctorants.update', $doctorant), ['etape' => 'identite', 'nom' => 'X'])->assertForbidden();
});

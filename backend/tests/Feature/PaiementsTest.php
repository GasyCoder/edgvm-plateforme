<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\FraisDoctorant;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\PaiementEnregistre;
use App\Notifications\PaiementRecu;
use App\Notifications\RappelPaiement;
use App\Support\SituationFinanciere;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->annee = anneeCourante();
    tarifsInscription();
});

/** Doctorant avec deux frais : droits (échus) puis tee-shirt (à venir). */
function doctorantAvecFrais(): Doctorant
{
    $doctorant = Doctorant::factory()->create();
    FraisDoctorant::factory()->for($doctorant)->echu(15)->create(['libelle' => "Droits d'inscription", 'montant' => 1_000_000]);
    FraisDoctorant::factory()->for($doctorant)->create(['libelle' => 'Tee-shirt', 'montant' => 30_000, 'echeance' => now()->addDays(20)]);

    return $doctorant;
}

it('creates registration fees at the level tariff, due at the end of the academic year', function (string $niveau, bool $derogation, int $attendu): void {
    $doctorant = Doctorant::factory()->create();
    foreach ($derogation ? range(1, 3) : [] as $rang) {
        Inscription::factory()->for($doctorant)->for(AnneeUniversitaire::factory()->rentree(2022 + $rang), 'anneeUniversitaire')->niveau(NiveauDoctorat::from('D'.$rang))->create();
    }

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $this->annee->id, 'niveau' => $niveau, 'statut' => 'validee', 'date_inscription' => '2026-11-02',
        'est_derogation' => $derogation, 'motif_derogation' => $derogation ? 'Terrain' : null,
    ])->assertCreated()->assertJsonPath('data.frais', $attendu);

    $frais = $doctorant->frais()->sole();
    expect($frais->montant)->toBe($attendu)->and($frais->echeance->toDateString())->toBe('2027-09-30');
})->with([
    'D1' => ['D1', false, 1_500_000],
    'D2' => ['D2', false, 1_200_000],
    'dérogation' => ['D3', true, 2_000_000],
]);

it('allocates a payment to the oldest fees first and numbers the receipt', function (): void {
    Notification::fake();
    $doctorant = doctorantAvecFrais();
    $admin = superAdmin();
    $collegue = superAdmin();

    $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), [
        'montant' => 1_010_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes',
    ])->assertCreated()
        ->assertJsonPath('data.numero_recu', 'REC-'.now()->year.'-000001')
        ->assertJsonPath('data.enregistre_le', fn (string $moment) => now()->diffInSeconds($moment, true) < 60)
        ->assertJsonPath('data.imputations.0.libelle', "Droits d'inscription")
        ->assertJsonPath('data.imputations.0.montant', 1_000_000)
        ->assertJsonPath('data.imputations.1.montant', 10_000);

    $this->actingAs($admin)->getJson(route('doctorants.finances', $doctorant))
        ->assertJsonPath('data.situation.paye', 1_010_000)
        ->assertJsonPath('data.situation.reste', 20_000)
        ->assertJsonPath('data.situation.statut', 'partiel');

    Notification::assertSentTo($doctorant->user, PaiementRecu::class);
    Notification::assertSentTo($collegue, PaiementEnregistre::class);
    Notification::assertNotSentTo($admin, PaiementEnregistre::class);
});

it('accepts an explicit split and rejects inconsistent ones', function (): void {
    $doctorant = doctorantAvecFrais();
    [$droits, $teeShirt] = $doctorant->frais()->orderBy('id')->get()->all();
    $admin = superAdmin();
    $base = ['date_paiement' => today()->toDateString(), 'mode' => 'mobile_money', 'reference' => 'MP261115.1234'];

    $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), [...$base, 'montant' => 30_000, 'imputations' => [['frais' => $teeShirt->id, 'montant' => 30_000]]])->assertCreated();
    expect($teeShirt->fresh()->reste())->toBe(0)->and($droits->fresh()->reste())->toBe(1_000_000);

    $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), [...$base, 'montant' => 5_000, 'imputations' => [['frais' => $teeShirt->id, 'montant' => 5_000]]])
        ->assertUnprocessable()->assertJsonValidationErrors(['imputations' => "Un des frais choisis est déjà soldé ou n'appartient pas à ce doctorant."]);
    $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), [...$base, 'montant' => 100_000, 'imputations' => [['frais' => $droits->id, 'montant' => 90_000]]])
        ->assertUnprocessable()->assertJsonValidationErrors('imputations');
});

it('validates a payment', function (array $valeurs, string $champ): void {
    $this->actingAs(superAdmin())->postJson(route('paiements.store', doctorantAvecFrais()), [
        'montant' => 10_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes', ...$valeurs,
    ])->assertUnprocessable()->assertJsonValidationErrors($champ);
})->with([
    'plus que le reste' => [['montant' => 2_000_000], 'montant'],
    'date future' => [['date_paiement' => now()->addDay()->toDateString()], 'date_paiement'],
    'mobile money sans référence' => [['mode' => 'mobile_money'], 'reference'],
    'mode inconnu' => [['mode' => 'troc'], 'mode'],
]);

it('cancels a payment: fees are due again and the student no longer sees it', function (): void {
    $doctorant = doctorantAvecFrais();
    $admin = superAdmin();
    $id = $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), ['montant' => 1_000_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes'])->json('data.id');

    $this->actingAs($admin)->postJson(route('paiements.annuler', $id), ['motif' => 'Saisie en double'])->assertOk()->assertJsonPath('data.peut_annuler', false);

    expect($doctorant->frais()->sum('montant_paye'))->toBe(0);
    $this->actingAs($doctorant->user)->getJson(route('doctorants.finances', $doctorant))->assertJsonCount(0, 'data.paiements')->assertJsonPath('data.situation.statut', 'en_retard');
    $this->actingAs($doctorant->user)->get(route('paiements.recu', $id))->assertForbidden();
});

it('serves the receipt to the school and to the student only', function (): void {
    $doctorant = doctorantAvecFrais();
    $id = $this->actingAs(superAdmin())->postJson(route('paiements.store', $doctorant), ['montant' => 500_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes'])->json('data.id');

    $this->actingAs($doctorant->user)->get(route('paiements.recu', $id))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('paiements.recu', $id))->assertForbidden();
    expect(SituationFinanciere::enLettres(1_500_000))->toBe('un million cinq cent mille ariary');
});

it('shows the situation of every student and filters late ones', function (): void {
    $enRetard = doctorantAvecFrais();
    $aJour = Doctorant::factory()->create();
    FraisDoctorant::factory()->for($aJour)->echu()->create(['montant' => 100_000, 'montant_paye' => 100_000]);
    Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('paiements.situation', ['situation' => 'en_retard']))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $enRetard->uuid)->assertJsonPath('data.0.en_retard', 1_000_000);
    $this->actingAs($admin)->getJson(route('paiements.situation', ['situation' => 'a_jour']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.statut', 'solde');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['paiement' => 'en_retard']))->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson(route('paiements.statistiques'))
        ->assertJsonPath('data.doctorants_en_retard', 1)
        ->assertJsonPath('data.reste_a_recouvrer', 1_030_000)
        ->assertJsonPath('data.montant_en_retard', 1_000_000);
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('paiements.situation'))->assertForbidden();
});

it('keeps finances private to the school and the student', function (): void {
    $equipe = EquipeAccueil::factory()->create();
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create();

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('doctorants.finances', $doctorant))->assertForbidden();
    $this->actingAs(Doctorant::factory()->create()->user)->getJson(route('doctorants.finances', $doctorant))->assertForbidden();
    $this->actingAs($doctorant->user)->getJson(route('doctorants.finances', $doctorant))->assertOk();
    $this->actingAs($doctorant->user)->postJson(route('paiements.store', $doctorant), ['montant' => 1, 'date_paiement' => today()->toDateString(), 'mode' => 'especes'])->assertForbidden();
});

it('refuses to delete a registration that has payments, and cancels unpaid fees with the registration', function (): void {
    $doctorant = Doctorant::factory()->create();
    $admin = superAdmin();
    $inscriptionId = $this->actingAs($admin)->postJson(route('inscriptions.store', $doctorant), ['annee_universitaire_id' => $this->annee->id, 'niveau' => 'D1', 'statut' => 'validee'])->json('data.id');

    $this->actingAs($admin)->putJson(route('inscriptions.update', $inscriptionId), ['annee_universitaire_id' => $this->annee->id, 'niveau' => 'D1', 'statut' => 'annulee'])->assertOk();
    expect($doctorant->frais()->sole()->annule_le)->not->toBeNull();

    $autre = Doctorant::factory()->create();
    $id = $this->actingAs($admin)->postJson(route('inscriptions.store', $autre), ['annee_universitaire_id' => $this->annee->id, 'niveau' => 'D1', 'statut' => 'validee'])->json('data.id');
    $this->actingAs($admin)->postJson(route('paiements.store', $autre), ['montant' => 100_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes']);

    $this->actingAs($admin)->deleteJson(route('inscriptions.destroy', $id))->assertConflict();
    expect(Paiement::query()->count())->toBe(1);
});

it('keeps on the receipt the balance left right after the payment', function (): void {
    $doctorant = doctorantAvecFrais();
    $admin = superAdmin();

    $paiement = $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), ['montant' => 1_030_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes'])
        ->assertJsonPath('data.reste_apres', 0)->json('data.id');
    FraisDoctorant::factory()->for($doctorant)->create(['libelle' => 'Cotisation', 'montant' => 20_000]);

    expect(Paiement::query()->find($paiement)->reste_apres)->toBe(0)
        ->and(SituationFinanciere::pour($doctorant)['reste'])->toBe(20_000);
});

it('stores the bank slip photo with the payment, privately', function (): void {
    Storage::fake(Paiement::DISQUE);
    $doctorant = doctorantAvecFrais();
    $admin = superAdmin();

    $reponse = $this->actingAs($admin)->post(route('paiements.store', $doctorant), [
        'montant' => '1000000', 'date_paiement' => today()->toDateString(), 'mode' => 'virement', 'reference' => 'BOA-2026-00123',
        'imputations' => [['frais' => $doctorant->frais()->orderBy('id')->first()->id, 'montant' => '1000000']],
        'justificatif' => UploadedFile::fake()->image('bordereau.jpg', 1200, 1600),
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.justificatif.nom', 'bordereau.jpg')
        ->assertJsonPath('data.justificatif.est_image', true);

    $paiement = Paiement::query()->findOrFail($reponse->json('data.id'));
    expect($paiement->justificatif_chemin)->toStartWith("paiements/{$doctorant->uuid}/REC-");
    Storage::disk(Paiement::DISQUE)->assertExists($paiement->justificatif_chemin);

    $this->actingAs($admin)->get(route('paiements.voir-justificatif', $paiement))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->actingAs($doctorant->user)->get(route('paiements.voir-justificatif', $paiement))->assertOk();
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('paiements.voir-justificatif', $paiement))->assertForbidden();
});

it('adds or replaces the bank slip afterwards and removes the old file', function (): void {
    Storage::fake(Paiement::DISQUE);
    $doctorant = doctorantAvecFrais();
    $admin = superAdmin();
    $id = $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), ['montant' => 30_000, 'date_paiement' => today()->toDateString(), 'mode' => 'virement', 'reference' => 'BOA-1'])
        ->assertJsonPath('data.justificatif', null)->json('data.id');

    $this->actingAs($admin)->getJson(route('paiements.index', ['justificatif' => 'manquant']))->assertJsonCount(1, 'data');
    $this->actingAs($admin)->post(route('paiements.justificatif', $id), ['justificatif' => UploadedFile::fake()->create('scan.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.justificatif.est_image', false);
    $premier = Paiement::query()->find($id)->justificatif_chemin;

    $this->actingAs($admin)->post(route('paiements.justificatif', $id), ['justificatif' => UploadedFile::fake()->image('photo.png')], ['Accept' => 'application/json'])->assertOk();
    Storage::disk(Paiement::DISQUE)->assertMissing($premier);
    $this->actingAs($admin)->getJson(route('paiements.index', ['justificatif' => 'manquant']))->assertJsonCount(0, 'data');
    $this->actingAs($doctorant->user)->post(route('paiements.justificatif', $id), ['justificatif' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])->assertForbidden();
});

it('refuses a bank slip that is not a photo or a PDF', function (): void {
    $this->actingAs(superAdmin())->post(route('paiements.store', doctorantAvecFrais()), [
        'montant' => 10_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes',
        'justificatif' => UploadedFile::fake()->create('releve.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.justificatif.0', 'Le reçu de versement doit être une photo (JPG, PNG, WEBP) ou un PDF.');
});

it('gives the totals of the filtered situation and the next deadline of each student', function (): void {
    $enRetard = doctorantAvecFrais();
    $aJour = Doctorant::factory()->create();
    FraisDoctorant::factory()->for($aJour)->create(['montant' => 100_000, 'montant_paye' => 100_000]);
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('paiements.situation'))
        ->assertJsonPath('totaux.doctorants', 2)
        ->assertJsonPath('totaux.du', 1_130_000)
        ->assertJsonPath('totaux.paye', 100_000)
        ->assertJsonPath('totaux.reste', 1_030_000)
        ->assertJsonPath('totaux.en_retard', 1_000_000);
    $this->actingAs($admin)->getJson(route('paiements.situation', ['situation' => 'en_retard']))
        ->assertJsonPath('totaux.doctorants', 1)
        ->assertJsonPath('data.0.prochaine_echeance', now()->subDays(15)->toDateString());
});

it('sends a payment reminder to chosen students, once a day at most', function (): void {
    Notification::fake();
    $doit = doctorantAvecFrais();
    $rienAPayer = Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('paiements.rappels'), ['doctorants' => [$doit->uuid, $rienAPayer->uuid]])
        ->assertOk()->assertJsonPath('data.envoyes', 1)->assertJsonPath('data.ignores', 1);
    Notification::assertSentTo($doit->user, RappelPaiement::class);
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('paiements.rappels'), ['doctorants' => [$doit->uuid]])->assertForbidden();
});

it('reminds a student who is not late yet with the next deadline', function (): void {
    $doctorant = Doctorant::factory()->create();
    FraisDoctorant::factory()->for($doctorant)->create(['montant' => 700_000, 'echeance' => '2027-09-30']);

    $this->actingAs(superAdmin())->postJson(route('paiements.rappels'), ['doctorants' => [$doctorant->uuid]])->assertJsonPath('data.envoyes', 1);
    $this->actingAs(superAdmin())->postJson(route('paiements.rappels'), ['doctorants' => [$doctorant->uuid]])->assertJsonPath('data.ignores', 1);

    expect($doctorant->user->notifications()->first()->data['message'])->toContain('prochaine échéance le 30 septembre 2027');
});

it('prints several receipts in one PDF and exports the chosen rows', function (): void {
    $admin = superAdmin();
    $ids = collect([doctorantAvecFrais(), doctorantAvecFrais()])->map(fn ($d) => $this->actingAs($admin)->postJson(route('paiements.store', $d), ['montant' => 30_000, 'date_paiement' => today()->toDateString(), 'mode' => 'especes'])->json('data.id'));

    $pdf = $this->actingAs($admin)->get(route('paiements.recus', ['ids' => $ids->all()]))->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf');

    $csv = $this->actingAs($admin)->get(route('paiements.export', ['ids' => [$ids->first()]]))->streamedContent();
    expect(substr_count(trim($csv), "\n"))->toBe(1);
    $situation = $this->actingAs($admin)->get(route('paiements.export-situation', ['situation' => 'en_retard']))->streamedContent();
    expect($situation)->toContain('En retard (Ar)')->and(substr_count(trim($situation), "\n"))->toBe(2);
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('paiements.recus', ['ids' => $ids->all()]))->assertForbidden();
});

it('tells partial payers apart from those who paid nothing', function (): void {
    $enRetard = doctorantAvecFrais();
    $partiel = Doctorant::factory()->create(['nom' => 'Partiel']);
    FraisDoctorant::factory()->for($partiel)->create(['montant' => 700_000, 'montant_paye' => 300_000]);
    $aPayer = Doctorant::factory()->create(['nom' => 'Apayer']);
    FraisDoctorant::factory()->for($aPayer)->create(['montant' => 700_000]);
    $aJour = Doctorant::factory()->create();
    FraisDoctorant::factory()->for($aJour)->echu()->create(['montant' => 100_000, 'montant_paye' => 100_000]);
    Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('doctorants.index', ['paiement' => 'partiel']))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $partiel->uuid)->assertJsonPath('data.0.paiement.statut', 'partiel');
    $this->actingAs($admin)->getJson(route('doctorants.index', ['paiement' => 'a_payer']))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aPayer->uuid)->assertJsonPath('data.0.paiement.statut', 'a_venir');

    $this->actingAs($admin)->getJson(route('doctorants.index', ['paiement' => 'en_retard']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $enRetard->uuid);
    $this->actingAs($admin)->getJson(route('doctorants.index', ['paiement' => 'a_jour']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aJour->uuid);
});

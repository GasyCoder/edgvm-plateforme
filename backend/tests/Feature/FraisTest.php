<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Models\Inscription;
use App\Models\TypeFrais;
use App\Models\User;
use App\Notifications\NouveauFrais;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->annee = anneeCourante());

it('applies a catalogue fee to all registered students of a level, without duplicates', function (): void {
    Notification::fake();
    $teeShirt = TypeFrais::factory()->create(['libelle' => 'Tee-shirt', 'montant' => 25_000]);
    $d1 = Doctorant::factory()->create();
    Inscription::factory()->for($d1)->for($this->annee, 'anneeUniversitaire')->create();
    $d2 = Doctorant::factory()->create();
    Inscription::factory()->for($d2)->for($this->annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::D2)->create();
    Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('frais.store'), ['type_frais_id' => $teeShirt->id, 'cible' => 'inscrits', 'niveau' => 'D1'])
        ->assertCreated()->assertJsonPath('data.crees', 1)->assertJsonPath('data.total', 25_000);
    $this->actingAs($admin)->postJson(route('frais.store'), ['type_frais_id' => $teeShirt->id, 'cible' => 'inscrits'])
        ->assertCreated()->assertJsonPath('data.crees', 1)->assertJsonPath('data.ignores.0.message', '« Tee-shirt » déjà appliqué pour 2026-2027.');

    expect(FraisDoctorant::query()->count())->toBe(2);
    Notification::assertSentTo($d1->user, NouveauFrais::class);
});

it('applies a one-off fee with its own label and amount to chosen students', function (): void {
    $choisis = Doctorant::factory()->count(2)->create();

    $this->actingAs(superAdmin())->postJson(route('frais.store'), [
        'libelle' => 'Participation au séminaire', 'montant' => 40_000, 'echeance' => '2026-12-15', 'doctorants' => $choisis->pluck('uuid')->all(),
    ])->assertCreated()->assertJsonPath('data.crees', 2);

    expect(FraisDoctorant::query()->where('libelle', 'Participation au séminaire')->where('montant', 40_000)->count())->toBe(2);
});

it('refuses a fee without amount', function (): void {
    $sansTarif = TypeFrais::factory()->create(['montant' => 0]);

    $this->actingAs(superAdmin())->postJson(route('frais.store'), ['type_frais_id' => $sansTarif->id, 'doctorants' => [Doctorant::factory()->create()->uuid]])
        ->assertCreated()->assertJsonPath('data.crees', 0)->assertJsonPath('data.ignores.0.message', 'Montant nul : précisez-le ou définissez le tarif dans les paramètres.');
});

it('adjusts a fee with a reason, never below what is paid', function (): void {
    $frais = FraisDoctorant::factory()->create(['montant' => 1_500_000, 'montant_paye' => 500_000]);
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(route('frais.update', $frais), ['montant' => 1_000_000])->assertUnprocessable()->assertJsonValidationErrors('motif_ajustement');
    $this->actingAs($admin)->putJson(route('frais.update', $frais), ['montant' => 400_000, 'motif_ajustement' => 'Bourse'])->assertUnprocessable()->assertJsonValidationErrors('montant');
    $this->actingAs($admin)->putJson(route('frais.update', $frais), ['montant' => 750_000, 'motif_ajustement' => 'Bourse partielle'])->assertOk()->assertJsonPath('data.reste', 250_000);
});

it('cancels an unpaid fee only', function (): void {
    $admin = superAdmin();
    $paye = FraisDoctorant::factory()->create(['montant_paye' => 100_000]);
    $libre = FraisDoctorant::factory()->create();

    $this->actingAs($admin)->postJson(route('frais.annuler', $paye), ['motif' => 'Exonération'])->assertConflict();
    $this->actingAs($admin)->postJson(route('frais.annuler', $libre), ['motif' => 'Exonération'])->assertOk()->assertJsonPath('data.situation', 'annule');
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('frais.annuler', $paye), ['motif' => 'x'])->assertForbidden();
});

it('manages the fee catalogue with a single amount or one per level', function (): void {
    $admin = superAdmin();

    $id = $this->actingAs($admin)->postJson(route('types-frais.store'), ['libelle' => 'Cotisation sociale', 'montant' => 20_000, 'delai_jours' => 15])
        ->assertCreated()->assertJsonPath('data.par_niveau', false)->json('data.id');
    $this->actingAs($admin)->putJson(route('types-frais.update', $id), [
        'libelle' => 'Cotisation sociale', 'par_niveau' => true, 'montants_niveaux' => ['D1' => 10_000, 'D2' => 15_000, 'D3' => 15_000, 'derogation' => 20_000], 'delai_jours' => 15, 'obligatoire' => true,
    ])->assertOk()->assertJsonPath('data.montants_niveaux.D2', 15_000)->assertJsonPath('data.obligatoire', true);
    $this->actingAs($admin)->putJson(route('types-frais.update', $id), ['libelle' => 'X', 'par_niveau' => true, 'montants_niveaux' => ['D1' => 1], 'delai_jours' => 15])
        ->assertUnprocessable()->assertJsonValidationErrors('montants_niveaux.D2');

    FraisDoctorant::factory()->create(['type_frais_id' => $id]);
    $this->actingAs($admin)->deleteJson(route('types-frais.destroy', $id))->assertConflict();
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('types-frais.index'))->assertForbidden();
});

it('applies a new tariff to fees not yet settled, by level, when asked', function (): void {
    $type = tarifsInscription(['D1' => 1_500_000, 'D2' => 1_500_000, 'D3' => 1_500_000, 'derogation' => 1_500_000]);
    $creer = function (NiveauDoctorat $niveau, array $valeurs = [], bool $derogation = false) use ($type): FraisDoctorant {
        $inscription = Inscription::factory()->for(AnneeUniversitaire::factory(), 'anneeUniversitaire')->niveau($niveau)->create($derogation ? ['est_derogation' => true, 'niveau' => 'D3'] : []);

        return FraisDoctorant::factory()->for($inscription->doctorant)->create(['type_frais_id' => $type->id, 'inscription_id' => $inscription->id, 'montant' => 1_500_000, ...$valeurs]);
    };
    $d1 = $creer(NiveauDoctorat::D1);
    $d2Partiel = $creer(NiveauDoctorat::D2, ['montant_paye' => 200_000]);
    $dejaPaye = $creer(NiveauDoctorat::D2, ['montant_paye' => 1_000_000]);
    $ajuste = $creer(NiveauDoctorat::D1, ['montant' => 500_000, 'motif_ajustement' => 'Bourse']);
    $solde = $creer(NiveauDoctorat::D1, ['montant_paye' => 1_500_000]);
    $derogation = $creer(NiveauDoctorat::D3, [], true);
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('types-frais.index'))->assertJsonPath('data.0.frais_non_soldes_count', 4);

    $this->actingAs($admin)->putJson(route('types-frais.update', $type), [
        'libelle' => "Droits d'inscription", 'par_niveau' => true, 'montants_niveaux' => ['D1' => 700_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 0],
        'delai_jours' => 30, 'obligatoire' => true, 'appliquer_aux_frais' => true,
    ])->assertOk()
        ->assertJsonPath('application.modifies', 2)
        ->assertJsonPath('application.annules', 1)
        ->assertJsonPath('application.ignores', 1);

    expect($d1->fresh()->montant)->toBe(700_000)
        ->and($d2Partiel->fresh()->reste())->toBe(500_000)
        ->and($dejaPaye->fresh()->montant)->toBe(1_500_000)
        ->and($ajuste->fresh()->montant)->toBe(500_000)
        ->and($solde->fresh()->montant)->toBe(1_500_000)
        ->and($derogation->fresh()->annule_le)->not->toBeNull();
});

it('leaves existing fees unchanged when the new tariff is not applied to them', function (): void {
    $type = tarifsInscription();
    $frais = FraisDoctorant::factory()->create(['type_frais_id' => $type->id, 'montant' => 1_500_000]);

    $this->actingAs(superAdmin())->putJson(route('types-frais.update', $type), [
        'libelle' => "Droits d'inscription", 'montant' => 700_000, 'delai_jours' => 30, 'obligatoire' => true,
    ])->assertOk()->assertJsonPath('application', null);

    expect($frais->fresh()->montant)->toBe(1_500_000);
});

it('splits registration fees into installments spread over the academic year', function (): void {
    $type = tarifsInscription(['D1' => 1_000_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 700_000]);
    $type->update(['tranches' => 3]);
    $doctorant = Doctorant::factory()->create();

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $this->annee->id, 'niveau' => 'D1', 'statut' => 'validee', 'date_inscription' => '2026-10-01',
    ])->assertCreated()->assertJsonPath('data.frais', 1_000_000);

    $tranches = $doctorant->frais()->orderBy('tranche')->get();
    expect($tranches->pluck('montant')->all())->toBe([333_000, 333_000, 334_000])
        ->and($tranches->pluck('libelle')->all())->toBe(["Droits d'inscription, tranche 1/3", "Droits d'inscription, tranche 2/3", "Droits d'inscription, tranche 3/3"])
        ->and($tranches->map(fn ($f) => $f->echeance->toDateString())->all())->toBe(['2027-01-30', '2027-06-01', '2027-09-30']);
});

it('counts as late only the installments whose date has passed', function (): void {
    tarifsInscription(['D1' => 700_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 700_000])->update(['tranches' => 2]);
    $doctorant = Doctorant::factory()->create();
    $admin = superAdmin();
    $this->actingAs($admin)->postJson(route('inscriptions.store', $doctorant), ['annee_universitaire_id' => $this->annee->id, 'niveau' => 'D1', 'statut' => 'validee', 'date_inscription' => '2026-10-01']);

    $this->actingAs($admin)->getJson(route('doctorants.finances', $doctorant))->assertJsonPath('data.situation.statut', 'a_venir');

    $this->travelTo('2027-05-01');
    $this->actingAs($admin)->getJson(route('doctorants.finances', $doctorant))
        ->assertJsonPath('data.situation.statut', 'en_retard')
        ->assertJsonPath('data.situation.en_retard', 350_000);

    $this->actingAs($admin)->postJson(route('paiements.store', $doctorant), ['montant' => 400_000, 'date_paiement' => '2027-05-01', 'mode' => 'virement', 'reference' => 'BOA-9']);
    $this->actingAs($admin)->getJson(route('doctorants.finances', $doctorant))
        ->assertJsonPath('data.situation.statut', 'partiel')
        ->assertJsonPath('data.situation.reste', 300_000);
});

it('moves the deadline of existing fees to the end of the year when the new rule is applied', function (): void {
    $type = tarifsInscription(['D1' => 700_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 700_000]);
    $inscription = Inscription::factory()->for($this->annee, 'anneeUniversitaire')->create(['date_inscription' => '2026-10-15']);
    $frais = FraisDoctorant::factory()->for($inscription->doctorant)->create([
        'type_frais_id' => $type->id, 'inscription_id' => $inscription->id, 'annee_universitaire_id' => $this->annee->id, 'montant' => 1_500_000, 'echeance' => '2026-11-14',
    ]);

    $this->actingAs(superAdmin())->putJson(route('types-frais.update', $type), [
        'libelle' => "Droits d'inscription", 'par_niveau' => true, 'montants_niveaux' => ['D1' => 700_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 700_000],
        'echeance' => 'fin_annee', 'tranches' => 1, 'delai_jours' => 30, 'obligatoire' => true, 'appliquer_aux_frais' => true,
    ])->assertOk()->assertJsonPath('application.modifies', 1);

    expect($frais->fresh())->montant->toBe(700_000)->and($frais->fresh()->echeance->toDateString())->toBe('2027-09-30');
});

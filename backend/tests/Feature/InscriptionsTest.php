<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\Inscription;
use App\Models\User;

/**
 * Doctorant déjà inscrit en D1, D2 et D3 sur trois années passées.
 */
function doctorantEnFinDeDuree(): Doctorant
{
    $doctorant = Doctorant::factory()->create();

    foreach ([2023, 2024, 2025] as $rang => $rentree) {
        Inscription::factory()->for($doctorant)
            ->for(AnneeUniversitaire::factory()->rentree($rentree), 'anneeUniversitaire')
            ->niveau(NiveauDoctorat::from('D'.($rang + 1)))
            ->create();
    }

    return $doctorant;
}

it('re-registers a student at the next level', function (): void {
    $doctorant = Doctorant::factory()->create();
    Inscription::factory()->for($doctorant)->for(AnneeUniversitaire::factory()->rentree(2025), 'anneeUniversitaire')->create();
    $annee = anneeCourante();

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $annee->id,
        'niveau' => 'D2',
        'montant_du' => 1_200_000,
        'statut' => 'validee',
    ])->assertCreated()->assertJsonPath('data.type', 'reinscription')->assertJsonPath('data.niveau_aff', 'D2');
});

it('refuses a second registration for the same year', function (): void {
    $annee = anneeCourante();
    $doctorant = Doctorant::factory()->create();
    Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire')->create();

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $annee->id, 'niveau' => 'D2', 'montant_du' => 0, 'statut' => 'validee',
    ])->assertUnprocessable()->assertJsonValidationErrors(['annee_universitaire_id' => 'Ce doctorant est déjà inscrit pour cette année universitaire.']);
});

it('requires an exemption after three years', function (): void {
    $doctorant = doctorantEnFinDeDuree();
    $annee = anneeCourante();

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $annee->id, 'niveau' => 'D3', 'montant_du' => 0, 'statut' => 'validee',
    ])->assertUnprocessable()->assertJsonValidationErrors('est_derogation');
});

it('records an exemption with its reason, pending until the decision is entered', function (): void {
    $doctorant = doctorantEnFinDeDuree();
    $annee = anneeCourante();
    $admin = superAdmin();

    $reponse = $this->actingAs($admin)->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => $annee->id,
        'niveau' => 'D3',
        'est_derogation' => true,
        'motif_derogation' => 'Travaux de terrain retardés par la saison des pluies',
        'montant_du' => 1_500_000,
        'statut' => 'en_attente',
    ])->assertCreated()->assertJsonPath('data.derogation_en_attente', true);

    $this->actingAs($admin)->putJson(route('inscriptions.update', $reponse->json('data.id')), [
        'annee_universitaire_id' => $annee->id,
        'niveau' => 'D3',
        'est_derogation' => true,
        'motif_derogation' => 'Travaux de terrain retardés par la saison des pluies',
        'decision_derogation' => 'Décision n° 08/2026/EDGVM',
        'date_decision_derogation' => '2026-11-10',
        'montant_du' => 1_500_000,
        'statut' => 'validee',
    ])->assertOk()->assertJsonPath('data.derogation_en_attente', false);

    $this->actingAs($admin)->getJson(route('doctorants.show', $doctorant))
        ->assertJsonPath('data.inscription_courante.niveau_aff', 'Dérogation 1')
        ->assertJsonPath('data.prochaine_inscription.est_derogation', true);
});

it('validates exemptions', function (array $valeurs, string $champ): void {
    $doctorant = doctorantEnFinDeDuree();

    $this->actingAs(superAdmin())->postJson(route('inscriptions.store', $doctorant), [
        'annee_universitaire_id' => anneeCourante()->id, 'montant_du' => 0, 'statut' => 'validee', 'est_derogation' => true, ...$valeurs,
    ])->assertUnprocessable()->assertJsonValidationErrors($champ);
})->with([
    'sans motif' => [['niveau' => 'D3'], 'motif_derogation'],
    'pas en D3' => [['niveau' => 'D2', 'motif_derogation' => 'Retard'], 'niveau'],
    'décision sans date' => [['niveau' => 'D3', 'motif_derogation' => 'Retard', 'decision_derogation' => 'n° 3'], 'date_decision_derogation'],
]);

it('lets only the school register students', function (): void {
    $doctorant = Doctorant::factory()->create();
    $donnees = ['annee_universitaire_id' => anneeCourante()->id, 'niveau' => 'D1', 'montant_du' => 0, 'statut' => 'validee'];

    $this->actingAs($doctorant->user)->postJson(route('inscriptions.store', $doctorant), $donnees)->assertForbidden();
    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('inscriptions.store', $doctorant), $donnees)->assertForbidden();
});

it('deletes a registration entered by mistake', function (): void {
    $inscription = Inscription::factory()->create();

    $this->actingAs($inscription->doctorant->user)->deleteJson(route('inscriptions.destroy', $inscription))->assertForbidden();
    $this->actingAs(superAdmin())->deleteJson(route('inscriptions.destroy', $inscription))->assertNoContent();

    expect(Inscription::query()->count())->toBe(0);
});

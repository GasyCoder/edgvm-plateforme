<?php

use App\Enums\NiveauDoctorat;
use App\Models\Acte;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\ModeleActe;
use App\Models\User;
use App\Support\GenerateurPdfActes;
use Database\Seeders\ModelesActesSeeder;

beforeEach(function (): void {
    $this->seed(ModelesActesSeeder::class);
    $this->annee = anneeCourante();
    $this->attestation = ModeleActe::query()->where('code', 'attestation-inscription')->sole();
    $this->admission = ModeleActe::query()->where('code', 'admission-doctorat')->sole();
});

function doctorantInscrit(AnneeUniversitaire $annee, array $valeurs = []): Doctorant
{
    $doctorant = Doctorant::factory()->create(['nom' => 'Rabe', 'prenoms' => 'Fanja', 'genre' => 'F', 'date_naissance' => '1991-09-23', 'directeur_these' => 'Pr RAKOTO Jean', ...$valeurs]);
    Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::D2)->create();

    return $doctorant;
}

it('delivers a numbered registration certificate with the student details', function (): void {
    $doctorant = doctorantInscrit($this->annee);

    $this->actingAs(superAdmin())->postJson(route('actes.store'), [
        'modele_acte_id' => $this->attestation->id,
        'doctorant' => $doctorant->uuid,
        'annee_universitaire_id' => $this->annee->id,
    ])->assertCreated()
        ->assertJsonPath('data.numero', 'EDGVM/'.now()->year.'/000001')
        ->assertJsonPath('data.titre', "ATTESTATION D'INSCRIPTION")
        ->assertJsonPath('data.doctorant.nom_complet', 'RABE Fanja');

    $acte = Acte::query()->sole();
    expect($acte->contenu)
        ->toContain('<strong>Mme RABE Fanja</strong>, née le 23/09/1991 est régulièrement inscrite en thèse, niveau D2')
        ->toContain('2026-2027')
        ->toContain('Pr RAKOTO Jean')
        ->and($acte->signataire['nom'])->toBe('ASSOUMACOU Elia Béatrice');
});

it('numbers acts one after the other within the year', function (): void {
    $admin = superAdmin();
    foreach ([doctorantInscrit($this->annee), doctorantInscrit($this->annee)] as $doctorant) {
        $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id])->assertCreated();
    }

    expect(Acte::query()->orderBy('id')->pluck('numero')->all())->toBe(['EDGVM/'.now()->year.'/000001', 'EDGVM/'.now()->year.'/000002']);
});

it('refuses a certificate without a validated registration or with an undecided exemption', function (Closure $preparer, string $message): void {
    $doctorant = Doctorant::factory()->create();
    $preparer($doctorant, $this->annee);

    $this->actingAs(superAdmin())->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', $message);
    expect(Acte::query()->count())->toBe(0);
})->with([
    'pas inscrit' => [fn () => null, "Pas d'inscription pour 2026-2027."],
    'en attente' => [fn ($d, $a) => Inscription::factory()->for($d)->for($a, 'anneeUniversitaire')->create(['statut' => 'en_attente']), "L'inscription 2026-2027 n'est pas encore validée."],
    'dérogation sans décision' => [fn ($d, $a) => Inscription::factory()->for($d)->for($a, 'anneeUniversitaire')->derogation(null)->create(), 'Dérogation 2026-2027 sans décision enregistrée.'],
]);

it('requires the extra fields of the template', function (): void {
    $doctorant = Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->admission->id, 'doctorant' => $doctorant->uuid])
        ->assertUnprocessable()->assertJsonValidationErrors('champs.date_presentation');

    $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->admission->id, 'doctorant' => $doctorant->uuid, 'champs' => ['date_presentation' => '2026-02-09']])
        ->assertCreated();
    expect(Acte::query()->sole()->contenu)->toContain('en date du 09 février 2026');
});

it('delivers acts in bulk and lists the students who cannot receive one', function (): void {
    $prets = [doctorantInscrit($this->annee, ['nom' => 'Andry']), doctorantInscrit($this->annee, ['nom' => 'Bema'])];
    $sansInscription = Doctorant::factory()->create(['nom' => 'Zafy']);

    $reponse = $this->actingAs(superAdmin())->postJson(route('actes.lot'), [
        'modele_acte_id' => $this->attestation->id,
        'annee_universitaire_id' => $this->annee->id,
        'doctorants' => [...collect($prets)->pluck('uuid'), $sansInscription->uuid],
    ])->assertOk()
        ->assertJsonCount(2, 'data.delivres')
        ->assertJsonPath('data.erreurs.0.message', "Pas d'inscription pour 2026-2027.")
        ->assertJsonPath('data.erreurs.0.doctorant', 'ZAFY '.$sansInscription->prenoms);

    $pdf = $this->get($reponse->json('data.url_pdf'))->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

it('lets the student download their act but not someone else\'s', function (): void {
    $doctorant = doctorantInscrit($this->annee);
    $this->actingAs(superAdmin())->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);
    $acte = Acte::query()->sole();

    $this->actingAs($doctorant->user)->get(route('actes.pdf', $acte))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($doctorant->user)->getJson(route('actes.index'))->assertJsonCount(1, 'data');
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('actes.pdf', $acte))->assertForbidden();
    $this->actingAs(Doctorant::factory()->create()->user)->getJson(route('actes.index'))->assertJsonCount(0, 'data');
    $this->actingAs($doctorant->user)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id])->assertForbidden();
});

it('shows team members the acts of their students', function (): void {
    $equipe = EquipeAccueil::factory()->create();
    $doctorant = doctorantInscrit($this->annee, ['equipe_accueil_id' => $equipe->id]);
    $this->actingAs(superAdmin())->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);

    $this->actingAs(User::factory()->membreEad($equipe)->create())->getJson(route('actes.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.peut_annuler', false);
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('actes.index'))->assertJsonCount(0, 'data');
});

it('cancels an act with a reason and shows it on the public verification page', function (): void {
    $doctorant = doctorantInscrit($this->annee);
    $admin = superAdmin();
    $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);
    $acte = Acte::query()->sole();

    app('auth')->forgetGuards();
    $this->get(route('actes.verifier', $acte->code_verification))->assertOk()->assertSee('Acte authentique et valide')->assertSee($acte->numero)->assertDontSee('23/09/1991');

    $this->actingAs($admin)->postJson(route('actes.annuler', $acte), ['motif' => ''])->assertUnprocessable()->assertJsonValidationErrors('motif');
    $this->actingAs($admin)->postJson(route('actes.annuler', $acte), ['motif' => 'Erreur sur le niveau'])->assertOk()->assertJsonPath('data.peut_annuler', false);

    app('auth')->forgetGuards();
    $this->get(route('actes.verifier', $acte->code_verification))->assertSee('Acte annulé le');
    $this->get(route('actes.verifier', 'code-inconnu'))->assertNotFound()->assertSee('Aucun acte ne correspond à ce code');
    $this->actingAs($doctorant->user)->get(route('actes.pdf', $acte))->assertForbidden();
});

it('lets the school edit templates, with known variables only and clean HTML', function (): void {
    $admin = superAdmin();

    $id = $this->actingAs($admin)->postJson(route('modeles-actes.store'), [
        'nom' => 'Attestation de présence',
        'titre' => 'ATTESTATION DE PRÉSENCE',
        'corps' => '<p style="text-align: justify; color: red" onclick="x()">{{civilite}} {{nom_complet}} a participé au {{seminaire}}.</p><script>alert(1)</script><img src="x">',
        'champs' => [['libelle' => 'Séminaire', 'type' => 'texte', 'obligatoire' => true]],
    ])->assertCreated()->assertJsonPath('data.champs.0.cle', 'seminaire')->json('data.id');

    expect(ModeleActe::query()->find($id)->corps)->toBe('<p style="text-align: justify">{{civilite}} {{nom_complet}} a participé au {{seminaire}}.</p>');

    $this->actingAs($admin)->putJson(route('modeles-actes.update', $id), ['nom' => 'X', 'titre' => 'X', 'corps' => '<p>{{inconnue}}</p>'])
        ->assertUnprocessable()->assertJsonValidationErrors('corps');
    $this->actingAs($admin)->putJson(route('modeles-actes.update', $id), ['nom' => 'X', 'titre' => 'X', 'corps' => '<p>x</p>', 'champs' => [['libelle' => 'Nom', 'type' => 'texte']]])
        ->assertUnprocessable()->assertJsonValidationErrors('champs.0.cle');
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('modeles-actes.index'))->assertForbidden();
});

it('previews a template as a PDF without saving anything', function (): void {
    $reponse = $this->actingAs(superAdmin())->postJson(route('modeles-actes.apercu'), [
        'nom' => 'Essai', 'titre' => 'ESSAI', 'corps' => '<p>{{nom_complet}}</p>',
    ])->assertOk();

    expect($reponse->headers->get('content-type'))->toBe('application/pdf')
        ->and($reponse->getContent())->toStartWith('%PDF')
        ->and(Acte::query()->count())->toBe(0);
});

it('deactivates rather than deletes a template already used', function (): void {
    $doctorant = doctorantInscrit($this->annee);
    $admin = superAdmin();
    $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);

    $this->actingAs($admin)->deleteJson(route('modeles-actes.destroy', $this->attestation))->assertConflict();
    $this->actingAs($admin)->deleteJson(route('modeles-actes.destroy', $this->admission))->assertNoContent();
});

it('keeps the text of delivered acts when the template changes', function (): void {
    $doctorant = doctorantInscrit($this->annee);
    $admin = superAdmin();
    $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);

    $this->attestation->update(['corps' => '<p>Nouveau texte</p>']);

    expect(Acte::query()->sole()->contenu)->toContain('régulièrement inscrite');
});

it('leaves the signatory name to the stamp unless asked to print it', function (): void {
    $doctorant = doctorantInscrit($this->annee);
    $admin = superAdmin();
    $delivrer = fn () => $this->actingAs($admin)->postJson(route('actes.store'), ['modele_acte_id' => $this->attestation->id, 'doctorant' => $doctorant->uuid, 'annee_universitaire_id' => $this->annee->id]);

    $delivrer();
    $html = GenerateurPdfActes::html(Acte::query()->get());
    expect($html)->toContain('Le Directeur,')->toContain('Fait à Mahajanga')->not->toContain('ASSOUMACOU')->not->toContain('Professeure Titulaire');

    $this->actingAs($admin)->post(route('reglages-actes.update'), [
        'afficher_nom_signataire' => true, 'signataire_nom' => 'ASSOUMACOU Elia Béatrice', 'signataire_fonction' => 'Le Directeur,', 'signataire_titre' => 'Professeure Titulaire', 'ville' => 'Mahajanga',
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.afficher_nom_signataire', true);
    Acte::query()->delete();
    $delivrer();

    expect(GenerateurPdfActes::html(Acte::query()->get()))->toContain('ASSOUMACOU Elia Béatrice')->toContain('Professeure Titulaire');
});

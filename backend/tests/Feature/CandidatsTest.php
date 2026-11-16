<?php

use App\Models\Acte;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\ModeleActe;
use App\Models\Passage;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\AdmissionPrononcee;
use App\Notifications\DecisionCandidature;
use App\Notifications\InvitationPlateforme;
use Database\Seeders\ModelesActesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->seed(ModelesActesSeeder::class);
    $this->annee = anneeCourante();
    tarifsInscription(['D1' => 700_000, 'D2' => 700_000, 'D3' => 700_000, 'derogation' => 700_000]);
    $this->equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1']);
    $this->admin = superAdmin();
});

function nouveauCandidat(EquipeAccueil $equipe, string $nom = 'Rabe', string $email = 'rabe@exemple.mg'): Doctorant
{
    $id = test()->actingAs(test()->admin)->postJson(route('doctorants.store'), [
        'nom' => $nom, 'prenoms' => 'Fanja', 'email' => $email, 'equipe_accueil_id' => $equipe->id, 'sujet_these' => 'Plantes du Boeny',
        'statut' => 'candidat', 'inscription' => ['annee_universitaire_id' => test()->annee->id, 'niveau' => 'D1'],
    ])->assertCreated()->json('data.id');

    return Doctorant::query()->where('uuid', $id)->sole();
}

function presentation(array $valeurs = []): Seance
{
    return Seance::factory()->create(['type' => 'presentation_projet', 'titre' => 'Présentation de projet de thèse', 'date' => '2026-12-10', 'heure_debut' => '08:00', ...$valeurs]);
}

it('registers a candidate without registration, fees, access or real student id, apart from the students', function (): void {
    $candidat = nouveauCandidat($this->equipe);

    expect($candidat->matricule)->toBe('CAND-'.now()->year.'-0001')
        ->and($candidat->user->actif)->toBeFalse()
        ->and($candidat->inscriptions()->count())->toBe(0)
        ->and($candidat->frais()->count())->toBe(0)
        ->and($candidat->candidature_le->toDateString())->toBe(today()->toDateString());
    Notification::assertNotSentTo($candidat->user, InvitationPlateforme::class);

    $this->actingAs($this->admin)->getJson(route('doctorants.index'))->assertJsonCount(0, 'data');
    $this->actingAs($this->admin)->getJson(route('doctorants.index', ['statut' => 'candidat']))->assertJsonCount(1, 'data');
    $this->actingAs($this->admin)->getJson(route('candidats.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.est_candidat', true)->assertJsonPath('data.0.candidature.decision', null);
    $this->actingAs($this->admin)->getJson(route('candidats.compteurs'))->assertJsonPath('tous', 1)->assertJsonPath('en_attente', 1)->assertJsonPath('admis', 0);
});

it('only lets candidates present a thesis project, and keeps them out of the other sessions', function (): void {
    $candidat = nouveauCandidat($this->equipe);
    $doctorant = Doctorant::factory()->dansEquipe($this->equipe)->create(['nom' => 'Ancien']);
    $projet = presentation();
    $seminaire = Seance::factory()->create(['date' => '2026-12-11']);

    $this->actingAs($this->admin)->getJson(route('seances.candidats', $projet))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $candidat->uuid)->assertJsonPath('data.0.raison', null);
    $this->actingAs($this->admin)->postJson(route('seances.passages', $projet), ['doctorants' => [$candidat->uuid, $doctorant->uuid]])
        ->assertJsonPath('ajoutes', 1)->assertJsonPath('refuses.0.raison', 'Déjà doctorant : seuls les candidats présentent leur projet.');
    $this->actingAs($this->admin)->postJson(route('seances.passages', $seminaire), ['doctorants' => [$candidat->uuid]])
        ->assertJsonPath('ajoutes', 0)->assertJsonPath('refuses.0.raison', fn (string $r) => str_contains($r, 'présentation de projet'));
});

it('admits the candidates declared admitted: student id, D1 with fees, admission letter, access and notification', function (): void {
    $admis = nouveauCandidat($this->equipe, 'Rabe', 'rabe@exemple.mg');
    $ajourne = nouveauCandidat($this->equipe, 'Zafy', 'zafy@exemple.mg');
    $seance = presentation();
    $this->actingAs($this->admin)->postJson(route('seances.passages', $seance), ['doctorants' => [$admis->uuid, $ajourne->uuid]])->assertOk();
    [$pAdmis, $pAjourne] = [Passage::query()->where('doctorant_id', $admis->id)->sole(), Passage::query()->where('doctorant_id', $ajourne->id)->sole()];

    $this->actingAs($this->admin)->putJson(route('passages.decision', $pAdmis), ['decision' => 'admis'])->assertOk()->assertJsonPath('data.passages.0.decision_libelle', 'Admis');
    $this->actingAs($this->admin)->putJson(route('passages.decision', $pAjourne), ['decision' => 'ajourne', 'observation' => 'Préciser la méthodologie.'])->assertOk();
    $this->actingAs($this->admin)->putJson(route('passages.decision', $pAdmis), ['decision' => 'reçu'])->assertJsonValidationErrors('decision');
    $this->actingAs($this->admin)->getJson(route('candidats.compteurs'))->assertJsonPath('admis_jury', 1)->assertJsonPath('ajourne', 1);

    $this->actingAs($this->admin)->postJson(route('seances.admettre', $seance))->assertOk()->assertJsonCount(1, 'admis')->assertJsonPath('admis.0.matricule', 'EDGVM-'.now()->year.'-0001');

    $admis->refresh();
    $inscription = $admis->inscriptions()->sole();
    expect($admis->statut->value)->toBe('actif')
        ->and($admis->admis_le)->not->toBeNull()
        ->and($admis->user->actif)->toBeTrue()
        ->and($inscription->niveau->value)->toBe('D1')
        ->and($inscription->annee_universitaire_id)->toBe($this->annee->id)
        ->and((int) $admis->frais()->sum('montant'))->toBe(700_000);
    $acte = Acte::query()->where('doctorant_id', $admis->id)->sole();
    expect($acte->modele->code)->toBe('admission-doctorat')->and($acte->donnees['date_presentation'])->toBe('2026-12-10');

    Notification::assertSentTo($admis->user, InvitationPlateforme::class);
    Notification::assertSentTo($admis->user, AdmissionPrononcee::class, function (AdmissionPrononcee $n) use ($admis): bool {
        $courriel = $n->toMail($admis->user);

        return count($courriel->rawAttachments) === 1 && str_contains($n->toArray($admis->user)['message'], '700');
    });
    expect($ajourne->fresh()->statut->value)->toBe('candidat');

    $this->actingAs($this->admin)->putJson(route('passages.decision', $pAdmis), ['decision' => 'non_admis'])->assertUnprocessable();
    $this->actingAs($this->admin)->postJson(route('seances.admettre', $seance))->assertUnprocessable();
    $this->actingAs($this->admin)->getJson(route('candidats.compteurs'))->assertJsonPath('admis', 1)->assertJsonPath('tous', 1);
});

it('informs the postponed and refused candidates by e-mail only, once', function (): void {
    $ajourne = nouveauCandidat($this->equipe, 'Zafy', 'zafy@exemple.mg');
    $refuse = nouveauCandidat($this->equipe, 'Bema', 'bema@exemple.mg');
    $seance = presentation();
    $this->actingAs($this->admin)->postJson(route('seances.passages', $seance), ['doctorants' => [$ajourne->uuid, $refuse->uuid]]);
    $this->actingAs($this->admin)->putJson(route('passages.decision', Passage::query()->where('doctorant_id', $ajourne->id)->sole()), ['decision' => 'ajourne']);
    $this->actingAs($this->admin)->putJson(route('passages.decision', Passage::query()->where('doctorant_id', $refuse->id)->sole()), ['decision' => 'non_admis']);

    $this->actingAs($this->admin)->postJson(route('seances.informer', $seance))->assertOk();
    Notification::assertSentTo($ajourne->user, DecisionCandidature::class, fn (DecisionCandidature $n, array $canaux) => $canaux === ['mail']);
    Notification::assertSentTo($refuse->user, DecisionCandidature::class);
    $this->actingAs($this->admin)->postJson(route('seances.informer', $seance))->assertUnprocessable();

    $suivante = presentation(['date' => '2027-01-15']);
    $this->actingAs($this->admin)->postJson(route('seances.passages', $suivante), ['doctorants' => [$ajourne->uuid, $refuse->uuid]])
        ->assertJsonPath('ajoutes', 1)->assertJsonPath('refuses.0.raison', 'Candidature non retenue.');
});

it('admits from the candidate file only after the jury said so', function (): void {
    $candidat = nouveauCandidat($this->equipe);

    $this->actingAs($this->admin)->postJson(route('candidats.admettre', $candidat))->assertUnprocessable()->assertJsonPath('message', "Le jury n'a pas déclaré RABE Fanja admis.");

    $candidat->update(['decision_candidature' => 'admis']);
    $this->actingAs($this->admin)->postJson(route('candidats.admettre', $candidat))->assertOk()->assertJsonPath('message', fn (string $m) => str_contains($m, 'inscrit en D1'));
    $this->actingAs($this->admin)->postJson(route('candidats.admettre', $candidat))->assertUnprocessable();
});

it('imports a file of candidates: no level, no student id, no access, even if the file has those columns', function (): void {
    $csv = "Nom;Prénoms;E-mail;Équipe;Sujet de thèse;Niveau;Matricule\n"
        ."Rasoa;Mialy;mialy@exemple.mg;EAD 1;Plantes marines;D2;EDGVM-9999\n"
        ."Bema;Paul;paul@exemple.mg;EAD 9;Géologie;;\n";
    $fichier = fn () => UploadedFile::fake()->createWithContent('candidats.csv', $csv);

    $this->actingAs($this->admin)->post(route('doctorants.import'), ['fichier' => $fichier(), 'simulation' => true, 'candidats' => true], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.valides', 1)->assertJsonPath('data.apercu.0.niveau', null)->assertJsonPath('data.erreurs.0.ligne', 3);
    expect(Doctorant::query()->count())->toBe(0);

    $this->actingAs($this->admin)->post(route('doctorants.import'), ['fichier' => $fichier(), 'candidats' => true, 'envoyer_invitation' => true], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.crees', 1);

    $candidat = Doctorant::query()->sole();
    expect($candidat->statut->value)->toBe('candidat')
        ->and($candidat->matricule)->toStartWith('CAND-')
        ->and($candidat->inscriptions()->count())->toBe(0)
        ->and($candidat->user->actif)->toBeFalse();
    Notification::assertNotSentTo($candidat->user, InvitationPlateforme::class);

    $modele = $this->actingAs($this->admin)->get(route('candidats.modele-import'))->assertOk()->streamedContent();
    expect($modele)->toContain('Nom;Prénoms')->not->toContain('Niveau')->not->toContain('Matricule');
});

it('exports the candidates with the list filters, in Excel or CSV, for the school only', function (): void {
    $attente = nouveauCandidat($this->equipe, 'Rabe', 'rabe@exemple.mg');
    $ajourne = nouveauCandidat($this->equipe, 'Zafy', 'zafy@exemple.mg');
    $ajourne->update(['decision_candidature' => 'ajourne']);

    $csv = $this->actingAs($this->admin)->get(route('candidats.export', ['format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('Numéro;Nom;Prénoms')->toContain('RABE')->toContain('ZAFY')->toContain('Ajourné')->toContain('En attente');

    $filtre = $this->actingAs($this->admin)->get(route('candidats.export', ['format' => 'csv', 'etat' => 'ajourne']))->streamedContent();
    expect($filtre)->toContain('ZAFY')->not->toContain('RABE');

    $this->actingAs($this->admin)->get(route('candidats.export', ['format' => 'xlsx']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->actingAs(User::factory()->membreEad($this->equipe)->create())->get(route('candidats.export', ['format' => 'csv']))->assertForbidden();
});

it('refuses any act to a candidate before admission, then delivers the admission letter on admission', function (): void {
    $candidat = nouveauCandidat($this->equipe);
    $modele = ModeleActe::query()->where('code', 'admission-doctorat')->sole();

    $this->actingAs($this->admin)->postJson(route('actes.store'), ['modele_acte_id' => $modele->id, 'doctorant' => $candidat->uuid, 'champs' => ['date_presentation' => '2026-12-10']])
        ->assertUnprocessable()->assertJsonPath('message', fn (string $m) => str_contains($m, 'aucun acte avant la décision du jury'));
    $this->actingAs($this->admin)->postJson(route('actes.lot'), ['modele_acte_id' => $modele->id, 'doctorants' => [$candidat->uuid], 'champs' => ['date_presentation' => '2026-12-10']])
        ->assertOk()->assertJsonCount(0, 'data.delivres')->assertJsonCount(1, 'data.erreurs');

    $candidat->update(['decision_candidature' => 'admis']);
    $this->actingAs($this->admin)->postJson(route('candidats.admettre', $candidat))->assertOk();
    expect(Acte::query()->where('doctorant_id', $candidat->id)->count())->toBe(1);
});

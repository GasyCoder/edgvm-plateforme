<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\FraisDoctorant;
use App\Models\Inscription;
use App\Models\MembreEquipe;
use App\Models\Passage;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\ConvocationIntervenant;
use App\Notifications\ConvocationPassage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->annee = anneeCourante();
    $this->equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1']);
    $this->admin = superAdmin();
});

function doctorantSeance(EquipeAccueil $equipe, string $nom, string $niveau = 'D2'): Doctorant
{
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create(['nom' => $nom]);
    Inscription::factory()->for($doctorant)->for(AnneeUniversitaire::courante(), 'anneeUniversitaire')->niveau(NiveauDoctorat::from($niveau))->create();

    return $doctorant;
}

function seanceLe(string $date = '2026-12-10', array $valeurs = []): Seance
{
    return Seance::factory()->create(['date' => $date, 'heure_debut' => '08:00', 'duree_passage' => 30, ...$valeurs]);
}

it('creates a session and lists it for the school', function (): void {
    $id = $this->actingAs($this->admin)->postJson(route('seances.store'), [
        'type' => 'seminaire', 'titre' => 'Séminaire doctoral de décembre', 'lieu' => 'Amphi A', 'date' => now()->addDays(10)->toDateString(),
        'heure_debut' => '08:00', 'duree_passage' => 20, 'pause' => 5, 'exige_paiement_solde' => true,
    ])->assertCreated()->assertJsonPath('data.type_libelle', 'Séminaire doctoral')->assertJsonPath('data.statut', 'brouillon')->json('data.id');

    $this->actingAs($this->admin)->getJson(route('seances.index'))->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.passages_count', 0);
    $this->actingAs($this->admin)->postJson(route('seances.store'), ['type' => 'fete', 'titre' => '', 'date' => 'demain', 'heure_debut' => '8h', 'duree_passage' => 2])
        ->assertJsonValidationErrors(['type', 'titre', 'date', 'heure_debut', 'duree_passage']);
    $this->actingAs(User::factory()->membreEad($this->equipe)->create())->postJson(route('seances.store'), [])->assertForbidden();
});

it('lists candidates by level and team and refuses those who have not paid everything when required', function (): void {
    $seance = seanceLe(valeurs: ['exige_paiement_solde' => true]);
    $solde = doctorantSeance($this->equipe, 'Solde', 'D1');
    $doit = doctorantSeance($this->equipe, 'Doit', 'D1');
    FraisDoctorant::factory()->for($doit)->create(['montant' => 700_000, 'montant_paye' => 200_000]);
    doctorantSeance(EquipeAccueil::factory()->create(), 'Ailleurs', 'D1');
    doctorantSeance($this->equipe, 'Troisieme', 'D3');

    $candidats = $this->actingAs($this->admin)->getJson(route('seances.candidats', [$seance, 'niveau' => 'D1', 'equipe' => $this->equipe->id]))->assertOk()->json('data');
    expect(collect($candidats)->pluck('nom_complet')->all())->toBe(['DOIT '.$doit->prenoms, 'SOLDE '.$solde->prenoms])
        ->and($candidats[0]['raison'])->toContain('Paiement non soldé : reste 500')
        ->and($candidats[1]['raison'])->toBeNull();

    $reponse = $this->actingAs($this->admin)->postJson(route('seances.passages', $seance), ['doctorants' => [$solde->uuid, $doit->uuid]])->assertOk();
    expect($reponse->json('ajoutes'))->toBe(1)->and($reponse->json('refuses.0.raison'))->toContain('Paiement non soldé');

    $seance->update(['exige_paiement_solde' => false]);
    $this->actingAs($this->admin)->postJson(route('seances.passages', $seance), ['doctorants' => [$doit->uuid, $solde->uuid]])
        ->assertJsonPath('ajoutes', 1)->assertJsonPath('refuses.0.raison', 'Déjà programmé dans cette séance.');
});

it('schedules passages one after another with the pause, then recalculates them', function (): void {
    $seance = seanceLe(valeurs: ['duree_passage' => 20, 'pause' => 10]);
    $a = doctorantSeance($this->equipe, 'Aaa');
    $b = doctorantSeance($this->equipe, 'Bbb');
    $c = doctorantSeance($this->equipe, 'Ccc');

    $this->actingAs($this->admin)->postJson(route('seances.passages', $seance), ['doctorants' => [$a->uuid, $b->uuid, $c->uuid]])
        ->assertJsonPath('data.passages.*.heure_debut', ['08:00', '08:30', '09:00'])
        ->assertJsonPath('data.passages.2.heure_fin', '09:20')
        ->assertJsonPath('data.heure_fin', '09:20');

    $ids = $seance->passages()->pluck('id')->all();
    $this->actingAs($this->admin)->postJson(route('seances.replanifier', $seance), ['ordre' => array_reverse($ids)])
        ->assertOk()->assertJsonPath('data.passages.0.doctorant.nom_complet', fn (string $nom) => str_starts_with($nom, 'CCC'));

    $this->actingAs($this->admin)->putJson(route('seances.update', $seance), [...$seance->only(['type', 'titre']), 'type' => 'seminaire', 'date' => '2026-12-11', 'heure_debut' => '14:00', 'duree_passage' => 20, 'pause' => 0])
        ->assertOk()->assertJsonPath('data.passages.*.debut', ['2026-12-11T14:00', '2026-12-11T14:20', '2026-12-11T14:40']);
});

it('never books a moderator or jury member twice at the same time, even in another session', function (): void {
    $membre = MembreEquipe::factory()->for($this->equipe)->create(['nom' => 'RAKOTO Eliot', 'titre' => 'Pr']);
    $autre = MembreEquipe::factory()->for($this->equipe)->create();
    $seance = seanceLe();
    $ailleurs = seanceLe(valeurs: ['titre' => 'État d\'avancement EAD 1', 'type' => 'etat_avancement']);
    $passage = Passage::factory()->for($seance)->for(doctorantSeance($this->equipe, 'Rabe'))->entre('2026-12-10 08:00', '2026-12-10 08:30')->create();
    $suivant = Passage::factory()->for($seance)->for(doctorantSeance($this->equipe, 'Zafy'))->entre('2026-12-10 08:30', '2026-12-10 09:00')->create();
    $occupant = Passage::factory()->for($ailleurs)->for(doctorantSeance($this->equipe, 'Bema'))->entre('2026-12-10 08:15', '2026-12-10 08:45')->create();
    $occupant->intervenants()->attach($membre->id, ['role' => 'jury']);

    $disponibilites = collect($this->actingAs($this->admin)->getJson(route('seances.disponibilites', [$seance, 'passages' => [$passage->id]]))->assertOk()->json('data'))->keyBy('id');
    expect($disponibilites[$membre->id]['occupations'][0])->toContain("État d'avancement EAD 1, 10/12 de 08:15 à 08:45")
        ->and($disponibilites[$autre->id]['occupations'])->toBe([]);

    $this->actingAs($this->admin)->postJson(route('seances.affecter', $seance), ['passages' => [$passage->id, $suivant->id], 'membres' => [$membre->id, $autre->id], 'role' => 'moderateur'])
        ->assertUnprocessable()->assertJsonPath('conflits.0', fn (string $texte) => str_contains($texte, 'Pr RAKOTO Eliot est déjà pris'));
    expect($passage->intervenants()->count())->toBe(0);

    $this->actingAs($this->admin)->postJson(route('seances.affecter', $seance), ['passages' => [$passage->id, $suivant->id], 'membres' => [$autre->id], 'role' => 'moderateur'])
        ->assertOk()->assertJsonPath('data.passages.0.intervenants.0.role_libelle', 'Modérateur');

    $ailleurs->update(['statut' => 'annulee']);
    $this->actingAs($this->admin)->postJson(route('seances.affecter', $seance), ['passages' => [$passage->id], 'membres' => [$membre->id], 'role' => 'jury'])->assertOk();

    $this->actingAs($this->admin)->deleteJson(route('passages.retirer-intervenant', [$passage, $membre]))->assertOk();
    expect($passage->intervenants()->pluck('membres_equipe.id')->all())->toBe([$autre->id]);
});

it('refuses to move a passage onto a slot where its moderator or student is busy', function (): void {
    $membre = MembreEquipe::factory()->for($this->equipe)->create();
    $seance = seanceLe();
    $passage = Passage::factory()->for($seance)->for(doctorantSeance($this->equipe, 'Rabe'))->entre('2026-12-10 08:00', '2026-12-10 08:30')->create();
    $passage->intervenants()->attach($membre->id, ['role' => 'moderateur']);
    $autre = Passage::factory()->for(seanceLe())->for(doctorantSeance($this->equipe, 'Zafy'))->entre('2026-12-10 10:00', '2026-12-10 10:30')->create();
    $autre->intervenants()->attach($membre->id, ['role' => 'jury']);

    $this->actingAs($this->admin)->putJson(route('passages.update', $passage), ['heure_debut' => '10:15', 'duree' => 30])->assertUnprocessable()->assertJsonValidationErrors('heure_debut');
    $this->actingAs($this->admin)->putJson(route('passages.update', $passage), ['heure_debut' => '11:00', 'duree' => 45, 'sujet' => 'Plantes du Boeny'])
        ->assertOk()->assertJsonPath('data.passages.0.heure_fin', '11:45')->assertJsonPath('data.passages.0.sujet', 'Plantes du Boeny');
});

it('sends the convocations to students and to moderators and jury, by e-mail and in the platform', function (): void {
    Notification::fake();
    $seance = seanceLe();
    $doctorant = doctorantSeance($this->equipe, 'Rabe');
    $passage = Passage::factory()->for($seance)->for($doctorant)->entre('2026-12-10 08:00', '2026-12-10 08:30')->create();
    $avecCompte = MembreEquipe::factory()->for($this->equipe)->create(['email' => 'membre@edgvm.mg']);
    $compte = User::factory()->membreEad($this->equipe)->create(['email' => 'membre@edgvm.mg']);
    $sansCompte = MembreEquipe::factory()->for($this->equipe)->create(['email' => 'externe@exemple.mg']);
    $sansEmail = MembreEquipe::factory()->for($this->equipe)->create(['email' => null, 'nom' => 'SANS Courriel', 'titre' => 'Dr']);
    $passage->intervenants()->attach([$avecCompte->id => ['role' => 'moderateur'], $sansCompte->id => ['role' => 'jury'], $sansEmail->id => ['role' => 'jury']]);

    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance))
        ->assertOk()->assertJsonPath('sans_email', ['Dr SANS Courriel'])->assertJsonPath('data.statut', 'publiee');

    Notification::assertSentTo($doctorant->user, ConvocationPassage::class, function (ConvocationPassage $n) use ($doctorant): bool {
        $donnees = $n->toArray($doctorant->user);

        return str_contains($donnees['message'], 'de 08:00 à 08:30') && str_contains($donnees['message'], 'Modération') && $donnees['lien'] === '/seances/'.$n->passage->seance_id;
    });
    Notification::assertSentTo($compte, ConvocationIntervenant::class, fn (ConvocationIntervenant $n, array $canaux) => $canaux === ['database', 'mail']);
    Notification::assertSentOnDemand(ConvocationIntervenant::class, function (ConvocationIntervenant $n, array $canaux, AnonymousNotifiable $dest): bool {
        $html = (string) $n->toMail($dest)->render();

        return $dest->routes['mail'] === 'externe@exemple.mg' && $canaux === ['mail'] && str_contains($html, '>Niveau<') && str_contains($html, '>D2<');
    });
    expect($passage->fresh()->convoque_le)->not->toBeNull();

    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance), ['nouveaux' => true])->assertUnprocessable();
    $this->actingAs($this->admin)->deleteJson(route('seances.destroy', $seance))->assertStatus(409);
    $this->actingAs($this->admin)->postJson(route('seances.annuler', $seance))->assertOk()->assertJsonPath('data.statut', 'annulee');
});

it('shows a student only their own passage, and a team member those of their students', function (): void {
    $seance = seanceLe();
    $rabe = doctorantSeance($this->equipe, 'Rabe');
    $zafy = doctorantSeance(EquipeAccueil::factory()->create(), 'Zafy');
    Passage::factory()->for($seance)->for($rabe)->entre('2026-12-10 08:00', '2026-12-10 08:30')->create();
    Passage::factory()->for($seance)->for($zafy)->entre('2026-12-10 08:30', '2026-12-10 09:00')->create();

    $this->actingAs($rabe->user)->getJson(route('seances.show', $seance))->assertOk()->assertJsonCount(1, 'data.passages')->assertJsonPath('data.passages.0.doctorant.id', $rabe->uuid);
    $this->actingAs($rabe->user)->getJson(route('seances.index'))->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->membreEad($this->equipe)->create())->getJson(route('seances.show', $seance))->assertOk()->assertJsonCount(1, 'data.passages');
    $this->actingAs(Doctorant::factory()->create()->user)->getJson(route('seances.show', $seance))->assertForbidden();
    $this->actingAs($rabe->user)->postJson(route('seances.passages', $seance), ['doctorants' => [$rabe->uuid]])->assertForbidden();
});

it('sends only what is needed: never sent, or changed since, and also one passage or a selection', function (): void {
    Notification::fake();
    $seance = seanceLe();
    $moderateur = MembreEquipe::factory()->for($this->equipe)->create(['email' => 'moderateur@exemple.mg']);
    $jury = MembreEquipe::factory()->for($this->equipe)->create(['email' => 'jury@exemple.mg']);
    [$a, $b, $c] = collect(['Aaa', 'Bbb', 'Ccc'])->map(fn (string $nom, int $i) => Passage::factory()->for($seance)->for(doctorantSeance($this->equipe, $nom))
        ->entre(sprintf('2026-12-10 %02d:00', 8 + $i), sprintf('2026-12-10 %02d:30', 8 + $i))->create())->all();
    foreach ([$a, $b, $c] as $passage) {
        $passage->intervenants()->attach($moderateur->id, ['role' => 'moderateur']);
    }

    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance), ['mode' => 'passages', 'passages' => [$a->id]])
        ->assertOk()->assertJsonPath('envoyes', 1)
        ->assertJsonPath('data.passages.0.convocation', 'a_jour')->assertJsonPath('data.passages.1.convocation', 'jamais');
    Notification::assertSentTo($a->doctorant->user, ConvocationPassage::class);
    Notification::assertNotSentTo($b->doctorant->user, ConvocationPassage::class);
    Notification::assertSentOnDemand(ConvocationIntervenant::class, fn (ConvocationIntervenant $n) => $n->passages->count() === 3);

    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance))->assertOk()->assertJsonPath('envoyes', 2);
    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance))->assertUnprocessable()->assertJsonPath('message', 'Tout le monde a déjà reçu une convocation à jour.');

    Notification::fake();
    $this->actingAs($this->admin)->putJson(route('passages.update', $b), ['heure_debut' => '11:00', 'duree' => 30])->assertOk();
    $this->actingAs($this->admin)->postJson(route('seances.affecter', $seance), ['passages' => [$c->id], 'membres' => [$jury->id], 'role' => 'jury'])
        ->assertJsonPath('data.passages.1.convocation', 'modifiee');
    expect($a->fresh()->etatConvocation())->toBe('a_jour');

    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance), ['mode' => 'intelligent'])->assertOk()->assertJsonPath('envoyes', 2);
    Notification::assertNotSentTo($a->doctorant->user, ConvocationPassage::class);
    Notification::assertSentTo($b->doctorant->user, ConvocationPassage::class);
    Notification::assertSentTo($c->doctorant->user, ConvocationPassage::class);

    $seance->update(['lieu' => 'Salle B']);
    $this->actingAs($this->admin)->postJson(route('seances.convoquer', $seance), ['mode' => 'tous'])->assertOk()->assertJsonPath('envoyes', 3);
});

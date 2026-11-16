<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\MembreEquipe;
use App\Models\User;
use App\Support\JetonApiPublique;

beforeEach(function (): void {
    $this->annee = anneeCourante();
    $this->equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1', 'nom' => 'Biodiversité']);
});

/** Doctorant actif, visible dans l'annuaire, inscrit cette année au niveau donné. */
function doctorantPublic(EquipeAccueil $equipe, string $nom, string $niveau = 'D1', array $attributs = []): Doctorant
{
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create(['nom' => $nom, 'visible_annuaire' => true, 'sujet_these' => "Sujet de {$nom}", 'directeur_these' => 'Pr RABE Hery', ...$attributs]);
    Inscription::factory()->for($doctorant)->for(AnneeUniversitaire::courante(), 'anneeUniversitaire')->niveau(NiveauDoctorat::from($niveau))->create();

    return $doctorant;
}

it('refuses every call without the token created in the settings', function (): void {
    $this->getJson(route('public.doctorants.index'))->assertUnauthorized()->assertJsonPath('message', fn (string $m) => str_contains($m, 'pas activée'));

    JetonApiPublique::generer();

    $this->getJson(route('public.doctorants.index'))->assertUnauthorized();
    $this->withToken('mauvais')->getJson(route('public.equipes.index'))->assertUnauthorized();
});

it('lets the administration create, replace and revoke the token, shown only once', function (): void {
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('api-publique.show'))->assertOk()->assertJsonPath('data.actif', false)->assertJsonMissingPath('data.jeton');
    $premier = $this->actingAs($admin)->postJson(route('api-publique.generer'))->assertCreated()->json('data.jeton');
    $second = $this->actingAs($admin)->postJson(route('api-publique.generer'))->json('data.jeton');

    expect($premier)->not->toBe($second)->and(JetonApiPublique::verifier($premier))->toBeFalse()->and(JetonApiPublique::verifier($second))->toBeTrue();
    $this->actingAs($admin)->getJson(route('api-publique.show'))->assertJsonPath('data.actif', true)->assertJsonMissingPath('data.jeton');
    $this->assertDatabaseMissing('reglages', ['valeur' => $second]);

    $this->actingAs($admin)->deleteJson(route('api-publique.revoquer'))->assertOk()->assertJsonPath('data.actif', false);
    $this->withToken($second)->getJson(route('public.equipes.index'))->assertUnauthorized();

    $this->actingAs(User::factory()->membreEad()->create())->postJson(route('api-publique.generer'))->assertForbidden();
});

it('lists only active students who agreed to appear, without any private field', function (): void {
    $jeton = JetonApiPublique::generer();
    $visible = doctorantPublic($this->equipe, 'Rabe');
    doctorantPublic($this->equipe, 'Cache', attributs: ['visible_annuaire' => false]);
    doctorantPublic($this->equipe, 'Parti', attributs: ['statut' => 'abandon']);

    $this->withToken($jeton)->getJson(route('public.doctorants.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure(['data' => [['id', 'nom', 'niveau', 'annee_inscription', 'sujet', 'equipe' => ['sigle', 'nom'], 'directeur' => ['nom', 'titre'], 'these_id', 'photo_url']], 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
        ->assertJsonPath('data.0.id', $visible->uuid)
        ->assertJsonPath('data.0.niveau', 'D1')
        ->assertJsonPath('data.0.annee_inscription', $this->annee->debut->year)
        ->assertJsonPath('data.0.equipe.sigle', 'EAD 1')
        ->assertJsonPath('data.0.directeur.nom', 'Pr RABE Hery')
        ->assertJsonMissingPath('data.0.matricule')
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.telephone')
        ->assertJsonMissingPath('data.0.date_naissance')
        ->assertJsonMissingPath('data.0.paiement')
        ->assertJsonMissingPath('data.0.est_boursier')
        ->assertJsonMissingPath('data.0.est_derogation');
});

it('filters the directory by search, team and level, and never reveals a derogation', function (): void {
    $jeton = JetonApiPublique::generer();
    $autre = EquipeAccueil::factory()->create(['sigle' => 'EAD 2']);
    doctorantPublic($this->equipe, 'Rabe', 'D1');
    doctorantPublic($autre, 'Rakoto', 'D3');
    $derogation = doctorantPublic($autre, 'Randria', 'D3');
    $derogation->inscriptionCourante()->update(['est_derogation' => true, 'motif_derogation' => 'Terrain']);

    $this->withToken($jeton)->getJson(route('public.doctorants.index', ['recherche' => 'Rako']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.nom', fn (string $nom) => str_starts_with($nom, 'RAKOTO'));
    $this->withToken($jeton)->getJson(route('public.doctorants.index', ['equipe' => 'EAD 2']))->assertJsonCount(2, 'data');
    $this->withToken($jeton)->getJson(route('public.doctorants.index', ['niveau' => 'd1']))->assertJsonCount(1, 'data');
    $reponse = $this->withToken($jeton)->getJson(route('public.doctorants.index', ['equipe' => 'EAD 2']));
    expect(json_encode($reponse->json()))->not->toContain('rogation')->not->toContain('Terrain');
});

it('publishes the teams with their head counts, matched to the website by acronym', function (): void {
    $jeton = JetonApiPublique::generer();
    doctorantPublic($this->equipe, 'Rabe', 'D1');
    doctorantPublic($this->equipe, 'Rakoto', 'D3', ['visible_annuaire' => false]);
    doctorantPublic($this->equipe, 'Parti', 'D2', ['statut' => 'abandon']);
    EquipeAccueil::factory()->create(['sigle' => 'EAD 9']);

    $this->withToken($jeton)->getJson(route('public.equipes.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.sigle', 'EAD 1')
        ->assertJsonPath('data.0.effectifs', ['total' => 2, 'D1' => 1, 'D2' => 0, 'D3' => 1])
        ->assertJsonPath('data.0.doctorants_annuaire', 1)
        ->assertJsonPath('data.1.effectifs.total', 0);

    $this->withToken($jeton)->getJson(route('public.equipes.show', 'EAD 1'))->assertOk()->assertJsonPath('data.nom', 'Biodiversité');
    $this->withToken($jeton)->getJson(route('public.equipes.show', 'EAD 404'))->assertNotFound();
});

it('publishes the domain, the programmes and the members of a team, without their contact details', function (): void {
    $jeton = JetonApiPublique::generer();
    $this->equipe->update(['domaine' => 'Sciences de la vie', 'description' => 'Étude du vivant.']);
    $this->equipe->programmes()->create(['nom' => 'Écologie', 'description' => 'Milieux humides.', 'ordre' => 2]);
    $this->equipe->programmes()->create(['nom' => 'Botanique', 'ordre' => 1]);
    MembreEquipe::factory()->for($this->equipe, 'equipeAccueil')->membre()->create(['nom' => 'ZAFY Aina', 'titre' => 'Dr', 'email' => 'aina@exemple.mg', 'telephone' => '0340000000']);
    MembreEquipe::factory()->for($this->equipe, 'equipeAccueil')->responsable()->create(['nom' => 'RAKOTO Jean', 'titre' => 'Pr', 'specialite' => 'Botanique']);

    $reponse = $this->withToken($jeton)->getJson(route('public.equipes.show', 'EAD 1'))
        ->assertOk()
        ->assertJsonPath('data.domaine', 'Sciences de la vie')
        ->assertJsonPath('data.description', 'Étude du vivant.')
        ->assertJsonPath('data.programmes', [['nom' => 'Botanique', 'description' => null], ['nom' => 'Écologie', 'description' => 'Milieux humides.']])
        ->assertJsonPath('data.membres.0', ['nom' => 'RAKOTO Jean', 'titre' => 'Pr', 'fonction' => 'responsable', 'fonction_libelle' => "Responsable de l'équipe", 'specialite' => 'Botanique', 'etablissement' => 'Université de Mahajanga'])
        ->assertJsonPath('data.membres.1.nom', 'ZAFY Aina')
        ->assertJsonMissingPath('data.membres.0.email')
        ->assertJsonMissingPath('data.membres.1.telephone');

    expect($reponse->getContent())->not->toContain('aina@exemple.mg')->not->toContain('0340000000');
    $this->withToken($jeton)->getJson(route('public.equipes.index'))->assertJsonPath('data.0.membres.1.nom', 'ZAFY Aina');
});

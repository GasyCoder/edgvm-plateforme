<?php

use App\Enums\CategoriePiece;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\PieceJointe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(PieceJointe::DISQUE));

it('lets the school attach a diploma, stored privately', function (): void {
    $doctorant = Doctorant::factory()->create();

    $reponse = $this->actingAs(superAdmin())->post(route('pieces.store', $doctorant), [
        'fichier' => UploadedFile::fake()->create('Diplome Master.pdf', 300, 'application/pdf'),
        'categorie' => 'diplome',
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.titre', 'Diplome Master')
        ->assertJsonPath('data.categorie_libelle', 'Diplôme')
        ->assertJsonPath('data.affichable', true);

    $piece = PieceJointe::query()->findOrFail($reponse->json('data.id'));
    expect($piece->chemin)->toStartWith("pieces/{$doctorant->uuid}/")->toEndWith('.pdf');
    Storage::disk(PieceJointe::DISQUE)->assertExists($piece->chemin);
});

it('lets a student add documents to their own file only', function (): void {
    $doctorant = Doctorant::factory()->create();
    $autre = Doctorant::factory()->create();
    $fichier = fn () => UploadedFile::fake()->image('cin.jpg');

    $this->actingAs($doctorant->user)->post(route('pieces.store', $doctorant), ['fichier' => $fichier(), 'categorie' => 'piece_identite', 'titre' => 'CIN recto'], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.peut_supprimer', true);
    $this->actingAs($doctorant->user)->post(route('pieces.store', $autre), ['fichier' => $fichier(), 'categorie' => 'autre'], ['Accept' => 'application/json'])
        ->assertForbidden();
    $this->actingAs(User::factory()->membreEad()->create())->post(route('pieces.store', $doctorant), ['fichier' => $fichier(), 'categorie' => 'autre'], ['Accept' => 'application/json'])
        ->assertForbidden();
});

it('refuses unexpected file types and oversized files', function (UploadedFile $fichier, string $message): void {
    $this->actingAs(superAdmin())->post(route('pieces.store', Doctorant::factory()->create()), ['fichier' => $fichier, 'categorie' => 'autre'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.fichier.0', $message);
})->with([
    'script' => [fn () => UploadedFile::fake()->create('virus.php', 5, 'application/x-php'), 'Formats acceptés : PDF, JPG, PNG, WEBP, DOC, DOCX.'],
    'trop gros' => [fn () => UploadedFile::fake()->create('scan.pdf', 11000, 'application/pdf'), 'Le fichier ne doit pas dépasser 10 Mo.'],
]);

it('shows team members their students documents except identity papers', function (): void {
    $equipe = EquipeAccueil::factory()->create();
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create();
    $diplome = PieceJointe::factory()->for($doctorant)->create();
    $cin = PieceJointe::factory()->for($doctorant)->categorie(CategoriePiece::PieceIdentite)->create();
    Storage::disk(PieceJointe::DISQUE)->put($diplome->chemin, '%PDF-1.4');
    $membre = User::factory()->membreEad($equipe)->create();

    $this->actingAs($membre)->getJson(route('pieces.index', $doctorant))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $diplome->id);
    $this->actingAs($membre)->get(route('pieces.telecharger', $diplome))->assertOk();
    $this->actingAs($membre)->get(route('pieces.telecharger', $cin))->assertForbidden();
    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('pieces.index', $doctorant))->assertForbidden();
    $this->actingAs(superAdmin())->getJson(route('pieces.index', $doctorant))->assertJsonCount(2, 'data');
});

it('downloads with the original name, or opens a PDF in the browser', function (): void {
    $piece = PieceJointe::factory()->create(['nom_original' => 'Diplôme master.pdf']);
    Storage::disk(PieceJointe::DISQUE)->put($piece->chemin, '%PDF-1.4');
    $admin = superAdmin();

    expect($this->actingAs($admin)->get(route('pieces.telecharger', $piece))->assertOk()->headers->get('content-disposition'))->toStartWith('attachment')
        ->and($this->actingAs($admin)->get(route('pieces.telecharger', [$piece, 'afficher' => 1]))->headers->get('content-disposition'))->toStartWith('inline');
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('pieces.telecharger', $piece))->assertForbidden();
});

it('deletes the file with the record, within each role rights', function (): void {
    $doctorant = Doctorant::factory()->create();
    $parEcole = PieceJointe::factory()->for($doctorant)->create();
    $parDoctorant = PieceJointe::factory()->for($doctorant)->create(['ajoute_par' => $doctorant->user_id]);
    Storage::disk(PieceJointe::DISQUE)->put($parDoctorant->chemin, 'x');

    $this->actingAs($doctorant->user)->deleteJson(route('pieces.destroy', $parEcole))->assertForbidden();
    $this->actingAs($doctorant->user)->deleteJson(route('pieces.destroy', $parDoctorant))->assertNoContent();
    Storage::disk(PieceJointe::DISQUE)->assertMissing($parDoctorant->chemin);
    $this->actingAs(superAdmin())->deleteJson(route('pieces.destroy', $parEcole))->assertNoContent();
    expect(PieceJointe::query()->count())->toBe(0);
});

it('sends a visitor without a session to the login page instead of the file', function (): void {
    $piece = PieceJointe::factory()->create();

    $this->get(route('pieces.telecharger', $piece))->assertRedirect('/connexion');
    $this->get(route('doctorants.export'))->assertRedirect('/connexion');
});

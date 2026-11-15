<?php

use App\Enums\NiveauDoctorat;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\Inscription;
use App\Models\User;
use App\Support\CarteDoctorat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('stores the photo as a 3:4 portrait jpeg, replaces it and removes it', function (): void {
    $doctorant = Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->post(route('doctorants.photo.store', $doctorant), ['photo' => UploadedFile::fake()->image('photo.png', 1200, 900)], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.photo_url', fn (string $url) => str_starts_with($url, "/telechargements/doctorants/{$doctorant->uuid}/photo?v="));
    $premiere = $doctorant->fresh()->photo_chemin;
    [$largeur, $hauteur, $type] = getimagesizefromstring(Storage::disk('local')->get($premiere));
    expect([$largeur, $hauteur, $type])->toBe([450, 600, IMAGETYPE_JPEG]);

    $this->actingAs($admin)->post(route('doctorants.photo.store', $doctorant), ['photo' => UploadedFile::fake()->image('autre.jpg', 600, 800)], ['Accept' => 'application/json'])->assertCreated();
    Storage::disk('local')->assertMissing($premiere);
    $this->actingAs($admin)->getJson(route('doctorants.show', $doctorant))->assertJsonPath('data.photo_url', fn (?string $url) => $url !== null);

    $this->actingAs($admin)->deleteJson(route('doctorants.photo.destroy', $doctorant))->assertOk();
    expect($doctorant->fresh()->photo_chemin)->toBeNull()->and(Storage::disk('local')->allFiles('photos'))->toBe([]);
});

it('refuses a file that is not a usable photo', function (UploadedFile $fichier, string $message): void {
    $this->actingAs(superAdmin())->post(route('doctorants.photo.store', Doctorant::factory()->create()), ['photo' => $fichier], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['photo' => $message]);
})->with([
    'pdf' => [fn () => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf'), 'image'],
    'trop petite' => [fn () => UploadedFile::fake()->image('mini.jpg', 80, 80), 'trop petite'],
]);

it('shows the photo to the school and to the student, never lets anyone else change it', function (): void {
    $doctorant = Doctorant::factory()->create();
    $this->actingAs(superAdmin())->post(route('doctorants.photo.store', $doctorant), ['photo' => UploadedFile::fake()->image('p.jpg', 600, 800)], ['Accept' => 'application/json']);

    $this->actingAs($doctorant->user)->get(route('doctorants.photo', $doctorant))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('doctorants.photo', $doctorant))->assertForbidden();
    $this->actingAs($doctorant->user)->post(route('doctorants.photo.store', $doctorant), ['photo' => UploadedFile::fake()->image('p.jpg', 600, 800)], ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs(User::factory()->membreEad()->create())->deleteJson(route('doctorants.photo.destroy', $doctorant))->assertForbidden();
});

it('prints the doctorate card, alone or for a selection, for the school only', function (): void {
    $doctorant = Doctorant::factory()->create(['nom' => 'Bezara', 'prenoms' => 'Roussel Florent']);
    $autre = Doctorant::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('doctorants.carte', $doctorant))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('doctorants.cartes', ['ids' => [$doctorant->uuid, $autre->uuid]]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('doctorants.cartes', ['ids' => ['inconnu']]))->assertNotFound();
    $this->actingAs($doctorant->user)->get(route('doctorants.carte', $doctorant))->assertForbidden();
    $this->actingAs($admin)->getJson(route('doctorants.show', $doctorant))->assertJsonPath('data.url_carte', "/telechargements/doctorants/{$doctorant->uuid}/carte.pdf");

    $html = CarteDoctorat::html(collect([$doctorant->fresh()]));
    expect($html)->toContain('CARTE DE DOCTORAT')->toContain('BEZARA')->toContain('Roussel Florent')->toContain($doctorant->matricule)->toContain('ASSOUMACOU Elia Béatrice');
});

it('fills the three thesis years from the registrations, or deduces them', function (): void {
    $annee2025 = AnneeUniversitaire::factory()->rentree(2025)->create();
    $annee2026 = AnneeUniversitaire::factory()->rentree(2026)->create();
    $doctorant = Doctorant::factory()->create();
    Inscription::factory()->for($doctorant)->for($annee2025, 'anneeUniversitaire')->niveau(NiveauDoctorat::D1)->create();
    Inscription::factory()->for($doctorant)->for($annee2026, 'anneeUniversitaire')->niveau(NiveauDoctorat::D2)->create();

    expect(CarteDoctorat::anneesDeThese($doctorant->fresh()))->toBe([
        ['rang' => '1ère', 'annee' => '2025-2026'],
        ['rang' => '2ème', 'annee' => '2026-2027'],
        ['rang' => '3ème', 'annee' => '2027-2028'],
    ]);

    $arrive = Doctorant::factory()->create();
    Inscription::factory()->for($arrive)->for($annee2026, 'anneeUniversitaire')->niveau(NiveauDoctorat::D3)->create();
    expect(collect(CarteDoctorat::anneesDeThese($arrive->fresh()))->pluck('annee')->all())->toBe(['2024-2025', '2025-2026', '2026-2027'])
        ->and(collect(CarteDoctorat::anneesDeThese(Doctorant::factory()->create()))->pluck('annee')->all())->toBe([null, null, null]);
});

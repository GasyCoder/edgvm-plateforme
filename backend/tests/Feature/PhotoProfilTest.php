<?php

use App\Models\Doctorant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('lets everyone set, replace and remove their own profile photo, as a square jpeg', function (): void {
    $membre = User::factory()->membreEad()->create();

    $url = $this->actingAs($membre)->post(route('moi.photo'), ['photo' => UploadedFile::fake()->image('moi.png', 900, 600)], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.photo_url');
    expect($url)->toStartWith("/telechargements/utilisateurs/{$membre->id}/photo?v=");
    $premiere = $membre->fresh()->photo_chemin;
    expect(getimagesizefromstring(Storage::disk('local')->get($premiere))[0])->toBe(400);

    $this->actingAs($membre)->post(route('moi.photo'), ['photo' => UploadedFile::fake()->image('autre.jpg', 500, 500)], ['Accept' => 'application/json'])->assertCreated();
    Storage::disk('local')->assertMissing($premiere);
    $this->actingAs($membre)->getJson(route('moi'))->assertJsonPath('data.photo_url', fn (?string $u) => $u !== null);
    $this->actingAs($membre)->get(route('utilisateurs.photo', $membre))->assertOk()->assertHeader('content-type', 'image/jpeg');

    $this->actingAs($membre)->deleteJson(route('moi.photo.supprimer'))->assertOk()->assertJsonPath('data.photo_url', null);
    expect(Storage::disk('local')->allFiles('avatars'))->toBe([]);
    $this->actingAs($membre)->post(route('moi.photo'), ['photo' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['photo' => 'image']);
});

it('lets the school manage the photo of any account, and keeps photos out of other students sight', function (): void {
    $admin = superAdmin();
    $membre = User::factory()->membreEad()->create();
    $doctorant = Doctorant::factory()->create();

    $this->actingAs($admin)->post(route('comptes.photo', $membre), ['photo' => UploadedFile::fake()->image('p.jpg', 400, 400)], ['Accept' => 'application/json'])->assertCreated();
    $this->actingAs($admin)->getJson(route('comptes.index'))->assertJsonPath('data', fn (array $comptes) => collect($comptes)->firstWhere('id', $membre->id)['photo_url'] !== null);

    $this->actingAs($doctorant->user)->get(route('utilisateurs.photo', $membre))->assertForbidden();
    $this->actingAs($membre)->post(route('comptes.photo', $admin), ['photo' => UploadedFile::fake()->image('p.jpg', 400, 400)], ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($admin)->deleteJson(route('comptes.photo.supprimer', $membre))->assertOk();
    expect($membre->fresh()->photo_chemin)->toBeNull();
});

it('uses the student file photo as avatar when the student has no profile photo', function (): void {
    $doctorant = Doctorant::factory()->create();
    $this->actingAs(superAdmin())->post(route('doctorants.photo.store', $doctorant), ['photo' => UploadedFile::fake()->image('d.jpg', 600, 800)], ['Accept' => 'application/json']);

    $this->actingAs($doctorant->user)->getJson(route('moi'))->assertJsonPath('data.photo_url', fn (string $u) => str_starts_with($u, "/telechargements/doctorants/{$doctorant->uuid}/photo"));
});

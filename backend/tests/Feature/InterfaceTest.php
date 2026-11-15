<?php

use App\Http\Controllers\InterfaceController;
use Illuminate\Support\Facades\File;

afterEach(fn () => File::delete(public_path(InterfaceController::FICHIER)));

it('serves the interface for every page address', function (string $adresse): void {
    File::put(public_path(InterfaceController::FICHIER), '<div id="__nuxt"></div>');

    $this->get($adresse)
        ->assertOk()
        ->assertSee('<div id="__nuxt"></div>', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/', '/doctorants', '/doctorants/9b0c-uuid/modifier', '/mot-de-passe/jeton']);

it('answers unknown API addresses in JSON, not with the interface', function (): void {
    File::put(public_path(InterfaceController::FICHIER), '<div id="__nuxt"></div>');

    $this->getJson('/api/inconnue')->assertNotFound()->assertJsonPath('message', 'Adresse introuvable.');
});

it('says when the interface has not been built yet', function (): void {
    $this->get('/doctorants')->assertServiceUnavailable();
});

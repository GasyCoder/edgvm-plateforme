<?php

use App\Models\Doctorant;
use App\Support\ReglagesActes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('updates the signatory and stores a scanned signature', function (): void {
    Storage::fake(ReglagesActes::DISQUE);
    $admin = superAdmin();

    $this->actingAs($admin)->getJson(route('reglages-actes.show'))->assertOk()
        ->assertJsonPath('data.signataire_nom', 'ASSOUMACOU Elia Béatrice')
        ->assertJsonPath('data.afficher_nom_signataire', false)
        ->assertJsonPath('data.images.signature.existe', false);

    $this->actingAs($admin)->post(route('reglages-actes.update'), [
        'signataire_nom' => 'RAKOTO Jean',
        'signataire_fonction' => 'Le Directeur par intérim,',
        'signataire_titre' => 'Professeur',
        'ville' => 'Mahajanga',
        'signature' => UploadedFile::fake()->image('signature.png', 400, 200),
    ], ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('data.signataire_fonction', 'Le Directeur par intérim,')
        ->assertJsonPath('data.images.signature.personnalisee', true);

    expect(ReglagesActes::instantane(true)['signature'])->toStartWith('reglages/signature-');
    $this->actingAs($admin)->get(route('reglages-actes.image', 'signature'))->assertOk();
    $this->actingAs($admin)->get(route('reglages-actes.image', 'logo_gauche'))->assertOk()->assertHeader('content-type', 'image/png');
});

it('reserves act settings to the school', function (): void {
    $this->actingAs(Doctorant::factory()->create()->user)->getJson(route('reglages-actes.show'))->assertForbidden();
});

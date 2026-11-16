<?php

use App\Enums\NiveauDoctorat;
use App\Models\Acte;
use App\Models\Doctorant;
use App\Models\Inscription;
use App\Models\ModeleActe;
use Database\Seeders\ModelesActesSeeder;

beforeEach(function (): void {
    $this->seed(ModelesActesSeeder::class);
    $this->annee = anneeCourante();
    $this->doctorant = Doctorant::factory()->create(['genre' => 'F', 'date_naissance' => '1991-09-23', 'directeur_these' => 'Pr RAKOTO Jean']);
    Inscription::factory()->for($this->doctorant)->for($this->annee, 'anneeUniversitaire')->niveau(NiveauDoctorat::D2)->create();
    $this->actingAs(superAdmin())->postJson(route('actes.store'), [
        'modele_acte_id' => ModeleActe::query()->where('code', 'attestation-inscription')->value('id'),
        'doctorant' => $this->doctorant->uuid,
        'annee_universitaire_id' => $this->annee->id,
    ])->assertCreated();
    $this->acte = Acte::query()->sole();
});

/** Lien signé demandé depuis l'application, puis ouvert sans session (navigateur du téléphone). */
function lienSigne(Doctorant $doctorant, string $chemin): string
{
    $url = test()->actingAs($doctorant->user)->postJson(route('liens-telechargement'), ['chemin' => $chemin])->assertOk()->json('data.url');
    app('auth')->forgetGuards();

    return $url;
}

it('opens a document with a short signed link, without a session', function (): void {
    $url = lienSigne($this->doctorant, "/telechargements/actes/{$this->acte->id}.pdf");

    expect($url)->toContain('_sig=');
    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    app('auth')->forgetGuards();
    $this->get("/telechargements/actes/{$this->acte->id}.pdf")->assertRedirect('/connexion');
});

it('refuses a signed link that was changed, has expired, or targets someone else\'s document', function (): void {
    $url = lienSigne($this->doctorant, "/telechargements/actes/{$this->acte->id}.pdf");
    $this->get(str_replace("/actes/{$this->acte->id}.pdf", '/actes/999.pdf', $url))->assertForbidden();
    $this->get($url.'&ids[]=2')->assertForbidden();

    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
    $this->travelBack();

    $autre = lienSigne(Doctorant::factory()->create(), "/telechargements/actes/{$this->acte->id}.pdf");
    $this->get($autre)->assertForbidden();
});

it('refuses a signed link for a deactivated account', function (): void {
    $url = lienSigne($this->doctorant, "/telechargements/actes/{$this->acte->id}.pdf");
    $this->doctorant->user->update(['actif' => false]);

    $this->get($url)->assertForbidden();
});

it('signs only platform downloads and only for a logged-in user', function (): void {
    app('auth')->forgetGuards();
    $this->postJson(route('liens-telechargement'), ['chemin' => '/telechargements/actes/1.pdf'])->assertUnauthorized();
    $this->actingAs($this->doctorant->user)->postJson(route('liens-telechargement'), ['chemin' => 'https://exemple.com/x.pdf'])
        ->assertUnprocessable()->assertJsonValidationErrors('chemin');
    $this->actingAs($this->doctorant->user)->postJson(route('liens-telechargement'), ['chemin' => '/api/moi'])->assertUnprocessable();
});

it('keeps the query parameters inside the signature', function (): void {
    $chemin = "/telechargements/actes.pdf?ids[]={$this->acte->id}";
    $url = lienSigne($this->doctorant, $chemin);

    expect(urldecode($url))->toContain("ids[0]={$this->acte->id}");
});

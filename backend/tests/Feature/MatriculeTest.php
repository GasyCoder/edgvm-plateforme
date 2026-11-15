<?php

use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Reglage;
use App\Models\User;
use App\Support\NumeroMatricule;
use Illuminate\Support\Facades\Notification;

/** @param array<string, mixed> $valeurs */
function reglerMatricule(array $valeurs): void
{
    foreach ([...NumeroMatricule::DEFAUTS, ...$valeurs] as $cle => $valeur) {
        Reglage::definir('matricule.'.$cle, (string) $valeur);
    }
}

it('keeps the current numbering by default', function (): void {
    Doctorant::factory()->create(['matricule' => 'EDGVM-'.now()->year.'-0041']);
    Doctorant::factory()->create(['matricule' => 'EDGVM-'.(now()->year - 1).'-0099']);

    expect(Doctorant::prochainMatricule())->toBe('EDGVM-'.now()->year.'-0042');
});

it('follows a custom pattern, counting only the numbers of that pattern', function (): void {
    reglerMatricule(['format' => '{NNN}-{AA}', 'remise' => 'annuelle']);
    $aa = substr((string) now()->year, -2);
    Doctorant::factory()->create(['matricule' => "270-{$aa}"]);
    Doctorant::factory()->create(['matricule' => '999-10']);
    Doctorant::factory()->create(['matricule' => 'EDGVM-'.now()->year.'-0500']);

    expect(Doctorant::prochainMatricule())->toBe("271-{$aa}");
});

it('starts from the chosen number and never goes back', function (): void {
    reglerMatricule(['format' => 'DOC/{AAAA}/{NNNN}', 'depart' => 120]);
    expect(Doctorant::prochainMatricule())->toBe('DOC/'.now()->year.'/0120');

    Doctorant::factory()->create(['matricule' => 'DOC/'.now()->year.'/0150']);
    expect(Doctorant::prochainMatricule())->toBe('DOC/'.now()->year.'/0151');
});

it('can keep counting across years and use the academic year', function (): void {
    AnneeUniversitaire::factory()->rentree(2025)->courante()->create();
    reglerMatricule(['format' => 'EDGVM-{AA}-{NNNNN}', 'remise' => 'jamais', 'annee' => 'universitaire']);
    Doctorant::factory()->create(['matricule' => 'EDGVM-23-00017']);

    expect(Doctorant::prochainMatricule())->toBe('EDGVM-25-00018');
});

it('gives new students the configured number when created', function (): void {
    Notification::fake();
    reglerMatricule(['format' => 'ED-{NNNN}', 'remise' => 'jamais']);

    $this->actingAs(superAdmin())->postJson(route('doctorants.store'), ['nom' => 'Rabe', 'email' => 'rabe@exemple.mg', 'equipe_accueil_id' => EquipeAccueil::factory()->create()->id])
        ->assertCreated()->assertJsonPath('data.matricule', 'ED-0001');
});

it('lets the school preview and save the pattern, with clear errors', function (): void {
    $admin = superAdmin();
    $corps = ['format' => '{NNN}-{AA}', 'annee' => 'civile', 'remise' => 'annuelle', 'depart' => 271];

    $this->actingAs($admin)->getJson(route('reglages-matricule.show'))->assertOk()->assertJsonPath('data.format', 'EDGVM-{AAAA}-{NNNN}')->assertJsonPath('data.apercu', 'EDGVM-'.now()->year.'-0001');
    $this->actingAs($admin)->postJson(route('reglages-matricule.apercu'), $corps)->assertOk()->assertJsonPath('data.apercu', '271-'.substr((string) now()->year, -2));
    expect(NumeroMatricule::reglages()['format'])->toBe('EDGVM-{AAAA}-{NNNN}');

    $this->actingAs($admin)->postJson(route('reglages-matricule.update'), $corps)->assertOk()->assertJsonPath('data.apercu', '271-'.substr((string) now()->year, -2));
    expect(NumeroMatricule::reglages())->toBe(['format' => '{NNN}-{AA}', 'annee' => 'civile', 'remise' => 'annuelle', 'depart' => 271, 'depart_annee' => now()->year])
        ->and(NumeroMatricule::suivant(annee: now()->year + 1))->toBe('001-'.substr((string) (now()->year + 1), -2));

    $this->actingAs(User::factory()->membreEad()->create())->getJson(route('reglages-matricule.show'))->assertForbidden();
});

it('refuses a pattern that would give duplicate or invalid numbers', function (string $format, string $remise, string $message): void {
    $this->actingAs(superAdmin())->postJson(route('reglages-matricule.update'), ['format' => $format, 'annee' => 'civile', 'remise' => $remise, 'depart' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['format' => $message]);
})->with([
    'sans numéro' => ['EDGVM-{AAAA}', 'annuelle', 'un seul numéro'],
    'deux numéros' => ['{NN}-{NNNN}', 'jamais', 'un seul numéro'],
    'remise sans année' => ['EDGVM-{NNNN}', 'annuelle', "doit contenir l'année"],
    'caractère interdit' => ['ED@{NNNN}', 'jamais', 'Caractères permis'],
    'repère inconnu' => ['ED-{MM}-{NNNN}', 'jamais', 'Caractères permis'],
]);

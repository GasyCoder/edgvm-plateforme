<?php

use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\InvitationPlateforme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

function fichierCsv(string $contenu): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doctorants.csv', $contenu);
}

beforeEach(function (): void {
    EquipeAccueil::factory()->create(['sigle' => 'EAD 1']);
    EquipeAccueil::factory()->create(['sigle' => 'EAD 2']);
});

it('exports the filtered list as an Excel friendly CSV', function (): void {
    $annee = anneeCourante();
    $equipe = EquipeAccueil::query()->where('sigle', 'EAD 2')->sole();
    $doctorant = Doctorant::factory()->dansEquipe($equipe)->create(['nom' => 'Rabe', 'prenoms' => 'Fanja', 'date_naissance' => '1991-04-02']);
    Inscription::factory()->for($doctorant)->for($annee, 'anneeUniversitaire')->create(['montant_du' => 1_200_000]);
    Doctorant::factory()->create(['nom' => 'Autre']);

    $reponse = $this->actingAs(superAdmin())->get(route('doctorants.export', ['equipe' => $equipe->id]))->assertOk();
    $contenu = $reponse->streamedContent();

    expect($reponse->headers->get('content-disposition'))->toContain('doctorants-edgvm-')
        ->and($contenu)->toStartWith("\xEF\xBB\xBFMatricule;Nom;Prénoms")
        ->and($contenu)->toContain('RABE;Fanja')
        ->and($contenu)->toContain('02/04/1991')
        ->and($contenu)->toContain(';D1;Validée;1200000')
        ->and($contenu)->not->toContain('AUTRE');
});

it('exports only the selected students', function (): void {
    $choisi = Doctorant::factory()->create(['nom' => 'Choisi']);
    Doctorant::factory()->create(['nom' => 'Ignore']);

    $contenu = $this->actingAs(superAdmin())->get(route('doctorants.export', ['ids' => [$choisi->uuid]]))->streamedContent();

    expect($contenu)->toContain('CHOISI')->not->toContain('IGNORE');
});

it('gives team members an export without personal data or fees', function (): void {
    $equipe = EquipeAccueil::query()->where('sigle', 'EAD 1')->sole();
    Doctorant::factory()->dansEquipe($equipe)->create(['nom' => 'Sien', 'date_naissance' => '1990-01-01']);
    Doctorant::factory()->create(['nom' => 'Pas_le_sien']);

    $contenu = $this->actingAs(User::factory()->membreEad($equipe)->create())->get(route('doctorants.export'))->streamedContent();

    expect($contenu)->toContain('SIEN')->not->toContain('PAS_LE_SIEN')->not->toContain('Date de naissance')->not->toContain('Droits');
    $this->actingAs(Doctorant::factory()->create()->user)->get(route('doctorants.export'))->assertForbidden();
});

it('provides an import template that the import accepts as is', function (): void {
    anneeCourante();
    $admin = superAdmin();
    $modele = $this->actingAs($admin)->get(route('doctorants.modele-import'))->assertOk()->streamedContent();

    $this->actingAs($admin)->post(route('doctorants.import'), ['fichier' => fichierCsv($modele), 'simulation' => true])
        ->assertOk()
        ->assertJsonPath('data.valides', 1)
        ->assertJsonPath('data.erreurs', []);
});

it('simulates an import without saving anything, then imports the valid lines', function (): void {
    Notification::fake();
    anneeCourante();
    Doctorant::factory()->for(User::factory()->doctorant()->state(['email' => 'deja@edgvm.mg']))->create();
    $csv = "Nom;Prénoms;Sexe;Né le;E-mail;Téléphone;EAD;Niveau;Droits (Ar)\n"
        ."Rasoa;Mialy;Femme;05/06/1992;Mialy@Exemple.mg;034 11 222 33;ead1;D2;1 500 000\n"
        ."Bema;Paul;M;1990-13-45;paul@exemple.mg;;EAD 9;D1;0\n"
        ."Doublon;;M;;deja@edgvm.mg;;EAD 2;;\n"
        ."Copie;;F;;mialy@exemple.mg;;EAD 2;;\n"
        .";;;;;;;;\n";
    $admin = superAdmin();

    $simulation = $this->actingAs($admin)->post(route('doctorants.import'), ['fichier' => fichierCsv($csv), 'simulation' => true])
        ->assertOk()
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.valides', 1)
        ->assertJsonPath('data.crees', 0)
        ->assertJsonPath('data.apercu.0.libelle', 'RASOA Mialy')
        ->json('data.erreurs');

    expect(collect($simulation)->pluck('ligne')->all())->toBe([3, 4, 5])
        ->and($simulation[0]['erreurs'])->toBe([
            'Date de naissance illisible : « 1990-13-45 » (format attendu : 25/03/1990).',
            "Équipe d'accueil inconnue : « EAD 9 ».",
        ])
        ->and($simulation[1]['erreurs'])->toContain('Cette adresse e-mail est déjà utilisée par un autre compte.')
        ->and($simulation[2]['erreurs'])->toContain('Adresse e-mail déjà présent ligne 2 du fichier.')
        ->and(Doctorant::query()->count())->toBe(1);

    $this->actingAs($admin)->post(route('doctorants.import'), ['fichier' => fichierCsv($csv), 'envoyer_invitation' => true])
        ->assertOk()
        ->assertJsonPath('data.crees', 1);

    $importe = Doctorant::query()->where('nom', 'Rasoa')->sole();
    expect($importe)->genre->toBe('F')
        ->and($importe->date_naissance->toDateString())->toBe('1992-06-05')
        ->and($importe->user->email)->toBe('mialy@exemple.mg')
        ->and($importe->user->telephone)->toBe('+261341122233')
        ->and($importe->equipeAccueil->sigle)->toBe('EAD 1')
        ->and($importe->inscriptions()->sole()->niveau->value)->toBe('D2')
        ->and($importe->inscriptions()->sole()->montant_du)->toBe(1_500_000);
    Notification::assertSentTo($importe->user, InvitationPlateforme::class);
});

it('reads files saved by Excel with commas and Windows encoding', function (): void {
    $csv = mb_convert_encoding("Nom,Prénoms,E-mail,Équipe\nRakoto,Hérilala,heri@exemple.mg,EAD 2\n", 'Windows-1252', 'UTF-8');

    $this->actingAs(superAdmin())->post(route('doctorants.import'), ['fichier' => fichierCsv($csv), 'simulation' => true])
        ->assertOk()
        ->assertJsonPath('data.valides', 1)
        ->assertJsonPath('data.apercu.0.libelle', 'RAKOTO Hérilala');
});

it('rejects a file without the required columns or with no lines', function (string $csv, string $message): void {
    $this->actingAs(superAdmin())->post(route('doctorants.import'), ['fichier' => fichierCsv($csv), 'simulation' => true], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.fichier.0', $message);
})->with([
    'sans e-mail' => ["Nom;Équipe\nRabe;EAD 1\n", "Colonne obligatoire absente : « email ». Partez du modèle d'import."],
    'vide' => ["Nom;E-mail;Équipe\n", 'Le fichier est vide ou ne contient que la ligne des titres.'],
]);

it('reserves the import to the school', function (): void {
    $this->actingAs(User::factory()->membreEad()->create())
        ->post(route('doctorants.import'), ['fichier' => fichierCsv("Nom;E-mail;Équipe\nA;a@b.mg;EAD 1\n")], ['Accept' => 'application/json'])
        ->assertForbidden();
});

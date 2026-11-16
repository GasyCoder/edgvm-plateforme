<?php

use App\Enums\StatutDoctorant;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use Database\Seeders\TypesFraisSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    anneeCourante();
    $this->seed(TypesFraisSeeder::class);
    foreach (['EAD 1', 'EAD 2', 'EAD 3', 'EAD 4', 'EAD 5', 'EAD 6'] as $sigle) {
        EquipeAccueil::factory()->create(['sigle' => $sigle]);
    }
    superAdmin();
});

it('creates 4 students (D1, D2, D3, derogation) and 3 candidates, hidden from the public site, without any e-mail or notification', function (): void {
    Notification::fake();
    Mail::fake();

    $this->artisan('plateforme:donnees-demo')->assertSuccessful()->expectsOutputToContain('Mot de passe commun');

    $demo = Doctorant::query()->whereHas('user', fn ($q) => $q->where('email', 'like', '%@demo.edgvm.test'));
    expect((clone $demo)->where('statut', StatutDoctorant::Actif)->count())->toBe(4)
        ->and((clone $demo)->where('statut', StatutDoctorant::Candidat)->count())->toBe(3)
        ->and((clone $demo)->where('visible_annuaire', true)->count())->toBe(0);

    $inscriptions = Inscription::query()->get();
    expect($inscriptions->pluck('niveau')->map->value->sort()->values()->all())->toBe(['D1', 'D2', 'D3', 'D3'])
        ->and($inscriptions->where('est_derogation', true)->count())->toBe(1)
        ->and(Paiement::query()->count())->toBe(3);

    Notification::assertNothingSent();
    Mail::assertNothingSent();
    $this->artisan('plateforme:donnees-demo')->assertFailed();
});

it('removes every demo record, including what was added during the demo, and nothing else', function (): void {
    $vrai = Doctorant::factory()->create();
    $this->artisan('plateforme:donnees-demo')->assertSuccessful();

    $this->artisan('plateforme:donnees-demo --supprimer')->assertSuccessful()->expectsOutputToContain('7 dossier(s)');

    expect(User::query()->where('email', 'like', '%@demo.edgvm.test')->count())->toBe(0)
        ->and(Doctorant::query()->pluck('id')->all())->toBe([$vrai->id])
        ->and(Paiement::query()->count())->toBe(0);
});

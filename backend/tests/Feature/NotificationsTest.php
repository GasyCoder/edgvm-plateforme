<?php

use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Notifications\RappelPaiement;
use App\Notifications\RecapitulatifRetards;

it('lists notifications with the unread count and the number of late students', function (): void {
    $admin = superAdmin();
    FraisDoctorant::factory()->echu()->create();
    $this->artisan('paiements:rappels')->assertSuccessful();

    $reponse = $this->actingAs($admin)->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('non_lues', 1)
        ->assertJsonPath('en_retard', 1)
        ->assertJsonPath('data.0.titre', 'Retards de paiement')
        ->assertJsonPath('data.0.lien', '/paiements?situation=en_retard');

    $this->actingAs($admin)->postJson(route('notifications.lue', $reponse->json('data.0.id')))->assertNoContent();
    $this->actingAs($admin)->getJson(route('notifications.index'))->assertJsonPath('non_lues', 0)->assertJsonPath('data.0.lue', true);
});

it('reminds late students at most once a week and sums up once a day for the school', function (): void {
    $admin = superAdmin();
    $enRetard = FraisDoctorant::factory()->echu()->create(['montant' => 300_000])->doctorant;
    FraisDoctorant::factory()->create();

    $this->artisan('paiements:rappels')->assertSuccessful();
    $this->artisan('paiements:rappels')->assertSuccessful();

    expect($enRetard->user->notifications()->where('type', RappelPaiement::class)->count())->toBe(1)
        ->and($admin->notifications()->where('type', RecapitulatifRetards::class)->count())->toBe(1)
        ->and($enRetard->user->notifications()->first()->data['message'])->toContain("300\u{00A0}000\u{00A0}Ar");

    $this->travel(8)->days();
    $this->artisan('paiements:rappels')->assertSuccessful();
    expect($enRetard->user->notifications()->where('type', RappelPaiement::class)->count())->toBe(2);
});

it('marks everything as read and keeps notifications private', function (): void {
    $doctorant = FraisDoctorant::factory()->echu()->create()->doctorant;
    $this->artisan('paiements:rappels');

    $this->actingAs(Doctorant::factory()->create()->user)->postJson(route('notifications.lue', $doctorant->user->notifications()->first()->id))->assertNotFound();
    $this->actingAs($doctorant->user)->getJson(route('notifications.index'))->assertJsonPath('non_lues', 1)->assertJsonPath('en_retard', null);
    $this->actingAs($doctorant->user)->postJson(route('notifications.tout-lu'))->assertNoContent();
    $this->actingAs($doctorant->user)->getJson(route('notifications.index'))->assertJsonPath('non_lues', 0);
});

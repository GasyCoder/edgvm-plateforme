<?php

use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\User;
use App\Notifications\InvitationPlateforme;
use Illuminate\Support\Facades\Notification;

it('creates a team member account attached to their teams and invites them', function (): void {
    Notification::fake();
    $equipes = EquipeAccueil::factory()->count(2)->create();

    $this->actingAs(superAdmin())->postJson(route('comptes.store'), [
        'name' => 'Pr RAZAFY Lova',
        'email' => 'lova@edgvm.mg',
        'telephone' => '+261 32 00 000 00',
        'role' => 'membre_ead',
        'equipes' => $equipes->pluck('id')->all(),
    ])->assertCreated()->assertJsonCount(2, 'data.equipes');

    $compte = User::query()->where('email', 'lova@edgvm.mg')->sole();
    expect($compte->telephone)->toBe('+261320000000')->and($compte->estMembreEad())->toBeTrue();
    Notification::assertSentTo($compte, InvitationPlateforme::class);
});

it('requires a team for a team member and refuses the student role', function (): void {
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('comptes.store'), ['name' => 'X', 'email' => 'x@edgvm.mg', 'role' => 'membre_ead'])
        ->assertUnprocessable()->assertJsonValidationErrors('equipes');
    $this->actingAs($admin)->postJson(route('comptes.store'), ['name' => 'X', 'email' => 'x@edgvm.mg', 'role' => 'doctorant'])
        ->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('lists staff accounts by default, and students on request', function (): void {
    $admin = superAdmin();
    User::factory()->membreEad()->create();
    Doctorant::factory()->create();

    $this->actingAs($admin)->getJson(route('comptes.index'))->assertJsonPath('meta.total', 2);
    $this->actingAs($admin)->getJson(route('comptes.index', ['role' => 'doctorant']))->assertJsonPath('meta.total', 1);
});

it('prevents an administrator from locking themselves out', function (): void {
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(route('comptes.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'membre_ead', 'actif' => false, 'equipes' => [EquipeAccueil::factory()->create()->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['role', 'actif']);
});

it('disables an account and resends an access link', function (): void {
    Notification::fake();
    $admin = superAdmin();
    $compte = User::factory()->membreEad()->create();

    $this->actingAs($admin)->putJson(route('comptes.update', $compte), [
        'name' => $compte->name, 'email' => $compte->email, 'role' => 'membre_ead', 'actif' => false, 'equipes' => $compte->idsEquipesSuivies(),
    ])->assertOk()->assertJsonPath('data.actif', false);
    $this->actingAs($admin)->postJson(route('comptes.lien-acces', $compte))->assertOk();

    Notification::assertSentTo($compte, InvitationPlateforme::class);
});

it('reserves account management to the school', function (): void {
    $membre = User::factory()->membreEad()->create();

    $this->actingAs($membre)->getJson(route('comptes.index'))->assertForbidden();
    $this->actingAs($membre)->postJson(route('comptes.lien-acces', superAdmin()))->assertForbidden();
});

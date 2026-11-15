<?php

use App\Models\User;
use App\Notifications\InvitationPlateforme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('logs a user in and returns what the interface needs', function (): void {
    $user = User::factory()->superAdmin()->create(['email' => 'scolarite@edgvm.mg', 'password' => 'motdepasse123']);

    depuisInterface()->postJson(route('connexion'), ['email' => ' Scolarite@EDGVM.mg ', 'password' => 'motdepasse123'])
        ->assertOk()
        ->assertJsonPath('data.role', 'super_admin')
        ->assertJsonPath('data.email', 'scolarite@edgvm.mg');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->derniere_connexion_le)->not->toBeNull();
});

it('refuses a wrong password without saying which field is wrong', function (): void {
    User::factory()->create(['email' => 'doc@edgvm.mg', 'password' => 'motdepasse123']);

    depuisInterface()->postJson(route('connexion'), ['email' => 'doc@edgvm.mg', 'password' => 'mauvais'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);

    $this->assertGuest();
});

it('refuses a disabled account', function (): void {
    User::factory()->inactif()->create(['email' => 'ancien@edgvm.mg', 'password' => 'motdepasse123']);

    depuisInterface()->postJson(route('connexion'), ['email' => 'ancien@edgvm.mg', 'password' => 'motdepasse123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('limits login attempts', function (): void {
    foreach (range(1, 6) as $essai) {
        depuisInterface()->postJson(route('connexion'), ['email' => 'x@edgvm.mg', 'password' => 'faux']);
    }

    depuisInterface()->postJson(route('connexion'), ['email' => 'x@edgvm.mg', 'password' => 'faux'])->assertTooManyRequests();
});

it('logs out', function (): void {
    $this->actingAs(User::factory()->create());

    depuisInterface()->postJson(route('deconnexion'))->assertNoContent();

    $this->assertGuest('web');
});

it('requires authentication for the current user', function (): void {
    $this->getJson(route('moi'))->assertUnauthorized();
});

it('returns the doctoral file id and teams of the current user', function (): void {
    $membre = User::factory()->membreEad()->create();

    $this->actingAs($membre)->getJson(route('moi'))
        ->assertOk()
        ->assertJsonPath('data.role', 'membre_ead')
        ->assertJsonCount(1, 'data.equipes');
});

it('sends a reset link without revealing whether the account exists', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'doc@edgvm.mg']);

    $this->postJson(route('mot-de-passe.oublie'), ['email' => 'doc@edgvm.mg'])->assertOk();
    $this->postJson(route('mot-de-passe.oublie'), ['email' => 'inconnu@edgvm.mg'])
        ->assertOk()
        ->assertJsonPath('message', 'Si un compte correspond à cette adresse, un lien vient de lui être envoyé.');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        return str_starts_with($notification->toMail($user)->actionUrl, 'http://localhost:3000/mot-de-passe/');
    });
    Notification::assertCount(1);
});

it('lets a new user choose a password with the invitation link', function (): void {
    $user = User::factory()->create(['email' => 'nouveau@edgvm.mg', 'derniere_connexion_le' => null]);
    $jeton = Password::broker('invitations')->createToken($user);
    $this->travel(3)->days();

    $this->postJson(route('mot-de-passe.reinitialiser'), [
        'token' => $jeton,
        'email' => 'nouveau@edgvm.mg',
        'password' => 'NouveauMot2026',
        'password_confirmation' => 'NouveauMot2026',
    ])->assertOk();

    depuisInterface()->postJson(route('connexion'), ['email' => 'nouveau@edgvm.mg', 'password' => 'NouveauMot2026'])->assertOk();
});

it('rejects an expired or invalid reset token', function (): void {
    $user = User::factory()->create(['email' => 'doc@edgvm.mg', 'derniere_connexion_le' => now()]);
    $jeton = Password::broker('users')->createToken($user);
    $this->travel(2)->hours();

    $this->postJson(route('mot-de-passe.reinitialiser'), [
        'token' => $jeton,
        'email' => 'doc@edgvm.mg',
        'password' => 'NouveauMot2026',
        'password_confirmation' => 'NouveauMot2026',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('requires a password of at least 10 characters with letters and digits', function (string $motDePasse): void {
    $user = User::factory()->create(['password' => 'ancienMot2025']);

    $this->actingAs($user)->putJson(route('moi.mot-de-passe'), [
        'mot_de_passe_actuel' => 'ancienMot2025',
        'password' => $motDePasse,
        'password_confirmation' => $motDePasse,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
})->with(['trop court' => 'abc123', 'sans chiffre' => 'motdepassesanschiffre', 'identique' => 'ancienMot2025']);

it('changes the password when the current one is right', function (): void {
    $user = User::factory()->create(['password' => 'ancienMot2025']);

    $this->actingAs($user)->putJson(route('moi.mot-de-passe'), [
        'mot_de_passe_actuel' => 'faux',
        'password' => 'NouveauMot2026',
        'password_confirmation' => 'NouveauMot2026',
    ])->assertUnprocessable()->assertJsonValidationErrors('mot_de_passe_actuel');

    $this->actingAs($user)->putJson(route('moi.mot-de-passe'), [
        'mot_de_passe_actuel' => 'ancienMot2025',
        'password' => 'NouveauMot2026',
        'password_confirmation' => 'NouveauMot2026',
    ])->assertOk();
});

it('builds the invitation link to the interface', function (): void {
    $user = User::factory()->create(['email' => 'doc@edgvm.mg']);

    expect((new InvitationPlateforme('jeton'))->lien($user))
        ->toBe('http://localhost:3000/mot-de-passe/jeton?email=doc%40edgvm.mg&premier=1');
});

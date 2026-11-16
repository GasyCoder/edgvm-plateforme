<?php

use App\Models\User;
use App\Support\Totp;

/** Active la double authentification et renvoie les codes de secours. */
function activerDeuxFacteurs(User $user): array
{
    test()->actingAs($user)->postJson(route('deux-facteurs.preparer'))->assertOk();
    $secret = $user->fresh()->deux_facteurs_secret;

    return test()->actingAs($user)->postJson(route('deux-facteurs.confirmer'), ['code' => Totp::code($secret)])->assertOk()->json('data.codes_secours');
}

it('turns on two-factor authentication with a QR code and a first code, and gives recovery codes once', function (): void {
    $user = User::factory()->membreEad()->create();

    $preparation = $this->actingAs($user)->postJson(route('deux-facteurs.preparer'))->assertOk();
    expect($preparation->json('data.qr'))->toStartWith('data:image/png;base64,')
        ->and($preparation->json('data.uri'))->toStartWith('otpauth://totp/EDGVM:')->toContain('issuer=EDGVM')
        ->and($preparation->json('data.cle'))->toMatch('/^[A-Z2-7]{4}( [A-Z2-7]{4})+$/');

    $secret = $user->fresh()->deux_facteurs_secret;
    expect($user->fresh()->getAttributes()['deux_facteurs_secret'])->not->toContain($secret);

    $this->actingAs($user)->postJson(route('deux-facteurs.confirmer'), ['code' => '000000'])->assertUnprocessable();
    expect($user->fresh()->deuxFacteursActive())->toBeFalse();

    $codes = $this->actingAs($user)->postJson(route('deux-facteurs.confirmer'), ['code' => Totp::code($secret)])->assertOk()->json('data.codes_secours');
    expect($codes)->toHaveCount(8)->and($codes[0])->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    $this->actingAs($user)->getJson(route('moi'))->assertJsonPath('data.deux_facteurs', true);
    $this->actingAs($user)->getJson(route('deux-facteurs.etat'))->assertJsonPath('data.codes_secours_restants', 8);
    $this->actingAs($user)->postJson(route('deux-facteurs.preparer'))->assertStatus(409);
});

it('asks for the app code after the password, and logs in only with a valid code', function (): void {
    $user = User::factory()->membreEad()->create(['password' => 'MotDePasse2026']);
    activerDeuxFacteurs($user);
    auth()->forgetGuards();
    $this->travel(31)->seconds();

    depuisInterface()->postJson(route('connexion'), ['email' => $user->email, 'password' => 'MotDePasse2026'])
        ->assertOk()->assertJsonPath('deux_facteurs', true)->assertJsonMissingPath('data.email');
    $this->assertGuest('web');

    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => '999999'])->assertJsonValidationErrors(['code' => 'Il reste 4 essais']);
    $code = Totp::code($user->fresh()->deux_facteurs_secret);
    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => $code])->assertOk()->assertJsonPath('data.email', $user->email);
    $this->assertAuthenticatedAs($user, 'web');
});

it('accepts each recovery code once, refuses a reused app code, and expires the second step', function (): void {
    $user = User::factory()->membreEad()->create(['password' => 'MotDePasse2026']);
    $codes = activerDeuxFacteurs($user);
    auth()->forgetGuards();

    depuisInterface()->postJson(route('connexion'), ['email' => $user->email, 'password' => 'MotDePasse2026'])->assertJsonPath('deux_facteurs', true);
    $codeUtilise = Totp::code($user->fresh()->deux_facteurs_secret);
    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => $codeUtilise])->assertJsonValidationErrors('code');

    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => strtolower($codes[0])])->assertOk();
    expect(count($user->fresh()->deux_facteurs_secours))->toBe(7);

    $this->post('/api/deconnexion');
    auth()->forgetGuards();
    depuisInterface()->postJson(route('connexion'), ['email' => $user->email, 'password' => 'MotDePasse2026']);
    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => $codes[0]])->assertJsonValidationErrors('code');

    $this->travel(6)->minutes();
    depuisInterface()->postJson(route('connexion.deux-facteurs'), ['code' => $codes[1]])->assertUnprocessable()->assertJsonPath('recommencer', true);
});

it('needs the password to turn it off, and lets the school reset it for a lost phone', function (): void {
    $user = User::factory()->membreEad()->create(['password' => 'MotDePasse2026']);
    activerDeuxFacteurs($user);

    $this->actingAs($user)->deleteJson(route('deux-facteurs.desactiver'), ['password' => 'faux'])->assertJsonValidationErrors(['password' => 'incorrect']);
    $this->actingAs($user)->postJson(route('deux-facteurs.codes'), ['password' => 'MotDePasse2026'])->assertOk()->assertJsonCount(8, 'data.codes_secours');
    $this->actingAs($user)->deleteJson(route('deux-facteurs.desactiver'), ['password' => 'MotDePasse2026'])->assertOk();
    expect($user->fresh()->deuxFacteursActive())->toBeFalse()->and($user->fresh()->deux_facteurs_secret)->toBeNull();

    activerDeuxFacteurs($user);
    $this->actingAs(User::factory()->membreEad()->create())->deleteJson(route('comptes.deux-facteurs', $user))->assertForbidden();
    $this->actingAs(superAdmin())->deleteJson(route('comptes.deux-facteurs', $user))->assertOk();
    expect($user->fresh()->deuxFacteursActive())->toBeFalse();
});

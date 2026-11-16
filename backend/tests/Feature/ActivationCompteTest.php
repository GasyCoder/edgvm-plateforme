<?php

use App\Models\CodeVerification;
use App\Models\Doctorant;
use App\Models\User;
use App\Notifications\Canaux\CanalWhatsApp;
use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use App\Notifications\CodeActivation;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Code envoyé lors du dernier appel (capturé par Notification::fake). */
function dernierCode(User $user): string
{
    $code = null;
    Notification::assertSentTo($user, CodeActivation::class, function (CodeActivation $n) use (&$code): bool {
        $code = $n->code;

        return true;
    });

    return (string) $code;
}

beforeEach(function (): void {
    Notification::fake();
});

it('activates an account by e-mail: code, then password, then logged in', function (): void {
    $doctorant = Doctorant::factory()->create();
    $user = $doctorant->user;

    $reponse = $this->postJson(route('activation.demande'), ['identifiant' => '  '.strtoupper($user->email).' '])
        ->assertCreated()->assertJsonPath('data.canal', 'email')->assertJsonPath('data.renvoyer_dans', 60);
    expect($reponse->json('data.destination'))->toContain('•')->toEndWith(explode('@', $user->email)[1]);
    $demande = $reponse->json('data.demande');
    $code = dernierCode($user);
    expect($code)->toMatch('/^\d{4}$/')->and(CodeVerification::query()->sole()->code_hash)->not->toBe($code);

    $jeton = $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => $code])->assertOk()->json('data.jeton');
    depuisInterface()->postJson(route('activation.terminer'), ['demande' => $demande, 'jeton' => $jeton, 'password' => 'Fanja2026x', 'password_confirmation' => 'Fanja2026x'])
        ->assertOk()->assertJsonPath('data.email', $user->email);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->derniere_connexion_le)->not->toBeNull();
    depuisInterface()->postJson(route('activation.terminer'), ['demande' => $demande, 'jeton' => $jeton, 'password' => 'Autre2026xx', 'password_confirmation' => 'Autre2026xx'])->assertUnprocessable();
});

it('sends the code by WhatsApp when the person gives a phone number', function (): void {
    $membre = User::factory()->membreEad()->create(['telephone' => '+261341234567']);

    $this->postJson(route('activation.demande'), ['identifiant' => '034 12 345 67'])
        ->assertCreated()->assertJsonPath('data.canal', 'whatsapp')->assertJsonPath('data.destination', '+261 34 •• ••• 67');

    Notification::assertSentTo($membre, CodeActivation::class, fn (CodeActivation $n, array $canaux) => $canaux === [CanalWhatsApp::class]
        && $n->toWhatsApp($membre)['parametres'] === [$n->code]);
});

it('refuses unknown, deactivated, already activated, candidate and admin accounts', function (): void {
    $this->postJson(route('activation.demande'), ['identifiant' => 'inconnu@exemple.mg'])->assertUnprocessable()->assertJsonValidationErrors(['identifiant' => 'Aucun compte']);
    $this->postJson(route('activation.demande'), ['identifiant' => '034 00 000 00'])->assertUnprocessable()->assertJsonValidationErrors(['identifiant' => 'Aucun compte ne correspond à ce numéro']);

    $desactive = User::factory()->membreEad()->create(['actif' => false]);
    $this->postJson(route('activation.demande'), ['identifiant' => $desactive->email])->assertUnprocessable();

    $admin = superAdmin();
    $this->postJson(route('activation.demande'), ['identifiant' => $admin->email])->assertUnprocessable();

    $deja = User::factory()->membreEad()->create(['derniere_connexion_le' => now()->subDay()]);
    $this->postJson(route('activation.demande'), ['identifiant' => $deja->email])->assertStatus(409)->assertJsonPath('code', 'deja_active');

    User::factory()->membreEad()->create(['telephone' => '+261349999999']);
    User::factory()->membreEad()->create(['telephone' => '+261349999999']);
    $this->postJson(route('activation.demande'), ['identifiant' => '0349999999'])->assertJsonValidationErrors(['identifiant' => 'Plusieurs comptes']);
    Notification::assertNothingSent();
});

it('limits wrong codes, waits before a new code, and expires codes', function (): void {
    $user = User::factory()->membreEad()->create();
    $demande = $this->postJson(route('activation.demande'), ['identifiant' => $user->email])->json('data.demande');
    $bon = dernierCode($user);
    $faux = $bon === '0000' ? '1111' : '0000';

    $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => $faux])->assertJsonValidationErrors(['code' => 'Il reste 4 essais']);
    $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => '12'])->assertJsonValidationErrors(['code' => '4 chiffres']);
    $this->postJson(route('activation.renvoyer'), ['demande' => $demande])->assertStatus(429)->assertJsonPath('renvoyer_dans', fn (int $s) => $s > 0);

    foreach (range(1, 4) as $essai) {
        $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => $faux]);
    }
    $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => $bon])->assertJsonValidationErrors(['code' => 'Trop d\'essais']);

    $this->travel(61)->seconds();
    $this->postJson(route('activation.renvoyer'), ['demande' => $demande])->assertOk();
    expect(CodeVerification::query()->sole()->essais)->toBe(0);

    $this->travel(11)->minutes();
    $this->postJson(route('activation.verifier'), ['demande' => $demande, 'code' => '1234'])->assertJsonValidationErrors(['code' => 'expiré']);
});

it('tells the person to use the e-mail when WhatsApp fails, and sends a validated template in production', function (): void {
    config(['services.whatsapp' => ['pilote' => 'meta', 'jeton' => 'jeton-test', 'numero_id' => '123456', 'version' => 'v21.0', 'langue' => 'fr', 'modele_code' => 'code_verification']]);
    Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['messages' => [['id' => 'wamid.1']]])->push(['error' => 'refus'], 400)]);

    app(EnvoiWhatsApp::class)->envoyer('+261341234567', 'code_verification', ['4821'], '4821 est votre code');
    Http::assertSent(fn ($requete) => $requete->url() === 'https://graph.facebook.com/v21.0/123456/messages'
        && $requete['to'] === '261341234567' && $requete['template']['name'] === 'code_verification'
        && $requete['template']['components'][0]['parameters'][0]['text'] === '4821'
        && $requete->hasHeader('Authorization', 'Bearer jeton-test'));

    Notification::swap(new ChannelManager(app()));
    User::factory()->membreEad()->create(['telephone' => '+261341234567']);
    $this->postJson(route('activation.demande'), ['identifiant' => '0341234567'])->assertStatus(503)->assertJsonValidationErrors(['identifiant' => 'WhatsApp']);
    expect(CodeVerification::query()->count())->toBe(0);
});

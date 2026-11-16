<?php

use App\Models\EquipeAccueil;
use App\Support\JetonApiPublique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.site_vitrine.url' => 'https://site.test/']);
    $this->jeton = JetonApiPublique::generer();
    $this->equipe = EquipeAccueil::factory()->create(['sigle' => 'EAD 1', 'nom' => 'Déchets et eau']);
    Http::fake(['site.test/*' => Http::response(null, 204)]);
    Illuminate\Support\defer()->forget('site-vitrine');
});

it('warns the website once, with a signature only the token holder can check, when a team changes', function (): void {
    $this->actingAs(superAdmin())->putJson(route('equipes.update', $this->equipe), ['sigle' => 'EAD 1', 'nom' => "Déchets et l'eau"])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $requete): bool {
        $cle = hash('sha256', $this->jeton);
        $corps = json_decode($requete->body(), true);

        return $requete->url() === 'https://site.test/api/scolarite/actualiser'
            && $requete->method() === 'POST'
            && $requete->header('X-Edgvm-Signature')[0] === 'sha256='.hash_hmac('sha256', $requete->body(), $cle)
            && $corps['evenement'] === 'donnees_modifiees'
            && abs($corps['horodatage'] - now()->getTimestamp()) < 5;
    });
});

it('sends a single notice for a request that changes many records', function (): void {
    $this->actingAs(superAdmin());
    $this->postJson(route('programmes.store', $this->equipe), ['nom' => 'Botanique'])->assertSuccessful();
    Http::assertSentCount(1);
});

it('stays silent when the website is not configured or no token exists', function (Closure $preparer): void {
    $preparer();
    $this->actingAs(superAdmin())->putJson(route('equipes.update', $this->equipe), ['sigle' => 'EAD 1', 'nom' => 'Autre nom'])->assertOk();

    Http::assertNothingSent();
})->with([
    'pas de site' => [fn () => config(['services.site_vitrine.url' => null])],
    'pas de jeton' => [fn () => JetonApiPublique::revoquer()],
]);

it('never fails the change when the website does not answer', function (): void {
    Http::fake(['site.test/*' => fn () => throw new ConnectionException('Délai dépassé')]);

    $this->actingAs(superAdmin())->putJson(route('equipes.update', $this->equipe), ['sigle' => 'EAD 1', 'nom' => 'Nom gardé'])->assertOk();

    expect($this->equipe->fresh()->nom)->toBe('Nom gardé');
});

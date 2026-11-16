<?php

namespace App\Providers;

use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use App\Models\MembreEquipe;
use App\Models\ProgrammeDoctoral;
use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use App\Notifications\Canaux\Pilotes\WhatsAppJournal;
use App\Notifications\Canaux\Pilotes\WhatsAppMeta;
use App\Support\SiteVitrine;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EnvoiWhatsApp::class, fn () => config('services.whatsapp.pilote') === 'meta' ? new WhatsAppMeta : new WhatsAppJournal);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Les liens de mot de passe ouvrent l'interface Nuxt, pas une page Laravel.
        ResetPassword::createUrlUsing(fn ($user, string $token): string => config('app.frontend_url')
            .'/mot-de-passe/'.$token.'?email='.urlencode($user->getEmailForPasswordReset()));

        // Ce que le site vitrine publie (équipes, programmes, membres, annuaire, effectifs) : il est prévenu à chaque changement.
        foreach ([EquipeAccueil::class, ProgrammeDoctoral::class, MembreEquipe::class, Doctorant::class, Inscription::class] as $modele) {
            $modele::saved(fn () => SiteVitrine::signalerChangement());
            $modele::deleted(fn () => SiteVitrine::signalerChangement());
        }
    }
}

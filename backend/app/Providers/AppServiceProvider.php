<?php

namespace App\Providers;

use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use App\Notifications\Canaux\Pilotes\WhatsAppJournal;
use App\Notifications\Canaux\Pilotes\WhatsAppMeta;
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
    }
}

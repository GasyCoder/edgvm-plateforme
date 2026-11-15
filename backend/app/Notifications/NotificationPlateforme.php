<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\ReglagesApplication;
use App\Support\TableauCourriel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Base des notifications de la plateforme : la cloche (base de données) est remplie tout de suite,
 * l'e-mail part par la file d'attente.
 */
abstract class NotificationPlateforme extends Notification implements ShouldQueue
{
    use Queueable;

    /** Envoyer aussi par e-mail */
    protected bool $parEmail = false;

    abstract protected function titre(): string;

    abstract protected function message(): string;

    abstract protected function lien(): string;

    abstract protected function categorie(): string;

    /**
     * Phrase d'introduction de l'e-mail (par défaut, le message de la cloche).
     */
    protected function introductionCourriel(): string|HtmlString
    {
        return $this->message();
    }

    /**
     * Encadré de détails sous l'introduction de l'e-mail (libellé => valeur), facultatif.
     *
     * @return array<string, ?string>
     */
    protected function detailsCourriel(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return $this->parEmail && $notifiable->actif ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array{titre: string, message: string, lien: string, categorie: string}
     */
    public function toArray(User $notifiable): array
    {
        return ['titre' => $this->titre(), 'message' => $this->message(), 'lien' => $this->lien(), 'categorie' => $this->categorie()];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $nom = ReglagesApplication::nomPourCourriels();

        $courriel = (new MailMessage)
            ->subject($this->titre().', '.$nom)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->introductionCourriel());

        if ($details = array_filter($this->detailsCourriel(), fn (?string $valeur) => filled($valeur))) {
            $courriel->line(TableauCourriel::details($details));
        }

        return $courriel
            ->action('Voir sur la plateforme', rtrim((string) config('app.frontend_url'), '/').$this->lien())
            ->salutation("L'équipe de l'{$nom}");
    }

    protected static function ar(int $montant): string
    {
        return number_format($montant, 0, ',', "\u{00A0}")."\u{00A0}Ar";
    }
}

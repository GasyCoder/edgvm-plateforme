<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return (new MailMessage)
            ->subject($this->titre().', EDGVM')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message())
            ->action('Voir sur la plateforme', rtrim((string) config('app.frontend_url'), '/').$this->lien())
            ->salutation("L'équipe de l'EDGVM");
    }

    protected static function ar(int $montant): string
    {
        return number_format($montant, 0, ',', "\u{00A0}")."\u{00A0}Ar";
    }
}

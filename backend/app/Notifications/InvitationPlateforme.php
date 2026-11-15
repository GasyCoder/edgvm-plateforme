<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\ReglagesApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationPlateforme extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $jeton) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function lien(User $notifiable): string
    {
        return config('app.frontend_url').'/mot-de-passe/'.$this->jeton.'?email='.urlencode($notifiable->email).'&premier=1';
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre accès à la plateforme '.ReglagesApplication::nomPourCourriels())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line("Un compte a été créé pour vous sur la plateforme de l'École Doctorale Génie du Vivant et Modélisation.")
            ->line('Choisissez votre mot de passe pour y accéder. Votre identifiant est votre adresse e-mail : '.$notifiable->email.'.')
            ->action('Choisir mon mot de passe', $this->lien($notifiable))
            ->line('Ce lien est valable 7 jours. Passé ce délai, demandez-en un nouveau à la scolarité ou utilisez « Mot de passe oublié ».')
            ->salutation("L'équipe de l'".ReglagesApplication::texte('app.nom'));
    }
}

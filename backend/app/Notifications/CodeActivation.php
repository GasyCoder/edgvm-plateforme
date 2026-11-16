<?php

namespace App\Notifications;

use App\Notifications\Canaux\CanalWhatsApp;
use App\Support\ReglagesApplication;
use App\Support\TableauCourriel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Code à 4 chiffres pour activer son compte, par e-mail ou par WhatsApp. Envoyé tout de suite (pas de file d'attente).
 */
class CodeActivation extends Notification
{
    public function __construct(public string $code, public string $canal) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [$this->canal === 'whatsapp' ? CanalWhatsApp::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nom = ReglagesApplication::nomPourCourriels();

        return (new MailMessage)
            ->subject("Votre code d'accès {$nom} : {$this->code}")
            ->greeting('Bonjour,')
            ->line(TableauCourriel::phrase('Voici votre code pour créer votre accès à la plateforme :code', ['code' => $nom.'.']))
            ->line(new HtmlString('<p style="margin:8px 0 24px;font-size:32px;font-weight:bold;letter-spacing:12px;color:#14563f;text-align:center;">'.e($this->code).'</p>'))
            ->line('Il est valable 10 minutes. Si vous n\'avez rien demandé, ignorez ce message : votre compte reste protégé.')
            ->salutation("L'équipe de l'{$nom}");
    }

    /**
     * @return array{modele: string, parametres: array<int, string>, texte: string}
     */
    public function toWhatsApp(object $notifiable): array
    {
        return [
            'modele' => (string) config('services.whatsapp.modele_code'),
            'parametres' => [$this->code],
            'texte' => "{$this->code} est votre code d'accès ".ReglagesApplication::texte('app.nom').'. Valable 10 minutes.',
        ];
    }
}

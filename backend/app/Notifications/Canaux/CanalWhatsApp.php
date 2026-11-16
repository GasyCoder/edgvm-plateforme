<?php

namespace App\Notifications\Canaux;

use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use Illuminate\Notifications\Notification;

/**
 * Canal « WhatsApp » des notifications : la notification fournit toWhatsApp() (modèle, paramètres, texte),
 * le destinataire son numéro E.164 (routeNotificationForWhatsapp ou attribut « telephone »).
 */
class CanalWhatsApp
{
    public function __construct(private EnvoiWhatsApp $envoi) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $numero = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('whatsapp', $notification) : null;
        $numero ??= $notifiable->telephone ?? null;

        if (blank($numero) || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        /** @var array{modele: string, parametres: array<int, string>, texte: string} $message */
        $message = $notification->toWhatsApp($notifiable);
        $this->envoi->envoyer($numero, $message['modele'], $message['parametres'], $message['texte']);
    }
}

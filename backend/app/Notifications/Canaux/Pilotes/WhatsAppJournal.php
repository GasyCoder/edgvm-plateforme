<?php

namespace App\Notifications\Canaux\Pilotes;

use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use Illuminate\Support\Facades\Log;

/**
 * Pilote local et de test : rien n'est envoyé, le message est écrit dans le journal de l'application.
 */
class WhatsAppJournal implements EnvoiWhatsApp
{
    public function envoyer(string $numeroE164, string $modele, array $parametres, string $texte): void
    {
        Log::info('WhatsApp (non envoyé, pilote « log »)', ['numero' => $numeroE164, 'modele' => $modele, 'texte' => $texte]);
    }
}

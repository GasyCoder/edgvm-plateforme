<?php

namespace App\Notifications\Canaux\Pilotes;

use App\Notifications\Canaux\Contrats\EnvoiWhatsApp;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Business (API Cloud de Meta) : envoi d'un modèle de message validé.
 * Réglages : WHATSAPP_JETON, WHATSAPP_NUMERO_ID, WHATSAPP_LANGUE (fr par défaut).
 */
class WhatsAppMeta implements EnvoiWhatsApp
{
    public function envoyer(string $numeroE164, string $modele, array $parametres, string $texte): void
    {
        $config = config('services.whatsapp');

        if (blank($config['jeton'] ?? null) || blank($config['numero_id'] ?? null)) {
            throw new RuntimeException('WhatsApp n\'est pas configuré (jeton ou identifiant du numéro manquant).');
        }

        $composants = $parametres === [] ? [] : [[
            'type' => 'body',
            'parameters' => array_map(fn (string $valeur) => ['type' => 'text', 'text' => $valeur], $parametres),
        ]];

        /* Le modèle « code de vérification » de Meta attend aussi le code dans le bouton « copier le code ». */
        if ($modele === config('services.whatsapp.modele_code') && $parametres !== []) {
            $composants[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $parametres[0]]]];
        }

        Http::withToken($config['jeton'])
            ->timeout(10)
            ->post('https://graph.facebook.com/'.($config['version'] ?? 'v21.0').'/'.$config['numero_id'].'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($numeroE164, '+'),
                'type' => 'template',
                'template' => ['name' => $modele, 'language' => ['code' => $config['langue'] ?? 'fr'], 'components' => $composants],
            ])
            ->throw();
    }
}

<?php

namespace App\Notifications\Canaux\Contrats;

/**
 * Envoi d'un message WhatsApp. Hors conversation ouverte, WhatsApp Business n'accepte que des modèles validés :
 * chaque message référence un nom de modèle et ses paramètres ; le texte sert au journal et aux tests.
 */
interface EnvoiWhatsApp
{
    /**
     * @param  array<int, string>  $parametres
     */
    public function envoyer(string $numeroE164, string $modele, array $parametres, string $texte): void;
}

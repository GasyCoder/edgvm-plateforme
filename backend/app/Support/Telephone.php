<?php

namespace App\Support;

/**
 * Numéros de téléphone au format international E.164 (+261...), utilisés par les SMS et WhatsApp.
 */
class Telephone
{
    /**
     * « 034 12 345 67 », « 261341234567 » ou « +261 34 12 345 67 » deviennent « +261341234567 ».
     * Renvoie null pour une valeur vide ; la valeur nettoyée telle quelle si elle n'est pas reconnue.
     */
    public static function normaliser(?string $numero): ?string
    {
        if (blank($numero)) {
            return null;
        }

        $chiffres = preg_replace('/\D+/', '', $numero);

        if (str_starts_with(trim($numero), '+')) {
            return '+'.$chiffres;
        }

        if (str_starts_with($chiffres, '00')) {
            return '+'.substr($chiffres, 2);
        }

        if (str_starts_with($chiffres, '261')) {
            return '+'.$chiffres;
        }

        if (str_starts_with($chiffres, '0') && strlen($chiffres) === 10) {
            return '+261'.substr($chiffres, 1);
        }

        return $chiffres;
    }

    public const FORMAT = '/^\+[1-9]\d{7,14}$/';
}

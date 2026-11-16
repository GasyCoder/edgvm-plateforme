<?php

namespace App\Support;

/**
 * Codes à usage unique basés sur le temps (TOTP, RFC 6238) : 6 chiffres, toutes les 30 secondes, HMAC-SHA1,
 * compatibles avec Google Authenticator, Microsoft Authenticator, FreeOTP...
 */
class Totp
{
    public const PERIODE = 30;

    public const CHIFFRES = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Secret de 160 bits, en base 32 (ce que l'appli enregistre) */
    public static function nouveauSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function code(string $secret, ?int $pas = null): string
    {
        $pas ??= self::pasActuel();
        $hmac = hash_hmac('sha1', pack('J', $pas), self::decoderBase32($secret), true);
        $decalage = ord($hmac[19]) & 0x0F;
        $nombre = ((ord($hmac[$decalage]) & 0x7F) << 24) | (ord($hmac[$decalage + 1]) << 16) | (ord($hmac[$decalage + 2]) << 8) | ord($hmac[$decalage + 3]);

        return str_pad((string) ($nombre % 10 ** self::CHIFFRES), self::CHIFFRES, '0', STR_PAD_LEFT);
    }

    /**
     * Pas de temps du code s'il est valable (une période de tolérance avant et après, pour une horloge un peu décalée),
     * sinon null. Un pas déjà utilisé est refusé : le même code ne sert pas deux fois.
     */
    public static function verifier(string $secret, string $code, ?int $dernierPasUtilise = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::CHIFFRES.'}$/', $code)) {
            return null;
        }

        $actuel = self::pasActuel();
        foreach ([$actuel - 1, $actuel, $actuel + 1] as $pas) {
            if (($dernierPasUtilise === null || $pas > $dernierPasUtilise) && hash_equals(self::code($secret, $pas), $code)) {
                return $pas;
            }
        }

        return null;
    }

    /** Adresse lue par l'appli quand on scanne le QR code */
    public static function uri(string $secret, string $compte, string $emetteur): string
    {
        $libelle = rawurlencode($emetteur).':'.rawurlencode($compte);

        return 'otpauth://totp/'.$libelle.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $emetteur,
            'algorithm' => 'SHA1',
            'digits' => self::CHIFFRES,
            'period' => self::PERIODE,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function pasActuel(): int
    {
        return intdiv(now()->getTimestamp(), self::PERIODE);
    }

    private static function base32(string $octets): string
    {
        $bits = '';
        foreach (str_split($octets) as $octet) {
            $bits .= str_pad(decbin(ord($octet)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $groupe) => self::ALPHABET[bindec(str_pad($groupe, 5, '0'))], str_split($bits, 5)));
    }

    private static function decoderBase32(string $texte): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($texte, '='))) as $caractere) {
            $position = strpos(self::ALPHABET, $caractere);
            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        return implode('', array_map(fn (string $octet) => chr(bindec($octet)), array_filter(str_split($bits, 8), fn (string $octet) => strlen($octet) === 8)));
    }
}

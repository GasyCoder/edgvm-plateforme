<?php

namespace App\Support;

use App\Models\User;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Double authentification d'un compte : code de l'appli (TOTP) ou code de secours à usage unique.
 */
class DeuxFacteurs
{
    public const NOMBRE_CODES_SECOURS = 8;

    /**
     * Vérifie un code de l'appli ou un code de secours ; un code de secours utilisé est retiré.
     */
    public static function verifier(User $user, string $saisie): bool
    {
        $saisie = trim($saisie);
        $secret = $user->deux_facteurs_secret;

        if ($secret && ($pas = Totp::verifier($secret, $saisie, $user->deux_facteurs_dernier_pas)) !== null) {
            $user->forceFill(['deux_facteurs_dernier_pas' => $pas])->save();

            return true;
        }

        $empreinte = self::empreinte($saisie);
        $codes = $user->deux_facteurs_secours ?? [];
        if (in_array($empreinte, $codes, true)) {
            $user->forceFill(['deux_facteurs_secours' => array_values(array_diff($codes, [$empreinte]))])->save();

            return true;
        }

        return false;
    }

    /**
     * Nouveaux codes de secours (affichés une fois) ; seules leurs empreintes sont gardées.
     *
     * @return array<int, string>
     */
    public static function nouveauxCodesSecours(User $user): array
    {
        $codes = collect(range(1, self::NOMBRE_CODES_SECOURS))
            ->map(fn () => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))
            ->map(fn (string $code) => strtr($code, ['O' => 'P', '0' => '7', 'I' => 'K', '1' => '8', 'L' => 'M']))
            ->all();
        $user->forceFill(['deux_facteurs_secours' => array_map(self::empreinte(...), $codes)])->save();

        return $codes;
    }

    public static function codesSecoursRestants(User $user): int
    {
        return count($user->deux_facteurs_secours ?? []);
    }

    public static function desactiver(User $user): void
    {
        $user->forceFill(['deux_facteurs_secret' => null, 'deux_facteurs_active_le' => null, 'deux_facteurs_secours' => null, 'deux_facteurs_dernier_pas' => null])->save();
    }

    /** QR code de l'adresse « otpauth » (image PNG en data URI) */
    public static function qrCode(string $uri): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'quietzoneSize' => 2,
        ])))->render($uri);
    }

    private static function empreinte(string $code): string
    {
        return hash_hmac('sha256', Str::upper(str_replace([' ', '-'], '', $code)), (string) config('app.key'));
    }
}

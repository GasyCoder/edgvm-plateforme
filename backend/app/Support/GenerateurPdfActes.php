<?php

namespace App\Support;

use App\Models\Acte;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;

/**
 * PDF A4 d'un ou plusieurs actes (une page par acte), avec en-tête officiel, signature et QR code de vérification.
 */
class GenerateurPdfActes
{
    /**
     * @param  Collection<int, Acte>  $actes
     */
    public static function generer(Collection $actes, bool $apercu = false): string
    {
        return Pdf::loadHTML(self::html($actes, $apercu))
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'Times-Roman'])
            ->output();
    }

    /**
     * HTML des actes, tel que dompdf le met en page.
     *
     * @param  Collection<int, Acte>  $actes
     */
    public static function html(Collection $actes, bool $apercu = false): string
    {
        @set_time_limit(300);
        $images = [];

        $pages = $actes->map(function (Acte $acte) use (&$images, $apercu): array {
            $signataire = $acte->signataire;
            $image = function (?string $chemin, string $cle) use (&$images): ?string {
                return $images[$cle.'|'.$chemin] ??= ReglagesActes::dataUri($chemin, $cle);
            };

            return [
                'acte' => $acte,
                'logo_gauche' => $image($signataire['logo_gauche'] ?? null, 'logo_gauche'),
                'logo_droite' => $image($signataire['logo_droite'] ?? null, 'logo_droite'),
                'signature' => ($signataire['signature'] ?? null) ? $image($signataire['signature'], 'signature') : null,
                'qr' => $apercu ? null : self::qr($acte->urlVerification()),
                'date' => GabaritActe::dateLongue($acte->delivre_le),
                'compact' => self::estLong($acte->contenu),
            ];
        });

        return view('actes.document', ['pages' => $pages, 'apercu' => $apercu])->render();
    }

    /**
     * Un texte long (fiche d'évaluation...) est resserré pour que la signature reste sur la même page.
     */
    private static function estLong(string $contenu): bool
    {
        $blocs = substr_count($contenu, '<p') + substr_count($contenu, '<li') + substr_count($contenu, '<br');

        return $blocs >= 11 || mb_strlen(strip_tags($contenu)) > 1700;
    }

    private static function qr(string $adresse): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 4,
            'quietzoneSize' => 1,
        ])))->render($adresse);
    }
}

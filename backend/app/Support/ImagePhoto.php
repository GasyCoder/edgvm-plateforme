<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Prépare une photo envoyée (téléphone, appareil, scan) : redressée selon l'EXIF, recadrée au format voulu
 * en gardant le haut de l'image (le visage), réduite, en JPEG.
 */
class ImagePhoto
{
    public static function jpeg(UploadedFile $fichier, int $largeur, int $hauteur): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        $source = self::redresser($source, $fichier->getRealPath());
        [$l, $h] = [imagesx($source), imagesy($source)];

        $ratio = $largeur / $hauteur;
        $coupeLargeur = min($l, (int) round($h * $ratio));
        $coupeHauteur = min($h, (int) round($l / $ratio));
        $x = intdiv($l - $coupeLargeur, 2);
        $y = (int) round(($h - $coupeHauteur) * 0.25);

        $photo = imagecreatetruecolor($largeur, $hauteur);
        imagefill($photo, 0, 0, (int) imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled($photo, $source, 0, 0, $x, $y, $largeur, $hauteur, $coupeLargeur, $coupeHauteur);

        ob_start();
        imagejpeg($photo, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Les photos de téléphone portent leur orientation dans les données EXIF.
     */
    private static function redresser(\GdImage $image, string $chemin): \GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($chemin)['Orientation'] ?? 1) : 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }
}

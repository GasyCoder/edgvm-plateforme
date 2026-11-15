<?php

namespace App\Support;

use App\Models\Doctorant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photo d'identité d'un doctorant : recadrée au format portrait 3:4, réduite et enregistrée en JPEG sur le disque privé.
 */
class PhotoDoctorant
{
    public const DISQUE = 'local';

    private const LARGEUR = 450;

    private const HAUTEUR = 600;

    public static function enregistrer(Doctorant $doctorant, UploadedFile $fichier): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        $source = self::redresser($source, $fichier->getRealPath());
        [$largeur, $hauteur] = [imagesx($source), imagesy($source)];

        /* Recadrage centré au format 3:4, en gardant le haut de l'image (le visage) */
        $ratio = self::LARGEUR / self::HAUTEUR;
        $coupeLargeur = min($largeur, (int) round($hauteur * $ratio));
        $coupeHauteur = min($hauteur, (int) round($largeur / $ratio));
        $x = intdiv($largeur - $coupeLargeur, 2);
        $y = (int) round(($hauteur - $coupeHauteur) * 0.25);

        $photo = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagefill($photo, 0, 0, (int) imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled($photo, $source, 0, 0, $x, $y, self::LARGEUR, self::HAUTEUR, $coupeLargeur, $coupeHauteur);

        ob_start();
        imagejpeg($photo, null, 85);
        $contenu = (string) ob_get_clean();

        $chemin = 'photos/'.$doctorant->uuid.'-'.Str::random(8).'.jpg';
        Storage::disk(self::DISQUE)->put($chemin, $contenu);

        if ($doctorant->photo_chemin) {
            Storage::disk(self::DISQUE)->delete($doctorant->photo_chemin);
        }

        $doctorant->forceFill(['photo_chemin' => $chemin])->save();

        return $chemin;
    }

    public static function supprimer(Doctorant $doctorant): void
    {
        if ($doctorant->photo_chemin) {
            Storage::disk(self::DISQUE)->delete($doctorant->photo_chemin);
        }

        $doctorant->forceFill(['photo_chemin' => null])->save();
    }

    public static function dataUri(Doctorant $doctorant): ?string
    {
        if (! $doctorant->photo_chemin || ! Storage::disk(self::DISQUE)->exists($doctorant->photo_chemin)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk(self::DISQUE)->get($doctorant->photo_chemin));
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

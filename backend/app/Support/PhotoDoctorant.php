<?php

namespace App\Support;

use App\Models\Doctorant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $contenu = ImagePhoto::jpeg($fichier, self::LARGEUR, self::HAUTEUR);

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
}

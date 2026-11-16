<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo de profil d'un compte (avatar carré 400 x 400, JPEG, disque privé).
 * Pour un doctorant sans photo de profil, la photo de son dossier sert d'avatar.
 */
class PhotoUtilisateur
{
    public const DISQUE = 'local';

    private const COTE = 400;

    public static function enregistrer(User $user, UploadedFile $fichier): void
    {
        $contenu = ImagePhoto::jpeg($fichier, self::COTE, self::COTE);
        $chemin = 'avatars/'.$user->id.'-'.Str::random(8).'.jpg';
        Storage::disk(self::DISQUE)->put($chemin, $contenu);

        if ($ancien = $user->getAttributes()['photo_chemin'] ?? null) {
            Storage::disk(self::DISQUE)->delete($ancien);
        }

        $user->forceFill(['photo_chemin' => $chemin])->save();
    }

    public static function supprimer(User $user): void
    {
        if ($ancien = $user->getAttributes()['photo_chemin'] ?? null) {
            Storage::disk(self::DISQUE)->delete($ancien);
        }

        $user->forceFill(['photo_chemin' => null])->save();
    }

    /**
     * Adresse de l'avatar : photo de profil, sinon photo du dossier doctorant (si la relation est chargée), sinon rien.
     */
    public static function url(User $user): ?string
    {
        /* Un modèle tout juste créé n'a pas encore la colonne : on ne la lit que si elle est chargée. */
        $chemin = $user->getAttributes()['photo_chemin'] ?? null;
        if ($chemin) {
            return '/telechargements/utilisateurs/'.$user->id.'/photo?v='.substr(md5($chemin), 0, 8);
        }

        $doctorant = $user->relationLoaded('doctorant') ? $user->doctorant : null;
        $photoDoctorant = $doctorant?->getAttributes()['photo_chemin'] ?? null;

        return $photoDoctorant
            ? '/telechargements/doctorants/'.$doctorant->uuid.'/photo?v='.substr(md5($photoDoctorant), 0, 8)
            : null;
    }
}

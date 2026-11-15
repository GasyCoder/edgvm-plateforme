<?php

namespace App\Support;

use App\Models\Reglage;
use Illuminate\Support\Facades\Storage;

/**
 * Signataire, ville et images (logos, signature avec cachet) des actes, modifiables dans l'interface.
 * Les images remplacées ne sont pas effacées : les actes déjà délivrés gardent celles de leur date.
 */
class ReglagesActes
{
    public const DISQUE = 'local';

    public const TEXTES = [
        'signataire_nom' => 'ASSOUMACOU Elia Béatrice',
        'signataire_fonction' => 'Le Directeur,',
        'signataire_titre' => 'Professeure Titulaire',
        'ville' => 'Mahajanga',
    ];

    public const IMAGES = ['logo_gauche', 'logo_droite', 'signature'];

    /** Le cachet porte déjà le nom et le titre du signataire : ils ne sont pas imprimés, sauf demande. */
    public const AFFICHER_NOM = 'afficher_nom_signataire';

    private const IMAGES_PAR_DEFAUT = [
        'logo_gauche' => 'images/actes/logo-umg.png',
        'logo_droite' => 'images/actes/logo-edgvm.jpg',
    ];

    public static function texte(string $cle): string
    {
        return (string) Reglage::valeur('actes.'.$cle, self::TEXTES[$cle] ?? '');
    }

    public static function afficherNom(): bool
    {
        return Reglage::valeur('actes.'.self::AFFICHER_NOM, '0') === '1';
    }

    public static function image(string $cle): ?string
    {
        return Reglage::valeur('actes.'.$cle);
    }

    /**
     * Réglages figés dans un acte au moment de sa délivrance.
     *
     * @return array{nom: string, fonction: string, titre: string, afficher_nom: bool, ville: string, signature: ?string, logo_gauche: ?string, logo_droite: ?string}
     */
    public static function instantane(bool $avecSignature): array
    {
        return [
            'nom' => self::texte('signataire_nom'),
            'fonction' => self::texte('signataire_fonction'),
            'titre' => self::texte('signataire_titre'),
            'afficher_nom' => self::afficherNom(),
            'ville' => self::texte('ville'),
            'signature' => $avecSignature ? self::image('signature') : null,
            'logo_gauche' => self::image('logo_gauche'),
            'logo_droite' => self::image('logo_droite'),
        ];
    }

    /**
     * Image en « data URI » pour le PDF : celle déposée dans l'interface, sinon l'image par défaut.
     */
    public static function dataUri(?string $chemin, string $cle): ?string
    {
        if ($chemin && Storage::disk(self::DISQUE)->exists($chemin)) {
            $contenu = Storage::disk(self::DISQUE)->get($chemin);
        } elseif (isset(self::IMAGES_PAR_DEFAUT[$cle])) {
            $contenu = file_get_contents(resource_path(self::IMAGES_PAR_DEFAUT[$cle]));
        } else {
            return null;
        }

        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu) ?: 'image/png';

        return 'data:'.$type.';base64,'.base64_encode($contenu);
    }
}

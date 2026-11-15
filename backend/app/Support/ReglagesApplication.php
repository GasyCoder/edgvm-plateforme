<?php

namespace App\Support;

use App\Models\Reglage;

/**
 * Réglages généraux de la plateforme, modifiables dans Paramètres > Application :
 * nom, École (description, ministère, arrêté d'habilitation), directeur, indexation, maintenance et apparence.
 * Les logos et le directeur sont partagés avec les actes, les reçus et la carte de doctorat (clés « actes.* »).
 */
class ReglagesApplication
{
    public const TEXTES = [
        'app.nom' => 'EDGVM',
        'app.sous_titre' => "Plateforme de l'École doctorale",
        'ecole.nom' => 'École Doctorale Génie du Vivant et Modélisation',
        'ecole.universite' => 'Université de Mahajanga',
        'ecole.ministere' => "MINISTERE DE L'ENSEIGNEMENT SUPERIEUR ET DE LA RECHERCHE SCIENTIFIQUE",
        'ecole.arrete' => "Arrêté d'habilitation N° 12259-2022-MESupRES du 29 Avril 2022.",
        'ecole.description' => '',
        'maintenance.message' => 'La plateforme est en maintenance. Elle sera de nouveau disponible très bientôt.',
    ];

    /** Directeur : mêmes clés que les actes, pour qu'un seul réglage serve partout. */
    public const DIRECTEUR = [
        'signataire_nom' => 'ASSOUMACOU Elia Béatrice',
        'signataire_grade' => 'Professeure',
        'signataire_titre' => 'Professeure Titulaire',
    ];

    public const THEMES = ['systeme', 'clair', 'sombre'];

    public const COULEURS = ['vert', 'bleu', 'ardoise', 'bordeaux'];

    public static function texte(string $cle): string
    {
        return (string) Reglage::valeur($cle, self::TEXTES[$cle] ?? '');
    }

    public static function directeur(string $cle): string
    {
        return (string) Reglage::valeur('actes.'.$cle, self::DIRECTEUR[$cle] ?? '');
    }

    /** « Professeure ASSOUMACOU Elia Béatrice » */
    public static function directeurAvecGrade(): string
    {
        return trim(self::directeur('signataire_grade').' '.self::directeur('signataire_nom'));
    }

    public static function indexationAutorisee(): bool
    {
        return Reglage::valeur('app.indexation', '0') === '1';
    }

    public static function maintenanceActive(): bool
    {
        return Reglage::valeur('maintenance.active', '0') === '1';
    }

    public static function theme(): string
    {
        $theme = (string) Reglage::valeur('apparence.theme', 'clair');

        return in_array($theme, self::THEMES, true) ? $theme : 'clair';
    }

    public static function couleur(): string
    {
        $couleur = (string) Reglage::valeur('apparence.couleur', 'vert');

        return in_array($couleur, self::COULEURS, true) ? $couleur : 'vert';
    }

    /**
     * Nom de la plateforme dans les courriels (en-tête et pied de page Laravel lisent « app.name »).
     */
    public static function nomPourCourriels(): string
    {
        $nom = self::texte('app.nom');
        config(['app.name' => $nom]);

        return $nom;
    }

    /**
     * Ce que l'interface lit avant même la connexion (page de connexion, maintenance, apparence).
     *
     * @return array<string, mixed>
     */
    public static function public(): array
    {
        $version = substr(md5((string) ReglagesActes::image('logo_gauche').'|'.ReglagesActes::image('logo_droite')), 0, 8);

        return [
            'nom' => self::texte('app.nom'),
            'sous_titre' => self::texte('app.sous_titre'),
            'ecole' => self::texte('ecole.nom'),
            'universite' => self::texte('ecole.universite'),
            'logos' => [
                'edgvm' => '/marque/logo_droite?v='.$version,
                'universite' => '/marque/logo_gauche?v='.$version,
            ],
            'maintenance' => ['active' => self::maintenanceActive(), 'message' => self::texte('maintenance.message')],
            'apparence' => ['theme' => self::theme(), 'couleur' => self::couleur()],
        ];
    }
}

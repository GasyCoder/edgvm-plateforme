<?php

namespace Database\Seeders;

use App\Models\EquipeAccueil;
use Illuminate\Database\Seeder;

/**
 * Les huit équipes d'accueil de l'EDGVM, avec les mêmes sigles que le site vitrine.
 */
class EquipesAccueilSeeder extends Seeder
{
    public const EQUIPES = [
        'EAD 1' => "Biotechnologies analytiques et impacts physiopathologiques des déchets, de l'environnement et de l'eau",
        'EAD 2' => 'Valorisation pharmacologique et cosmétique de la biodiversité végétale et des ressources marines',
        'EAD 3' => "Conception et développement de modèles biologiques in vitro et in vivo pour découvrir des médicaments d'origine naturelle",
        'EAD 4' => 'Géosciences et Développement Durable',
        'EAD 5' => 'Physiologie, Hygiène et Alimentation Animale',
        'EAD 6' => 'Aliments, Nutrition et Sécurité alimentaire',
        'EAD 7' => "Sciences des Sociétés et Sciences de l'homme (3SH)",
        'EAD 8' => 'Science de la Structure de la Société et de la Matière',
    ];

    public function run(): void
    {
        foreach (self::EQUIPES as $sigle => $nom) {
            EquipeAccueil::query()->firstOrCreate(['sigle' => $sigle], ['nom' => $nom]);
        }
    }
}

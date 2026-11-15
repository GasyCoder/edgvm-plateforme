<?php

namespace Database\Seeders;

use App\Models\EquipeAccueil;
use Illuminate\Database\Seeder;

/**
 * Programmes doctoraux de chaque équipe d'accueil, repris du site vitrine de l'EDGVM.
 * Crée seulement ceux qui manquent : un programme modifié dans l'interface n'est pas écrasé.
 */
class ProgrammesDoctorauxSeeder extends Seeder
{
    public const PROGRAMMES = [
        'EAD 1' => [
            'Biotechnologies analytiques des eaux et des déchets',
            "Étude d'impact environnemental et modèle de gestion des déchets solides et liquides",
        ],
        'EAD 2' => [
            'Plantes médicinales et organismes marins',
            'Recherche et développement de molécules actives',
            'Valorisation des ressources marines',
        ],
        'EAD 3' => [
            'Allopathie',
            "Diagnostic et typage (phénotypage et génotypage) des populations d'hémoparasites",
            'Phytothérapie',
            'Santé et Bien-être',
        ],
        'EAD 4' => [
            'Exploitation, inventaire, conservation et valorisation des géo-ressources à Madagascar',
            'Paléontologie et géologie',
        ],
        'EAD 5' => [
            'Alimentation et Nutrition animales',
            'Études écotoxicologique, microbiologique et physiopathologique',
            'Physiologie et pathologie animales',
        ],
        'EAD 6' => [
            'Alimentation et Nutrition humaines',
            'Sécurité alimentaire',
        ],
        'EAD 7' => [
            "Sciences des Sociétés et Sciences de l'homme",
        ],
        'EAD 8' => [
            'Science de la Structure, de la Société et de la Matière',
        ],
    ];

    public function run(): void
    {
        foreach (self::PROGRAMMES as $sigle => $programmes) {
            $equipe = EquipeAccueil::query()->where('sigle', $sigle)->first();

            foreach ($equipe ? $programmes : [] as $ordre => $nom) {
                $equipe->programmes()->firstOrCreate(['nom' => $nom], ['ordre' => $ordre + 1]);
            }
        }
    }
}

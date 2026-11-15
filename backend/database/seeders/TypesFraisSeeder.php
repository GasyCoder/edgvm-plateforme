<?php

namespace Database\Seeders;

use App\Models\TypeFrais;
use Illuminate\Database\Seeder;

/**
 * Catalogue de départ. Les droits d'inscription sont créés par la migration ; les autres frais sont des exemples
 * à compléter dans Paramètres (montant à 0 tant que l'École ne l'a pas fixé). Un frais modifié n'est jamais écrasé.
 */
class TypesFraisSeeder extends Seeder
{
    public function run(): void
    {
        $frais = [
            ['code' => 'droits-inscription', 'libelle' => "Droits d'inscription", 'description' => 'Appliqués à chaque inscription annuelle, selon le niveau.', 'montant' => 1500000, 'montants_niveaux' => ['D1' => 1500000, 'D2' => 1500000, 'D3' => 1500000, 'derogation' => 1500000], 'obligatoire' => true, 'echeance' => 'fin_annee', 'ordre' => 1],
            ['code' => 'tee-shirt', 'libelle' => 'Tee-shirt', 'description' => "Tee-shirt de l'École doctorale.", 'montant' => 0, 'ordre' => 2],
            ['code' => 'cotisation-sociale', 'libelle' => 'Cotisation sociale', 'description' => 'Caisse sociale des doctorants.', 'montant' => 0, 'ordre' => 3],
            ['code' => 'frais-communication', 'libelle' => 'Frais de communication', 'description' => 'Communication et diffusion des travaux.', 'montant' => 0, 'ordre' => 4],
        ];

        foreach ($frais as $type) {
            TypeFrais::query()->firstOrCreate(['code' => $type['code']], ['delai_jours' => 30, 'actif' => true, 'obligatoire' => false, ...$type]);
        }
    }
}

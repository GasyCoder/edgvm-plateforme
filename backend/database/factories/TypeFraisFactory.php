<?php

namespace Database\Factories;

use App\Models\TypeFrais;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TypeFrais>
 */
class TypeFraisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $libelle = ucfirst(fake()->unique()->words(2, true));

        return [
            'code' => Str::slug($libelle),
            'libelle' => $libelle,
            'montant' => 25_000,
            'montants_niveaux' => null,
            'obligatoire' => false,
            'delai_jours' => 30,
            'actif' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\EquipeAccueil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipeAccueil>
 */
class EquipeAccueilFactory extends Factory
{
    /**
     * Sigle au hasard au-delà de EAD 999 : jamais l'un de ceux que les tests écrivent (EAD 1, EAD 2...).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sigle' => 'EAD '.fake()->unique()->numberBetween(1000, 99999),
            'nom' => ucfirst(fake()->words(5, true)),
            'description' => null,
        ];
    }
}

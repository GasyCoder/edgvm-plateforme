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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sigle' => 'EAD '.fake()->unique()->numberBetween(1, 999),
            'nom' => ucfirst(fake()->words(5, true)),
            'description' => null,
        ];
    }
}

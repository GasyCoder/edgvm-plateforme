<?php

namespace Database\Factories;

use App\Models\EquipeAccueil;
use App\Models\ProgrammeDoctoral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeDoctoral>
 */
class ProgrammeDoctoralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipe_accueil_id' => EquipeAccueil::factory(),
            'nom' => ucfirst(fake()->unique()->words(4, true)),
            'description' => null,
            'ordre' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FraisDoctorant>
 */
class FraisDoctorantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctorant_id' => Doctorant::factory(),
            'libelle' => "Droits d'inscription",
            'montant' => 1_500_000,
            'montant_paye' => 0,
            'echeance' => now()->addDays(20)->toDateString(),
        ];
    }

    public function echu(int $joursDeRetard = 10): static
    {
        return $this->state(fn (array $attributes) => ['echeance' => now()->subDays($joursDeRetard)->toDateString()]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\ModePaiement;
use App\Models\Doctorant;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_recu' => 'REC-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'doctorant_id' => Doctorant::factory(),
            'date_paiement' => now()->toDateString(),
            'montant' => 500_000,
            'mode' => ModePaiement::Especes,
        ];
    }
}

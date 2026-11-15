<?php

namespace Database\Factories;

use App\Enums\StatutSeance;
use App\Enums\TypeSeance;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TypeSeance::Seminaire,
            'titre' => 'Séminaire doctoral',
            'description' => null,
            'lieu' => 'Amphithéâtre A',
            'date' => now()->addWeeks(2)->toDateString(),
            'heure_debut' => '08:00',
            'duree_passage' => 30,
            'pause' => 0,
            'exige_paiement_solde' => false,
            'statut' => StatutSeance::Brouillon,
        ];
    }

    public function exigePaiement(): static
    {
        return $this->state(['exige_paiement_solde' => true]);
    }
}

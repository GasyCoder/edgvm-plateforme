<?php

namespace Database\Factories;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscription>
 */
class InscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctorant_id' => Doctorant::factory(),
            'annee_universitaire_id' => AnneeUniversitaire::factory(),
            'niveau' => NiveauDoctorat::D1,
            'type' => TypeInscription::Premiere,
            'est_derogation' => false,
            'statut' => StatutInscription::Validee,
            'date_inscription' => now()->toDateString(),
        ];
    }

    public function niveau(NiveauDoctorat $niveau): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => $niveau,
            'type' => $niveau === NiveauDoctorat::D1 ? TypeInscription::Premiere : TypeInscription::Reinscription,
        ]);
    }

    public function derogation(?string $decision = 'Décision n° 12/2026/EDGVM'): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => NiveauDoctorat::D3,
            'type' => TypeInscription::Reinscription,
            'est_derogation' => true,
            'motif_derogation' => 'Retard dans les travaux de terrain',
            'decision_derogation' => $decision,
            'date_decision_derogation' => $decision ? now()->toDateString() : null,
        ]);
    }
}

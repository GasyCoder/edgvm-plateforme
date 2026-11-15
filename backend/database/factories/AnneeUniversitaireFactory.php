<?php

namespace Database\Factories;

use App\Models\AnneeUniversitaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeUniversitaire>
 */
class AnneeUniversitaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /* Années lointaines : jamais celles que les tests créent par rentree() ou anneeCourante() (2020 à 2030). */
        $debut = fake()->unique()->numberBetween(2040, 2099);

        return [
            'libelle' => $debut.'-'.($debut + 1),
            'debut' => "{$debut}-10-01",
            'fin' => ($debut + 1).'-09-30',
            'est_courante' => false,
        ];
    }

    /**
     * Année donnée par son année de rentrée, par exemple 2026 pour « 2026-2027 ».
     */
    public function rentree(int $annee): static
    {
        return $this->state(fn (array $attributes) => [
            'libelle' => $annee.'-'.($annee + 1),
            'debut' => "{$annee}-10-01",
            'fin' => ($annee + 1).'-09-30',
        ]);
    }

    public function courante(): static
    {
        return $this->state(fn (array $attributes) => ['est_courante' => true]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\FonctionMembre;
use App\Models\EquipeAccueil;
use App\Models\MembreEquipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembreEquipe>
 */
class MembreEquipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipe_accueil_id' => EquipeAccueil::factory(),
            'nom' => mb_strtoupper(fake()->lastName()).' '.fake()->firstName(),
            'titre' => fake()->randomElement(['Pr', 'Dr', 'Professeur Titulaire', 'Maître de conférences']),
            'fonction' => FonctionMembre::Encadrant,
            'specialite' => null,
            'etablissement' => 'Université de Mahajanga',
            'email' => fake()->unique()->safeEmail(),
            'telephone' => null,
        ];
    }

    public function responsable(): static
    {
        return $this->state(['fonction' => FonctionMembre::Responsable]);
    }

    public function membre(): static
    {
        return $this->state(['fonction' => FonctionMembre::Membre]);
    }
}

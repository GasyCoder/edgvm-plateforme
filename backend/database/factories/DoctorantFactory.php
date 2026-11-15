<?php

namespace Database\Factories;

use App\Enums\StatutDoctorant;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctorant>
 */
class DoctorantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genre = fake()->randomElement(['M', 'F']);

        return [
            'user_id' => User::factory()->doctorant(),
            'matricule' => 'EDGVM-'.fake()->unique()->numerify('####-####'),
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName($genre === 'M' ? 'male' : 'female'),
            'genre' => $genre,
            'date_naissance' => fake()->dateTimeBetween('-45 years', '-25 years')->format('Y-m-d'),
            'lieu_naissance' => fake()->randomElement(['Mahajanga', 'Antananarivo', 'Toamasina', 'Fianarantsoa', 'Toliara', 'Antsiranana']),
            'nationalite' => 'Malagasy',
            'equipe_accueil_id' => EquipeAccueil::factory(),
            'sujet_these' => ucfirst(fake()->sentence(10)),
            'statut' => StatutDoctorant::Actif,
            'est_boursier' => false,
            'visible_annuaire' => false,
        ];
    }

    public function dansEquipe(EquipeAccueil $equipe): static
    {
        return $this->state(fn (array $attributes) => ['equipe_accueil_id' => $equipe->id]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\CategoriePiece;
use App\Models\Doctorant;
use App\Models\PieceJointe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceJointe>
 */
class PieceJointeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctorant_id' => Doctorant::factory(),
            'categorie' => CategoriePiece::Diplome,
            'titre' => 'Diplôme de master',
            'nom_original' => 'master.pdf',
            'chemin' => 'pieces/'.fake()->uuid().'.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 120_000,
        ];
    }

    public function categorie(CategoriePiece $categorie): static
    {
        return $this->state(fn (array $attributes) => ['categorie' => $categorie]);
    }
}

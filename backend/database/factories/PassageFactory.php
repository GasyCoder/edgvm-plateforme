<?php

namespace Database\Factories;

use App\Models\Doctorant;
use App\Models\Passage;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Passage>
 */
class PassageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debut = now()->addWeeks(2)->setTime(8, 0);

        return [
            'seance_id' => Seance::factory(),
            'doctorant_id' => Doctorant::factory(),
            'debut' => $debut,
            'fin' => $debut->copy()->addMinutes(30),
            'sujet' => null,
            'ordre' => 1,
        ];
    }

    public function entre(string $debut, string $fin): static
    {
        return $this->state(['debut' => $debut, 'fin' => $fin]);
    }
}

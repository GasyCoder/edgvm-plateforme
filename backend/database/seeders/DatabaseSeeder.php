<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([EquipesAccueilSeeder::class, ProgrammesDoctorauxSeeder::class, AnneesUniversitairesSeeder::class, ModelesActesSeeder::class, TypesFraisSeeder::class]);

        if (app()->environment('local')) {
            $this->call(DemonstrationSeeder::class);
        }
    }
}

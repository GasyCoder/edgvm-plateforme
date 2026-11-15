<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([EquipesAccueilSeeder::class, AnneesUniversitairesSeeder::class, ModelesActesSeeder::class]);

        if (app()->environment('local')) {
            $this->call(DemonstrationSeeder::class);
        }
    }
}

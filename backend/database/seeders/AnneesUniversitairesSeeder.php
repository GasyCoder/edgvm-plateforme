<?php

namespace Database\Seeders;

use App\Models\AnneeUniversitaire;
use Illuminate\Database\Seeder;

class AnneesUniversitairesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([2023, 2024, 2025, 2026] as $rentree) {
            AnneeUniversitaire::query()->firstOrCreate(
                ['libelle' => $rentree.'-'.($rentree + 1)],
                ['debut' => "{$rentree}-10-01", 'fin' => ($rentree + 1).'-09-30'],
            );
        }

        if (! AnneeUniversitaire::courante()) {
            AnneeUniversitaire::query()->where('libelle', '2026-2027')->first()?->definirCommeCourante();
        }
    }
}

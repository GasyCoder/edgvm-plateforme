<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les droits d'inscription deviennent un frais comme les autres : chaque montant saisi sur une inscription
     * est repris en frais « Droits d'inscription », puis la colonne est retirée.
     */
    public function up(): void
    {
        $maintenant = now();
        $typeId = DB::table('types_frais')->insertGetId([
            'code' => 'droits-inscription',
            'libelle' => "Droits d'inscription",
            'description' => 'Appliqués à chaque inscription annuelle, selon le niveau.',
            'montant' => 1500000,
            'montants_niveaux' => json_encode(['D1' => 1500000, 'D2' => 1500000, 'D3' => 1500000, 'derogation' => 1500000]),
            'obligatoire' => true,
            'delai_jours' => 30,
            'actif' => true,
            'ordre' => 1,
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ]);

        DB::table('inscriptions')->where('montant_du', '>', 0)->where('statut', '!=', 'annulee')->orderBy('id')->each(function (object $inscription) use ($typeId, $maintenant): void {
            $debut = $inscription->date_inscription ?? $inscription->created_at;
            DB::table('frais_doctorants')->insert([
                'doctorant_id' => $inscription->doctorant_id,
                'type_frais_id' => $typeId,
                'annee_universitaire_id' => $inscription->annee_universitaire_id,
                'inscription_id' => $inscription->id,
                'libelle' => "Droits d'inscription",
                'montant' => $inscription->montant_du,
                'montant_paye' => 0,
                'echeance' => $debut ? date('Y-m-d', strtotime($debut.' +30 days')) : null,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('montant_du');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('montant_du')->default(0);
        });
    }
};

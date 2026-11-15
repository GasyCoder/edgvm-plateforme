<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encadrement de la thèse (repris dans les actes : attestation, admission, fiche d'évaluation).
     */
    public function up(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->string('directeur_these')->nullable()->after('sujet_these');
            $table->string('co_directeur')->nullable()->after('directeur_these');
            $table->string('co_encadrant')->nullable()->after('co_directeur');
        });
    }

    public function down(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->dropColumn(['directeur_these', 'co_directeur', 'co_encadrant']);
        });
    }
};

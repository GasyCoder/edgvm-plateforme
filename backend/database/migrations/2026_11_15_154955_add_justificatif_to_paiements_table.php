<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Justificatif du paiement : photo ou scan du reçu de versement bancaire, stocké hors du dossier public.
     */
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('justificatif_chemin')->nullable()->after('observation');
            $table->string('justificatif_nom')->nullable()->after('justificatif_chemin');
            $table->string('justificatif_type', 120)->nullable()->after('justificatif_nom');
            $table->unsignedBigInteger('justificatif_taille')->nullable()->after('justificatif_type');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['justificatif_chemin', 'justificatif_nom', 'justificatif_type', 'justificatif_taille']);
        });
    }
};

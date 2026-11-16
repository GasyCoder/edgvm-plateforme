<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidats : date de candidature, dernière décision du jury, date d'admission.
     * Passages : décision du jury pour une présentation de projet de thèse, et date d'information du candidat.
     */
    public function up(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->date('candidature_le')->nullable()->after('statut');
            $table->string('decision_candidature', 20)->nullable()->after('candidature_le');
            $table->timestamp('admis_le')->nullable()->after('decision_candidature');
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->string('decision', 20)->nullable()->after('sujet');
            $table->text('decision_observation')->nullable()->after('decision');
            $table->timestamp('decision_notifiee_le')->nullable()->after('decision_observation');
        });
    }

    public function down(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->dropColumn(['candidature_le', 'decision_candidature', 'admis_le']);
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->dropColumn(['decision', 'decision_observation', 'decision_notifiee_le']);
        });
    }
};

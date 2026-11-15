<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Échéance d'un frais : fin de l'année universitaire ou délai en jours, avec un paiement possible en tranches.
     * Les droits d'inscription se paient sur toute l'année (en un ou plusieurs versements).
     */
    public function up(): void
    {
        Schema::table('types_frais', function (Blueprint $table) {
            $table->string('echeance', 20)->default('delai')->after('obligatoire');
            $table->unsignedTinyInteger('tranches')->default(1)->after('delai_jours');
        });

        Schema::table('frais_doctorants', function (Blueprint $table) {
            $table->unsignedTinyInteger('tranche')->nullable()->after('echeance');
            $table->unsignedTinyInteger('tranches')->nullable()->after('tranche');
        });

        DB::table('types_frais')->where('code', 'droits-inscription')->update(['echeance' => 'fin_annee']);
    }

    public function down(): void
    {
        Schema::table('types_frais', function (Blueprint $table) {
            $table->dropColumn(['echeance', 'tranches']);
        });

        Schema::table('frais_doctorants', function (Blueprint $table) {
            $table->dropColumn(['tranche', 'tranches']);
        });
    }
};

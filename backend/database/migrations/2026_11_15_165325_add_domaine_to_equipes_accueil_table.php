<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine scientifique de l'équipe (même champ que sur le site vitrine).
     */
    public function up(): void
    {
        Schema::table('equipes_accueil', function (Blueprint $table) {
            $table->string('domaine')->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('equipes_accueil', function (Blueprint $table) {
            $table->dropColumn('domaine');
        });
    }
};

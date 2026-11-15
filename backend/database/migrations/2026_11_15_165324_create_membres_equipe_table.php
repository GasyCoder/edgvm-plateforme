<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personnes d'une équipe d'accueil : responsable, encadrants (directeurs de thèse) et membres.
     * Indépendant des comptes : une personne n'a pas besoin d'accès à la plateforme pour y figurer.
     */
    public function up(): void
    {
        Schema::create('membres_equipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_accueil_id')->constrained('equipes_accueil')->cascadeOnDelete();
            $table->string('nom');
            $table->string('titre', 120)->nullable();
            $table->string('fonction', 20)->default('membre')->index();
            $table->string('specialite')->nullable();
            $table->string('etablissement')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres_equipe');
    }
};

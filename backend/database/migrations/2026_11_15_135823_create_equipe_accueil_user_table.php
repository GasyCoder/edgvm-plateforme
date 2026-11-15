<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membres des équipes d'accueil (comptes « membre_ead »).
     */
    public function up(): void
    {
        Schema::create('equipe_accueil_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_accueil_id')->constrained('equipes_accueil')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fonction', 20)->default('membre');
            $table->timestamps();

            $table->unique(['equipe_accueil_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipe_accueil_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modèles d'actes modifiables : titre, texte avec variables ({{nom_complet}}...) et champs à remplir à la délivrance.
     */
    public function up(): void
    {
        Schema::create('modeles_actes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('nom', 120);
            $table->string('titre', 190);
            $table->longText('corps');
            $table->json('champs')->nullable();
            $table->boolean('exige_inscription')->default(false);
            $table->boolean('inclure_signature')->default(true);
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_actes');
    }
};

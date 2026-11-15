<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue des frais (paramètres) : droits d'inscription, tee-shirt, cotisation sociale...
     * Montant unique, ou montant par niveau (D1, D2, D3, dérogation).
     */
    public function up(): void
    {
        Schema::create('types_frais', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('libelle', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('montant')->default(0);
            $table->json('montants_niveaux')->nullable();
            $table->boolean('obligatoire')->default(false);
            $table->unsignedSmallInteger('delai_jours')->default(30);
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_frais');
    }
};

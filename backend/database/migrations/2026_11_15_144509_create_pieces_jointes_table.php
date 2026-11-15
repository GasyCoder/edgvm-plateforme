<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièces jointes du dossier doctorant (diplômes, pièce d'identité, attestations...), stockées hors du dossier public.
     */
    public function up(): void
    {
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctorant_id')->constrained()->cascadeOnDelete();
            $table->string('categorie', 20)->index();
            $table->string('titre', 190);
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('type_mime', 120);
            $table->unsignedBigInteger('taille');
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};

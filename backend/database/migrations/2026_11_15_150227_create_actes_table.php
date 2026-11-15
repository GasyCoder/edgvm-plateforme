<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actes délivrés. Le texte est figé à la délivrance : modifier un modèle ne change pas les actes déjà remis.
     */
    public function up(): void
    {
        Schema::create('actes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->string('code_verification', 40)->unique();
            $table->foreignId('modele_acte_id')->constrained('modeles_actes')->restrictOnDelete();
            $table->foreignId('doctorant_id')->constrained()->restrictOnDelete();
            $table->foreignId('annee_universitaire_id')->nullable()->constrained('annees_universitaires')->restrictOnDelete();
            $table->foreignId('inscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('titre', 190);
            $table->longText('contenu');
            $table->json('signataire');
            $table->json('donnees')->nullable();
            $table->foreignId('delivre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivre_le');
            $table->timestamp('annule_le')->nullable();
            $table->string('motif_annulation', 500)->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doctorant_id', 'delivre_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actes');
    }
};

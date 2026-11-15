<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frais dus par un doctorant. « montant_paye » est recalculé à chaque paiement ou annulation.
     */
    public function up(): void
    {
        Schema::create('frais_doctorants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctorant_id')->constrained()->restrictOnDelete();
            $table->foreignId('type_frais_id')->nullable()->constrained('types_frais')->restrictOnDelete();
            $table->foreignId('annee_universitaire_id')->nullable()->constrained('annees_universitaires')->restrictOnDelete();
            $table->foreignId('inscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('libelle', 150);
            $table->unsignedBigInteger('montant');
            $table->unsignedBigInteger('montant_paye')->default(0);
            $table->date('echeance')->nullable()->index();
            $table->string('motif_ajustement', 300)->nullable();
            $table->timestamp('annule_le')->nullable();
            $table->string('motif_annulation', 300)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doctorant_id', 'annule_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais_doctorants');
    }
};

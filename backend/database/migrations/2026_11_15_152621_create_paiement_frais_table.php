<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Répartition d'un paiement entre les frais qu'il règle.
     */
    public function up(): void
    {
        Schema::create('paiement_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('frais_doctorant_id')->constrained('frais_doctorants')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['paiement_id', 'frais_doctorant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_frais');
    }
};

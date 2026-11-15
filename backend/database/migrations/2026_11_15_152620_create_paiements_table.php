<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versements saisis par la scolarité, avec un reçu numéroté. Un paiement ne se supprime pas : il s'annule.
     */
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 30)->unique();
            $table->foreignId('doctorant_id')->constrained()->restrictOnDelete();
            $table->date('date_paiement')->index();
            $table->unsignedBigInteger('montant');
            $table->string('mode', 20);
            $table->string('reference', 120)->nullable();
            $table->string('observation', 500)->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->string('motif_annulation', 300)->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};

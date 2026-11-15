<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctorant_id')->constrained()->restrictOnDelete();
            $table->foreignId('annee_universitaire_id')->constrained('annees_universitaires')->restrictOnDelete();
            $table->string('niveau', 2);
            $table->string('type', 20);
            $table->boolean('est_derogation')->default(false);
            $table->text('motif_derogation')->nullable();
            $table->string('decision_derogation')->nullable();
            $table->date('date_decision_derogation')->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->unsignedBigInteger('montant_du')->default(0);
            $table->date('date_inscription')->nullable();
            $table->timestamps();

            $table->unique(['doctorant_id', 'annee_universitaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Séances de communication : séminaire doctoral, état d'avancement... avec des passages programmés.
     */
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('lieu')->nullable();
            $table->date('date');
            $table->time('heure_debut');
            $table->unsignedSmallInteger('duree_passage')->default(30);
            $table->unsignedSmallInteger('pause')->default(0);
            $table->foreignId('annee_universitaire_id')->nullable()->constrained('annees_universitaires')->nullOnDelete();
            $table->boolean('exige_paiement_solde')->default(false);
            $table->string('statut', 20)->default('brouillon')->index();
            $table->timestamp('convocations_envoyees_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};

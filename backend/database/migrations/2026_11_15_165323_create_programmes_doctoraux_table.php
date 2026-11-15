<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programmes doctoraux (spécialités) d'une équipe d'accueil, comme sur le site vitrine.
     */
    public function up(): void
    {
        Schema::create('programmes_doctoraux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_accueil_id')->constrained('equipes_accueil')->cascadeOnDelete();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['equipe_accueil_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes_doctoraux');
    }
};

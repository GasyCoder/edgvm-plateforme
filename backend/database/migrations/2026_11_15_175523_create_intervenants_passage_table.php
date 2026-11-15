<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modérateurs et jury d'un passage, choisis parmi les personnes des équipes d'accueil.
     */
    public function up(): void
    {
        Schema::create('intervenants_passage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passage_id')->constrained('passages')->cascadeOnDelete();
            $table->foreignId('membre_equipe_id')->constrained('membres_equipe')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['passage_id', 'membre_equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervenants_passage');
    }
};

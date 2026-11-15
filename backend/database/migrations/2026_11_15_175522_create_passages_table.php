<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passage d'un doctorant dans une séance, sur un créneau précis.
     */
    public function up(): void
    {
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('doctorant_id')->constrained('doctorants')->restrictOnDelete();
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->string('sujet', 500)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('convoque_le')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'doctorant_id']);
            $table->index(['debut', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};

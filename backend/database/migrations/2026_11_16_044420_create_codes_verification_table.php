<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes à 4 chiffres de l'activation du compte (« Créer mon accès ») : jamais stockés en clair.
     */
    public function up(): void
    {
        Schema::create('codes_verification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('canal', 20);
            $table->string('code_hash');
            $table->timestamp('expire_le');
            $table->unsignedTinyInteger('essais')->default(0);
            $table->unsignedTinyInteger('envois')->default(1);
            $table->timestamp('dernier_envoi_le');
            $table->timestamp('verifie_le')->nullable();
            $table->string('jeton_hash')->nullable();
            $table->timestamp('utilise_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_verification');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctorants', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('matricule', 30)->unique();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->char('genre', 1)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite', 60)->nullable();
            $table->string('numero_piece', 40)->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('equipe_accueil_id')->nullable()->constrained('equipes_accueil')->restrictOnDelete();
            $table->string('sujet_these', 500)->nullable();
            $table->string('statut', 20)->default('actif')->index();
            $table->boolean('visible_annuaire')->default(false);
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['nom', 'prenoms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctorants');
    }
};

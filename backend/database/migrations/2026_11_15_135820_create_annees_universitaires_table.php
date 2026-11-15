<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_universitaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 9)->unique();
            $table->date('debut');
            $table->date('fin');
            $table->boolean('est_courante')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_universitaires');
    }
};

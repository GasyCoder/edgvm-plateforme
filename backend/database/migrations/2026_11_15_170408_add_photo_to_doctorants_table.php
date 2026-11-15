<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo d'identité du doctorant (disque privé), reprise sur la carte de doctorat.
     */
    public function up(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->string('photo_chemin')->nullable()->after('adresse');
        });
    }

    public function down(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->dropColumn('photo_chemin');
        });
    }
};

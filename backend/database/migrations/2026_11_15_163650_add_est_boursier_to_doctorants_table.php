<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Doctorant boursier ou non (filtre de la liste, export, import).
     */
    public function up(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->boolean('est_boursier')->default(false)->index()->after('co_encadrant');
        });
    }

    public function down(): void
    {
        Schema::table('doctorants', function (Blueprint $table) {
            $table->dropColumn('est_boursier');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empreinte de ce qui a été envoyé dans la convocation (horaire, lieu, sujet, modérateurs, jury) :
     * l'envoi « intelligent » reconvoque seulement les passages modifiés depuis.
     */
    public function up(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->string('convocation_empreinte', 64)->nullable()->after('convoque_le');
        });
    }

    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->dropColumn('convocation_empreinte');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Double authentification par appli (codes TOTP à 6 chiffres) : secret chiffré, date d'activation,
     * codes de secours (empreintes, chiffrées), dernier pas de temps utilisé (un code ne sert qu'une fois).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('deux_facteurs_secret')->nullable()->after('password');
            $table->timestamp('deux_facteurs_active_le')->nullable()->after('deux_facteurs_secret');
            $table->text('deux_facteurs_secours')->nullable()->after('deux_facteurs_active_le');
            $table->unsignedBigInteger('deux_facteurs_dernier_pas')->nullable()->after('deux_facteurs_secours');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deux_facteurs_secret', 'deux_facteurs_active_le', 'deux_facteurs_secours', 'deux_facteurs_dernier_pas']);
        });
    }
};

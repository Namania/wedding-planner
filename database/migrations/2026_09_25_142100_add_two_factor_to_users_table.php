<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Chiffré au niveau du modèle (cast "encrypted") : la colonne doit
            // donc être un text, le secret brut ne faisant que 32 caractères.
            $table->text('two_factor_secret')->nullable()->after('password');

            // Tant que cette colonne est nulle, le secret ci-dessus n'est qu'un
            // enrôlement en cours : il n'a pas encore été confirmé par un code.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};

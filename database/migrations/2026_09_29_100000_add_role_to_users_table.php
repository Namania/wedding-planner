<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La colonne arrive nullable le temps de renseigner les comptes
        // existants, puis devient obligatoire. Lui donner un défaut
        // transformerait tout oubli en création silencieuse d'administrateur.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->nullable()->index();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });

        DB::table('users')->whereNull('role')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'banned_at', 'last_seen_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('guests')
            ->whereNull('attendance')
            ->update(['attendance' => json_encode(['ceremony', 'cocktail'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Impossible de distinguer les invités qui avaient déjà exactement
        // cette valeur avant le backfill — pas de rollback fiable.
    }
};

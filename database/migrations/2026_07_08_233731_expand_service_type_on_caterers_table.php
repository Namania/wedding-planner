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
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE caterers DROP CONSTRAINT caterers_service_type_check');
        DB::statement("ALTER TABLE caterers ADD CONSTRAINT caterers_service_type_check CHECK (service_type IN ('cocktail', 'cocktail_dinatoire', 'seated', 'buffet', 'food_truck', 'brunch', 'live_cooking'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE caterers DROP CONSTRAINT caterers_service_type_check');
        DB::statement("ALTER TABLE caterers ADD CONSTRAINT caterers_service_type_check CHECK (service_type IN ('buffet', 'seated', 'cocktail'))");
    }
};

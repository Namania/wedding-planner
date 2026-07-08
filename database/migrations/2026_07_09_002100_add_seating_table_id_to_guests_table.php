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
        Schema::table('guests', function (Blueprint $table) {
            // nullOnDelete : si une table est supprimée, ses invités redeviennent
            // "non assignés" plutôt que d'être supprimés.
            $table->foreignId('seating_table_id')->nullable()->after('confirmed')
                ->constrained('seating_tables')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seating_table_id');
        });
    }
};

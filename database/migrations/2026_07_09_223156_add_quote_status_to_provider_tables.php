<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Les prestataires (+ tenues) partagent un même suivi de devis : pas de
    // contrainte enum en base, la liste des statuts est validée côté
    // application (constante QUOTE_STATUSES de chaque modèle).
    private const TABLES = ['venues', 'caterers', 'florists', 'animations', 'outfits'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('quote_status')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('quote_status');
            });
        }
    }
};

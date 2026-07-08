<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        // Le budget est un réglage unique (une seule ligne) : on l'initialise ici
        // pour que l'app ait toujours une valeur à afficher après la migration.
        DB::table('budgets')->insert([
            'total' => 20000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};

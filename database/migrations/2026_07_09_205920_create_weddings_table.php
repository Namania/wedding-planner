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
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->string('spouse_1_name');
            $table->string('spouse_2_name');
            $table->date('date');
            $table->timestamps();
        });

        // Le mariage est un réglage unique (une seule ligne) : on l'initialise ici
        // pour que l'app ait toujours une valeur à afficher après la migration.
        DB::table('weddings')->insert([
            'spouse_1_name' => 'Margaux',
            'spouse_2_name' => 'Mael',
            'date' => '2028-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};

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
        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('caterer_id')->nullable()->constrained('caterers')->nullOnDelete();
            $table->foreignId('florist_id')->nullable()->constrained('florists')->nullOnDelete();
            $table->timestamps();
        });

        // Une seule simulation active à la fois, garanti au niveau base.
        DB::statement('CREATE UNIQUE INDEX simulations_one_active ON simulations (is_active) WHERE is_active = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulations');
    }
};

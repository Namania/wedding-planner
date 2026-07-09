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
        // Contrairement au lieu/traiteur/fleuriste (un seul choix par simulation),
        // une simulation peut retenir plusieurs animations (DJ + feu d'artifice, etc.) :
        // table pivot many-to-many plutôt qu'une colonne animation_id sur simulations.
        Schema::create('animation_simulation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['simulation_id', 'animation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animation_simulation');
    }
};

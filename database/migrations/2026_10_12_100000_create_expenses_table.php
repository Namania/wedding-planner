<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->string('note');
            // Texte libre plutôt qu'une référence aux mariés : un parent ou un
            // témoin peut aussi avoir payé.
            $table->string('paid_by', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

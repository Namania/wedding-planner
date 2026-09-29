<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trois étapes séparées : Postgres refuse de renommer une colonne
        // encore tenue par une contrainte de clé étrangère.
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropForeign(['gallery_guest_id']);
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->renameColumn('gallery_guest_id', 'user_id');
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->renameColumn('user_id', 'gallery_guest_id');
        });

        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->foreign('gallery_guest_id')->references('id')->on('gallery_guests')->cascadeOnDelete();
        });
    }
};

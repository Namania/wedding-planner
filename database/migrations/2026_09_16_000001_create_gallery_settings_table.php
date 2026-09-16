<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gallery_settings', function (Blueprint $table) {
            $table->id();
            $table->text('invite_token');
            $table->boolean('registrations_open')->default(true);
            $table->timestamp('registration_closes_at')->nullable();
            $table->unsignedInteger('max_guests')->default(200);
            $table->unsignedInteger('max_photos_per_guest')->default(100);
            $table->timestamps();
        });

        DB::table('gallery_settings')->insert([
            'invite_token' => Crypt::encryptString(Str::random(64)),
            'registrations_open' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // gallery_guest_id et user_id vivent dans deux espaces d'identifiants
        // disjoints (gallery_guests.id contre users.id) : un simple renommage
        // ne les fait pas correspondre. La spec a écarté toute reprise de
        // données ici parce que la galerie n'a jamais servi en production —
        // mais si cette hypothèse était fausse, un renommage muet
        // réattribuerait silencieusement des photos à d'autres comptes (ou
        // échouerait sur la contrainte de clé étrangère avec un message
        // opaque). On s'arrête donc net, avant toute chose, si la table
        // contient déjà des lignes.
        if (DB::table('gallery_photos')->exists()) {
            throw new RuntimeException(
                'gallery_photos contient des lignes : cette migration ne sait pas remapper '.
                'gallery_guest_id vers users.id (deux espaces d\'identifiants disjoints). '.
                'Migrer ou vider ces données à la main avant de continuer.'
            );
        }

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

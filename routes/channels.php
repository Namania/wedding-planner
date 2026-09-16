<?php

use App\Models\GalleryGuest;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('wedding', function ($user) {
    return $user instanceof User;
});

Broadcast::channel('gallery', function ($user) {
    return $user instanceof User
        || ($user instanceof GalleryGuest && ! $user->isBanned());
}, ['guards' => ['web', 'sanctum']]);

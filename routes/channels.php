<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('wedding', function ($user) {
    return $user instanceof User && $user->isAdmin();
});

Broadcast::channel('gallery', function ($user) {
    return $user instanceof User
        && ($user->isAdmin() || ! $user->isBanned());
}, ['guards' => ['web']]);

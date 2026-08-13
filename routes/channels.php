<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal unique pour toute l'app : les deux mariés partagent les mêmes données,
// pas besoin de canaux par ressource ou par utilisateur.
Broadcast::channel('wedding', function ($user) {
    return $user !== null;
});

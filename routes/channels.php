<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channel authorization untuk Laravel Reverb.
| Private channel memastikan user hanya bisa listen ke notifikasi miliknya.
|
*/

Broadcast::channel('notifications.{id}', function (User $user, string $id) {
    return $user->id === $id;
});

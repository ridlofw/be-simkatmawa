<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels & Auth Middleware
|--------------------------------------------------------------------------
|
| Channel authorization untuk Laravel Reverb.
| Menggunakan middleware auth:sanctum agar /broadcasting/auth dapat
| memverifikasi Bearer Token dari Frontend (Next.js).
|
*/

Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

Broadcast::channel('notifications.{id}', function (User $user, string $id) {
    return (string) $user->id === (string) $id;
});

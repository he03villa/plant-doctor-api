<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('plant.{plantId}', function ($user, int $plantId) {
    $plant = \App\Models\Plant::find($plantId);
    if (!$plant) return false;
    return $plant->user_id === $user->id;
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('diseases', function ($user) {
    return true;
});

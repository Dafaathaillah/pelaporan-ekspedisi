<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('coordinator.{site}', fn ($user, $site) => $user->role === UserRole::COORDINATOR && $user->site === $site && ($user->hasPermission('coordinator.expedition') || $user->hasPermission('coordinator.vendor')));
Broadcast::channel('vendor.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

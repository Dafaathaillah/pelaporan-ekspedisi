<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('coordinator.{site}', fn ($user, $site) => in_array($user->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true) && $user->site === $site && ($user->hasPermission('coordinator.expedition') || $user->hasPermission('coordinator.vendor')));
Broadcast::channel('vendor.{id}', fn ($user, $id) => (int) $user->id === (int) $id);
Broadcast::channel('driver.{site}', fn ($user, $site) => $user->role === UserRole::DRIVER && $user->site === $site && $user->hasPermission('driver.escort'));
Broadcast::channel('expedition.{site}', fn ($user, $site) => $user->role === UserRole::EXPEDITION && $user->site === $site && $user->hasPermission('expedition.report'));

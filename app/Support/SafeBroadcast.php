<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $exception) {
            // Realtime is an enhancement. A temporary Reverb/ngrok failure
            // must never turn an already successful business action into 500.
            Log::warning('Realtime broadcast unavailable; primary operation completed.', [
                'event' => $event::class,
                'exception' => $exception::class,
            ]);
        }
    }
}

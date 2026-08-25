<?php

namespace Tests\Unit;

use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SafeBroadcastTest extends TestCase
{
    public function test_realtime_failure_does_not_escape_into_primary_action(): void
    {
        Event::listen(BrokenRealtimeEvent::class, fn () => throw new RuntimeException('Realtime unavailable'));
        Log::shouldReceive('warning')->once();

        SafeBroadcast::dispatch(new BrokenRealtimeEvent);

        $this->assertTrue(true);
    }
}

class BrokenRealtimeEvent {}

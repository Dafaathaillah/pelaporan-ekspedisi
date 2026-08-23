<?php

namespace App\Events;

use App\Models\ExpeditionReport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionReportCreated implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public ExpeditionReport $report) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('coordinator.'.$this->report->site)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->report->id];
    }
}

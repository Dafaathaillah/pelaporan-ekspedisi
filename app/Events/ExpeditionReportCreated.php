<?php

namespace App\Events;

use App\Models\ExpeditionReport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionReportCreated implements ShouldBroadcastNow
{
    use Dispatchable,SerializesModels;

    public function __construct(public ExpeditionReport $report) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('coordinator.'.$this->report->site), new PrivateChannel('driver.'.$this->report->site)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->report->id, 'company' => $this->report->expedition_company, 'driver' => $this->report->driver_name];
    }

    public function broadcastAs(): string
    {
        return 'ExpeditionReportCreated';
    }
}

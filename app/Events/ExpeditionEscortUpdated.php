<?php

namespace App\Events;

use App\Models\ExpeditionReport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionEscortUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ExpeditionReport $report)
    {
        $this->report->loadMissing(['escortDriver:id,name,hull_number', 'escortCloser:id,name,hull_number']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('coordinator.'.$this->report->site), new PrivateChannel('driver.'.$this->report->site)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->report->id, 'report_number' => $this->report->report_number, 'company' => $this->report->expedition_company, 'plate' => $this->report->vehicle_plate, 'status' => $this->report->escort_status, 'driver' => $this->report->escortDriver?->name, 'hull_number' => $this->report->escortDriver?->hull_number, 'closer' => $this->report->escortCloser?->name, 'closer_hull_number' => $this->report->escortCloser?->hull_number];
    }

    public function broadcastAs(): string
    {
        return 'ExpeditionEscortUpdated';
    }
}

<?php

namespace App\Events;

use App\Models\PlanningItem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanningUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public PlanningItem $planningItem) {}

    public function broadcastOn(): array
    {
        $submission = $this->planningItem->submission;

        return [
            new PrivateChannel('coordinator.'.$submission->site),
            new PrivateChannel('expedition.'.$submission->site),
            new PrivateChannel('vendor.'.$submission->created_by),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlanningUpdated';
    }

    public function broadcastWith(): array
    {
        $submission = $this->planningItem->submission;

        return [
            'id' => $this->planningItem->id,
            'submission_id' => $submission->id,
            'number' => $submission->submission_number,
            'vendor' => $submission->vendor_name,
            'planned_date' => $this->planningItem->planned_date?->format('Y-m-d'),
        ];
    }
}

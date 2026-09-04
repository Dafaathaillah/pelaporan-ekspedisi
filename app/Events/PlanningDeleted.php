<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PlanningDeleted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public string $id,
        public string $submissionId,
        public string $number,
        public string $vendor,
        public string $site,
        public int $vendorUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('coordinator.'.$this->site),
            new PrivateChannel('expedition.'.$this->site),
            new PrivateChannel('vendor.'.$this->vendorUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlanningDeleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->id, 'submission_id' => $this->submissionId, 'number' => $this->number, 'vendor' => $this->vendor];
    }
}

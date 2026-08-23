<?php

namespace App\Events;

use App\Models\VendorSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorSubmissionCreated implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public VendorSubmission $submission) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('coordinator.'.$this->submission->site)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->submission->id, 'type' => $this->submission->type->value];
    }
}

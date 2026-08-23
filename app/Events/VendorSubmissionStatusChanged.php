<?php

namespace App\Events;

use App\Models\VendorSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorSubmissionStatusChanged implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public VendorSubmission $submission) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('vendor.'.$this->submission->created_by)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->submission->id, 'status' => $this->submission->status->value, 'note' => $this->submission->coordinator_note];
    }
}

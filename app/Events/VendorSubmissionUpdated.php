<?php

namespace App\Events;

use App\Models\VendorSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorSubmissionUpdated implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public VendorSubmission $submission) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('coordinator.'.$this->submission->site)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->submission->id, 'type' => $this->submission->type->value, 'label' => $this->submission->type->label(), 'vendor' => $this->submission->vendor_name, 'applicant' => $this->submission->applicant_name];
    }

    public function broadcastAs(): string
    {
        return 'VendorSubmissionUpdated';
    }
}

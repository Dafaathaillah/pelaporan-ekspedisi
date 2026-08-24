<?php

namespace App\Events;

use App\Models\VendorSubmission;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorResubmissionRequested implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;
    public function __construct(public VendorSubmission $submission) {}
    public function broadcastOn(): array { return [new PrivateChannel('vendor.'.$this->submission->created_by), new PrivateChannel('coordinator.'.$this->submission->site)]; }
    public function broadcastAs(): string { return 'VendorResubmissionRequested'; }
    public function broadcastWith(): array { return ['id'=>$this->submission->id,'number'=>$this->submission->submission_number,'type'=>$this->submission->type->value,'label'=>$this->submission->type->label(),'vendor'=>$this->submission->vendor_name,'applicant'=>$this->submission->applicant_name]; }
}

<?php

namespace App\Events;

use App\Models\ExpeditionRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ExpeditionRequest $expeditionRequest) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('coordinator.'.$this->expeditionRequest->site),
            new PrivateChannel('purchasing.'.$this->expeditionRequest->site),
        ];
        foreach ($this->expeditionRequest->vendorCompany->users()->pluck('id') as $id) {
            $channels[] = new PrivateChannel('vendor.'.$id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ExpeditionRequestUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->expeditionRequest->id,
            'number' => $this->expeditionRequest->request_number,
            'pr_number' => $this->expeditionRequest->pr_number,
            'po_number' => $this->expeditionRequest->po_number,
        ];
    }
}

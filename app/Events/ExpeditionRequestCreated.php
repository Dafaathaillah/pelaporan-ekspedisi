<?php

namespace App\Events;

use App\Models\ExpeditionRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpeditionRequestCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ExpeditionRequest $expeditionRequest) {}

    public function broadcastOn(): array
    {
        $vendorUsers = $this->expeditionRequest->vendorCompany->users()->pluck('id');

        return $vendorUsers->map(fn ($id) => new PrivateChannel('vendor.'.$id))->all();
    }

    public function broadcastAs(): string
    {
        return 'ExpeditionRequestCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->expeditionRequest->id,
            'number' => $this->expeditionRequest->request_number,
            'type' => $this->expeditionRequest->expedition_type,
            'route' => $this->expeditionRequest->route_from.' → '.$this->expeditionRequest->route_to,
        ];
    }
}

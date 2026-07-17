<?php

namespace App\Events;

use App\Models\Plant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlantUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Plant $plant
    ) {}

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('plant.' . $this->plant->id),
            new \Illuminate\Broadcasting\PrivateChannel('user.' . $this->plant->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'plant.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->plant->id,
            'name' => $this->plant->name,
            'status' => $this->plant->status,
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Disease;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiseaseCatalogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Disease $disease,
        public string $action
    ) {}

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\Channel('diseases'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'disease.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->disease->id,
            'name' => $this->disease->name,
            'action' => $this->action,
        ];
    }
}

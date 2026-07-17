<?php

namespace App\Events;

use App\Models\Diagnosis;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiagnosisCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Diagnosis $diagnosis
    ) {}

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('user.' . $this->diagnosis->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'diagnosis.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->diagnosis->id,
            'plant_id' => $this->diagnosis->plant_id,
            'disease_id' => $this->diagnosis->disease_id,
            'confidence_score' => $this->diagnosis->confidence_score,
            'status' => $this->diagnosis->status,
        ];
    }
}

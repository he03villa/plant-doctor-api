<?php

namespace App\Jobs;

use App\Models\Plant;
use App\Services\GeolocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeolocatePlantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private int $plantId) {}

    public function handle(GeolocationService $geolocationService): void
    {
        $plant = Plant::find($this->plantId);

        if (!$plant || !$plant->latitude || !$plant->longitude) {
            return;
        }

        $location = $geolocationService->findLocationByCoordinates(
            $plant->latitude,
            $plant->longitude
        );

        $plant->update($location);
    }
}

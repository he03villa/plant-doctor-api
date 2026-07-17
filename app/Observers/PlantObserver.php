<?php

namespace App\Observers;

use App\Jobs\GeolocatePlantJob;
use App\Models\Plant;

class PlantObserver
{
    public function created(Plant $plant): void
    {
        if ($plant->latitude && $plant->longitude) {
            GeolocatePlantJob::dispatch($plant->id);
        }
    }

    public function updated(Plant $plant): void
    {
        if ($plant->wasChanged(['latitude', 'longitude']) && $plant->latitude && $plant->longitude) {
            GeolocatePlantJob::dispatch($plant->id);
        }
    }
}

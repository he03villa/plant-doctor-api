<?php

namespace App\Observers;

use App\Models\Disease;

class DiseaseObserver
{
    public function created(Disease $disease): void
    {
        // Sync publication on creation
        if (method_exists($disease, 'syncPublication')) {
            $disease->syncPublication();
        }
    }

    public function updated(Disease $disease): void
    {
        if ($disease->wasChanged(['name', 'description', 'status'])) {
            if (method_exists($disease, 'syncPublication')) {
                $disease->syncPublication();
            }
        }
    }

    public function deleted(Disease $disease): void
    {
        $disease->publication?->delete();
    }
}

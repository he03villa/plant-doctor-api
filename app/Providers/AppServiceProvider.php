<?php

namespace App\Providers;

use App\Models\Disease;
use App\Models\Plant;
use App\Observers\DiseaseObserver;
use App\Observers\PlantObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!defined('SWAGGER_SERVER_HOST')) {
            define('SWAGGER_SERVER_HOST', config('app.url'));
        }

        Plant::observe(PlantObserver::class);
        Disease::observe(DiseaseObserver::class);

        Carbon::setLocale('es');
        URL::forceRootUrl(config('app.url'));
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Fix public path for InfinityFree (when index.php is in htdocs and code is in a subfolder)
        if ($this->app->environment('production')) {
            $this->app->bind('path.public', function() {
                // If base_path is .../htdocs/Atharaman_Backend_PHP, 
                // public path should be .../htdocs/
                return dirname(base_path());
            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Add morph map for polymorphic relationships
        Relation::morphMap([
            'location' => 'App\Models\Location',
            'hotel' => 'App\Models\Hotel',
            'guide' => 'App\Models\Guides',
            'shop' => 'App\Models\Shop',
            'vehicle' => 'App\Models\Vehicle',
        ]);
    }
}
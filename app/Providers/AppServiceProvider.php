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
        //
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
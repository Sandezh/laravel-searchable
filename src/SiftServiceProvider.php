<?php

namespace Searchkit\Searchable;

use Illuminate\Support\ServiceProvider;

class SiftServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the package's services (if needed)
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/sift.php' => config_path('searchable.php'),
        ], 'config');
    }
}

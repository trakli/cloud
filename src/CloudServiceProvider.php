<?php

namespace Trakli\Cloud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CloudServiceProvider extends ServiceProvider
{
    protected $namespace = 'Trakli\\Cloud\\Http\\Controllers';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register any bindings here if needed
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->publishConfig();
    }

    /**
     * Register the plugin routes.
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['api'])
            ->prefix('api/v1/cloud')
            ->namespace($this->namespace)
            ->group(function () {
                $this->loadRoutesFrom(base_path('plugins/cloud/routes/api.php'));
            });
    }

    /**
     * Publish the configuration file.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            base_path('plugins/cloud/config/cloudplans.php') => config_path('cloudplans.php'),
        ], 'cloud-config');
    }
}

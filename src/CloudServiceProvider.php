<?php

namespace Trakli\Cloud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Trakli\Cloud\Console\SyncPlansCommand;
use Whilesmart\Entitlements\Contracts\Entitlements;

class CloudServiceProvider extends ServiceProvider
{
    protected $namespace = 'Trakli\\Cloud\\Http\\Controllers';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->publishConfig();

        // A cancelled Stripe subscription drops the owner back to the free
        // plan instead of leaving them with none.
        if (blank(config('entitlements-cashier.default_plan'))) {
            config(['entitlements-cashier.default_plan' => 'free']);
        }
        $this->app->singleton(Entitlements::class, \Trakli\Cloud\Support\CloudEntitlements::class);
        if ($this->app->runningInConsole()) {
            $this->commands([SyncPlansCommand::class]);
        }
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

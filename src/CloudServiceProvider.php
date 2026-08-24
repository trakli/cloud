<?php

namespace Trakli\Cloud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Trakli\Cloud\Console\SyncPlansCommand;
use Trakli\Cloud\Support\FreePlanFallbackSource;
use Trakli\Cloud\Support\TokenMeterUsage;
use Whilesmart\Entitlements\Contracts\Entitlements;
use Whilesmart\Entitlements\Contracts\PlanSource;
use Whilesmart\Entitlements\Contracts\UsageMeter;
use Whilesmart\Entitlements\Support\PlanEntitlements;

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
        $this->enforcePlans();

        // A cancelled Stripe subscription drops the owner back to the free
        // plan instead of leaving them with none.
        if (blank(config('entitlements-cashier.default_plan'))) {
            config(['entitlements-cashier.default_plan' => 'free']);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPlansCommand::class]);
        }
    }

    /**
     * Replace the permissive default with the plan-backed implementation.
     * Bound here rather than in register() so it wins whatever order the
     * plugin engine loads this provider in.
     */
    protected function enforcePlans(): void
    {
        if (config('cloudplans.freemode_enabled', false)) {
            return;
        }

        $this->app->singleton(PlanSource::class, FreePlanFallbackSource::class);
        $this->app->singleton(UsageMeter::class, TokenMeterUsage::class);
        $this->app->singleton(Entitlements::class, PlanEntitlements::class);
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

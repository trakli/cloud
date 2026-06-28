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
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cloudplans.php',
            'cloudplans'
        );

        $this->app->singleton(\App\Contracts\Entitlements::class, \Trakli\Cloud\Support\CloudEntitlements::class);
        config(['cashier.model' => \Trakli\Cloud\Models\BillingCustomer::class]);
        \Laravel\Cashier\Cashier::useCustomerModel(\Trakli\Cloud\Models\BillingCustomer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->publishConfig();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register feature gating middleware
        $this->app['router']->aliasMiddleware('cloud.feature', \Trakli\Cloud\Http\Middleware\GateFeature::class);

        // Register Stripe webhook event listener to clear plan cache
        \Illuminate\Support\Facades\Event::listen(
            \Laravel\Cashier\Events\WebhookReceived::class,
            \Trakli\Cloud\Listeners\StripeWebhookListener::class
        );
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

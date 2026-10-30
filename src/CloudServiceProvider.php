<?php

namespace Trakli\Cloud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Trakli\Cloud\Console\SyncPlansCommand;
use Trakli\Cloud\Engagement\BillingMetricProvider;
use Trakli\Cloud\Support\FreePlanFallbackSource;
use Trakli\Cloud\Support\TokenMeterUsage;
use Whilesmart\Admin\Offers\FreePasses;
use Whilesmart\Admin\Offers\StripePromotionCodes;
use Whilesmart\Entitlements\Contracts\Entitlements;
use Whilesmart\Entitlements\Contracts\PlanSource;
use Whilesmart\Entitlements\Contracts\UsageMeter;

class CloudServiceProvider extends ServiceProvider
{
    protected $namespace = 'Trakli\\Cloud\\Http\\Controllers';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cloudplans.php', 'cloudplans');
        $this->mergeConfigFrom(__DIR__ . '/../config/workspace-billing.php', 'workspace-billing');
        $this->app->bind(\Trakli\Cloud\Contracts\WorkspacePricing::class, fn ($app) => $app->make(config('workspace-billing.pricing')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(\App\Services\IntegrationRegistry::class)->register(new CloudIntegration());
        $this->registerRoutes();
        $this->publishConfig();
        $this->enforcePlans();
        config(['engagement.providers' => array_values(array_unique([
            BillingMetricProvider::class,
            ...(array) config('engagement.providers', []),
        ]))]);
        config(['admin.offer_providers' => array_values(array_unique(array_merge(
            (array) config('admin.offer_providers', []),
            [StripePromotionCodes::class, FreePasses::class],
        )))]);
        app(\Illuminate\Routing\Router::class)->aliasMiddleware('feature', \App\Http\Middleware\RequireFeature::class);

        if (config('entitlements-cashier.trial_days') === null) {
            config(['entitlements-cashier.trial_days' => config('cloudplans.trial_days', 3)]);
        }

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
        $this->app->singleton(Entitlements::class, \Trakli\Cloud\Billing\WorkspaceMemberEntitlements::class);
        \App\Models\Workspace::observe(\Trakli\Cloud\Billing\WorkspaceDeletionGuard::class);
        $this->app->bind(\Whilesmart\Workspaces\Contracts\InvitationGuard::class, \Trakli\Cloud\Billing\WorkspaceInvitationGuard::class);
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

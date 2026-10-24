<?php

namespace Trakli\Cloud\Console;

use Illuminate\Console\Command;
use Trakli\Cloud\Support\CloudPlanSync;
use Whilesmart\EntitlementsCashier\PlanPriceSync;

class SyncPlansCommand extends Command
{
    protected $signature = 'cloud:sync-plans {--features-only : Update existing plan features without syncing prices}';

    protected $description = 'Mirror the configured cloud plans into the entitlements tables and ensure a Stripe price for each';

    public function handle(CloudPlanSync $plans, PlanPriceSync $prices): int
    {
        if ($this->option('features-only')) {
            $this->info(sprintf('Synced features for %d existing plans.', $plans->featuresOnly()));
            return self::SUCCESS;
        }

        $this->info(sprintf('Synced %d plans.', $plans->run()));

        $prices->run();

        return self::SUCCESS;
    }
}

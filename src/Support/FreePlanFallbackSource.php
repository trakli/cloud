<?php

namespace Trakli\Cloud\Support;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Entitlements\Contracts\PlanSource;
use Whilesmart\Entitlements\Models\Plan;
use Whilesmart\Entitlements\Support\EloquentPlanSource;
use Whilesmart\Entitlements\Support\ResolvedPlan;

/**
 * An owner with no subscription is on the free plan, not on no plan at all.
 * Without this they would be refused every feature while their wallet and
 * category limits read as unlimited, which is the wrong way round.
 */
class FreePlanFallbackSource implements PlanSource
{
    public function __construct(private EloquentPlanSource $subscribed)
    {
    }

    public function resolve(?Model $owner): ?ResolvedPlan
    {
        return $this->subscribed->resolve($owner) ?? $this->freePlan();
    }

    private function freePlan(): ?ResolvedPlan
    {
        $model = config('entitlements.models.plan', Plan::class);
        $plan = $model::where('key', CloudPlanSync::key('free'))->first();

        if ($plan === null) {
            return null;
        }

        return ResolvedPlan::of(
            $plan->features ?? [],
            $plan->limits ?? [],
            $plan->meters ?? [],
        );
    }
}

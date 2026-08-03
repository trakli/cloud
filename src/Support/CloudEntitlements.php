<?php

namespace Trakli\Cloud\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Whilesmart\Entitlements\Contracts\Entitlements;
use Whilesmart\Entitlements\Models\Subscription;
use Whilesmart\Entitlements\Support\AccessResult;


class CloudEntitlements implements Entitlements
{
    /**
     * Determine if the owner is allowed to use a given feature.
     */
    public function allows(?Model $owner, string $feature): bool
    {
        return true;
    }

    /**
     * Get the limit for a given key.
     */
    public function limit(?Model $owner, string $key): ?int
    {
        return null;
    }

    /**
     * Get the remaining token allowance for the current period.
     */
    public function remaining(?Model $owner, string $meter): int|float
    {
        if ($meter !== 'ai_tokens') {
            return INF;
        }

        // If in freemode, return unlimited tokens
        if (config('cloudplans.freemode_enabled', false)) {
            return INF;
        }

        if (!$owner) {
            return 0;
        }

        // Get owner's current plan code
        $planCode = $this->getPlanCode($owner);

        $plan = config("cloudplans.plans.{$planCode}");
        if (!$plan) {
            return 0;
        }

        $allowance = (int)($plan['token_allowance'] ?? 0);
        if ($allowance <= 0) {
            return 0;
        }

        $periodStart = Carbon::now()->startOfMonth();
        $used = method_exists($owner, 'tokensUsed') ? (int)$owner->tokensUsed($periodStart) : 0;

        return max(0, $allowance - $used);
    }

    /**
     * Consume a given amount of tokens for the current period.
     */
    public function consume(?Model $owner, string $meter, int $amount): void
    {
        // Handled in trakli core via whilesmart/eloquent-agent-metrics
    }

    private function subscriptionModel(): string
    {
        return config('entitlements.models.subscription', Subscription::class);
    }

    private function activeSubscriptionFor(Model $owner): ?Subscription
    {
        return $this->subscriptionModel()::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Get the active plan code for the owner.
     *
     * The entitlement_plans table keys are suffixed with the region
     * (e.g. monthly-us), while cloudplans.php is keyed by base plan
     * (free, monthly, yearly). Strip the suffix before config lookup.
     */
    private function getPlanCode(Model $owner): string
    {
        $subscription = $this->activeSubscriptionFor($owner);
        if ($subscription && $subscription->plan) {
            return explode('-', $subscription->plan->key)[0]; // gets monthly from monthly-eu
        }

        // Assume user is on the free plan
        return 'free';
    }

    public function check(?Model $owner, string $feature): AccessResult
    {
        // If in freemode or feature is unconditionally allowed
        if (config('cloudplans.freemode_enabled', false)) {
            return AccessResult::allow($feature);
        }

        if (! $owner) {
            return AccessResult::deny($feature, 'no_owner');
        }

        $planCode = $this->getPlanCode($owner);
        $plan = config("cloudplans.plans.{$planCode}");

        // Check if the feature is listed in the plan's features or permissions
        if ($plan && in_array($feature, $plan['features'] ?? [], true)) {
            return AccessResult::allow($feature);
        }

        return AccessResult::deny($feature, 'not_in_plan');
    }
}





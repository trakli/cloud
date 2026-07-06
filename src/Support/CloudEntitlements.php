<?php

namespace Trakli\Cloud\Support;

use App\Contracts\Entitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Trakli\Cloud\Models\AiUsageCounter;

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

        // Get owner's current plan via Cashier
        $planCode = $this->getPlanCode($owner);
        if ($planCode === 'free') {
            return INF;
        }

        $plan = config("cloudplans.plans.{$planCode}");
        if (!$plan) {
            return 0;
        }

        $allowance = $plan['token_allowance'] ?? 0;
        if ($allowance <= 0) {
            return 0;
        }

        $periodStart = Carbon::now()->startOfMonth();
        $used = AiUsageCounter::where('owner_id', $owner->id)
            ->where('owner_type', get_class($owner))
            ->where('period_start', $periodStart)
            ->value('tokens_used') ?? 0;

        return max(0, $allowance - $used);
    }

    /**
     * Consume a given amount of tokens for the current period.
     */
    public function consume(?Model $owner, string $meter, int $amount): void
    {
        if ($meter !== 'ai_tokens' || $amount <= 0 || !$owner) {
            return;
        }

        if (config('cloudplans.freemode_enabled', false)) {
            return;
        }

        $planCode = $this->getPlanCode($owner);
        if ($planCode === 'free') {
            return;
        }

        $periodStart = Carbon::now()->startOfMonth();

        $counter = AiUsageCounter::where('owner_id', $owner->id)
            ->where('owner_type', get_class($owner))
            ->where('period_start', $periodStart)
            ->first();

        if ($counter) {
            $counter->increment('tokens_used', $amount);
        } else {
            AiUsageCounter::create([
                'owner_id' => $owner->id,
                'owner_type' => get_class($owner),
                'period_start' => $periodStart,
                'tokens_used' => $amount,
            ]);
        }
    }

    /**
     * Get the active plan code for the owner via Cashier.
     */
    private function getPlanCode(Model $owner): string
    {
        /** @var \Trakli\Cloud\Models\BillingCustomer|null $billingCustomer */
        $billingCustomer = \Trakli\Cloud\Models\BillingCustomer::where('user_id', $owner->getKey())->first();
        if ($billingCustomer) {
            if ($billingCustomer->subscribed('monthly')) {
                return 'monthly';
            }
            if ($billingCustomer->subscribed('yearly')) {
                return 'yearly';
            }
            return 'free';
        }

        return 'free';
    }
}

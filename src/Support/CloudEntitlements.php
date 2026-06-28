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
    public function allows(?Model $owner, string $feature): bool
    {
        if (!$owner) {
            return false;
        }

        if (config('cloudplans.freemode_enabled', false)) {
            return true;
        }

        $billingCustomer = \Trakli\Cloud\Models\BillingCustomer::where('user_id', $owner->getKey())->first();
        if (!$billingCustomer) {
            return true;
        }

        $planCode = $this->getPlanCode($owner);

        $features = config("cloudplans.plans.{$planCode}.feature_keys", []);

        return in_array($feature, $features);
    }

    /**
     * Get the limit for a given key.
     */
    public function limit(?Model $owner, string $key): ?int
    {
        if (!$owner) {
            return 0;
        }

        if (config('cloudplans.freemode_enabled', false)) {
            return null;
        }

        $billingCustomer = \Trakli\Cloud\Models\BillingCustomer::where('user_id', $owner->getKey())->first();
        if (!$billingCustomer) {
            return null;
        }

        $planCode = $this->getPlanCode($owner);

        return config("cloudplans.plans.{$planCode}.limits.{$key}");
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

        $billingCustomer = \Trakli\Cloud\Models\BillingCustomer::where('user_id', $owner->getKey())->first();
        if (!$billingCustomer) {
            return INF;
        }

        // Get owner's current plan via Cashier
        $planCode = $this->getPlanCode($owner);

        $plan = config("cloudplans.plans.{$planCode}");
        if (!$plan) {
            return 0;
        }

        $allowance = $plan['token_allowance'] ?? 0;
        if ($allowance <= 0) {
            return 0;
        }

        $periodStart = Carbon::now()->startOfMonth();
        $used = AiUsageCounter::where('user_id', $owner->id)
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

        $billingCustomer = \Trakli\Cloud\Models\BillingCustomer::where('user_id', $owner->getKey())->first();
        if (!$billingCustomer) {
            return;
        }

        $planCode = $this->getPlanCode($owner);

        // Extract message ID from debug backtrace to ensure idempotency
        $messageId = $this->getCurrentChatMessageId();
        if ($messageId === null) {
            return;
        }

        $cacheKey = "cloud_ai_consumed_message_{$messageId}";
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addDays(30));

        $periodStart = Carbon::now()->startOfMonth();

        $counter = AiUsageCounter::where('user_id', $owner->id)
            ->where('period_start', $periodStart)
            ->first();

        if ($counter) {
            $counter->increment('tokens_used', $amount);
        } else {
            AiUsageCounter::create([
                'user_id' => $owner->id,
                'period_start' => $periodStart,
                'tokens_used' => $amount,
            ]);
        }
    }

    private function getPlanCode(Model $owner): string
    {
        $cacheKey = "user_plan_code_{$owner->getKey()}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($owner) {
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
        });
    }

    /**
     * Find the chat message ID from the backtrace to key the idempotent increment.
     */
    private function getCurrentChatMessageId(): ?int
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof \App\Jobs\ProcessChatMessageJob) {
                return $frame['object']->assistantMessage->id;
            }
        }
        return null;
    }
}

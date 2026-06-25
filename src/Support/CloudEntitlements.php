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

        // Get owner's current plan
        $planCode = $owner->getConfigValue('subscription_plan');
        if (empty($planCode)) {
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

        $planCode = $owner->getConfigValue('subscription_plan');
        if (empty($planCode)) {
            return;
        }

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

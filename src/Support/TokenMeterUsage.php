<?php

namespace Trakli\Cloud\Support;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Entitlements\Contracts\UsageMeter;

/**
 * Counts AI tokens against the allowance the owner's plan carries. The count
 * comes from the token usage core already records, so this owns no table of
 * its own and cannot drift from what was actually spent.
 */
class TokenMeterUsage implements UsageMeter
{
    private const METER = 'ai_tokens';

    public function remaining(?Model $owner, string $meter, int|float $allowance): int|float
    {
        if ($meter !== self::METER || is_infinite($allowance)) {
            return INF;
        }

        if ($owner === null) {
            return $allowance;
        }

        return max(0, $allowance - $this->used($owner));
    }

    public function consume(?Model $owner, string $meter, int $amount): void
    {
        // Core records every turn through the token meter, so counting here
        // as well would bill the owner twice for the same tokens.
    }

    /**
     * Tokens spent in the current period. Owners that do not record usage at
     * all have spent nothing, which keeps a future owner type from failing
     * closed before it is metered.
     */
    private function used(Model $owner): int
    {
        return method_exists($owner, 'tokensUsed')
            ? (int) $owner->tokensUsed(now()->startOfMonth())
            : 0;
    }
}

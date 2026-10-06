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
        if (is_infinite($allowance)) {
            return INF;
        }

        // Any other meter is someone else's to count, and nothing has been
        // spent against it here, so its allowance comes back untouched rather
        // than as unlimited.
        if ($meter !== self::METER || $owner === null) {
            return $allowance;
        }

        return max(0, $allowance - $this->used($owner));
    }

    public function consume(?Model $owner, string $meter, int $amount): void
    {
        // Core records every turn through the token meter, so counting here
        // as well would bill the owner twice for the same tokens.
    }

    public function used(Model $owner): int
    {
        if (! method_exists($owner, 'tokenUsages')) {
            throw new \RuntimeException('The billing owner does not support token usage accounting.');
        }

        $start = now('UTC')->startOfMonth();

        return (int) $owner->tokenUsages()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->copy()->addMonth())
            ->sum('total_tokens');
    }
}

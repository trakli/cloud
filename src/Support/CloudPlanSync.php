<?php

namespace Trakli\Cloud\Support;

use Whilesmart\Entitlements\Models\Plan;

/**
 * Mirrors the plans in config/cloudplans.php into the entitlements plans table,
 * one row per plan and region because a Stripe price carries a single currency.
 *
 * Only the amount is written here; the Stripe price id is derived from it by
 * the entitlements-cashier price sync, so no price id is maintained by hand.
 */
class CloudPlanSync
{
    public static function key(string $plan, ?string $region = null): string
    {
        return $region === null ? $plan : "{$plan}-{$region}";
    }

    /**
     * @return int the number of plans written
     */
    public function run(): int
    {
        $config = (array) config('cloudplans');
        $synced = 0;

        foreach ($config['plans'] ?? [] as $id => $plan) {
            $priceKey = $id.'_price';
            $name = $plan['name'] ?? $id;

            $regions = array_filter(
                $config['regions'] ?? [],
                fn (array $region) => isset($region[$priceKey])
            );

            if ($regions === []) {
                $this->write(self::key($id), $name, $plan, null);
                $synced++;

                continue;
            }

            foreach ($regions as $code => $region) {
                $this->write(
                    self::key($id, $code),
                    sprintf('%s (%s)', $name, $region['name'] ?? $code),
                    $plan,
                    [
                        'amount_cents' => (int) round(((float) $region[$priceKey]) * 100),
                        'currency' => strtolower((string) ($region['currency'] ?? 'usd')),
                        'interval' => $plan['interval'] ?? 'month',
                    ]
                );
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Merges into existing metadata rather than replacing it, so the price
     * signature the price sync records is not lost and Stripe prices are not
     * recreated on every run.
     */
    private function write(string $key, string $name, array $source, ?array $price): void
    {
        $model = config('entitlements.models.plan', Plan::class);

        $plan = $model::firstOrNew(['key' => $key]);
        $plan->name = $name;
        $plan->features = $this->features($source);
        $plan->limits = $source['limits'] ?? [];
        $plan->meters = ['ai_tokens' => (int) ($source['token_allowance'] ?? 0)];

        if ($price !== null) {
            $plan->metadata = array_merge($plan->metadata ?? [], ['price' => $price]);
        }

        $plan->save();
    }

    /**
     * Features are configured as a list of keys but gated by lookup, so the
     * list becomes a map.
     */
    private function features(array $source): array
    {
        return array_fill_keys($source['feature_keys'] ?? [], true);
    }
}

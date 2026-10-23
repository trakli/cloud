<?php

namespace Trakli\Cloud\Engagement;

use App\Models\User;
use Illuminate\Support\Collection;
use Whilesmart\Engagement\Contracts\MetricProvider;
use Whilesmart\Engagement\Support\Metric;
use Whilesmart\Engagement\Support\Period;
use Whilesmart\Entitlements\Enums\SubscriptionStatus;
use Whilesmart\Entitlements\Models\Subscription;

class BillingMetricProvider implements MetricProvider
{
    public function key(): string
    {
        return 'billing';
    }

    public function label(): string
    {
        return 'Billing';
    }

    public function metrics(Period $period): array
    {
        $model = config('entitlements.models.subscription', Subscription::class);
        $ownerType = (new User())->getMorphClass();
        $latest = $model::query()->selectRaw('MAX(id)')->where('owner_type', $ownerType)->groupBy('owner_id');
        $subscriptions = $model::query()->with('plan')->whereIn('id', $latest)->active()
            ->whereHasMorph('owner', [User::class])->get();
        $paying = $subscriptions->where('status', SubscriptionStatus::Active);
        $trialing = $subscriptions->where('status', SubscriptionStatus::Trialing);
        $plans = $subscriptions->groupBy(fn ($subscription) => $subscription->plan?->name ?? 'Complimentary');

        return [
            Metric::count('active', 'Paying', $paying->count()),
            Metric::count('trialing', 'On trial', $trialing->count()),
            Metric::count(
                'comped',
                'Complimentary users',
                $subscriptions->where('status', SubscriptionStatus::Comped)->count(),
            ),
            Metric::count('trials_ending', 'Trials ending in this period', $trialing->filter(
                fn ($subscription) => $subscription->current_period_end?->between($period->start, $period->end),
            )->count()),
            ...$this->monthlyValues($paying, 'monthly_plan_value', 'Monthly plan value'),
            ...$this->monthlyValues($trialing, 'monthly_trial_value', 'Monthly trial plan value'),
            Metric::ranking('by_plan', 'Current plans', $plans->map(
                fn (Collection $subscribers, string $name) => ['label' => $name, 'value' => $subscribers->count()],
            )->sortByDesc('value')->values()->all()),
        ];
    }

    private function monthlyValues(Collection $subscriptions, string $key, string $label): array
    {
        $totals = [];
        foreach ($subscriptions as $subscription) {
            $price = $subscription->plan?->metadata['price'] ?? null;
            if ($price === null) {
                continue;
            }

            $currency = strtoupper($price['currency']);
            $amount = $price['amount_cents'] / (($price['interval'] === 'year') ? 12 : 1);
            $totals[$currency] = ($totals[$currency] ?? 0) + $amount;
        }

        ksort($totals);

        return array_map(
            fn (string $currency) => Metric::sum(
                sprintf('%s_%s', $key, strtolower($currency)),
                $label,
                round($totals[$currency] / 100, 2),
                $currency,
            ),
            array_keys($totals),
        );
    }
}

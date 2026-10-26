<?php

namespace Trakli\Cloud\Billing;

use App\Models\Workspace;
use Whilesmart\Entitlements\Support\PlanEntitlements;
use Whilesmart\Entitlements\Models\Plan;
use Whilesmart\EntitlementsCashier\Models\BillingProfile;

class WorkspaceSubscription
{
    public function canDelete(Workspace $workspace): bool
    {
        $model = config('entitlements-cashier.billing_profile_model', BillingProfile::class);
        $profile = $model::where('owner_type', $workspace->getMorphClass())->where('owner_id', $workspace->id)->first();
        $subscription = $profile?->subscription('default');

        return (! $subscription || $subscription->ended())
            && ! \Whilesmart\EntitlementsCashier\Models\CheckoutAttempt::forOwner($workspace)
                ->whereIn('state', ['pending', 'ready'])->where('expires_at', '>', now())->exists();
    }

    public function capacity(Workspace $workspace): int
    {
        $entitlements = app(PlanEntitlements::class);
        if (! $workspace->is_active || ! $entitlements->allows($workspace, 'shared_workspaces')) {
            return 0;
        }
        $model = config('entitlements-cashier.billing_profile_model', BillingProfile::class);
        $profile = $model::where('owner_type', $workspace->getMorphClass())->where('owner_id', $workspace->id)->first();
        $subscription = $profile?->subscription('default');
        if (! $subscription || ! in_array($subscription->stripe_status, ['active', 'trialing'], true) || $subscription->ended()) {
            return 0;
        }
        $planModel = config('entitlements.models.plan', Plan::class);
        $paidPlan = $planModel::where('provider_price_id', $subscription->stripe_price)->first();
        if (! ($paidPlan?->features['shared_workspaces'] ?? false)) {
            return 0;
        }

        return min((int) ($paidPlan->limits['workspace_members'] ?? 0), (int) $entitlements->limit($workspace, 'workspace_members'));
    }
}

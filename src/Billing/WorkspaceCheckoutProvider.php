<?php

namespace Trakli\Cloud\Billing;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\EntitlementsCashier\RecoverableCashierBillingProvider;
use Whilesmart\Entitlements\Models\Plan;

class WorkspaceCheckoutProvider extends RecoverableCashierBillingProvider
{
    public function createCheckout(Model $owner, Plan $plan): string
    {
        $previous = ['entitlements-cashier.success_url' => config('entitlements-cashier.success_url'),
            'entitlements-cashier.cancel_url' => config('entitlements-cashier.cancel_url')];
        $url = rtrim(config('workspace-billing.return_url'), '/').'/workspaces/'.$owner->slug;
        config(['entitlements-cashier.success_url' => $url.'?checkout=success',
            'entitlements-cashier.cancel_url' => $url.'?checkout=cancel']);
        try {
            return parent::createCheckout($owner, $plan);
        } finally {
            config($previous);
        }
    }

    protected function trialDays(): ?int
    {
        return null;
    }

    protected function customerOptions(Model $owner): array
    {
        return parent::customerOptions($owner instanceof Workspace ? $owner->owner : $owner);
    }
}

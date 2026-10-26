<?php

namespace Trakli\Cloud\Billing;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\EntitlementsCashier\CashierBillingProvider;
use Whilesmart\Entitlements\Models\Plan;

class WorkspaceCheckoutProvider extends CashierBillingProvider
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
}

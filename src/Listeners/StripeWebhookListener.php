<?php

namespace Trakli\Cloud\Listeners;

use Laravel\Cashier\Events\WebhookReceived;
use Trakli\Cloud\Models\BillingCustomer;
use Illuminate\Support\Facades\Cache;

class StripeWebhookListener
{
    /**
     * Handle the event.
     *
     * @param  WebhookReceived  $event
     * @return void
     */
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $stripeId = $payload['data']['object']['customer'] ?? null;

        if ($stripeId) {
            $customer = BillingCustomer::where('stripe_id', $stripeId)->first();
            if ($customer) {
                Cache::forget("user_plan_code_{$customer->user_id}");
            }
        }
    }
}

<?php

namespace Trakli\Cloud\Http\Controllers;

use App\Http\Controllers\API\ApiController;
use App\Models\Workspace;
use App\Services\WorkspaceFinance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Trakli\Cloud\Contracts\WorkspacePricing;
use Trakli\Cloud\Billing\WorkspaceCheckoutProvider;
use Whilesmart\Entitlements\Models\Plan;
use Whilesmart\EntitlementsCashier\Models\BillingProfile;
use Whilesmart\EntitlementsCashier\PlanPriceSync;

class WorkspaceBillingController extends ApiController
{
    public function quote(Request $request): JsonResponse
    {
        return $this->success($this->quoteFor($request));
    }

    public function status(Workspace $workspace, Request $request): JsonResponse
    {
        app(WorkspaceFinance::class)->authorize($workspace, $request->user());
        $model = config('entitlements.models.subscription');
        $subscription = $model::where('owner_type', $workspace->getMorphClass())
            ->where('owner_id', $workspace->id)->latest('id')->first();
        $profile = $this->profile($workspace);

        return $this->success([
            'active' => app(WorkspaceFinance::class)->eligible($workspace),
            'status' => $subscription?->status?->value ?? 'inactive',
            'member_count' => $workspace->members()->distinct()->count('users.id'),
            'capacity' => app(\Whilesmart\Entitlements\Contracts\Entitlements::class)->limit($workspace, 'workspace_members'),
            'quote' => $subscription?->plan?->metadata['workspace_quote'] ?? null,
            'can_manage_billing' => $workspace->owner_id === $request->user()->id && $workspace->owner_type === $request->user()->getMorphClass(),
            'has_billing_account' => filled($profile?->stripe_id),
            'has_subscription' => $profile?->subscription('default') && ! $profile->subscription('default')->ended(),
            'pending_checkout' => \Whilesmart\EntitlementsCashier\Models\CheckoutAttempt::forOwner($workspace)
                ->whereIn('state', ['pending', 'ready'])->where('expires_at', '>', now())->exists(),
        ]);
    }

    public function checkout(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorizeOwner($workspace, $request);
        $quote = $this->quoteFor($request);
        $request->validate(['quote_id' => 'required|string', 'confirmed' => 'required|accepted']);
        abort_unless(hash_equals($quote['quote_id'], $request->string('quote_id')->toString()), 409, 'Pricing has changed. Review the new total before continuing.');

        return Cache::lock('workspace-billing:'.$workspace->id, 300)->block(5, function () use ($workspace, $quote) {
            abort_if($workspace->members()->distinct()->count('users.id') > $quote['members'], 422, 'Remove members before choosing a smaller subscription.');
            abort_if(blank(config('cashier.secret')), 503, 'Workspace billing is not configured.');
            $plan = $this->plan($quote);
            app(PlanPriceSync::class)->ensure($plan);
            $profile = $this->profile($workspace);
            $subscription = $profile?->subscription('default');
            if ($subscription && ! $subscription->ended() && $subscription->stripe_status !== 'incomplete_expired') {
                return DB::transaction(function () use ($workspace, $quote, $plan, $subscription) {
                    $workspace = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                    abort_if($workspace->members()->distinct()->count('users.id') > $quote['members'], 422, 'Remove members before choosing a smaller subscription.');
                    abort_if($subscription->stripe_price === $plan->provider_price_id, 409, 'This capacity is already selected. Refresh the workspace status.');
                    $subscription->errorIfPaymentFails()->swapAndInvoice($plan->provider_price_id);

                    return $this->success(['pending' => true]);
                });
            }

            return $this->success(['url' => app(WorkspaceCheckoutProvider::class)->createCheckout($workspace, $plan)]);
        });
    }

    public function cancelCheckout(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorizeOwner($workspace, $request);
        app(WorkspaceCheckoutProvider::class)->expireCheckout($workspace);

        return $this->success(['canceled' => true]);
    }

    public function portal(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorizeOwner($workspace, $request);
        $profile = $this->profile($workspace);
        abort_unless(filled($profile?->stripe_id), 409, 'No workspace billing account exists yet.');

        return $this->success(['url' => $profile->billingPortalUrl(rtrim(config('workspace-billing.return_url'), '/').'/workspaces/'.$workspace->slug)]);
    }

    private function quoteFor(Request $request): array
    {
        $data = $request->validate(['members' => 'sometimes|integer|min:1']);
        try {
            $quote = app(WorkspacePricing::class)->quote((int) ($data['members'] ?? config('workspace-billing.included_members')));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
        $quote['quote_id'] = hash('sha256', json_encode([$quote, config('workspace-billing.feature_keys'), config('workspace-billing.limits')], JSON_THROW_ON_ERROR));

        return $quote;
    }

    private function plan(array $quote): Plan
    {
        $model = config('entitlements.models.plan', Plan::class);

        return $model::firstOrCreate(['key' => 'workspace-'.$quote['quote_id']], [
            'name' => 'Group budgeting: '.$quote['members'].' people',
            'features' => array_fill_keys(config('workspace-billing.feature_keys'), true),
            'limits' => array_merge(config('workspace-billing.limits'), ['workspace_members' => $quote['members']]),
            'meters' => ['ai_tokens' => 0],
            'metadata' => ['workspace_quote' => $quote, 'price' => [
                'amount_cents' => $quote['amount_cents'], 'currency' => $quote['currency'], 'interval' => $quote['interval'],
            ]],
        ]);
    }

    private function profile(Workspace $workspace): ?BillingProfile
    {
        $model = config('entitlements-cashier.billing_profile_model', BillingProfile::class);

        return $model::where('owner_type', $workspace->getMorphClass())->where('owner_id', $workspace->id)->first();
    }

    private function authorizeOwner(Workspace $workspace, Request $request): void
    {
        app(WorkspaceFinance::class)->authorize($workspace, $request->user());
        abort_unless($workspace->owner_id === $request->user()->id && $workspace->owner_type === $request->user()->getMorphClass(), 403, 'Only the workspace owner can manage its subscription.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Trakli\Cloud\CloudServiceProvider;
use Whilesmart\Entitlements\Enums\SubscriptionStatus;
use Whilesmart\Entitlements\Models\Plan;
use Whilesmart\Entitlements\Models\Subscription;

class WorkspaceCheckoutTransport implements \Stripe\HttpClient\ClientInterface
{
    public array $sessions = [];
    public bool $interrupt = false;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $fixtures = base_path('packages/entitlements-cashier/tests/Fixtures/');
        if ($method === 'post' && str_ends_with($absUrl, '/expire')) {
            return [file_get_contents($fixtures.'stripe-checkout-expired.json'), 200, []];
        }
        if ($method === 'get' && str_contains($absUrl, '/checkout/sessions/')) {
            $session = json_decode(file_get_contents($fixtures.'stripe-checkout-open.json'), true);
            $session['metadata'] = $this->sessions[array_key_last($this->sessions)]['parameters']['metadata'];

            return [json_encode($session), 200, []];
        }
        if ($method === 'post' && str_ends_with($absUrl, '/checkout/sessions')) {
            $this->sessions[] = ['parameters' => $params, 'headers' => $headers];
            if ($this->interrupt) {
                $this->interrupt = false;
                throw new \RuntimeException('Interrupted checkout response.');
            }

            $session = json_decode(file_get_contents($fixtures.'stripe-checkout-open.json'), true);
            $session['metadata'] = $this->sessions[array_key_last($this->sessions)]['parameters']['metadata'];

            return [json_encode($session), 200, []];
        }

        throw new \RuntimeException('Unexpected Stripe request in workspace checkout test.');
    }
}

class WorkspaceBillingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $loader = require $app->basePath('vendor/autoload.php');
        $loader->addPsr4('Trakli\\Cloud\\', $app->basePath('plugins/cloud/src'));
        $loader->addPsr4('Whilesmart\\EntitlementsCashier\\', $app->basePath('packages/entitlements-cashier/src'));
        $app->register(\Whilesmart\EntitlementsCashier\EntitlementsCashierServiceProvider::class);
        $app->register(CloudServiceProvider::class);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_quotes_are_authenticated_and_follow_configured_pricing(): void
    {
        $this->getJson('/api/v1/cloud/workspace-pricing')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        foreach ([2 => 1200, 3 => 1700, 4 => 2200, 5 => 2700] as $members => $amount) {
            $this->getJson('/api/v1/cloud/workspace-pricing?members='.$members)->assertOk()
                ->assertJsonPath('data.amount_cents', $amount)->assertJsonPath('data.currency', 'usd');
        }
        config(['workspace-billing.base_amount_cents' => 1800, 'workspace-billing.included_members' => 3,
            'workspace-billing.additional_member_amount_cents' => 700]);
        $this->getJson('/api/v1/cloud/workspace-pricing?members=5')->assertOk()->assertJsonPath('data.amount_cents', 3200);
        foreach ([0, -1, 2, 101, 'bad', '3.5'] as $members) {
            $this->getJson('/api/v1/cloud/workspace-pricing?members='.$members)->assertUnprocessable();
        }
    }

    public function test_workspace_creation_is_a_draft_until_its_subscription_is_active(): void
    {
        $workspace = $this->draft();
        $this->getJson('/api/v1/workspaces/'.$workspace->slug)->assertOk()->assertJsonPath('data.read_only', true);
        $this->postJson('/api/v1/workspaces/'.$workspace->slug.'/wallets', ['name' => 'Cash', 'currency' => 'USD', 'type' => 'cash'])->assertForbidden()
            ->assertJsonPath('message', 'Changes to this workspace are not allowed.');
        $this->getJson($this->billing($workspace))->assertOk()->assertJsonPath('data.active', false)->assertJsonPath('data.capacity', 0);
    }

    public function test_checkout_requires_owner_confirmation_and_a_current_server_quote(): void
    {
        $workspace = $this->draft();
        $quote = $this->getJson('/api/v1/cloud/workspace-pricing?members=2')->assertOk()->json('data');
        $this->postJson($this->billing($workspace).'/checkout', ['members' => 2, 'quote_id' => $quote['quote_id']])->assertUnprocessable();
        config(['workspace-billing.base_amount_cents' => 1500]);
        $this->postJson($this->billing($workspace).'/checkout', ['members' => 2, 'quote_id' => $quote['quote_id'], 'confirmed' => true])->assertConflict();
        $this->actingAs(User::factory()->create())->postJson($this->billing($workspace).'/checkout', [])->assertForbidden();
        $this->getJson($this->billing($workspace))->assertForbidden();
    }

    public function test_capacity_is_enforced_when_accepting_and_members_gain_bank_access(): void
    {
        $workspace = $this->draft();
        $owner = $workspace->owner;
        $subscription = $this->activate($workspace, 2);
        $this->deleteJson('/api/v1/workspaces/'.$workspace->slug)->assertConflict()
            ->assertJsonPath('message', 'End the workspace subscription and cancel any pending checkout before deleting this space.');
        $member = User::factory()->create();
        $third = User::factory()->create();
        $base = '/api/v1/workspaces/'.$workspace->slug;
        $firstToken = $this->invite($base, $member);
        $thirdToken = $this->invite($base, $third);
        $this->actingAs($member)->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', false);
        $this->postJson('/api/v1/workspaces/invitations/'.$firstToken.'/accept')->assertOk();
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', true)->assertJsonPath('data.ai_usage.allowance', 0)->assertJsonPath('data.status', 'group');
        $this->actingAs($third)->postJson('/api/v1/workspaces/invitations/'.$thirdToken.'/accept')->assertStatus(402);
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', false);
        $this->actingAs($owner)->getJson($this->billing($workspace))->assertJsonPath('data.member_count', 2)->assertJsonPath('data.capacity', 2);
        $this->actingAs($member)->postJson($this->billing($workspace).'/checkout', [])->assertForbidden();
        $this->postJson($base.'/leave')->assertOk();
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', false);
        $this->actingAs($third)->postJson('/api/v1/workspaces/invitations/'.$thirdToken.'/accept')->assertOk();
        $profile = \Whilesmart\EntitlementsCashier\Models\BillingProfile::forOwner($workspace);
        $profile->subscription('default')->update(['stripe_status' => 'past_due']);
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', false);
        $profile->subscription('default')->update(['stripe_status' => 'active']);
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', true);
        $subscription->update(['status' => SubscriptionStatus::Canceled]);
        $this->getJson('/api/v1/cloud/billing/status')->assertJsonPath('data.paid_access', false);
        $this->actingAs($owner)->getJson($base)->assertJsonPath('data.read_only', true);
    }

    public function test_each_workspace_has_its_own_capacity_and_downgrades_cannot_evict_members(): void
    {
        $workspace = $this->draft();
        $this->activate($workspace, 3);
        $base = '/api/v1/workspaces/'.$workspace->slug;
        $owner = $workspace->owner;
        foreach (range(1, 2) as $index) {
            $this->actingAs($owner);
            $member = User::factory()->create();
            $token = $this->invite($base, $member);
            $this->actingAs($member)->postJson('/api/v1/workspaces/invitations/'.$token.'/accept')->assertOk();
        }
        $this->actingAs($owner);
        $second = $this->draft($owner);
        $this->getJson($this->billing($second))->assertJsonPath('data.active', false);
        $quote = $this->getJson('/api/v1/cloud/workspace-pricing?members=2')->json('data');
        $this->postJson($this->billing($workspace).'/checkout', ['members' => 2, 'quote_id' => $quote['quote_id'], 'confirmed' => true])->assertUnprocessable();
        $this->getJson($this->billing($workspace))->assertJsonPath('data.member_count', 3);
    }

    public function test_checkout_reuses_its_price_and_recovers_without_granting_access_early(): void
    {
        config(['cashier.secret' => 'test-stripe-key', 'entitlements-cashier.trial_days' => 7, 'cache.default' => 'array',
            'workspace-billing.base_amount_cents' => 899, 'workspace-billing.additional_member_amount_cents' => 0]);
        $transport = new WorkspaceCheckoutTransport();
        \Stripe\ApiRequestor::setHttpClient($transport);
        try {
            $workspace = $this->draft();
            $quote = $this->getJson('/api/v1/cloud/workspace-pricing?members=3')->json('data');
            $fixture = json_decode(file_get_contents(base_path('packages/entitlements-cashier/tests/Fixtures/stripe-checkout-open.json')), true);
            Plan::create(['key' => 'workspace-'.$quote['quote_id'], 'name' => 'Group budgeting',
                'provider_price_id' => $fixture['line_items']['data'][0]['price']['id'],
                'features' => ['shared_workspaces' => true], 'limits' => ['workspace_members' => 3],
                'metadata' => ['workspace_quote' => $quote, 'price' => ['amount_cents' => 899, 'currency' => 'usd', 'interval' => 'month'], 'price_signature' => '899:usd:month']]);
            \Whilesmart\EntitlementsCashier\Models\BillingProfile::forOwner($workspace)->update(['stripe_id' => $fixture['customer']]);
            $payload = ['members' => 3, 'quote_id' => $quote['quote_id'], 'confirmed' => true];
            $transport->interrupt = true;
            $this->postJson($this->billing($workspace).'/checkout', $payload)->assertStatus(500);
            $this->postJson($this->billing($workspace).'/checkout', $payload)->assertOk();
            $this->postJson($this->billing($workspace).'/checkout', $payload)->assertOk();
            $this->assertCount(2, $transport->sessions);
            $this->assertEquals($transport->sessions[0]['parameters'], $transport->sessions[1]['parameters']);
            $this->assertSame($transport->sessions[0]['headers'], $transport->sessions[1]['headers']);
            $parameters = $transport->sessions[0]['parameters'];
            $this->assertSame($fixture['line_items']['data'][0]['price']['id'], $parameters['line_items'][0]['price']);
            $this->assertSame(1, $parameters['line_items'][0]['quantity']);
            $this->assertSame($workspace->getMorphClass(), $parameters['metadata']['owner_type']);
            $this->assertSame((string) $workspace->id, $parameters['metadata']['owner_id']);
            $this->assertArrayNotHasKey('trial_period_days', $parameters['subscription_data']);
            $this->assertStringContainsString('/workspaces/'.$workspace->slug.'?checkout=success', $parameters['success_url']);
            $this->getJson($this->billing($workspace))->assertJsonPath('data.active', false)->assertJsonPath('data.pending_checkout', true);
            $this->deleteJson('/api/v1/workspaces/'.$workspace->slug)->assertConflict()
            ->assertJsonPath('message', 'End the workspace subscription and cancel any pending checkout before deleting this space.');
            $this->postJson($this->billing($workspace).'/cancel-checkout')->assertOk();
            $this->getJson($this->billing($workspace))->assertJsonPath('data.pending_checkout', false);
            $this->deleteJson('/api/v1/workspaces/'.$workspace->slug)->assertNoContent();
        } finally {
            \Stripe\ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient());
        }
    }

    private function draft(?User $owner = null): Workspace
    {
        $this->actingAs($owner ?? User::factory()->create());
        $slug = $this->postJson('/api/v1/workspaces', ['name' => 'Our shared space'])->assertCreated()->json('data.slug');

        return Workspace::where('slug', $slug)->firstOrFail();
    }

    private function activate(Workspace $workspace, int $members): Subscription
    {
        $fixture = json_decode(file_get_contents(base_path('packages/entitlements-cashier/tests/Fixtures/stripe-subscription.json')), true);
        $plan = Plan::create(['provider_price_id' => $fixture['items']['data'][0]['price']['id'], 'key' => 'test-workspace-'.$workspace->id, 'name' => 'Group budgeting',
            'features' => ['shared_workspaces' => true, 'plaid' => true, 'bank_connections' => true],
            'limits' => ['workspace_members' => $members], 'meters' => ['ai_tokens' => 0]]);

        \Whilesmart\EntitlementsCashier\Models\BillingProfile::forOwner($workspace)->subscriptions()->create([
            'type' => 'default', 'stripe_id' => $fixture['id'], 'stripe_status' => $fixture['status'],
            'stripe_price' => $fixture['items']['data'][0]['price']['id'], 'quantity' => 1,
        ]);

        return Subscription::create(['owner_type' => $workspace->getMorphClass(), 'owner_id' => $workspace->id,
            'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    }

    private function invite(string $base, User $member): string
    {
        return basename($this->postJson($base.'/members/invite', ['email' => $member->email, 'role' => 'member'])
            ->assertCreated()->json('data.url'));
    }

    private function billing(Workspace $workspace): string
    {
        return '/api/v1/cloud/workspaces/'.$workspace->slug.'/billing';
    }
}

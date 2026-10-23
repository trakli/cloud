<?php

namespace Trakli\Cloud\Http\Controllers;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Trakli\Cloud\Support\CloudPlanSync;
use Whilesmart\Entitlements\Contracts\BillingProvider;
use Whilesmart\Entitlements\Models\Plan;

#[OA\Info(version: '1.0.0', title: 'Trakli Cloud Subscriptions API')]
#[OA\Tag(
    name: 'Cloud',
    description: 'Endpoints for managing Trakli Cloud subscriptions and plans'
)]
#[OA\Schema(
    schema: 'PlansResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'region', type: 'string', example: 'United States'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'trial_days', type: 'integer', example: 3),
        new OA\Property(property: 'free_plan_enabled', type: 'boolean', example: true),
        new OA\Property(
            property: 'plans',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Plan')
        ),
    ]
)]
#[OA\Schema(
    schema: 'AllPlansResponse',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/PlansResponse'),
    ]
)]
#[OA\Schema(
    schema: 'Plan',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'monthly'),
        new OA\Property(property: 'name', type: 'string', example: 'Monthly'),
        new OA\Property(property: 'price', type: 'number', example: 8.99),
        new OA\Property(property: 'price_formatted', type: 'string', example: '$8.99'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'interval', type: 'string', example: 'month'),
        new OA\Property(property: 'trial_days', type: 'integer', example: 3),
        new OA\Property(
            property: 'features',
            type: 'array',
            items: new OA\Items(type: 'string')
        ),
    ]
)]
#[OA\Schema(
    schema: 'BenefitsResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'overview', ref: '#/components/schemas/Overview'),
        new OA\Property(
            property: 'benefits',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Benefit')
        ),
        new OA\Property(property: 'trial_days', type: 'integer', example: 3),
    ]
)]
#[OA\Schema(
    schema: 'Overview',
    type: 'object',
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'Benefit',
    type: 'object',
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
    ]
)]
class CloudController extends ApiController
{
    private ?array $config;

    public function __construct()
    {
        $this->config = config('cloudplans');

        if (! $this->config) {
            abort(503, 'Trakli Cloud is not configured. Please publish the configuration file.');
        }
    }

    /**
     * Get all available cloud plans
     */
    #[OA\Get(
        path: '/plans',
        summary: 'Get subscription plans',
        tags: ['Cloud'],
        servers: [new OA\Server(url: '/api/v1/cloud', description: 'Cloud Plugin API')],
        parameters: [
            new OA\Parameter(
                name: 'region',
                description: 'Region code (us, eu, uk)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'us')
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/PlansResponse'),
                new OA\Schema(ref: '#/components/schemas/AllPlansResponse'),
            ]
        )
    )]
    public function getPlans(Request $request): JsonResponse
    {
        $config = $this->config;

        if ($config['freemode_enabled'] ?? false) {
            return $this->success([]);
        }

        $region = $request->query('region');

        if ($region === null) {
            $basePlans = collect($config['plans'])
                ->filter(fn ($plan) => ($config['free_plan_enabled'] ?? false) || $plan['id'] !== 'free')
                ->values();

            $regionsWithPricing = [];
            foreach ($config['regions'] as $regionCode => $regionData) {
                $prices = [];
                foreach ($basePlans as $plan) {
                    if ($plan['id'] !== 'free') {
                        $prices[$plan['id']] = $this->mapPlanData($plan, $regionData, true);
                    }
                }
                $regionsWithPricing[$regionCode] = [
                    'name' => $regionData['name'],
                    'currency' => $regionData['currency'],
                    'prices' => $prices,
                ];
            }

            return $this->success([
                'overview' => $config['overview']['plans'] ?? null,
                'trial_days' => $config['trial_days'] ?? 3,
                'free_plan_enabled' => (bool) ($config['free_plan_enabled'] ?? false),
                'plans' => $basePlans->toArray(),
                'regions' => $regionsWithPricing,
            ]);
        }

        // Validate region format
        if (! preg_match('/^[a-z]{2,3}$/', $region)) {
            $region = 'us';
        }

        $regionData = $config['regions'][$region] ?? $config['regions']['us'];

        $plans = collect($config['plans'])
            ->filter(function ($plan) use ($config) {
                return ($config['free_plan_enabled'] ?? false) || $plan['id'] !== 'free';
            })
            ->map(function ($plan) use ($regionData) {
                return $this->mapPlanData($plan, $regionData);
            })->values();

        return $this->success([
            'overview' => $config['overview']['plans'] ?? null,
            'region' => $regionData['name'] ?? 'United States',
            'currency' => $regionData['currency'] ?? 'USD',
            'trial_days' => $config['trial_days'] ?? 3,
            'free_plan_enabled' => ($config['free_plan_enabled'] ?? false),
            'plans' => $plans,
        ]);
    }

    /**
     * Get cloud benefits and overview
     */
    #[OA\Get(
        path: '/api/cloud/benefits',
        operationId: 'getBenefits',
        summary: 'Get cloud benefits and overview',
        tags: ['Cloud'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/BenefitsResponse')
    )]
    public function getBenefits(): JsonResponse
    {
        $config = $this->config;

        return $this->success([
            'overview' => $config['overview']['benefits'] ?? null,
            'benefits' => $config['benefits'] ?? [],
            'trial_days' => $config['trial_days'] ?? 3,
        ]);
    }

    /**
     * Create a Stripe checkout session for a cloud plan
     */
    #[OA\Post(
        path: '/checkout',
        summary: 'Create checkout session',
        description: 'Creates a Stripe checkout session for a specific subscription plan and region.',
        tags: ['Cloud'],
        servers: [new OA\Server(url: '/api/v1/cloud', description: 'Cloud Plugin API')],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'plan', type: 'string', enum: ['monthly', 'yearly'], example: 'monthly'),
                    new OA\Property(property: 'region', type: 'string', enum: ['us', 'eu', 'uk'], example: 'us'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Checkout session created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'url', type: 'string', example: 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3'),
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request / Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Plan not found'
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error'
    )]
    public function checkout(Request $request): JsonResponse
    {
        $validation = $this->validateRequest($request, [
            'plan' => ['required', 'string', 'in:monthly,yearly'],
            'region' => ['nullable', 'string', 'in:us,eu,uk'],
        ]);

        if (! $validation['isValidated']) {
            return $this->failure($validation['message'], $validation['code'], $validation['errors'] ?? []);
        }

        $user = $request->user();

        if (! $user) {
            return $this->failure('Unauthorized', 401);
        }

        $key = CloudPlanSync::key($request->input('plan'), $request->input('region', 'us'));
        $model = config('entitlements.models.plan', Plan::class);
        $plan = $model::where('key', $key)->first();

        if (! $plan) {
            return $this->failure('Plan not found', 404);
        }

        try {
            return $this->success([
                'url' => app(BillingProvider::class)->createCheckout($user, $plan),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $this->failure('Failed to initiate checkout', 500);
        }
    }

    /**
     * Maps plan data to include pricing and currency information.
     */
    public function billingStatus(Request $request): JsonResponse
    {
        $owner = $request->user();
        $entitlements = app(\Whilesmart\Entitlements\Contracts\Entitlements::class);
        $model = config('entitlements.models.subscription', \Whilesmart\Entitlements\Models\Subscription::class);
        $subscription = $model::where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())->latest('id')->first();
        $used = app(\Trakli\Cloud\Support\TokenMeterUsage::class)->used($owner);
        $paid = $entitlements->allows($owner, 'bank_connections');
        $start = now('UTC')->startOfMonth();

        return $this->success([
            'has_billing_account' => app(config('entitlements-cashier.billing_profile_model', \Whilesmart\EntitlementsCashier\Models\BillingProfile::class))->newQuery()
                ->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->whereNotNull('stripe_id')->exists(),
            'paid_access' => $paid,
            'status' => $subscription?->status?->value ?? 'free',
            'paid_through' => $subscription?->current_period_end?->toIso8601String(),
            'limits' => [
                'wallets' => $entitlements->limit($owner, 'max_wallets'),
                'categories' => $entitlements->limit($owner, 'max_categories'),
                'bank_connections' => $paid ? null : 0,
            ],
            'ai_usage' => [
                'used' => $used,
                'allowance' => $paid ? 100000 : 0,
                'remaining' => $paid ? max(0, 100000 - $used) : 0,
                'period_start' => $start->toIso8601String(),
                'period_end' => $start->copy()->addMonth()->toIso8601String(),
            ],
        ]);
    }

    public function billingPortal(Request $request): JsonResponse
    {
        $model = config('entitlements-cashier.billing_profile_model', \Whilesmart\EntitlementsCashier\Models\BillingProfile::class);
        $profile = $model::where('owner_type', $request->user()->getMorphClass())
            ->where('owner_id', $request->user()->getKey())->first();

        if (! $profile?->stripe_id) {
            return $this->failure('No billing account exists yet.', 409);
        }

        try {
            return $this->success(['url' => $profile->billingPortalUrl(config('entitlements-cashier.portal_return_url', config('app.url')))]);
        } catch (\Throwable $e) {
            report($e);

            return $this->failure('Billing management is unavailable.', 503);
        }
    }

    private function mapPlanData(array $plan, array $regionData, bool $pricesOnly = false): array
    {
        $priceKey = $plan['interval'] === 'month' ? 'monthly_price' : 'yearly_price';
        $priceFormattedKey = $plan['interval'] === 'month' ? 'monthly_price_formatted' : 'yearly_price_formatted';
        $price = $plan['interval'] === 'lifetime' ? 0 : ($regionData[$priceKey] ?? 0);
        $priceFormatted = $plan['interval'] === 'lifetime' ? 'Free' : ($regionData[$priceFormattedKey] ?? 'N/A');

        $data = [
            'price' => $price,
            'price_formatted' => $priceFormatted,
        ];

        return $pricesOnly ? $data : array_merge($plan, $data);
    }
}

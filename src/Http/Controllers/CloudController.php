<?php

namespace Trakli\Cloud\Http\Controllers;

use App\Http\Controllers\API\ApiController;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

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
        new OA\Property(property: 'price', type: 'number', example: 5.00),
        new OA\Property(property: 'price_formatted', type: 'string', example: '$5.00'),
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
    public function getPlans(\Illuminate\Http\Request $request): JsonResponse
    {
        $config = $this->config;

        if ($config['freemode_enabled'] ?? false) {
            return \response()->json([]);
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
     * Maps plan data to include pricing and currency information.
     */
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

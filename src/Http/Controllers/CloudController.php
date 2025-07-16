<?php

namespace Trakli\Cloud\Http\Controllers;

use App\Http\Controllers\Controller;
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
        new OA\Property(property: 'price_cents', type: 'integer', example: 5000),
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
class CloudController extends Controller
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
        $region = $request->query('region');
        $config = $this->config;

        // If no region is specified, return all regions
        if ($region === null) {
            $result = [];
            foreach ($config['regions'] as $regionCode => $regionData) {
                $result[$regionCode] = [
                    'name' => $regionData['name'],
                    'currency' => $regionData['currency'],
                    'trial_days' => $config['trial_days'] ?? 3,
                    'free_plan_enabled' => $config['free_plan_enabled'] ?? true,
                    'plans' => collect($config['plans'])->map(function ($plan) use ($regionData, $config) {
                        $priceKey = $plan['id'].'_price';

                        return array_merge($plan, [
                            'price_cents' => $regionData[$priceKey] ?? 0,
                            'currency' => $regionData['currency'] ?? 'USD',
                            'trial_days' => $config['trial_days'] ?? 3,
                        ]);
                    })->toArray(),
                ];
            }

            return response()->json($result);
        }

        // Validate region format
        if (! preg_match('/^[a-z]{2,3}$/', $region)) {
            $region = 'us';
        }

        $regionData = $config['regions'][$region] ?? $config['regions']['us'];

        $plans = collect($config['plans'])->map(function ($plan) use ($regionData, $config) {
            $priceKey = $plan['id'].'_price';

            return array_merge($plan, [
                'price_cents' => $regionData[$priceKey] ?? 0,
                'currency' => $regionData['currency'] ?? 'USD',
                'trial_days' => $config['trial_days'] ?? 3,
            ]);
        });

        return response()->json([
            'region' => $regionData['name'] ?? 'United States',
            'currency' => $regionData['currency'] ?? 'USD',
            'trial_days' => $config['trial_days'] ?? 3,
            'free_plan_enabled' => $config['free_plan_enabled'] ?? true,
            'plans' => $plans,
        ]);
    }

    /**
     * Get cloud benefits and overview
     */
    #[OA\Get(
        path: '/benefits',
        summary: 'Get cloud benefits',
        tags: ['Cloud'],
        servers: [new OA\Server(url: '/api/v1/cloud', description: 'Cloud Plugin API')]
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/BenefitsResponse')
    )]
    public function getBenefits(): JsonResponse
    {
        $config = $this->config;

        return new JsonResponse([
            'overview' => $config['overview'] ?? null,
            'benefits' => $config['benefits'] ?? [],
            'trial_days' => $config['trial_days'] ?? 3,
        ]);
    }
}

<?php

namespace Trakli\Cloud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CloudController extends Controller
{
    /**
     * Get all available cloud plans for a region
     */
    public function getPlans(\Illuminate\Http\Request $request): JsonResponse
    {
        $region = $request->query('region');
        $config = config('cloudplans');

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
    public function getBenefits(): JsonResponse
    {
        return response()->json([
            'overview' => config('cloudplans.overview'),
            'benefits' => config('cloudplans.benefits'),
            'trial_days' => config('cloudplans.trial_days'),
        ]);
    }
}

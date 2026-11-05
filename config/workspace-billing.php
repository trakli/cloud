<?php

return [
    'pricing' => Trakli\Cloud\Billing\BasePlusMemberPricing::class,
    'currency' => 'usd',
    'return_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/'),
    'base_amount_cents' => (int) env('CLOUD_GROUP_BASE_AMOUNT_CENTS', 1200),
    'included_members' => (int) env('CLOUD_GROUP_INCLUDED_MEMBERS', 2),
    'additional_member_amount_cents' => (int) env('CLOUD_GROUP_ADDITIONAL_MEMBER_AMOUNT_CENTS', 500),
    'max_members' => (int) env('CLOUD_GROUP_MAX_MEMBERS', 100),
    'feature_keys' => ['shared_workspaces', 'plaid', 'bank_connections'],
    'member_feature_keys' => ['plaid', 'bank_connections'],
    'limits' => ['max_wallets' => null, 'max_categories' => null],
];
